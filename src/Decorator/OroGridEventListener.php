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

use Gally\OroPlugin\Search\SearchEngine;
use Oro\Bundle\DataGridBundle\Event\PreBuild;
use Oro\Bundle\ProductBundle\DataGrid\EventListener\FrontendProductGridEventListener;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;

/**
 * Disable native grid event listener to avoid try to filter facet and sort elements
 * this allows to let Gally manage these elements and avoid a useless api call to Gally.
 */
class OroGridEventListener
{
    public function __construct(
        private FrontendProductGridEventListener $decorated,
        private EngineParameters $engineParameters,
    ) {
    }

    public function onPreBuild(PreBuild $event): void
    {
        if (SearchEngine::ENGINE_NAME === $this->engineParameters->getEngineName()) {
            return;
        }

        $this->decorated->onPreBuild($event);
    }
}
