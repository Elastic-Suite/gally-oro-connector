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

use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class StockDataNormalizer extends AbstractNormalizer
{
    public function normalize(
        Website $website,
        string $entityClass,
        string|int $entityId,
        array &$fieldsValues,
        array &$preparedEntityData,
    ): void {
        if (Product::class == $entityClass) {
            $status = (!empty($fieldsValues['inv_status']))
                ? reset($fieldsValues['inv_status'])['value']
                : (
                    !empty($fieldsValues['inventory_status'])
                        ? reset($fieldsValues['inventory_status'])['value']
                        : Product::INVENTORY_STATUS_OUT_OF_STOCK
                );
            $qty = (!empty($fieldsValues['inv_qty']))
                ? reset($fieldsValues['inv_qty'])['value']
                : (
                    !empty($fieldsValues['inventory_qty'])
                        ? reset($fieldsValues['inventory_qty'])['value']
                        : 0
                );

            $preparedEntityData['stock'] = ['status' => Product::INVENTORY_STATUS_IN_STOCK == $status, 'qty' => $qty];
            unset($fieldsValues['inv_status']);
            unset($fieldsValues['inventory_status']);
            unset($fieldsValues['inv_qty']);
            unset($fieldsValues['inventory_qty']);
        }
    }
}
