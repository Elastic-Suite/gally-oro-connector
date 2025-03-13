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

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor as BaseExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;
use Gally\Sdk\Entity\SourceField;
use Gally\Sdk\GraphQl\Request;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\SearchBundle\Query\Criteria\Comparison as OroComparison;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\VisibilityBundle\Entity\VisibilityResolved\BaseVisibilityResolved;

class ExpressionVisitor extends BaseExpressionVisitor
{
    private const GALLY_TYPE_AND = '_must';
    private const GALLY_TYPE_OR = '_should';
    private const GALLY_TYPE_NOT = '_not';

    /** @var SourceField[] */
    private array $selectSourceFields = [];
    private ?string $searchQuery = null;

    public function __construct(
        private array $attributeMapping,
    ) {
    }

    public function setSelectSourceFields(array $selectSourceFields): void
    {
        $this->selectSourceFields = $selectSourceFields;
    }

    /**
     * @param Expression $expr            composite expression to convert
     * @param bool       $isStitchedQuery define is the converted query should stitch or not
     * @param bool       $isMainQuery     define is the current composite expression is part of the main query
     *                                    The main query are all clauses that can be grouped in one AND clause.
     *                                    For example:
     *                                    the query "A AND (B AND (C AND (D OR E)))" can be factorized in
     *                                    "(A AND B AND C) AND (D OR E)". The main query is (A AND B AND C).
     *                                    The main query will determine which clause can be added at the root
     *                                    of the Gally request, without encapsulate it in a boolFilter query.
     *
     * @return array|string A gally format query
     */
    public function dispatch(
        Expression $expr,
        bool $isStitchedQuery = false,
        bool $isMainQuery = true,
    ) {
        // Use main query parameter to flatten main and expression.
        switch (true) {
            case $expr instanceof CompositeExpression:
                return $this->walkCompositeExpression($expr, $isStitchedQuery, $isMainQuery);
            case $expr instanceof Comparison:
                return $this->walkComparison($expr, $isStitchedQuery);
            case $expr instanceof Value:
                return $this->walkValue($expr);
            default:
                throw new \RuntimeException('Unknown Expression ' . $expr::class);
        }
    }

