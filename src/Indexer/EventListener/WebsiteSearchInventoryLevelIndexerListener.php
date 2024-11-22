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

namespace Gally\OroPlugin\Indexer\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Gally\OroPlugin\Search\SearchEngine;
use Oro\Bundle\InventoryBundle\Entity\InventoryLevel;
use Oro\Bundle\ProductBundle\EventListener\WebsiteSearchProductIndexerListenerInterface;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;
use Oro\Bundle\WarehouseBundle\Provider\EnabledWarehousesProvider;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Event\IndexEntityEvent;

/**
 * Add stock quantity to product data.
 */
class WebsiteSearchInventoryLevelIndexerListener implements WebsiteSearchProductIndexerListenerInterface
{
    use ContextTrait;

    public function __construct(
        private ManagerRegistry $doctrine,
        private EngineParameters $engineParameters,
        private EnabledWarehousesProvider $enabledWarehousesProvider,
    ) {
    }

    public function onWebsiteSearchIndex(IndexEntityEvent $event): void
    {
        if (SearchEngine::ENGINE_NAME !== $this->engineParameters->getEngineName()) {
            return;
        }

        if (!$this->hasContextFieldGroup($event->getContext(), 'inventory')) {
            return;
        }

        $stockLevels = [];
        $inventoryLevelRepository = $this->doctrine->getRepository(InventoryLevel::class);
        $inventoryLevels = $inventoryLevelRepository->findBy([
            'product' => $event->getEntities(),
            'warehouse' => $this->enabledWarehousesProvider->getEnabledWarehouseIds(),
        ]);

        /** @var InventoryLevel $inventoryLevel */
        foreach ($inventoryLevels as $inventoryLevel) {
            $product = $inventoryLevel->getProduct();
            if ($inventoryLevel->getProductUnitPrecision() !== $inventoryLevel->getProduct()->getPrimaryUnitPrecision()) {
                continue;
            }

            if (!\array_key_exists($product->getId(), $stockLevels)) {
                $stockLevels[$product->getId()] = 0;
            }

            $stockLevels[$product->getId()] += $inventoryLevel->getQuantity();
        }

        foreach ($stockLevels as $entityId => $stockLevel) {
            $event->addField($entityId, 'inv_qty', $stockLevel);
        }
    }
}
