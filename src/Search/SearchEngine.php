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

namespace Gally\OroPlugin\Search;

use Gally\OroPlugin\Service\ContextProvider;
use Gally\Sdk\Service\SearchManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\SearchBundle\Provider\AbstractSearchMappingProvider;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\SearchBundle\Query\Result;
use Oro\Bundle\SearchBundle\Query\Result\Item;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractEngine;
use Oro\Bundle\WebsiteSearchBundle\Engine\Mapper;
use Oro\Bundle\WebsiteSearchBundle\Resolver\QueryPlaceholderResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Gally website search engine.
 */
class SearchEngine extends AbstractEngine
{
    public const ENGINE_NAME = 'gally';

    protected Mapper $mapper;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        QueryPlaceholderResolverInterface $queryPlaceholderResolver,
        AbstractSearchMappingProvider $mappingProvider,
        private SearchManager $searchManager,
        private GallyRequestBuilder $requestBuilder,
        private ContextProvider $contextProvider,
        private array $attributeMapping,
    ) {
        parent::__construct($eventDispatcher, $queryPlaceholderResolver, $mappingProvider);
    }

    public function setMapper(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    protected function doSearch(Query $query, array $context = [])
    {
        $request = $this->requestBuilder->build($query, $context);
        $response = $this->searchManager->search($request);
        $results = [];
        if ('product' === $request->getMetadata()->getEntity()) {
            $this->contextProvider->setRequest($request);
            $this->contextProvider->setResponse($response);
        }

        foreach ($response->getCollection() as $item) {
            $item['id'] = (int) basename($item['id']);

            foreach ($query->getSelect() as $field) {
                [$type, $name] = Criteria::explodeFieldTypeName($field);
                $value = $item[$this->attributeMapping[$name] ?? $name] ?? null;

                if (('inv_status' === $name || 'inventory_status' === $name) && isset($item['stock'])) {
                    $value = $item['stock']['status']
                        ? Product::INVENTORY_STATUS_IN_STOCK
                        : Product::INVENTORY_STATUS_OUT_OF_STOCK;
                } elseif ('minimal_price' === $name && isset($item['price'])) {
                    $value = $item['price'][0]['price'];
                } elseif ('tree' !== $name && \is_array($value)) {
                    $value = reset($value)['value'];
                    $item['additional'][$name . '_label'] = reset($value)['label'];
                }

                $item[$name] = $value;
            }

            $itemObject = new Item(
                $request->getMetadata()->getEntity(),
                $item['id'],
                $item['url'] ?? null,
                $this->mapper->mapSelectedData($query, $item),
                $this->mappingProvider->getEntityConfig($request->getMetadata()->getEntity())
            );
            if (\array_key_exists('tree', $item)) {
                $selectedData = $itemObject->getSelectedData();
                $selectedData['tree'] = $item['tree'];
                $itemObject->setSelectedData($selectedData);
            }
            $results[] = $itemObject;
        }

        $aggregations = [];
        foreach ($response->getAggregations() as $aggregation) {
            $field = $aggregation['field'];
            foreach ($aggregation['options'] as $option) {
                $aggregations[$field][$option['value']] = $option['count'];
            }
        }

        return new Result($query, $results, $response->getTotalCount(), $aggregations);
    }
}
