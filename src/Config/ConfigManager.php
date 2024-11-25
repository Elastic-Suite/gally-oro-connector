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

use Doctrine\Persistence\ManagerRegistry;
use Gally\OroPlugin\Search\SearchEngine;
use Oro\Bundle\ConfigBundle\Config\ConfigManager as OroConfigManager;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class ConfigManager
{
    public function __construct(
        private ManagerRegistry $registry,
        private OroConfigManager $configManager,
        private SymmetricCrypterInterface $crypter,
    ) {
    }

    public function getConfigValue(string $key, int $websiteId = null)
    {
        if ($websiteId) {
            $website = $this->registry
                ->getManagerForClass(Website::class)
                ->getRepository(Website::class)
                ->find($websiteId);

            return $this->configManager->get($key, false, false, $website);
        }

        return $this->configManager->get($key);
    }

    public function isGallyEnabled(int $websiteId = null): bool
    {
        return (bool) $this->getConfigValue('gally_oro.enabled', $websiteId);
    }

    public function getGallyUrl(): string
    {
        return $this->getConfigValue('gally_oro.url') ?? '';
    }

    public function getGallyEmail(): string
    {
        return $this->getConfigValue('gally_oro.email') ?? '';
    }

    public function getGallyPassword(): string
    {
        $encryptedPassword = $this->getConfigValue('gally_oro.password') ?? '';
        $decryptedPassword = $this->crypter->decryptData($encryptedPassword);

        return $decryptedPassword ?: $encryptedPassword;
    }

    public function getDsn(): string
    {
        $url = parse_url($this->getGallyUrl());

        return sprintf(
            '%s://%s:%s@%s:%s',
            SearchEngine::ENGINE_NAME,
            rawurlencode($this->getGallyEmail()),
            rawurlencode($this->getGallyPassword()),
            $url['host'],
            $url['port'] ?? ('https' === $url['scheme'] ? 443 : 80)
        );
    }
}
