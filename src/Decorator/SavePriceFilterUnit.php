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

namespace Gally\OroPlugin\Decorator;

use Gally\OroPlugin\Search\ContextProvider;
use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\FilterBundle\Filter\FilterInterface;
use Oro\Bundle\SearchBundle\Datagrid\Filter\SearchNumberFilter;

class SavePriceFilterUnit implements FilterInterface
{
    public function __construct(
        private SearchNumberFilter $decorated,
        private ContextProvider $contextProvider,
    ) {
    }

    public function init($name, array $params)
    {
        $this->decorated->init($name, $params);
    }

    public function getName()
    {
        return $this->decorated->getName();
    }

    public function getForm()
    {
        return $this->decorated->getForm();
    }

    public function getMetadata()
    {
        return $this->decorated->getMetadata();
    }

    public function resolveOptions()
    {
        return $this->decorated->resolveOptions();
    }

    public function apply(FilterDatasourceAdapterInterface $ds, $data): bool
    {
        if (isset($data['unit'])) {
            $this->contextProvider->setPriceFilterUnit($data['unit']);
        }

        return $this->decorated->apply($ds, $data); // @phpstan-ignore argument.type
    }

    public function prepareData(array $data): array
    {
        return $this->decorated->prepareData($data);
    }

    public function setFilterState($state): self
    {
        $this->decorated->setFilterState($state);

        return $this;
    }

    public function getFilterState()
    {
        return $this->decorated->getFilterState();
    }

    public function reset(): void
    {
        $this->decorated->reset();
    }
}
