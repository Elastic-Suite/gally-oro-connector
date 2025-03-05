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

namespace Gally\OroPlugin\Tests\Search;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\Value;
use Gally\OroPlugin\Search\ExpressionVisitor;
use Gally\Sdk\Entity\Metadata;
use Gally\Sdk\Entity\SourceField;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class ExpressionVisitorTest extends WebTestCase
{
    private array $attributeMapping = [
        'system_entity_id' => 'id',
        'names' => 'name',
        'descriptions' => 'description',
    ];

    /**
     * @dataProvider dispatchTestDataProvider
     */
    public function testDispatch(
        Expression $expr,
        ?array $gallyFilters,
    ): void {
        $exprVisitor = new ExpressionVisitor($this->attributeMapping);
        $exprVisitor->setSelectSourceFields(
            [
                'brand' => new SourceField(
                    new Metadata('product'),
                    'brand',
                    SourceField::TYPE_SELECT,
                    'Brand',
                    [],
                ),
            ]
        );
        self::assertSame($gallyFilters, $exprVisitor->dispatch($expr));
    }

    protected function dispatchTestDataProvider(): iterable
    {
        yield [
            new Comparison('id', 'IN', new Value([1, 2, 3])),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'id', 'in' => ['1', '2', '3']]],
                ],
            ],
        ];
        yield [
            new Comparison('type', 'NIN', new Value(['configurable', 'kit'])),
            [
                'queryFilters' => [
                    ['boolFilter' => ['_not' => [['equalFilter' => ['field' => 'type', 'in' => ['configurable', 'kit']]]]]],
                ],
            ],
        ];
        yield [
            new Comparison('sku', '=', new Value('test')),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'sku', 'eq' => 'test']],
                ],
            ],
        ];
        yield [
            new Comparison('name', 'LIKE', new Value('tomato')),
            [
                'queryFilters' => [
                    ['matchFilter' => ['field' => 'name', 'match' => 'tomato']],
                ],
            ],
        ];
        yield [
            new Comparison('all_text', 'LIKE', new Value('tomato')),
            null,
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('0')),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'isNew', 'eq' => 'false']],
                ],
            ],
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('1')),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'isNew', 'eq' => 'true']],
                ],
            ],
        ];
        yield [
            new Comparison('bool.gally__isNew', '=', new Value('1')),
            [
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'isNew', 'eq' => 'true']],
                ],
            ],
        ];
        yield [
            new Comparison('category__id', '=', new Value(23)),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'category__id', 'eq' => '23']],
                ],
            ],
        ];
        yield [
            new Comparison('category__id', 'IN', new Value([1, 2, 3])),
            [
                'queryFilters' => [
                    [
                        'boolFilter' => [
                            '_should' => [
                                ['equalFilter' => ['field' => 'category__id', 'eq' => '1']],
                                ['equalFilter' => ['field' => 'category__id', 'eq' => '2']],
                                ['equalFilter' => ['field' => 'category__id', 'eq' => '3']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('category__id', 'NOT IN', new Value([1, 2, 3])),
            [
                'queryFilters' => [
                    ['boolFilter' => ['_not' => [['equalFilter' => ['field' => 'category__id', 'eq' => '1']]]]],
                    ['boolFilter' => ['_not' => [['equalFilter' => ['field' => 'category__id', 'eq' => '2']]]]],
                    ['boolFilter' => ['_not' => [['equalFilter' => ['field' => 'category__id', 'eq' => '3']]]]],
                ],
            ],
        ];
        yield [
            new Comparison('category_paths.1_2_3', 'EXISTS', new Value(null)),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'category_paths', 'in' => '1_2_3']],
                ],
            ],
        ];
        yield [
            new Comparison('assigned_to.variant_42', 'EXISTS', new Value(1)),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'assigned_to', 'in' => '42']],
                ],
            ],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['out_of_stock'])),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'stock__status', 'eq' => 'false']],
                ],
            ],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock'])),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'stock__status', 'eq' => 'true']],
                ],
            ],
        ];
        yield [
            new Comparison('gally__inv_status', 'IN', new Value(['in_stock'])),
            [
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'stock__status', 'eq' => 'true']],
                ],
            ],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock', 'out_of_stock'])),
            null,
        ];
        yield [
            new Comparison('visibility_customer.12', 'EXISTS', new Value(1)),
            [
                'queryFilters' => [
                    [
                        'boolFilter' => [
                            '_should' => [
                                ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '12']],
                                ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '12']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('visibility_customer.13', '=', 1),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '13']],
                ],
            ],
        ];
        yield [
            new Comparison('visibility_customer.14', '=', -1),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '14']],
                ],
            ],
        ];
        yield [
            new Comparison('decimal.price__price', '>=', 100),
            [
                'queryFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                ],
            ],
        ];
        yield [
            new Comparison('integer.brand', '=', 1230),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1230']],
                ],
            ],
        ];
        yield [
            new Comparison('integer.gally__brand', '=', 1231),
            [
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1231']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new Comparison('decimal.price__price', '>=', '100'),
                    new Comparison('decimal.price__price', '<=', '200'),
                ],
            ),
            [
                'queryFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                    ['rangeFilter' => ['field' => 'price__price', 'lte' => '200']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new Comparison('decimal.gally__price__price', '>=', '200'),
                    new Comparison('decimal.gally__price__price', '<=', '300'),
                ],
            ),
            [
                'facetFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '200']],
                    ['rangeFilter' => ['field' => 'price__price', 'lte' => '300']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new Comparison('decimal.price__price', '>=', '100'),
                            new Comparison('integer.brand', '=', 1232),
                        ],
                    ),
                    new Comparison('visibility_customer.14', '=', -1),
                ],
            ),
            [
                'queryFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1232']],
                    ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '14']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new Comparison('decimal.gally__price__price', '>=', '100'),
                            new Comparison('integer.gally__brand', '=', 1233),
                        ],
                    ),
                    new Comparison('gally__visibility_customer.14', '=', -1),
                ],
            ),
            [
                'facetFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1233']],
                    ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '14']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        expressions: [
                            new Comparison('decimal.price__price', '>=', '100'),
                            new Comparison('integer.brand', '=', 1234),
                        ]
                    ),
                    new Comparison('gally__visibility_customer.14', '=', -1),
                ]
            ),
            [
                'queryFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1234']],
                ],
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '14']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        expressions: [
                            new Comparison('decimal.price__price', '>=', '100'),
                            new Comparison('integer.gally__brand', '=', 1235),
                        ]
                    ),
                    new Comparison('visibility_customer.14', '=', -1),
                ],
            ),
            [
                'queryFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                    ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '14']],
                ],
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1235']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('category_paths.1_5', 'EXISTS', new Value(1)),
                                    new Comparison('gally__color__value', 'IN', new Value(['green', 'blue'])),
                                ],
                            ),
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('status', 'IN', new Value(['enabled'])),
                                    new Comparison('integer.gally__brand', '=', 1236),
                                ],
                            ),
                        ],
                    ),
                    new CompositeExpression(
                        'OR',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(1)),
                                    new Comparison('visibility_customer.42', 'NOT EXISTS', new Value(null)),
                                ],
                            ),
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(-1)),
                                    new Comparison('visibility_customer.42', '=', new Value(1)),
                                ],
                            ),
                        ],
                    ),
                ]
            ),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'category_paths', 'in' => '1_5']],
                    ['equalFilter' => ['field' => 'status', 'in' => ['enabled']]],
                    [
                        'boolFilter' => [
                            '_should' => [
                                [
                                    'boolFilter' => [
                                        '_must' => [
                                            ['equalFilter' => ['field' => 'visible_by_default', 'eq' => '1']],
                                            [
                                                'boolFilter' => [
                                                    '_not' => [
                                                        ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '42']],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'boolFilter' => [
                                                    '_not' => [
                                                        ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '42']],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'boolFilter' => [
                                        '_must' => [
                                            ['equalFilter' => ['field' => 'visible_by_default', 'eq' => '-1']],
                                            ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '42']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'color__value', 'in' => ['green', 'blue']]],
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1236']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new CompositeExpression(
                                        'AND',
                                        [
                                            new CompositeExpression(
                                                'AND',
                                                [
                                                    new Comparison('integer.brand', '=', new Value(1425)),
                                                    new Comparison('integer.is_variant', '=', new Value(0)),
                                                ],
                                            ),
                                            new Comparison('integer.is_variant', '=', new Value(0)),
                                        ],
                                    ),
                                    new Comparison('status', 'IN', new Value(['enabled'])),
                                ]
                            ),
                            new Comparison('inv_status', 'IN', new Value(['in_stock', 'out_of_stock'])),
                        ],
                    ),
                    new Comparison('integer.visibility_anonymous', '=', new Value(1)),
                ],
            ),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'brand__value', 'eq' => '1425']],
                    ['equalFilter' => ['field' => 'is_variant', 'eq' => '0']],
                    ['equalFilter' => ['field' => 'is_variant', 'eq' => '0']],
                    ['equalFilter' => ['field' => 'status', 'in' => ['enabled']]],
                    ['equalFilter' => ['field' => 'visibility_anonymous', 'eq' => '1']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new CompositeExpression(
                                        'AND',
                                        [
                                            new Comparison('all_text', 'CONTAINS', new Value('medical')),
                                            new Comparison('decimal.gally__price__price', '>=', new Value(10)),
                                        ],
                                    ),
                                    new Comparison('decimal.gally__price__price', '<=', new Value('20')),
                                ],
                            ),
                            new Comparison('decimal.gally__price__price', '>=', new Value(10)),
                        ],
                    ),
                    new Comparison('decimal.gally__price__price', '<=', new Value('20')),
                ],
            ),
            [
                'facetFilters' => [
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '10']],
                    ['rangeFilter' => ['field' => 'price__price', 'lte' => '20']],
                    ['rangeFilter' => ['field' => 'price__price', 'gte' => '10']],
                    ['rangeFilter' => ['field' => 'price__price', 'lte' => '20']],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('integer.is_variant', '=', new Value('0')),
                                    new Comparison('integer.is_variant', '=', new Value('0')),
                                ]
                            ),
                            new Comparison('gally__pim_923__value', 'IN', new Value('9461')),
                        ]
                    ),
                    new CompositeExpression(
                        'OR',
                        [
                            new Comparison('integer.is_variant', '=', new Value(0)),
                        ]
                    ),
                ]
            ),
            [
                'queryFilters' => [
                    ['equalFilter' => ['field' => 'is_variant', 'eq' => '0']],
                    ['equalFilter' => ['field' => 'is_variant', 'eq' => '0']],
                    [
                        'boolFilter' => [
                            '_should' => [
                                ['equalFilter' => ['field' => 'is_variant', 'eq' => '0']],
                            ],
                        ],
                    ],
                ],
                'facetFilters' => [
                    ['equalFilter' => ['field' => 'pim_923__value', 'in' => '9461']],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dispatchStitchedTestDataProvider
     */
    public function testDispatchStitched(
        Expression $expr,
        ?array $gallyFilters,
    ): void {
        $exprVisitor = new ExpressionVisitor($this->attributeMapping);
        $exprVisitor->setSelectSourceFields(
            [
                'brand' => new SourceField(
                    new Metadata('product'),
                    'brand',
                    SourceField::TYPE_SELECT,
                    'Brand',
                    [],
                ),
            ]
        );
        self::assertSame($gallyFilters, $exprVisitor->dispatch($expr, true));
    }

    protected function dispatchStitchedTestDataProvider(): iterable
    {
        yield [
            new Comparison('id', 'IN', new Value([1, 2, 3])),
            [
                'queryFilters' => [
                    'id' => ['in' => ['1', '2', '3']],
                ],
            ],
        ];
        yield [
            new Comparison('sku', '=', new Value('test')),
            [
                'queryFilters' => [
                    'sku' => ['eq' => 'test'],
                ],
            ],
        ];
        yield [
            new Comparison('name', 'LIKE', new Value('tomato')),
            [
                'queryFilters' => [
                    'name' => ['match' => 'tomato'],
                ],
            ],
        ];
        yield [
            new Comparison('all_text', 'LIKE', new Value('tomato')),
            null,
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('0')),
            [
                'queryFilters' => [
                    'isNew' => ['eq' => false],
                ],
            ],
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('1')),
            [
                'queryFilters' => [
                    'isNew' => ['eq' => true],
                ],
            ],
        ];
        yield [
            new Comparison('bool.gally__isNew', '=', new Value('1')),
            [
                'facetFilters' => [
                    'isNew' => ['eq' => true],
                ],
            ],
        ];
        yield [
            new Comparison('category__id', '=', new Value(23)),
            [
                'queryFilters' => [
                    'category__id' => ['eq' => '23'],
                ],
            ],
        ];
        yield [
            new Comparison('category__id', 'IN', new Value([1, 2, 3])),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_should' => [
                            ['category__id' => ['eq' => '1']],
                            ['category__id' => ['eq' => '2']],
                            ['category__id' => ['eq' => '3']],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('category__id', 'NOT IN', new Value([4, 5, 6])),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_must' => [
                            ['boolFilter' => ['_not' => [['category__id' => ['eq' => '4']]]]],
                            ['boolFilter' => ['_not' => [['category__id' => ['eq' => '5']]]]],
                            ['boolFilter' => ['_not' => [['category__id' => ['eq' => '6']]]]],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('category_paths.1_2_3', 'EXISTS', new Value(null)),
            [
                'queryFilters' => [
                    'category_paths' => ['in' => '1_2_3'],
                ],
            ],
        ];
        yield [
            new Comparison('assigned_to.variant_42', 'EXISTS', new Value(1)),
            [
                'queryFilters' => [
                    'assigned_to' => ['in' => '42'],
                ],
            ],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['out_of_stock'])),
            [
                'queryFilters' => [
                    'stock__status' => ['eq' => false],
                ],
            ],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock'])),
            [
                'queryFilters' => [
                    'stock__status' => ['eq' => true],
                ],
            ],
        ];
        yield [
            new Comparison('gally__inv_status', 'IN', new Value(['in_stock'])),
            [
                'facetFilters' => [
                    'stock__status' => ['eq' => true],
                ],
            ],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock', 'out_of_stock'])),
            null,
        ];
        yield [
            new Comparison('visibility_customer.12', 'EXISTS', new Value(1)),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_should' => [
                            ['visible_for_customer' => ['eq' => '12']],
                            ['hidden_for_customer' => ['eq' => '12']],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('visibility_customer.13', '=', 1),
            [
                'queryFilters' => [
                    'visible_for_customer' => ['eq' => '13'],
                ],
            ],
        ];
        yield [
            new Comparison('visibility_customer.14', '=', -1),
            [
                'queryFilters' => [
                    'hidden_for_customer' => ['eq' => '14'],
                ],
            ],
        ];
        yield [
            new Comparison('decimal.price__price', '>=', 100),
            [
                'queryFilters' => [
                    'price__price' => ['gte' => 100.0],
                ],
            ],
        ];
        yield [
            new Comparison('integer.brand', '=', 1230),
            [
                'queryFilters' => [
                    'brand__value' => ['eq' => '1230'],
                ],
            ],
        ];
        yield [
            new Comparison('integer.gally__brand', '=', 1231),
            [
                'facetFilters' => [
                    'brand__value' => ['eq' => '1231'],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new Comparison('decimal.price__price', '>=', '100'),
                    new Comparison('decimal.price__price', '<=', '200'),
                ],
            ),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_must' => [
                            ['price__price' => ['gte' => 100.0]],
                            ['price__price' => ['lte' => 200.0]],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new Comparison('decimal.gally__price__price', '>=', '200'),
                    new Comparison('decimal.gally__price__price', '<=', '300'),
                ],
            ),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_must' => [
                            ['price__price' => ['gte' => 200.0]],
                            ['price__price' => ['lte' => 300.0]],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new Comparison('decimal.price__price', '>=', '100'),
                            new Comparison('integer.brand', '=', 1232),
                        ],
                    ),
                    new Comparison('visibility_customer.14', '=', -1),
                ]
            ),
            [
                'queryFilters' => [
                    'price__price' => ['gte' => 100.0],
                    'brand__value' => ['eq' => '1232'],
                    'hidden_for_customer' => ['eq' => '14'],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new Comparison('decimal.gally__price__price', '>=', '100'),
                            new Comparison('integer.gally__brand', '=', 1233),
                        ],
                    ),
                    new Comparison('gally__visibility_customer.14', '=', -1),
                ],
            ),
            [
                'facetFilters' => [
                    'price__price' => ['gte' => 100.0],
                    'brand__value' => ['eq' => '1233'],
                    'hidden_for_customer' => ['eq' => '14'],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        expressions: [
                            new Comparison('decimal.price__price', '>=', '100'),
                            new Comparison('integer.brand', '=', 1234),
                        ],
                    ),
                    new Comparison('gally__visibility_customer.14', '=', -1),
                ],
            ),
            [
                'queryFilters' => [
                    'price__price' => ['gte' => 100.0],
                    'brand__value' => ['eq' => '1234'],
                ],
                'facetFilters' => [
                    'hidden_for_customer' => ['eq' => '14'],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        expressions: [
                            new Comparison('decimal.price__price', '>=', '100'),
                            new Comparison('integer.gally__brand', '=', 1235),
                        ],
                    ),
                    new Comparison('visibility_customer.14', '=', -1),
                ],
            ),
            [
                'queryFilters' => [
                    'price__price' => ['gte' => 100.0],
                    'hidden_for_customer' => ['eq' => '14'],
                ],
                'facetFilters' => [
                    'brand__value' => ['eq' => '1235'],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('category_paths.1_5', 'EXISTS', new Value(1)),
                                    new Comparison('gally__color__value', 'IN', new Value(['green', 'blue'])),
                                ],
                            ),
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('status', 'IN', new Value(['enabled'])),
                                    new Comparison('integer.gally__brand', '=', 1236),
                                ],
                            ),
                        ],
                    ),
                    new CompositeExpression(
                        'OR',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(1)),
                                    new Comparison('visibility_customer.42', 'NOT EXISTS', new Value(null)),
                                ],
                            ),
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(-1)),
                                    new Comparison('visibility_customer.42', '=', new Value(1)),
                                ],
                            ),
                        ],
                    ),
                ],
            ),
            [
                'queryFilters' => [
                    'category_paths' => ['in' => '1_5'],
                    'status' => ['in' => ['enabled']],
                    'boolFilter' => [
                        '_should' => [
                            [
                                'boolFilter' => [
                                    '_must' => [
                                        ['visible_by_default' => ['eq' => '1']],
                                        [
                                            'boolFilter' => [
                                                '_must' => [
                                                    [
                                                        'boolFilter' => [
                                                            '_not' => [
                                                                ['visible_for_customer' => ['eq' => '42']],
                                                            ],
                                                        ],
                                                    ],
                                                    [
                                                        'boolFilter' => [
                                                            '_not' => [
                                                                ['hidden_for_customer' => ['eq' => '42']],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'boolFilter' => [
                                    '_must' => [
                                        ['visible_by_default' => ['eq' => '-1']],
                                        ['visible_for_customer' => ['eq' => '42']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'facetFilters' => [
                    'color__value' => ['in' => ['green', 'blue']],
                    'brand__value' => ['eq' => '1236'],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new CompositeExpression(
                                        'AND',
                                        [
                                            new CompositeExpression(
                                                'AND',
                                                [
                                                    new Comparison('integer.brand', '=', new Value(1425)),
                                                    new Comparison('integer.is_variant', '=', new Value(0)),
                                                ],
                                            ),
                                            new Comparison('integer.is_variant', '=', new Value(0)),
                                        ],
                                    ),
                                    new Comparison('status', 'IN', new Value(['enabled'])),
                                ]
                            ),
                            new Comparison('inv_status', 'IN', new Value(['in_stock', 'out_of_stock'])),
                        ],
                    ),
                    new Comparison('integer.visibility_anonymous', '=', new Value(1)),
                ],
            ),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_must' => [
                            ['brand__value' => ['eq' => '1425']],
                            ['is_variant' => ['eq' => 0]],
                            ['is_variant' => ['eq' => 0]],
                        ],
                    ],
                    'status' => ['in' => ['enabled']],
                    'visibility_anonymous' => ['eq' => 1],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new CompositeExpression(
                                        'AND',
                                        [
                                            new Comparison('all_text', 'CONTAINS', new Value('medical')),
                                            new Comparison('decimal.gally__price__price', '>=', new Value(10)),
                                        ],
                                    ),
                                    new Comparison('decimal.gally__price__price', '<=', new Value('20')),
                                ],
                            ),
                            new Comparison('decimal.gally__price__price', '>=', new Value(10)),
                        ],
                    ),
                    new Comparison('decimal.gally__price__price', '<=', new Value('20')),
                ],
            ),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_must' => [
                            [
                                'boolFilter' => [
                                    '_must' => [
                                        ['price__price' => ['gte' => 10.0]],
                                        ['price__price' => ['lte' => 20.0]],
                                    ],
                                ],
                            ],
                            [
                                'boolFilter' => [
                                    '_must' => [
                                        ['price__price' => ['gte' => 10.0]],
                                        ['price__price' => ['lte' => 20.0]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new CompositeExpression(
                        'AND',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('integer.is_variant', '=', new Value('0')),
                                    new Comparison('integer.is_variant', '=', new Value('0')),
                                ]
                            ),
                            new Comparison('gally__pim_923__value', 'IN', new Value('9461')),
                        ]
                    ),
                    new CompositeExpression(
                        'OR',
                        [
                            new Comparison('integer.is_variant', '=', new Value(0)),
                        ]
                    ),
                ]
            ),
            [
                'queryFilters' => [
                    'boolFilter' => [
                        '_must' => [
                            [
                                'boolFilter' => [
                                    '_must' => [
                                        ['is_variant' => ['eq' => 0]],
                                        ['is_variant' => ['eq' => 0]],
                                    ],
                                ],
                            ],
                            [
                                'boolFilter' => [
                                    '_should' => [
                                        ['is_variant' => ['eq' => 0]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'facetFilters' => [
                    'pim_923__value' => ['in' => '9461'],
                ],
            ],
        ];
    }
}
