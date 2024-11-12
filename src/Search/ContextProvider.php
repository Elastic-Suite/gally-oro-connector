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

namespace Gally\OroPlugin\Search;

use Gally\OroPlugin\Indexer\Provider\CatalogProvider;
use Gally\Sdk\Entity\LocalizedCatalog;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\WebCatalogBundle\Entity\ContentNode;
use Oro\Bundle\WebCatalogBundle\Provider\RequestWebContentVariantProvider;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;

class ContextProvider
{
    public function __construct(
        private WebsiteManager $websiteManager,
        private LocalizationHelper $localizationHelper,
        private CatalogProvider $catalogProvider,
        private RequestWebContentVariantProvider $requestWebContentVariantProvider,
    ) {
    }

    public function getCurrentWebsite(): Website
    {
        return $this->websiteManager->getCurrentWebsite();
    }

    public function getCurrentLocalization(): Localization
    {
        return $this->localizationHelper->getCurrentLocalization();
    }

    public function getCurrentLocalizedCatalog(): LocalizedCatalog
    {
        return $this->catalogProvider->buildLocalizedCatalog(
            $this->getCurrentWebsite(),
            $this->getCurrentLocalization(),
        );
    }

    public function getCurrentContentNode(): ?ContentNode
    {
        return $this->requestWebContentVariantProvider->getContentVariant()?->getNode();
    }
}