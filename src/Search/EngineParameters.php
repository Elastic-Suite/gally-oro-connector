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
use Oro\Bundle\SearchBundle\Engine\EngineParameters as BaseEngineParameters;

/**
 * Override website search DSN if gally is enabled on this website.
 */
class EngineParameters extends BaseEngineParameters
{
    public function __construct(
        string $dsn,
        ContextProvider $contextProvider,
        ConfigManager $configManager,
    ) {
        $website = $contextProvider->getCurrentWebsite();
        $isGallyEnabled = $website && $configManager->isGallyEnabled($website->getId());
        $dsn = $isGallyEnabled ? $configManager->getDsn() : $dsn;
        parent::__construct($dsn);
    }
}
