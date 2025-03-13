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

namespace Gally\OroPlugin\Service;

use Gally\OroPlugin\Indexer\Provider\CatalogProvider;
use Gally\Sdk\Entity\LocalizedCatalog;
use Gally\Sdk\GraphQl\Request;
use Gally\Sdk\GraphQl\Response;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;

class ContextProvider
{
    private bool $isGallyContext = false;
    private ?string $currentContentNodeId = null;
    private Request $request;
    private Response $response;
    private ?string $priceFilterUnit = null;
    private bool $isAutocompleteContext = false;

    public function __construct(
        private ConfigManager $configManager,
        private WebsiteManager $websiteManager,
        private LocalizationHelper $localizationHelper,
        private CatalogProvider $catalogProvider,
    ) {
    }

    public function isGallyContext(): bool
    {
        return $this->isGallyContext;
    }

    public function setIsGallyContext(bool $isGallyContext): void
    {
        $this->isGallyContext = $isGallyContext;
    }

    public function getCurrentWebsite(): ?Website
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

    public function setCurrentContentNodeId(string $contentNodeId): void
    {
        $this->currentContentNodeId = $contentNodeId;
    }

    public function getCurrentContentNodeId(): ?string
    {
        return $this->currentContentNodeId;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function getPriceFilterUnit(): ?string
    {
        return $this->priceFilterUnit;
    }

    public function setPriceFilterUnit(string $priceFilterUnit): void
    {
        $defaultUnit = $this->configManager->get('oro_product.default_unit');
        if ($defaultUnit !== $priceFilterUnit) {
            $this->priceFilterUnit = $priceFilterUnit;
        }
    }

    public function isAutocompleteContext(): bool
    {
        return $this->isAutocompleteContext;
    }

    public function setIsAutocompleteContext(bool $isAutocompleteContext): void
    {
        $this->isAutocompleteContext = $isAutocompleteContext;
    }
}
