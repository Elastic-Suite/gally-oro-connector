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

namespace Gally\OroPlugin\Indexer\Listener;

use Gally\OroPlugin\Config\ConfigManager;
use Gally\OroPlugin\Convertor\LocalizationConvertor;
use Gally\OroPlugin\Indexer\Event\BeforeSaveIndexDataEvent;
use Gally\OroPlugin\Indexer\Indexer;
use Gally\OroPlugin\Indexer\Provider\CatalogProvider;
use Gally\OroPlugin\Indexer\Provider\SourceFieldProvider;
use Gally\OroPlugin\Indexer\Registry\IndexRegistry;
use Gally\Sdk\Entity\Index;
use Gally\Sdk\Entity\LocalizedCatalog;
use Gally\Sdk\Service\IndexOperation;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\SearchBundle\Engine\IndexerInterface;
use Oro\Bundle\SearchBundle\Provider\SearchMappingProvider;
use Oro\Bundle\WebsiteBundle\Provider\AbstractWebsiteLocalizationProvider;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractIndexer;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexDataProvider;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexerInputValidator;
use Oro\Bundle\WebsiteSearchBundle\Entity\Repository\EntityIdentifierRepository;
use Oro\Bundle\WebsiteSearchBundle\Event\AfterReindexEvent;
use Oro\Bundle\WebsiteSearchBundle\Event\BeforeReindexEvent;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderInterface;
use Oro\Bundle\WebsiteSearchBundle\Resolver\EntityDependenciesResolverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class IndexerEventListener
{
    public function __construct(
        private ConfigManager $configManager,
        private Indexer $indexer,
    ) {
    }

    public function beforeReindex(BeforeReindexEvent $event): void
    {
        if ($this->configManager->isGallyEnabled()) {
            $this->indexer->beforeReindex($event);
        }
    }

    public function afterReindex(AfterReindexEvent $event): void
    {
        if ($this->configManager->isGallyEnabled()) {
            $this->indexer->afterReindex($event);
        }
    }
}
