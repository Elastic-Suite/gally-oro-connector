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

namespace Gally\OroPlugin\Config;

use Gally\OroPlugin\Search\SearchEngine;

class ConfigManager
{
    public function __construct(
        private string $engineName,
    ) {
    }

    public function isGallyEnabled(): bool
    {
        return SearchEngine::ENGINE_NAME === $this->engineName;
    }
}
