<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Gally to newer versions in the future.
 *
 * @package   Gally
 * @author    Gally Team <elasticsuite@smile.fr>
 * @copyright 2024-present Smile
 * @license   Open Software License v. 3.0 (OSL-3.0)
 */

declare(strict_types=1);

namespace Gally\OroPlugin\Search\Extension;

use Gally\OroPlugin\Search\SearchEngine;
use Gally\OroPlugin\Service\ContextProvider;
use Gally\Sdk\Entity\Metadata;
use Gally\Sdk\Entity\SourceField;
use Gally\Sdk\GraphQl\Request;
use Gally\Sdk\GraphQl\Response;
use Gally\Sdk\Service\SearchManager;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Common\MetadataObject;
use Oro\Bundle\DataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\DataGridBundle\Extension\AbstractExtension;
use Oro\Bundle\DataGridBundle\Extension\Sorter\Configuration;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;

/**
 * Adapt data grid for result managed by Gally.
 */
class GallyDataGridExtension extends AbstractExtension
{
    public function __construct(
        private EngineParameters $engineParameters,
        private SearchManager $searchManager,
        private ContextProvider $contextProvider,
        private array $dataGridNames,
    ) {
    }

    public function addDataGridName(string $name): void
    {
        $this->dataGridNames[] = $name;
    }

    public function isApplicable(DatagridConfiguration $config): bool
    {
        return SearchEngine::ENGINE_NAME === $this->engineParameters->getEngineName()
            && \in_array($config->getName(), $this->dataGridNames, true);
    }

    public function visitDatasource(DatagridConfiguration $config, DatasourceInterface $datasource): void
    {
        $this->addFilterFieldsFromGallyConfiguration($config);
        $this->addSortFieldsFromGallyConfiguration($config);
    }

    public function visitMetadata(DatagridConfiguration $config, MetadataObject $object): void
    {
        $this->addFiltersFromGallyResult($config);
        $this->setAppliedSortingFromGallyResult($config);
    }

    public function getPriority(): int
    {
        return 100;
    }

    private function addFilterFieldsFromGallyConfiguration(DatagridConfiguration $config): void
    {
        $filterableSourceField = $this->searchManager->getFilterableSourceField(new Metadata('product'));
        $filters = $config->offsetGetByPath('[filters][columns]');

        $proceed = [];
        foreach ($filters as $filter) {
            $name = $filter['data_name'];
            $proceed[] = $name;
        }

        foreach ($filterableSourceField as $sourceField) {
            if (!\in_array($sourceField->getCode(), $proceed, true)) {
                $fieldName = SearchEngine::GALLY_FILTER_PREFIX . $sourceField->getCode();
                $type = null;
                $filter = [
                    'label' => $sourceField->getDefaultLabel(),
                    'type' => 'gally-select',
                    'visible' => false,
                    'disabled' => false,
                    'renderable' => true,
                    'choices' => [],
                ];

                // @see \Gally\Search\Decoration\GraphQl\AddAggregationsData::formatAggregation
                switch ($sourceField->getType()) {
                    case SourceField::TYPE_CATEGORY:
                        $fieldName .= '__id';
                        $filter['type'] = 'gally-select';
                        break;
                    case SourceField::TYPE_PRICE:
                        $fieldName .= '__price';
                        $filter['type'] = 'frontend-product-price';
                        break;
                    case SourceField::TYPE_SELECT:
                        $fieldName .= '__value';
                        $filter['type'] = 'gally-select';
                        break;
                    case SourceField::TYPE_STOCK:
                        $fieldName .= '__status';
                        $filter['type'] = 'boolean';
                        break;
                    case SourceField::TYPE_FLOAT:
                    case SourceField::TYPE_INT:
                        $filter['type'] = 'number-range';
                        break;
                    case SourceField::TYPE_BOOLEAN:
                        $type = 'bool';
                        $filter['type'] = 'boolean';
                        break;
                }

                $filter['data_name'] = $type ? "$type.$fieldName" : $fieldName;
                $filters[$fieldName] = $filter;
            }
        }

        $config->offsetSetByPath('[filters][columns]', $filters);
    }

