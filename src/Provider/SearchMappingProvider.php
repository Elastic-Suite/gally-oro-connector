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

namespace Gally\OroPlugin\Provider;

use Gally\OroPlugin\Service\ContextProvider;
use Oro\Bundle\SearchBundle\Configuration\MappingConfigurationProviderAbstract;
use Oro\Bundle\SearchBundle\Provider\SearchMappingProvider as BaseSearchMappingProvider;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The search mapping provider.
 */
class SearchMappingProvider extends BaseSearchMappingProvider
{
    public function __construct(
        EventDispatcherInterface $dispatcher,
        MappingConfigurationProviderAbstract $mappingConfigProvider,
        CacheItemPoolInterface $cache,
        private ContextProvider $contextProvider,
        string $cacheKeyPrefix,
        string $searchEngineName,
        string $eventName,
    ) {
        parent::__construct($dispatcher, $mappingConfigProvider, $cache, $cacheKeyPrefix, $searchEngineName, $eventName);
    }

    public function getMappingConfig(): array
    {
        $this->contextProvider->setIsGallyContext(true);
        $config = parent::getMappingConfig();
        $this->contextProvider->setIsGallyContext(false);

        return $config;
    }
}
