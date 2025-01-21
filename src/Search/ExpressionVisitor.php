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
use Gally\Sdk\GraphQl\Request;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\VisibilityBundle\Entity\VisibilityResolved\BaseVisibilityResolved;

class ExpressionVisitor extends BaseExpressionVisitor
{
    public function __construct(
        private array $attributeMapping,
    ) {
    }

    private ?string $searchQuery = null;

    public function dispatch(Expression $expr, bool $isStitchedQuery = false, bool $isMainQuery = true)
    {
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
        $type = '_must';
        if (CompositeExpression::TYPE_AND !== $expr->getType()) {
            $isMainQuery = false;
            $type = '_should';
        }

        $filters = [];
        foreach ($expr->getExpressionList() as $expression) {
            $filter = $this->dispatch($expression, $isStitchedQuery, $isMainQuery);
            if ($filter) {
                if ($isMainQuery) {
                    foreach ($filter as $field => $data) {
                        if (!\array_key_exists($field, $filters)) {
                            $filters[$field] = $data;
                        } else {
                            $filters[Request::FILTER_TYPE_BOOLEAN] = [
                                $type => [
                                    [$field => $filters[$field]],
                                    [$field => $data],
                                ],
                            ];
                            if (Request::FILTER_TYPE_BOOLEAN !== $field) {
                                unset($filters[$field]);
                            }
                        }
                    }
                } else {
                    $filters[] = $filter;
                }
            }
        }
        $filters = array_filter($filters);

        return $isMainQuery
            ? $filters
            : [Request::FILTER_TYPE_BOOLEAN => [$type => $filters]];
    }

    public function walkComparison(Comparison $comparison, bool $isStitchedQuery = false): ?array
    {
        [$type, $field] = $this->explodeFieldTypeName($comparison->getField());
        $value = $this->dispatch($comparison->getValue(), $isStitchedQuery);
        $operator = $this->getGallyOperator($comparison->getOperator());
        $hasNegation = str_starts_with($comparison->getOperator(), 'NOT') || '<>' === $comparison->getOperator();

        if ('all_text' === $field) {
            $this->searchQuery = $value;

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
        } elseif (str_starts_with($field, 'category_paths.')) {
            [$field, $value] = explode('.', $field);
            $type = 'text';
            $operator = Request::FILTER_OPERATOR_IN;
        } elseif ('category__id' === $field && Request::FILTER_OPERATOR_IN === $operator) {
            // Category filter do not support in operator
            $value = array_map(
                fn ($value) => $this->dispatch(new Comparison($field, '=', $value), $isStitchedQuery),
                $value
            );
            $field = Request::FILTER_TYPE_BOOLEAN;
            $operator = '_should';
        } elseif (str_starts_with($field, 'visibility_customer.')) {
            [$field, $customerId] = explode('.', $field);
            $type = 'text';
            if (Request::FILTER_OPERATOR_EXISTS === $operator) {
                $field = Request::FILTER_TYPE_BOOLEAN;
                $operator = '_should';
                $value = [
                    $this->dispatch(new Comparison('visible_for_customer', '=', $customerId), $isStitchedQuery),
                    $this->dispatch(new Comparison('hidden_for_customer', '=', $customerId), $isStitchedQuery),
                ];
            } elseif (Request::FILTER_OPERATOR_EQ == $operator) {
                $field = BaseVisibilityResolved::VISIBILITY_HIDDEN === $value
                    ? 'hidden_for_customer'
                    : 'visible_for_customer';
                $value = $customerId;
            }
        }

        if ('bool' === $type) {
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

        return $hasNegation
            ? [Request::FILTER_TYPE_BOOLEAN => ['_not' => [$rule]]]
            : $rule;
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
     * @return array [string, string]
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

        return [$type, $this->attributeMapping[$field] ?? $field];
    }

    private function getGallyOperator(string $operator): string
    {
        return match ($operator) {
            'IN', 'NOT IN' => Request::FILTER_OPERATOR_IN,
            'LIKE', 'NOT LIKE' => Request::FILTER_OPERATOR_MATCH,
            'EXISTS', 'NOT EXISTS' => Request::FILTER_OPERATOR_EXISTS,
            '>' => Request::FILTER_OPERATOR_GT,
            '>=' => Request::FILTER_OPERATOR_GTE,
            '<' => Request::FILTER_OPERATOR_LT,
            '<=' => Request::FILTER_OPERATOR_LTE,
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