    private function addSortFieldsFromGallyConfiguration(DatagridConfiguration $config): void
    {
        $sortableAttributes = $this->searchManager->getProductSortingOptions();

        /** @var SourceField[] $sorters */
        $sorters = [];
        $config->offsetSetByPath('[sorters][columns]', []);
        foreach ($sortableAttributes as $attribute) {
            if (Request::SORT_RELEVANCE_FIELD !== $attribute->getCode()) {
                $sorters[$attribute->getCode()] = $attribute;
                $config->offsetSetByPath(
                    '[sorters][columns][' . $attribute->getCode() . ']',
                    array_filter(
                        [
                            'data_name' => $attribute->getCode(),
                            'type' => match ($attribute->getType()) {
                                SourceField::TYPE_TEXT => 'string',
                                default => null
                            },
                        ]
                    )
                );
            }
        }

        $proceed = [];
        foreach ($config->offsetGetOr('columns') ?? [] as $name => $column) {
            if (\array_key_exists($name, $sorters)) {
                $proceed[] = $name;
            }
        }

        $columnToAdd = array_diff(array_keys($sorters), $proceed);
        foreach ($columnToAdd as $attributeCode) {
            $attribute = $sorters[$attributeCode];
            $columnData = [
                'label' => $attribute->getDefaultLabel(),
                'type' => 'field',
                'frontend_type' => 'string',
                'translatable' => false,
                'editable' => false,
                'shortenableLabel' => true,
            ];
            $config->offsetAddToArrayByPath('[columns]', [$attribute->getCode() => $columnData]);
        }

        // Let gally define default sort by.
        $config->offsetSetByPath(Configuration::DISABLE_DEFAULT_SORTING_PATH, false);
    }

    private function addFiltersFromGallyResult(DatagridConfiguration $config): void
    {
        $gallyFilters = $this->contextProvider->getResponse()->getAggregations();
        $currentFilters = $config->offsetGetByPath('[filters][columns]') ?? [];
        $filters = [];

        foreach ($currentFilters as $code => $filter) {
            if (\in_array($code, ['sku', 'names'], true) || 'gally-select' === $filter['type']) {
                $filters[$code] = $filter;
            }
        }

        foreach ($gallyFilters as $gallyFilter) {
            $gallyFilter['field'] = SearchEngine::GALLY_FILTER_PREFIX . $gallyFilter['field'];
            $filter = [
                'data_name' => $gallyFilter['field'],
                'label' => $gallyFilter['label'],
                'visible' => true,
                'disabled' => false,
                'renderable' => true,
            ];

            if (Response::FILTER_TYPE_SLIDER === $gallyFilter['type']) {
                $filter['type'] = (SearchEngine::GALLY_FILTER_PREFIX . 'price__price') === $gallyFilter['field'] ? 'frontend-product-price' : 'number-range';
                $filters[$gallyFilter['field']] = $filter;
            } elseif (Response::FILTER_TYPE_BOOLEAN === $gallyFilter['type']) {
                $filter['type'] = 'boolean';
                $filters[$gallyFilter['field']] = $filter;
            } else {
                foreach ($gallyFilter['options'] as $index => $option) {
                    $gallyFilter['options'][$index]['data'] = $option['value'];
                }
                $filter['choices'] = $gallyFilter['options'];
                $filter['options']['gally_options'] = $gallyFilter['options'];
                $filter['options']['has_more'] = $gallyFilter['hasMore'];
                $filter['type'] = 'gally-select';
                $filters[$gallyFilter['field']] = $filter;
            }
        }

        $config->offsetSetByPath('[filters][columns]', $filters);
    }

    private function setAppliedSortingFromGallyResult(DatagridConfiguration $config): void
    {
        $sortField = $this->contextProvider->getResponse()->getSortField();
        $sortDirection = $this->contextProvider->getResponse()->getSortDirection();

        if (Request::SORT_RELEVANCE_FIELD !== $sortField) {
            $config->offsetSetByPath(
                '[sorters][default]',
                [$sortField => Request::SORT_DIRECTION_ASC === $sortDirection ? 'ASC' : 'DESC']
            );
        }
    }
}
