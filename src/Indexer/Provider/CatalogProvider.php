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

namespace Gally\OroPlugin\Indexer\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Gally\OroPlugin\Config\ConfigManager;
use Gally\OroPlugin\Convertor\LocalizationConvertor;
use Gally\Sdk\Entity\Catalog;
use Gally\Sdk\Entity\LocalizedCatalog;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\PricingBundle\Provider\WebsiteCurrencyProvider;
use Oro\Bundle\WebsiteBundle\Entity\Repository\WebsiteRepository;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Provider\AbstractWebsiteLocalizationProvider;

/**
 * Gally Catalog data provider.
 */
class CatalogProvider implements ProviderInterface
{
    private array $catalogCache = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AbstractWebsiteLocalizationProvider $websiteLocalizationProvider,
        private WebsiteCurrencyProvider $currencyProvider,
        private ConfigManager $configManager,
    ) {
    }

    /**
     * @return iterable<LocalizedCatalog>
     */
    public function provide(): iterable
    {
        if (!$this->configManager->isGallyEnabled()) {
            return [];
        }

        /** @var WebsiteRepository $websiteRepository */
        $websiteRepository = $this->entityManager->getRepository(Website::class);
        $websites = $websiteRepository->findAll();
        /** @var Website $website */
        foreach ($websites as $website) {
            foreach ($this->websiteLocalizationProvider->getLocalizations($website) as $localization) {
                yield $this->buildLocalizedCatalog($website, $localization);
            }
        }
    }

    public function getCatalogCodeFromWebsiteId(int $websiteId): string
    {
        return 'website_' . $websiteId;
    }

    public function buildLocalizedCatalog(Website $website, Localization $localization): LocalizedCatalog
    {
        if (!\array_key_exists($website->getId(), $this->catalogCache)) {
            $this->catalogCache[$website->getId()] = new Catalog(
                $this->getCatalogCodeFromWebsiteId($website->getId()),
                $website->getName(),
            );
        }

        return new LocalizedCatalog(
            $this->catalogCache[$website->getId()],
            'website_' . $website->getId() . '_' . $localization->getFormattingCode(),
            $localization->getName(),
            LocalizationConvertor::getLocaleFormattingCode($localization),
            $this->currencyProvider->getWebsiteDefaultCurrency($website->getId())
        );
    }
}
