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

use Gally\OroPlugin\Config\ConfigManager;
use Gally\OroPlugin\Service\ContextProvider;
use Oro\Bundle\SearchBundle\Engine\EngineParameters as BaseEngineParameters;

/**
 * Override website search DSN if gally is enabled on this website.
 */
class EngineParameters extends BaseEngineParameters
{
    private bool $hasBeenReinit = false;

    public function __construct(
        private string $dsn,
        private ContextProvider $contextProvider,
        private ConfigManager $configManager,
    ) {
        parent::__construct($dsn);
    }

    public function reinit(): void
    {
        $website = $this->contextProvider->getCurrentWebsite();
        $isGallyEnabled = $website && $this->configManager->isGallyEnabled($website->getId());
        $dsn = $isGallyEnabled ? $this->configManager->getDsn() : $this->dsn;

        parent::__construct($dsn);
    }

    public function getEngineName(): string
    {
        // It is not possible to get the current website from constructor. It is not set yet.
        // So we reinit the DNS on the first call in "real" context.
        // This means that might "miss" first calls to getEngineName from constructor,
        // because we cannot guess the current website in this context.
        if ($this->contextProvider->getCurrentWebsite() && !$this->hasBeenReinit) {
            $this->reinit();
            $this->hasBeenReinit = true;
        }

        return parent::getEngineName();
    }
}
