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

namespace Gally\OroPlugin\Search\Extension;

use Gally\OroPlugin\Search\SearchEngine;
use Oro\Bundle\CatalogBundle\Datagrid\Filter\SubcategoryFilter as BaseSubcategoryFilter;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\FilterBundle\Filter\FilterUtility;
use Oro\Bundle\SearchBundle\Datagrid\Filter\Adapter\SearchFilterDatasourceAdapter;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;
use Oro\Component\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Disable native subcategory_filter grid extension to avoid having category path in query
 * that will break gally virtual categories.
 */
class SubcategoryFilter extends BaseSubcategoryFilter
{
    public function __construct(
        private EngineParameters $engineParameters,
        FormFactoryInterface $factory,
        FilterUtility $util)
    {
        parent::__construct($factory, $util);
    }

    /**
     * {@inheritDoc}
     */
    public function apply(FilterDatasourceAdapterInterface $ds, $data)
    {
        if (!$ds instanceof SearchFilterDatasourceAdapter) {
            throw new UnexpectedTypeException($ds, SearchFilterDatasourceAdapter::class);
        }

        if (SearchEngine::ENGINE_NAME === $this->engineParameters->getEngineName()) {
            return true;
        }

        return $this->applyRestrictions($ds, $data);
    }
}
