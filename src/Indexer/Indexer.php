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

namespace Gally\OroPlugin\Indexer;

use Gally\OroPlugin\Config\ConfigManager;
use Gally\OroPlugin\Convector\LocalizationConvector;
use Gally\OroPlugin\Entity\IndexBatchCount;
use Gally\OroPlugin\Indexer\Event\BeforeSaveIndexDataEvent;
use Gally\OroPlugin\Indexer\Provider\CatalogProvider;
use Gally\OroPlugin\Indexer\Provider\SourceFieldProvider;
use Gally\OroPlugin\Indexer\Registry\IndexRegistry;
use Gally\Sdk\Entity\Index;
use Gally\Sdk\Entity\LocalizedCatalog;
use Gally\Sdk\Service\IndexOperation;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\SearchBundle\Provider\SearchMappingProvider;
use Oro\Bundle\WebsiteBundle\Provider\AbstractWebsiteLocalizationProvider;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractIndexer;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexDataProvider;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexerInputValidator;
use Oro\Bundle\WebsiteSearchBundle\Event\AfterReindexEvent;
use Oro\Bundle\WebsiteSearchBundle\Event\BeforeReindexEvent;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderInterface;
use Oro\Bundle\WebsiteSearchBundle\Resolver\EntityDependenciesResolverInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Indexer extends AbstractIndexer
{
    use ContextTrait;

    public const CONTEXT_LOCALIZATION = 'localization';

    /** @var LocalizedCatalog[][] */
    private array $localizedCatalogByWebsite;

    /** @var Index[]|string[] */
    private array $indicesByLocale;

    private AbstractIndexer $fallBackIndexer;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        SearchMappingProvider $mappingProvider,
        EntityDependenciesResolverInterface $entityDependenciesResolver,
        IndexDataProvider $indexDataProvider,
        PlaceholderInterface $placeholder,
        IndexerInputValidator $indexerInputValidator,
        EventDispatcherInterface $eventDispatcher,
        PlaceholderInterface $regexPlaceholder,
        private AbstractWebsiteLocalizationProvider $websiteLocalizationProvider,
        private CatalogProvider $catalogProvider,
        private SourceFieldProvider $sourceFieldProvider,
        private IndexOperation $indexOperation,
        private ConfigManager $configManager,
        private IndexRegistry $indexRegistry,
    ) {
        parent::__construct(
            $doctrineHelper,
            $mappingProvider,
            $entityDependenciesResolver,
            $indexDataProvider,
            $placeholder,
            $indexerInputValidator,
            $eventDispatcher,
            $regexPlaceholder
        );
    }

    public function setFallBackIndexer(AbstractIndexer $fallBackIndexer): void
    {
        $this->fallBackIndexer = $fallBackIndexer;
    }

    /**
     * {@inheritdoc}
     */
    public function reindex($classOrClasses = null, array $context = [])
    {
        [$entityClassesToIndex, $websiteIdsToIndex] = $this->inputValidator->validateRequestParameters(
            $classOrClasses,
            $context
        );

        $entityClassesToIndex = $this->getClassesForReindex($entityClassesToIndex);
        if (empty($context['skip_pre_processing'])) {
            $this->eventDispatcher->dispatch(
                new BeforeReindexEvent($classOrClasses, $context),
                BeforeReindexEvent::EVENT_NAME
            );
            $context['indices_by_locale'] = $this->indexRegistry->getIndicesByLocale();
        }

        $handledItems = 0;

        foreach ($websiteIdsToIndex as $websiteId) {
            if (!$this->ensureWebsiteExists($websiteId)) {
                continue;
            }

            // Find the good indexer for the current website.
            $indexer = $this->configManager->isGallyEnabled($websiteId) ? $this : $this->fallBackIndexer;
            $websiteContext = $this->indexDataProvider->collectContextForWebsite($websiteId, $context);
            foreach ($entityClassesToIndex as $entityClass) {
                $handledItems += $indexer->reindexEntityClass($entityClass, $websiteContext);
            }
            // Check again to ensure Website was not deleted during reindexation otherwise drop index
            if (!$this->ensureWebsiteExists($websiteId)) { // @phpstan-ignore booleanNot.alwaysFalse
                $handledItems = 0;
            }
        }

        return $handledItems;
    }

    public function beforeReindex(BeforeReindexEvent $event): void
    {
        [$entityClassesToIndex, $websiteIdsToIndex] = $this->inputValidator->validateRequestParameters(
            $event->getClassOrClasses(),
            $event->getContext()
        );
        $entityClassesToIndex = $this->getClassesForReindex($entityClassesToIndex);
        $context = $event->getContext();
        $indicesByLocale = [];
        $isFullContext = empty($this->getContextEntityIds($context));

        foreach ($websiteIdsToIndex as $websiteId) {
            if ($this->configManager->isGallyEnabled($websiteId)) {
                foreach ($entityClassesToIndex as $entityClass) {
                    $indicesByLocale[$websiteId][$entityClass] = $this->getIndexByLocal(
                        $websiteId,
                        $entityClass,
                        $isFullContext
                    );
                }
            }
        }

        $this->indexRegistry->setIndicesByLocale($indicesByLocale);
    }

    public function afterReindex(AfterReindexEvent $event): void
    {
        $context = $event->getWebsiteContext();
        $contextEntityIds = $this->getContextEntityIds($context);
        $entityClass = $event->getEntityClass();
        $websiteId = $context[self::CONTEXT_CURRENT_WEBSITE_ID_KEY];

        // Sync full reindexation
        if (empty($contextEntityIds)) {
            foreach ($this->indicesByLocale[$websiteId][$entityClass] as $index) {
                $this->installIndex($index);
            }
        }

        // Async full reindexation, we need to check if all message has been received.
        if ($context['is_full_indexation'] ?? false) {
            $batchCountManager = $this->doctrineHelper->getEntityManager(IndexBatchCount::class);
            foreach ($this->indicesByLocale[$websiteId][$entityClass] as $index) {
                $index = \is_string($index) ? $index : $index->getName();
                /** @var IndexBatchCount $messageCount */
                $messageCount = $batchCountManager->find(IndexBatchCount::class, $index) ?: new IndexBatchCount($index);

                if ($messageCount->getMessageCount() < $context['message_count']) {
                    $messageCount->increment();
                    $batchCountManager->persist($messageCount);
                } else {
                    $this->installIndex($index);
                    $batchCountManager->remove($messageCount);
                }
                $batchCountManager->flush();
            }
        }
    }

    protected function reindexEntityClass($entityClass, array $context)
    {
        if ('Oro\Bundle\WebsiteElasticSearchBundle\Entity\SavedSearch' === $entityClass) {
            // Todo managed savedSearch https://doc.oroinc.com/user/storefront/account/saved-search/
            return 0;
        }

        $websiteId = $context[self::CONTEXT_CURRENT_WEBSITE_ID_KEY];
        $this->indicesByLocale = ($context['indices_by_locale'] ?? [])
            ?: [$websiteId => [$entityClass => $this->getIndexByLocal($websiteId, $entityClass)]];

        return parent::reindexEntityClass($entityClass, $context);
    }

    protected function indexEntities($entityClass, array $entityIds, array $context, $aliasToSave): array
    {
        $result = [];

        $restrictedEntities = $this->getRestrictedEntities($entityIds, $context, $entityClass);

        if (!$restrictedEntities) {
            return [];
        }

        $websiteId = $context[self::CONTEXT_CURRENT_WEBSITE_ID_KEY];
        $localizations = $this->websiteLocalizationProvider->getLocalizationsByWebsiteId($websiteId);

        foreach ($localizations as $localization) {
            $context[self::CONTEXT_LOCALIZATION] = $localization;
            $entityConfig = $this->mappingProvider->getEntityConfig($entityClass);

            // Unset context field group because Gally is not able to manage partial documents.
            unset($context[AbstractIndexer::CONTEXT_FIELD_GROUPS]);
            $entitiesData = $this->indexDataProvider->getEntitiesData(
                $entityClass,
                $restrictedEntities,
                $context,
                $entityConfig
            );

            $result = $this->saveIndexData($entityClass, $entitiesData, $aliasToSave, $context);
        }

        return $result;
    }

    public function delete($entity, array $context = []): bool
    {
        // Index deletion is managed by gally on install.
        return true;
    }

    public function resetIndex($class = null, array $context = [])
    {
    }

    protected function saveIndexData($entityClass, array $entitiesData, $entityAliasTemp, array $context)
    {
        $realAlias = $this->getEntityAlias($entityClass, $context);

        if (null === $realAlias || empty($entitiesData)) {
            return [];
        }

        $event = new BeforeSaveIndexDataEvent($entityClass, $entitiesData);
        $this->eventDispatcher->dispatch($event, BeforeSaveIndexDataEvent::NAME);
        $bulk = array_map(fn ($data) => json_encode($data), $entitiesData);
        $websiteId = $context[self::CONTEXT_CURRENT_WEBSITE_ID_KEY];
        /** @var Localization $localization */
        $localization = $context[self::CONTEXT_LOCALIZATION];
        $locale = LocalizationConvector::getLocaleFormattingCode($localization);
        $index = $this->indicesByLocale[$websiteId][$entityClass][$locale] ?? null;

        if (!$index) {
            throw new \LogicException(sprintf('Missing index for website %d, class %s and locale %s.', $websiteId, $entityClass, $locale));
        }
        $this->indexOperation->executeBulk($index, $bulk);

        return array_keys($entitiesData);
    }

    protected function savePartialIndexData($entityClass, array $entitiesData, $entityAliasTemp, array $context): array
    {
        // Partial indexation not manage with Gally @see Indexer/Indexer.php:221
        return [];
    }

    protected function getIndexedEntities($entityClass, array $entities, array $context): array
    {
        // Partial indexation not manage with Gally @see Indexer/Indexer.php:221
        return [];
    }

    protected function renameIndex($temporaryAlias, $currentAlias): void
    {
    }

    protected function getIndexByLocal(int $websiteId, string $entityClass, bool $isFullContext = false)
    {
        $indicesByLocale = [];
        $metadata = $this->sourceFieldProvider->getMetadataFromEntityClass($entityClass);
        foreach ($this->getLocalizedCatalogByWebsite($websiteId) as $localizedCatalog) {
            if ($isFullContext) {
                $index = $this->indexOperation->createIndex($metadata, $localizedCatalog);
            } else {
                $index = $this->indexOperation->getIndexByName($metadata, $localizedCatalog);
            }

            $indicesByLocale[$localizedCatalog->getLocale()] = $index->getName();
        }

        return $indicesByLocale;
    }

    protected function installIndex(Index|string $index): void
    {
        $this->indexOperation->refreshIndex($index);
        $this->indexOperation->installIndex($index);
    }

    /**
     * @return LocalizedCatalog[]
     */
    private function getLocalizedCatalogByWebsite(int $websiteId): array
    {
        if (!isset($this->localizedCatalogByWebsite)) {
            foreach ($this->catalogProvider->provide() as $localizedCatalog) {
                $catalogCode = $localizedCatalog->getCatalog()->getCode();
                if (!isset($this->localizedCatalogByWebsite[$catalogCode])) {
                    $this->localizedCatalogByWebsite[$catalogCode] = [];
                }
                $this->localizedCatalogByWebsite[$catalogCode][] = $localizedCatalog;
            }
        }

        $catalogCode = $this->catalogProvider->getCatalogCodeFromWebsiteId($websiteId);

        return $this->localizedCatalogByWebsite[$catalogCode];
    }
}
