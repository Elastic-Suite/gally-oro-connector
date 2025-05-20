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
use Gally\Sdk\Client\Configuration;

/**
 * Create gally configuration from Oro DSN configuration.
 */
class ConfigurationFactory
{
    public static function create(string $dsn): Configuration
    {
        $websiteSearchDsn = parse_url($dsn);
        parse_str($websiteSearchDsn['query'] ?? '', $options);

        if (SearchEngine::ENGINE_NAME !== $websiteSearchDsn['scheme']) {
            throw new \LogicException('Your website search engine DSN is not configured to use gally.');
        }

        return new Configuration(
            sprintf(
                '%s://%s/%s',
                443 === $websiteSearchDsn['port'] ? 'https' : 'http',
                $websiteSearchDsn['host'],
                $options['path']
            ),
            (bool) ($options['check_ssl'] ?? true),
            stripcslashes($websiteSearchDsn['user'] ?? ''),
            stripcslashes($websiteSearchDsn['pass'] ?? ''),
        );
    }
}