    public function walkCompositeExpression(
        CompositeExpression $expr,
        bool $isStitchedQuery = false,
        bool $isMainQuery = true
    ): array {
        $type = self::GALLY_TYPE_AND;
        if (CompositeExpression::TYPE_AND !== $expr->getType()) {
            // Composite expression with OR operator need to be encapsulated in a boolFilter query
            $isMainQuery = false;
            $type = self::GALLY_TYPE_OR;
        }

        // Convert each expression of the composite expression in a gally filter format.
        $filters = [];
        foreach ($expr->getExpressionList() as $expression) {
            $exprFilters = $this->dispatch($expression, $isStitchedQuery, $isMainQuery);
            foreach (['queryFilters', 'facetFilters'] as $filterType) {
                if ($isStitchedQuery) {
                    foreach ($exprFilters[$filterType] ?? [] as $field => $filter) {
                        if (\array_key_exists($field, $filters[$filterType] ?? [])) {
                            // The field is already present in the filters list, we merge the value.
                            if (1 === \count($filters[$filterType][$field])) {
                                $filters[$filterType][$field] = [$filters[$filterType][$field], $filter];
                            } else {
                                $filters[$filterType][$field] = [...$filters[$filterType][$field], $filter];
                            }
                        } else {
                            // The field is not present in the filers list yet, we add it.
                            $filters[$filterType][$field] = $filter;
                        }
                    }
                } else {
                    foreach ($exprFilters[$filterType] ?? [] as $filter) {
                        $filters[$filterType][] = $filter;
                    }
                }
            }
        }

        // Rationalize generated filter and encapsulate them in a boolFilter query if needed.
        // There is multiple case where we need to encapsulate the queries :
        //   - When there is multiple filter on the same field
        //   - When the composite expression is not part of the main query (OR queries for example)
        // But there are cases where we need to encapsulate queryFilters but not facetFilters.
        $needEncapsulate = [
            'queryFilters' => !$isMainQuery,
            'facetFilters' => !$isMainQuery,
        ];
        foreach (['queryFilters', 'facetFilters'] as $filterType) {
            foreach ($filters[$filterType] ?? [] as $filterData) {
                // We can't stitch multiple filter on the same field.
                if (\count($filterData) > 1) {
                    $needEncapsulate[$filterType] = true;
                }
            }

            $rationalizedFilters = [];
            foreach ($filters[$filterType] ?? [] as $field => $filterData) {
                if (\count($filterData) > 1) {
                    foreach ($filterData as $filterItem) {
                        $rationalizedFilters[] = \is_string($field) ? [$field => $filterItem] : $filterItem;
                    }
                } elseif (\is_string($field) && !$needEncapsulate[$filterType]) {
                    $rationalizedFilters[$field] = $filterData;
                } else {
                    $rationalizedFilters[] = \is_string($field) ? [$field => $filterData] : $filterData;
                }
            }
            $filters[$filterType] = $rationalizedFilters;
        }

        // If the current composite expression is not in the main query,
        // we should encapsulate it in a main boolFilter clause.
        if (!empty(array_filter($needEncapsulate))) {
            if (empty($filters['facetFilters'])) {
                $boolFilter = [
                    Request::FILTER_TYPE_BOOLEAN => [
                        $type => $filters['queryFilters'] ?? [],
                    ],
                ];
                $filters['queryFilters'] = $isStitchedQuery ? $boolFilter : [$boolFilter];
                $filters['facetFilters'] = [];
            } elseif (empty($filters['queryFilters'])) {
                $boolFilter = [
                    Request::FILTER_TYPE_BOOLEAN => [
                        $type => $filters['facetFilters'],
                    ],
                ];
                $filters['queryFilters'] = $isStitchedQuery ? $boolFilter : [$boolFilter];
                $filters['facetFilters'] = [];
            } elseif ($needEncapsulate['facetFilters']) {
                $boolFilter = [
                    Request::FILTER_TYPE_BOOLEAN => [
                        $type => [
                            \count($filters['queryFilters']) > 1
                                ? [Request::FILTER_TYPE_BOOLEAN => [$type => [$filters['queryFilters']]]]
                                : $filters['queryFilters'],
                            \count($filters['facetFilters']) > 1
                                ? [Request::FILTER_TYPE_BOOLEAN => [$type => $filters['facetFilters']]]
                                : $filters['facetFilters'],
                        ],
                    ],
                ];
                $filters['queryFilters'] = $isStitchedQuery ? $boolFilter : [$boolFilter];
                $filters['facetFilters'] = [];
            } else {
                $boolFilter = [Request::FILTER_TYPE_BOOLEAN => [$type => $filters['queryFilters']]];
                $filters['queryFilters'] = $isStitchedQuery ? $boolFilter : [$boolFilter];
            }
        }

        return array_filter($filters);
    }

