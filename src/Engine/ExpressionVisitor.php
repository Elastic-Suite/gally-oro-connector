<?php

namespace Gally\OroPlugin\Engine;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor as BaseExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;
use Gally\Sdk\GraphQl\Request;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;

class ExpressionVisitor extends BaseExpressionVisitor
{
    private ?string $searchQuery = null;
    private ?string $currentCategoryId = null;

    public function walkCompositeExpression(CompositeExpression $expr): array
    {
        $filters = [];
        foreach ($expr->getExpressionList() as $expression) {
            $filters[] = $this->dispatch($expression);
        }

        $type = CompositeExpression::TYPE_AND === $expr->getType() ? '_must' : '_should';
        return ['boolFilter' => [$type  => array_values(array_filter($filters))]];
    }

    public function walkComparison(Comparison $comparison): ?array
    {
        [$type, $field] = Criteria::explodeFieldTypeName($comparison->getField());
        $value = $this->dispatch($comparison->getValue());
        $operator = match ($comparison->getOperator()) {
            'IN' => Request::FILTER_OPERATOR_IN,
            'LIKE' => Request::FILTER_OPERATOR_MATCH,
            default => Request::FILTER_OPERATOR_EQ,
            // todo add EXISTS
        };

        if ($field === 'all_text') {
            $this->searchQuery = $value;
            return null;
        }

        if ($field === 'inv_status') {
            return null;
        }

        if ($field === 'category_path') {
            $this->currentCategoryId = 'node_' . basename(str_replace('_', '/', $value));
            return null;
        }

        if (str_starts_with($field, 'assigned_to')) {
            return null; // Todo manage this
        }
        if (str_starts_with($field, 'manually_added_to')) {
            return null; // Todo manage this
        }

        if (str_starts_with($field, 'category_path')) {
            return null;
        }

        return [$field => [$operator => $value]];
    }

    public function walkValue(Value $value): mixed
    {
        return $value->getValue();
    }

    public function getCurrentCategoryId(): ?string
    {
        return $this->currentCategoryId;
    }

    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }
}
