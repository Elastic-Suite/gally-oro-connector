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

namespace Gally\OroPlugin\Search\Listener;

use Gally\OroPlugin\Search\SearchEngine;
use Gally\OroPlugin\Service\ContextProvider;
use Gally\Sdk\GraphQl\Request;
use Gally\Sdk\GraphQl\Response;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\SearchBundle\Datagrid\Event\SearchResultAfter;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;

/**
 * Adapt data grid for result managed by Gally.
 */
class FrontendProductGridEventListener
{
    public function __construct(
        private EngineParameters $engineParameters,
        private ContextProvider $contextProvider,
        private array $dataGridNames,
    ) {
    }

    public function addDataGridName(string $name): void
    {
        $this->dataGridNames[] = $name;
    }

    /**
     * Check if we are in a Gally context.
     */
    public function isApplicable(DatagridConfiguration $config): bool
    {
        return SearchEngine::ENGINE_NAME === $this->engineParameters->getEngineName()
            && \in_array($config->getName(), $this->dataGridNames, true);
    }

    public function onResultAfter(SearchResultAfter $event): void
    {
        $config = $event->getDatagrid()->getConfig();
        if ($this->isApplicable($config)) {
            $this->setAppliedSortingFromGallyResult($config);
            $this->addFiltersFromGallyResult($config);
        }
    }

    /**
     * Make filter visible if they are in gally aggregations.
     */
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
                $filter['type'] = (SearchEngine::GALLY_FILTER_PREFIX . 'price__price') === $gallyFilter['field']
                    ? 'frontend-product-price'
                    : 'number-range';
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

    /**
     * Set current sort order in datagrid from gally response.
     */
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
