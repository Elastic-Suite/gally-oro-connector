<?php

namespace Gally\OroPlugin\Engine;

use Gally\OroPlugin\RequestBuilder\GallyRequestBuilder;
use Gally\Sdk\Service\SearchManager;
use Oro\Bundle\SearchBundle\Provider\AbstractSearchMappingProvider;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\SearchBundle\Query\Result;
use Oro\Bundle\SearchBundle\Query\Result\Item;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractEngine;
use Oro\Bundle\WebsiteSearchBundle\Engine\Mapper;
use Oro\Bundle\WebsiteSearchBundle\Resolver\QueryPlaceholderResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Gally website search engine
 */
class SearchEngine extends AbstractEngine
{
    protected Mapper $mapper;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        QueryPlaceholderResolverInterface $queryPlaceholderResolver,
        AbstractSearchMappingProvider $mappingProvider,
        private SearchManager $searchManager,
        private GallyRequestBuilder $requestBuilder,
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
        $response = $this->searchManager->searchProduct($request);

        $results = [];
        foreach ($response->getCollection() as $item) {
            $results[] = new Item(
                'product', // Todo manage other entity
                basename($item['id']),
                $item['url'] ?? null,
                $this->mapper->mapSelectedData($query, $item),
                $this->mappingProvider->getEntityConfig('product')
            );
        }

//        $aggregatedData = $this->parseAggregatedData($response);

        return new Result($query, $results, $response->getTotalCount(), [] /* $aggregatedData */);
    }
}