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
use Oro\Bundle\CatalogBundle\DependencyInjection\Configuration as CatalogConfiguration;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ProductBundle\Event\ProcessAutocompleteDataEvent;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Factory\QueryFactoryInterface;
use Oro\Bundle\UIBundle\Twig\HtmlTagExtension;

/**
 * Adds category aggregation to product autocomplete.
 */
class Category
{
    public function __construct(
        private QueryFactoryInterface $queryFactory,
        private HtmlTagExtension $htmlTagExtension,
        private ConfigManager $configManager,
        private GallyConfigManager $gallyConfigManager,
    ) {
    }

    public function onProcessAutocompleteData(ProcessAutocompleteDataEvent $event): void
    {
        if (!$this->gallyConfigManager->isGallyEnabled()) {
            return;
        }

        $numberOfCategories = $this->configManager
            ->get(CatalogConfiguration::getConfigKeyByName(CatalogConfiguration::SEARCH_AUTOCOMPLETE_MAX_CATEGORIES));

        $query = $this->queryFactory->create()
            ->addSelect('id')
            ->addSelect('tree')
            ->addSelect('url')
            ->setFrom('oro_website_search_category_WEBSITE_ID')
            ->addWhere(Criteria::expr()->eq('all_text', $event->getQueryString()))
            ->setMaxResults($numberOfCategories);

        $categoryData = [];
        foreach ($query->getResult()->getElements() as $item) {
            $tree = $item->getSelectedData()['tree'];
            $tree = \is_array($tree) ? $tree : [$tree];

            foreach ($tree as $treeKey => $treeTitle) {
                $tree[$treeKey] = $this->htmlTagExtension->htmlSanitize($treeTitle);
            }

            $categoryData[] = [
                'id' => $item->getSelectedData()['id'],
                'url' => $item->getSelectedData()['url'],
                'tree' => array_values($tree),
            ];
        }

        $data = $event->getData();
        $data['categories'] = $categoryData;
        $event->setData($data);
    }
}
