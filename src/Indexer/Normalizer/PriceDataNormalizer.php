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

namespace Gally\OroPlugin\Indexer\Normalizer;

use Gally\OroPlugin\Resolver\PriceGroupResolver;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\PricingBundle\Entity\CombinedPriceList;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\Repository\CombinedPriceListRepository;
use Oro\Bundle\PricingBundle\Placeholder\CPLIdPlaceholder;
use Oro\Bundle\PricingBundle\Placeholder\CurrencyPlaceholder;
use Oro\Bundle\PricingBundle\Placeholder\PriceListIdPlaceholder;
use Oro\Bundle\PricingBundle\Placeholder\UnitPlaceholder;
use Oro\Bundle\PricingBundle\Provider\WebsiteCurrencyProvider;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderValue;

class PriceDataNormalizer extends AbstractNormalizer
{
    private string $defaultCurrency;
    private PriceList|CombinedPriceList|null $defaultPriceList;

    public function __construct(
        private DoctrineHelper $doctrineHelper,
        private ConfigManager $configManager,
        private FeatureChecker $featureChecker,
        private WebsiteCurrencyProvider $currencyProvider,
        private PriceGroupResolver $priceGroupResolver,
    ) {
    }

    public function preProcess(
        Website $website,
        Localization $localization,
        string $entityClass,
        array $entityConfig,
        array &$indexData,
    ): void {
        if (Product::class === $entityClass) {
            $this->defaultCurrency = $this->currencyProvider->getWebsiteDefaultCurrency($website->getId());
            $this->defaultPriceList = $this->getDefaultPriceListForWebsite($website);
        }
    }

    public function normalize(
        Website $website,
        string $entityClass,
        string|int $entityId,
        array &$fieldsValues,
        array &$preparedEntityData,
    ): void {
        if (Product::class === $entityClass) {
            $prices = [];
            $isCombinedPriceListEnable = $this->featureChecker->isFeatureEnabled('oro_price_lists_combined');
            $priceListPlaceHolder = $isCombinedPriceListEnable ? CPLIdPlaceholder::NAME : PriceListIdPlaceholder::NAME;
            $minimalPrices = array_merge(
                $fieldsValues["minimal_price.{$priceListPlaceHolder}_CURRENCY"] ?? [],
                $fieldsValues["minimal_price.{$priceListPlaceHolder}_CURRENCY_UNIT"] ?? [],
            );
            foreach ($this->toArray($minimalPrices) as $value) {
                $value = $value['value'];
                $placeholders = [];

                if ($value instanceof PlaceholderValue) {
                    $placeholders = $value->getPlaceholders();
                    $value = $value->getValue();
                }

                $priceListId = $placeholders[CPLIdPlaceholder::NAME] ?? $placeholders[PriceListIdPlaceholder::NAME];
                $prices[] = [
                    'price' => (float) $value,
                    'group_id' => $this->priceGroupResolver->getGroupId(
                        isset($placeholders[CPLIdPlaceholder::NAME]),
                        $priceListId,
                        $placeholders[CurrencyPlaceholder::NAME],
                        $placeholders[UnitPlaceholder::NAME] ?? null
                    ),
                ];

                if ($this->defaultPriceList->getId() === $priceListId
                    && $this->defaultCurrency === $placeholders[CurrencyPlaceholder::NAME]
                    && !isset($placeholders[UnitPlaceholder::NAME])
                ) {
                    array_unshift($prices, ['price' => (float) $value, 'group_id' => 0]);
                }
            }

            if (!empty($prices)) {
                $preparedEntityData['price'] = $prices;
            }
            unset($fieldsValues["minimal_price.{$priceListPlaceHolder}_CURRENCY"]);
            unset($fieldsValues["minimal_price.{$priceListPlaceHolder}_CURRENCY_UNIT"]);
        }
    }

    private function getDefaultPriceListForWebsite(Website $website): PriceList|CombinedPriceList|null
    {
        $isCombinedPriceListEnable = $this->featureChecker->isFeatureEnabled('oro_price_lists_combined');

        if ($isCombinedPriceListEnable) {
            /** @var CombinedPriceListRepository $combinedPriceListRepository */
            $combinedPriceListRepository = $this->doctrineHelper->getEntityRepositoryForClass(CombinedPriceList::class);

            return $combinedPriceListRepository->getPriceListByWebsite($website, true);
        }

        $priceListId = $this->configManager->get(
            'oro_pricing.default_price_list',
            false,
            false,
            $website->getId()
        );

        if ($priceListId) {
            return $this->doctrineHelper->getEntityRepositoryForClass(PriceList::class)->find($priceListId);
        }

        return null;
    }
}
