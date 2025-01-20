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

namespace Gally\OroPlugin\Controller\Frontend;

use Gally\OroPlugin\Search\GallyRequestBuilder;
use Gally\Sdk\GraphQl\Request as SearchRequest;
use Gally\Sdk\Service\SearchManager;
use Oro\Bundle\DataGridBundle\Datagrid;
use Oro\Bundle\SearchBundle\Datagrid\Datasource\SearchDatasource;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\WebsiteSearchBundle\Event\BeforeSearchEvent;
use Oro\Bundle\WebsiteSearchBundle\Resolver\QueryPlaceholderResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ViewMoreController extends AbstractController
{
    public const PRODUCT_SEARCH_DATAGRID = 'frontend-product-search-grid';

    public function __construct(
        private Datagrid\Manager $dataGridManager,
        private Datagrid\RequestParameterBagFactory $parameterBagFactory,
        private EventDispatcherInterface $eventDispatcher,
        private QueryPlaceholderResolverInterface $queryPlaceholderResolver,
        private GallyRequestBuilder $requestBuilder,
        private SearchManager $searchManager,
    ) {
    }

    /**
     * @Route("/filter_view_more", name="gally_filter_view_more", methods={"GET"}, options={"expose"=true})
     * @AclAncestor("oro_product_frontend_view")
     */
    public function getDataAction(Request $request): JsonResponse
    {
        $options = $this->searchManager->viewMoreProductFilterOption(
            $this->getSearchRequest(),
            $request->query->get('field')
        );

        return new JsonResponse($options);
    }

    private function getSearchRequest(): SearchRequest
    {
        $dataGrid = $this->dataGridManager->getDatagrid(
            self::PRODUCT_SEARCH_DATAGRID,
            $this->parameterBagFactory->fetchParameters(self::PRODUCT_SEARCH_DATAGRID)
        );

        /** @var SearchDatasource $datasource */
        $datasource = $dataGrid->acceptDatasource()->getDatasource();
        $query = $datasource->getSearchQuery();
        $event = new BeforeSearchEvent($query->getQuery(), []);
        $this->eventDispatcher->dispatch($event, BeforeSearchEvent::EVENT_NAME);
        $this->queryPlaceholderResolver->replace($event->getQuery());

        return $this->requestBuilder->build($event->getQuery(), []);
    }
}
