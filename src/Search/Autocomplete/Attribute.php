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

namespace Gally\OroPlugin\Search\Autocomplete;

use Gally\OroPlugin\Config\ConfigManager as GallyConfigManager;
use Gally\OroPlugin\Service\ContextProvider;
use Oro\Bundle\ProductBundle\Event\ProcessAutocompleteDataEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds category aggregation to product autocomplete.
 */
class Attribute
{
    public function __construct(
        private ContextProvider $contextProvider,
        private GallyConfigManager $gallyConfigManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onProcessAutocompleteData(ProcessAutocompleteDataEvent $event): void
    {
        $attributeData = [];

        if ($this->gallyConfigManager->isGallyEnabled()) {
            $searchQuery = $this->contextProvider->getRequest()->getSearchQuery();
            foreach ($this->contextProvider->getResponse()->getAggregations() as $aggregationData) {
                foreach ($aggregationData['options'] as $option) {
                    $params = [
                        'f' => [$aggregationData['field'] => ['value' => [$option['value']]]],
                        'g' => ['search' => $searchQuery],
                    ];
                    $url = $this->urlGenerator->generate(
                        'oro_product_frontend_product_search',
                        [
                            'search' => $searchQuery,
                            'grid' => ['frontend-product-search-grid' => http_build_query($params)],
                        ]
                    );
                    $attributeData[] = [
                        'field' => $aggregationData['label'],
                        'label' => $option['label'],
                        'url' => $url,
                    ];
                }
            }
        }

        $data = $event->getData();
        $data['attributes'] = $attributeData;
        $event->setData($data);
    }
}