    public function walkComparison(Comparison $comparison, bool $isStitchedQuery = false): ?array
    {
        [$type, $field, $comeFromFacet] = $this->explodeFieldTypeName($comparison->getField());
        $value = $this->dispatch($comparison->getValue(), $isStitchedQuery);
        $operator = $this->getGallyOperator($comparison->getOperator());
        $hasNegation = str_starts_with($comparison->getOperator(), 'NOT') || \in_array($comparison->getOperator(), ['<>', 'NIN'], true);

        // Gally can't manage all_text filter, it is redondant with the main "search" filter.
        if ('all_text' === $field) {
            $this->searchQuery = $this->enforceValueType('text', $value);

            return null;
        }

        // Gally manage current category context in a dedicated top level parameter.
        if (str_starts_with($field, 'category_paths.')) {
            return null;
        }

        if ('id' === $field) {
            $type = 'text';
        } elseif ('inv_status' === $field || 'inventory_status' === $field || 'stock__status' === $field) {
            if (\count($value) > 1) {
                return null; // if we want in stock and out of stock product, we do not need this filter.
            }
            $type = 'bool';
            $field = 'stock__status';
            $value = \in_array(Product::INVENTORY_STATUS_IN_STOCK, $value, true) || \in_array(1, $value, true);
        } elseif (str_starts_with($field, 'assigned_to.') || str_starts_with($field, 'manually_added_to.')) {
            [$field, $variantId] = explode('.', $field);
            [$_, $value] = explode('_', $variantId);
            $operator = Request::FILTER_OPERATOR_IN;
        } elseif ('category__id' === $field && Request::FILTER_OPERATOR_IN === $operator) {
            // Category filter do not support "in" operator
            return $this->dispatch(
                new CompositeExpression(
                    $hasNegation ? CompositeExpression::TYPE_AND : CompositeExpression::TYPE_OR,
                    array_map(
                        fn ($value) => new Comparison($field, $hasNegation ? '<>' : '=', $value),
                        $value
                    )
                ),
                $isStitchedQuery
            );
        } elseif (str_starts_with($field, 'visibility_customer.')) {
            [$field, $customerId] = explode('.', $field);
            $type = 'text';
            if (Request::FILTER_OPERATOR_EXISTS === $operator) {
                return $this->dispatch(
                    new CompositeExpression(
                        $hasNegation ? CompositeExpression::TYPE_AND : CompositeExpression::TYPE_OR,
                        [
                            new Comparison('visible_for_customer', $hasNegation ? '<>' : '=', $customerId),
                            new Comparison('hidden_for_customer', $hasNegation ? '<>' : '=', $customerId),
                        ],
                    ),
                    $isStitchedQuery
                );
            }
            if (Request::FILTER_OPERATOR_EQ == $operator) {
                $field = BaseVisibilityResolved::VISIBILITY_HIDDEN === $value
                    ? 'hidden_for_customer'
                    : 'visible_for_customer';
                $value = $customerId;
            }
        } elseif (\array_key_exists($field, $this->selectSourceFields)) {
            $field .= '__value';
            $type = 'text';
        }

        if ('bool' === $type) {
            // If we have true and false value for a bool field,
            // we only need to check if the field exist for the document.
            if (\is_array($value) && \count($value) > 1) {
                $operator = Request::FILTER_OPERATOR_EXISTS;
                $value = true;
            } else {
                $operator = Request::FILTER_OPERATOR_EQ;
                $value = \is_array($value) ? reset($value) : $value;
            }

            $value = $isStitchedQuery ? $value : ($value ? 'true' : 'false');
        }

        $rule = ($isStitchedQuery || Request::FILTER_TYPE_BOOLEAN === $field)
            ? [$field => [$operator => $this->enforceValueType($type, $value)]]
            : [
                Request::getFilterTypeByOperator($operator) => [
                    'field' => $field,
                    $operator => $this->enforceValueType('text', $value),
                ],
            ];

        $rule = $hasNegation
            ? [Request::FILTER_TYPE_BOOLEAN => [self::GALLY_TYPE_NOT => [$rule]]]
            : $rule;

        return [($comeFromFacet ? 'facetFilters' : 'queryFilters') => $isStitchedQuery ? $rule : [$rule]];
    }

    public function walkValue(Value $value): mixed
    {
        return $value->getValue();
    }

    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    /**
     * Remove oro type and gally prefix from field name.
     *
     * @return array [string, string, bool] the type, the field name and a boolean indicate if the filter come from facet or not
     */
    private function explodeFieldTypeName(string $field): array
    {
        [$type, $field] = Criteria::explodeFieldTypeName($field);
        if (str_contains($field, '.')) {
            $parts = explode('.', $field, 2);
            if ('bool' === $parts[0]) {
                [$type, $field] = $parts;
            }
        }

        // Gally facets are prefixed by "gally__".
        $comeFromFacet = str_contains($field, SearchEngine::GALLY_FILTER_PREFIX);
        $field = str_replace(SearchEngine::GALLY_FILTER_PREFIX, '', $field);

        return [$type, $this->attributeMapping[$field] ?? $field, $comeFromFacet];
    }

    private function getGallyOperator(string $operator): string
    {
        return match ($operator) {
            OroComparison::LT => Request::FILTER_OPERATOR_LT,
            OroComparison::LTE => Request::FILTER_OPERATOR_LTE,
            OroComparison::GT => Request::FILTER_OPERATOR_GT,
            OroComparison::GTE => Request::FILTER_OPERATOR_GTE,
            OroComparison::IN,
            'NOT ' . OroComparison::IN,
            OroComparison::NIN => Request::FILTER_OPERATOR_IN,
            OroComparison::LIKE,
            OroComparison::NOT_LIKE,
            OroComparison::CONTAINS,
            OroComparison::NOT_CONTAINS => Request::FILTER_OPERATOR_MATCH,
            OroComparison::EXISTS,
            OroComparison::NOT_EXISTS => Request::FILTER_OPERATOR_EXISTS,
            default => Request::FILTER_OPERATOR_EQ,
        };
    }

    private function enforceValueType(string $type, mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(fn ($item) => $this->enforceValueType($type, $item), $value);
        }

        return match ($type) {
            'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            'bool' => (bool) $value,
            'text' => (string) $value,
            default => $value,
        };
    }
}
