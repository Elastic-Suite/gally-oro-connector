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

use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class BrandDataNormalizer extends AbstractNormalizer
{
    public function preProcess(
        Website $website,
        Localization $localization,
        string $entityClass,
        array $entityConfig,
        array &$indexData,
    ): void {
        if (Product::class === $entityClass) {
            foreach ($indexData as &$fieldsValues) {
                if (isset($fieldsValues['brand_LOCALIZATION_ID'])) {
                    $fieldsValues['brand_name'] = $fieldsValues['brand_LOCALIZATION_ID'];
                    unset($fieldsValues['brand_LOCALIZATION_ID']);
                }
            }
        }
    }

    public function postProcess(
        Website $website,
        string $entityClass,
        array &$preparedIndexData,
    ): void {
        if (Product::class === $entityClass) {
            foreach ($preparedIndexData as $entityId => &$data) {
                if (isset($data['brand'])) {
                    $preparedIndexData[$entityId]['brand'] = [[
                        'value' => $data['brand'],
                        'label' => $data['brand_name'] ?? '',  // Todo fix
                    ]];
                }
                unset($preparedIndexData[$entityId]['brand_name']);
            }
        }
    }
}
