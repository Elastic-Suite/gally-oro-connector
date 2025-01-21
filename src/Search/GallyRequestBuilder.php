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

use Gally\OroPlugin\Resolver\PriceGroupResolver;
use Gally\OroPlugin\Service\ContextProvider;
use Gally\Sdk\Entity\Metadata;
use Gally\Sdk\GraphQl\Request;
use Oro\Bundle\PricingBundle\Placeholder\CPLIdPlaceholder;
use Oro\Bundle\PricingBundle\Placeholder\CurrencyPlaceholder;
use Oro\Bundle\PricingBundle\Placeholder\PriceListIdPlaceholder;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderRegistry;

class GallyRequestBuilder
{
    public function __construct(
        private ContextProvider $contextProvider,
        private ExpressionVisitor $expressionVisitor,
        private PriceGroupResolver $priceGroupResolver,
        private PlaceholderRegistry $registry,
        private array $attributeMapping,
    ) {
    }

    public function build(Query $query, array $context): Request
    {
        $from = $query->getFrom();
        $entityCode = str_replace('website_search_', '', str_replace('oro_', '', $from[0]));
        $metadata = new Metadata($entityCode);

        [$currentPage, $pageSize] = $this->getPaginationInfo($query);
        [$sortField, $sortDirection] = $this->getSortInfo($query);
        [$searchQuery, $filters] = $this->getFilters($query, $metadata);
        $currentContentNode = $this->contextProvider->getCurrentContentNode();

        return new Request(
            $this->contextProvider->getCurrentLocalizedCatalog(),
            $metadata,
            $this->contextProvider->isAutocompleteContext(),
            $this->getSelectedFields($query),
            $currentPage,
            $pageSize,
            $currentContentNode ? (string) $currentContentNode->getId() : null,
            $searchQuery,
            $filters,
            $sortField,
            $sortDirection,
            $this->getPriceGroup(),
        );
    }

    private function getSelectedFields(Query $query): array
    {
        $fields = $query->getSelect();
        $selectedFields = empty($fields) ? [] : ['id'];
        foreach ($fields as $field) {
            [$type, $name] = Criteria::explodeFieldTypeName($field);
            if (\in_array($name, ['minimal_price', 'inv_status', 'inventory_status'], true)) {
                continue;
            }
            $selectedFields[] = $this->attributeMapping[$name] ?? $name;
        }

        return $selectedFields;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function getPaginationInfo(Query $query): array
    {
        $from = (int) $query->getCriteria()->getFirstResult();
        $pageSize = (int) $query->getCriteria()->getMaxResults() ?: 25;
        $currentPage = (int) ceil($from / $pageSize) + 1;

        return [$currentPage, $pageSize];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function getSortInfo(Query $query): array
    {
        $orders = $query->getCriteria()->getOrderings();

        if (!empty($orders)) {
            // We can only use one sort order in gally (score and id ordering are added automatically).
            $order = array_key_first($orders);
            [$type, $field] = Criteria::explodeFieldTypeName($order);

            if ('category_sort_order' == $field || str_starts_with($field, 'assigned_to_sort_order.')) {
                // Let gally manage default sorting order.
                return [null, null];
            }

            return [$field, 'ASC' === $orders[$order] ? Request::SORT_DIRECTION_ASC : Request::SORT_DIRECTION_DESC];
        }

        return [null, null];
    }

    /**
     * @return array{0: ?string, 1: array}
     */
    private function getFilters(Query $query, Metadata $metadata): array
    {
        $filters = [];

        if ($expression = $query->getCriteria()->getWhereExpression()) {
            $filters = $this->expressionVisitor->dispatch($expression, 'product' === $metadata->getEntity());
        }

        return [$this->expressionVisitor->getSearchQuery(), array_filter([$filters])];
    }

    private function getPriceGroup(): string
    {
        /** @var CPLIdPlaceholder $cplIdPlaceholder */
        $cplIdPlaceholder = $this->registry->getPlaceholder(CPLIdPlaceholder::NAME);
        $cplId = $cplIdPlaceholder->getDefaultValue();
        /** @var PriceListIdPlaceholder $plIdPlaceholder */
        $plIdPlaceholder = $this->registry->getPlaceholder(PriceListIdPlaceholder::NAME);
        $plId = $plIdPlaceholder->getDefaultValue();
        /** @var CurrencyPlaceholder $currencyPlaceholder */
        $currencyPlaceholder = $this->registry->getPlaceholder(CurrencyPlaceholder::NAME);
        $currency = $currencyPlaceholder->getDefaultValue();

        return $this->priceGroupResolver->getGroupId(
            (bool) $cplId,
            (int) ($cplId ?: $plId),
            $currency,
            $this->contextProvider->getPriceFilterUnit()
        );
    }
}
