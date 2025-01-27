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
                )
            ]
        );
        self::assertSame($gallyFilters, $exprVisitor->dispatch($expr));
    }

    protected function dispatchTestDataProvider(): iterable
    {
        yield [
            new Comparison('id', 'IN', new Value([1, 2, 3])),
            ['equalFilter' => ['field' => 'id', 'in' => ['1', '2', '3']]],
        ];
        yield [
            new Comparison('sku', '=', new Value('test')),
            ['equalFilter' => ['field' => 'sku', 'eq' => 'test']],
        ];
        yield [
            new Comparison('name', 'LIKE', new Value('tomato')),
            ['matchFilter' => ['field' => 'name', 'match' => 'tomato']],
        ];
        yield [
            new Comparison('all_text', 'LIKE', new Value('tomato')),
            null,
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('0')),
            ['equalFilter' => ['field' => 'isNew', 'eq' => 'false']],
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('1')),
            ['equalFilter' => ['field' => 'isNew', 'eq' => 'true']],
        ];
        yield [
            new Comparison('category__id', '=', new Value(23)),
            ['equalFilter' => ['field' => 'category__id', 'eq' => '23']],
        ];
        yield [
            new Comparison('category__id', 'IN', new Value([1, 2, 3])),
            [
                'boolFilter' => [
                    '_should' => [
                        ['equalFilter' => ['field' => 'category__id', 'eq' => '1']],
                        ['equalFilter' => ['field' => 'category__id', 'eq' => '2']],
                        ['equalFilter' => ['field' => 'category__id', 'eq' => '3']],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('category_paths.1_2_3', 'EXISTS', new Value(null)),
            ['equalFilter' => ['field' => 'category_paths', 'in' => '1_2_3']],
        ];
        yield [
            new Comparison('assigned_to.variant_42', 'EXISTS', new Value(1)),
            ['equalFilter' => ['field' => 'assigned_to', 'in' => '42']],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['out_of_stock'])),
            ['equalFilter' => ['field' => 'stock__status', 'eq' => 'false']],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock'])),
            ['equalFilter' => ['field' => 'stock__status', 'eq' => 'true']],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock', 'out_of_stock'])),
            null,
        ];
        yield [
            new Comparison('visibility_customer.12', 'EXISTS', new Value(1)),
            [
                'boolFilter' => [
                    '_should' => [
                        ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '12']],
                        ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '12']],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('visibility_customer.13', '=', 1),
            ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '13']],
        ];
        yield [
            new Comparison('visibility_customer.14', '=', -1),
            ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '14']],
        ];
        yield [
            new Comparison('decimal.price__price', '>=', '100'),
            ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
        ];
        yield [
            new Comparison('integer.brand', '=', 1234),
            ['equalFilter' => ['field' => 'brand__value', 'eq' => '1234']],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new Comparison('decimal.price__price', '>=', '100'),
                    new Comparison('decimal.price__price', '<=', '200'),
                ]
            ),
            [
                'boolFilter' => [
                    '_must' => [
                        ['rangeFilter' => ['field' => 'price__price', 'gte' => '100']],
                        ['rangeFilter' => ['field' => 'price__price', 'lte' => '200']],
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
                                    new Comparison('category_paths.1_5', 'EXISTS', new Value(1)),
                                    new Comparison('color__value', 'IN', new Value(['green', 'blue'])),
                                ]
                            ),
                            new Comparison('status', 'IN', new Value(['enabled'])),
                        ]
                    ),
                    new CompositeExpression(
                        'OR',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(1)),
                                    new Comparison('visibility_customer.42', 'NOT EXISTS', new Value(null)),
                                ]
                            ),
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(-1)),
                                    new Comparison('visibility_customer.42', '=', new Value(1)),
                                ]
                            ),
                        ]
                    ),
                ]
            ),
            [
                'boolFilter' => [
                    '_must' => [
                        [
                            'boolFilter' => [
                                '_must' => [
                                    ['equalFilter' => ['field' => 'category_paths', 'in' => '1_5']],
                                    ['equalFilter' => ['field' => 'color__value', 'in' => ['green', 'blue']]],
                                ],
                            ],
                        ],
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
                                                            [
                                                                'boolFilter' => [
                                                                    '_should' => [
                                                                        ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '42']],
                                                                        ['equalFilter' => ['field' => 'hidden_for_customer', 'eq' => '42']],
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
                                                ['equalFilter' => ['field' => 'visible_by_default', 'eq' => '-1']],
                                                ['equalFilter' => ['field' => 'visible_for_customer', 'eq' => '42']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'equalFilter' => ['field' => 'status', 'in' => ['enabled']],
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
                )
            ]
        );
        self::assertSame($gallyFilters, $exprVisitor->dispatch($expr, true));
    }

    protected function dispatchStitchedTestDataProvider(): iterable
    {
        yield [
            new Comparison('id', 'IN', new Value([1, 2, 3])),
            ['id' => ['in' => ['1', '2', '3']]],
        ];
        yield [
            new Comparison('sku', '=', new Value('test')),
            ['sku' => ['eq' => 'test']],
        ];
        yield [
            new Comparison('name', 'LIKE', new Value('tomato')),
            ['name' => ['match' => 'tomato']],
        ];
        yield [
            new Comparison('all_text', 'LIKE', new Value('tomato')),
            null,
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('0')),
            ['isNew' => ['eq' => false]],
        ];
        yield [
            new Comparison('bool.isNew', '=', new Value('1')),
            ['isNew' => ['eq' => true]],
        ];
        yield [
            new Comparison('category__id', '=', new Value(23)),
            ['category__id' => ['eq' => '23']],
        ];
        yield [
            new Comparison('category__id', 'IN', new Value([1, 2, 3])),
            [
                'boolFilter' => [
                    '_should' => [
                        ['category__id' => ['eq' => '1']],
                        ['category__id' => ['eq' => '2']],
                        ['category__id' => ['eq' => '3']],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('category_paths.1_2_3', 'EXISTS', new Value(null)),
            ['category_paths' => ['in' => '1_2_3']],
        ];
        yield [
            new Comparison('assigned_to.variant_42', 'EXISTS', new Value(1)),
            ['assigned_to' => ['in' => '42']],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['out_of_stock'])),
            ['stock__status' => ['eq' => false]],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock'])),
            ['stock__status' => ['eq' => true]],
        ];
        yield [
            new Comparison('inv_status', 'IN', new Value(['in_stock', 'out_of_stock'])),
            null,
        ];
        yield [
            new Comparison('visibility_customer.12', 'EXISTS', new Value(1)),
            [
                'boolFilter' => [
                    '_should' => [
                        ['visible_for_customer' => ['eq' => '12']],
                        ['hidden_for_customer' => ['eq' => '12']],
                    ],
                ],
            ],
        ];
        yield [
            new Comparison('visibility_customer.13', '=', 1),
            ['visible_for_customer' => ['eq' => '13']],
        ];
        yield [
            new Comparison('visibility_customer.14', '=', -1),
            ['hidden_for_customer' => ['eq' => '14']],
        ];
        yield [
            new Comparison('decimal.price__price', '>=', 100),
            ['price__price' => ['gte' => 100.0]],
        ];
        yield [
            new Comparison('integer.brand', '=', 1234),
            ['brand__value' => ['eq' => "1234"]],
        ];
        yield [
            new CompositeExpression(
                'AND',
                [
                    new Comparison('decimal.price__price', '>=', '100'),
                    new Comparison('decimal.price__price', '<=', '200'),
                ]
            ),
            [
                'boolFilter' => [
                    '_must' => [
                        ['price__price' => ['gte' => 100.0]],
                        ['price__price' => ['lte' => 200.0]],
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
                                    new Comparison('category_paths.1_5', 'EXISTS', new Value(1)),
                                    new Comparison('color__value', 'IN', new Value(['green', 'blue'])),
                                ]
                            ),
                            new Comparison('status', 'IN', new Value(['enabled'])),
                        ]
                    ),
                    new CompositeExpression(
                        'OR',
                        [
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(1)),
                                    new Comparison('visibility_customer.42', 'NOT EXISTS', new Value(null)),
                                ]
                            ),
                            new CompositeExpression(
                                'AND',
                                [
                                    new Comparison('visible_by_default', '=', new Value(-1)),
                                    new Comparison('visibility_customer.42', '=', new Value(1)),
                                ]
                            ),
                        ]
                    ),
                ]
            ),
            [
                'category_paths' => ['in' => '1_5'],
                'color__value' => ['in' => ['green', 'blue']],
                'status' => ['in' => ['enabled']],
                'boolFilter' => [
                    '_should' => [
                        [
                            'boolFilter' => [
                                '_must' => [
                                    ['visible_by_default' => ['eq' => '1']],
                                    [
                                        'boolFilter' => [
                                            '_not' => [
                                                [
                                                    'boolFilter' => [
                                                        '_should' => [
                                                            ['visible_for_customer' => ['eq' => '42']],
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
        ];
    }
}
