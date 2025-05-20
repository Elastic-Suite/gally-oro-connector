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
use Gally\OroPlugin\Config\ConfigManager;
use Gally\OroPlugin\Indexer\Indexer;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\ProductBundle\EventListener\WebsiteSearchProductIndexerListenerInterface;
use Oro\Bundle\WebCatalogBundle\Entity\ContentNode;
use Oro\Bundle\WebCatalogBundle\Provider\WebCatalogProvider;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Event\IndexEntityEvent;
use Oro\Bundle\WebsiteSearchBundle\Manager\WebsiteContextManager;

/**
 * Add web catalog node related data to search index.
 */
class WebsiteSearchWebCatalogIndexerListener implements WebsiteSearchProductIndexerListenerInterface
{
    use ContextTrait;

    public function __construct(
        private WebsiteContextManager $websiteContextManager,
        private ManagerRegistry $doctrine,
        private ConfigManager $configManager,
        private WebCatalogProvider $webCatalogProvider,
        protected LocalizationHelper $localizationHelper,
    ) {
    }

    public function onWebsiteSearchIndex(IndexEntityEvent $event): void
    {
        if (!$this->configManager->isGallyEnabled()) {
            return;
        }

        if (!$this->hasContextFieldGroup($event->getContext(), 'main')) {
            return;
        }

        /** @var Localization $localization */
        $localization = $event->getContext()[Indexer::CONTEXT_LOCALIZATION];
        $website = $this->getWebsite($event);
        if (!$website) {
            $event->stopPropagation();

            return;
        }

        $root = $this->webCatalogProvider->getNavigationRootWithCatalogRootFallback($website);

        /** @var ContentNode[] $nodes */
        $nodes = $event->getEntities();

        foreach ($nodes as $node) {
            if (str_starts_with($node->getMaterializedPath(), $root->getMaterializedPath())) {
                $isRoot = $root->getId() == $node->getId();
                $nodeId = (string) $node->getId();
                $parentId = $isRoot ? null : (string) $node->getParentNode()->getId();
                $level = $isRoot ? 1 : ($node->getLevel() - $root->getLevel() + 1);
                $path = str_replace(
                    '_',
                    '/',
                    str_replace($root->getMaterializedPath(), (string) $root->getId(), $node->getMaterializedPath())
                );
                $name = $this->localizationHelper->getLocalizedValue($node->getTitles(), $localization);
                $url = $this->localizationHelper->getLocalizedValue($node->getLocalizedUrls(), $localization);

                if ($parentId) {
                    $event->addField($nodeId, 'parentId', $parentId);
                }

                $event->addField($nodeId, 'level', $level);
                $event->addField($nodeId, 'path', $path);
                $event->addField($nodeId, 'name', $name?->getString());     // @phpstan-ignore nullsafe.neverNull
                $event->addField($nodeId, 'url', $url?->getText());         // @phpstan-ignore nullsafe.neverNull
                $event->addField($nodeId, 'tree', $this->getTree($root, $node, $localization));
            }
        }
    }

    private function getWebsite(IndexEntityEvent $event): ?Website
    {
        $websiteId = $this->websiteContextManager->getWebsiteId($event->getContext());
        if ($websiteId) {
            return $this->doctrine->getManagerForClass(Website::class)->find(Website::class, $websiteId);
        }

        return null;
    }

    private function getTree(ContentNode $root, ContentNode $node, Localization $localization): array
    {
        return $root->getId() == $node->getId()
            ? []
            : array_merge(
                $this->getTree($root, $node->getParentNode(), $localization),
                [$this->localizationHelper->getLocalizedValue($node->getTitles(), $localization)?->getString()], // @phpstan-ignore nullsafe.neverNull
            );
    }
}
