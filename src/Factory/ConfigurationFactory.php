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

namespace Gally\OroPlugin\Factory;

use Gally\OroPlugin\Config\ConfigManager;
use Gally\Sdk\Client\Configuration;

class ConfigurationFactory
{
    public static function create(ConfigManager $configManager): Configuration
    {
        return new Configuration(
            $configManager->getGallyUrl(),
            $configManager->getGallyEmail(),
            $configManager->getGallyPassword(),
        );
    }
}
