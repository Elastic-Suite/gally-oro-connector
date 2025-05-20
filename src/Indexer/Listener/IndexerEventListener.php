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

namespace Gally\OroPlugin\Indexer\Listener;

use Gally\OroPlugin\Config\ConfigManager;
use Gally\OroPlugin\Indexer\Indexer;
use Oro\Bundle\WebsiteSearchBundle\Event\AfterReindexEvent;
use Oro\Bundle\WebsiteSearchBundle\Event\BeforeReindexEvent;

class IndexerEventListener
{
    public function __construct(
        private ConfigManager $configManager,
        private Indexer $indexer,
    ) {
    }

    public function beforeReindex(BeforeReindexEvent $event): void
    {
        if ($this->configManager->isGallyEnabled()) {
            $this->indexer->beforeReindex($event);
        }
    }

    public function afterReindex(AfterReindexEvent $event): void
    {
        if ($this->configManager->isGallyEnabled()) {
            $this->indexer->afterReindex($event);
        }
    }
}
