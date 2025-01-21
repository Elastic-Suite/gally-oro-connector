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

use Gally\OroPlugin\Indexer\Normalizer\AbstractNormalizer;
use Gally\OroPlugin\Service\ContextProvider;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\UIBundle\Tools\HtmlTagHelper;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexDataProvider as BaseIndexDataProvider;
use Oro\Bundle\WebsiteSearchBundle\Event;
use Oro\Bundle\WebsiteSearchBundle\Helper\PlaceholderHelper;
use Oro\Bundle\WebsiteSearchBundle\Manager\WebsiteContextManager;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\LocalizationIdPlaceholder;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderInterface;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderValue;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class is responsible for triggering all events during indexation
 * and returning all collected and prepared for saving event data.
 */
class IndexDataProvider extends BaseIndexDataProvider
{
    use ContextTrait;

    /**
     * @param iterable<AbstractNormalizer> $normalizers
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private EntityAliasResolver $entityAliasResolver,
        private PlaceholderInterface $placeholder,
        HtmlTagHelper $htmlTagHelper,
        PlaceholderHelper $placeholderHelper,
        private WebsiteContextManager $websiteContextManager,
        private ContextProvider $contextProvider,
        private array $attributeMapping,
        private iterable $normalizers,
    ) {
        parent::__construct($eventDispatcher, $entityAliasResolver, $placeholder, $htmlTagHelper, $placeholderHelper);
    }

    /**
     * {@inheritDoc}
     */
    public function getEntitiesData(
        $entityClass,
        array $restrictedEntities,
        array $context,
        array $entityConfig
    ): array {
        $entityAlias = $this->entityAliasResolver->getAlias($entityClass);
        $this->contextProvider->setIsGallyContext(true);

        $indexEntityEvent = new Event\IndexEntityEvent($entityClass, $restrictedEntities, $context);
        $this->eventDispatcher->dispatch($indexEntityEvent, Event\IndexEntityEvent::NAME);
        $this->eventDispatcher->dispatch(
            $indexEntityEvent,
            sprintf('%s.%s', Event\IndexEntityEvent::NAME, $entityAlias)
        );

        $data = $this->prepareIndexData($entityClass, $indexEntityEvent->getEntitiesData(), $entityConfig, $context);
        $this->contextProvider->setIsGallyContext(false);

        return $data;
    }

    /**
     * Adds field types according to entity config, applies placeholders.
     *
     * @return array Structured and cleared data ready to be saved
     */
    private function prepareIndexData(
        string $entityClass,
        array $indexData,
        array $entityConfig,
        array $context
    ): array {
        $preparedIndexData = [];

        /** @var Localization $localization */
        $localization = $context[Indexer::CONTEXT_LOCALIZATION];
        $website = $this->websiteContextManager->getWebsite($context);

        foreach ($this->normalizers as $normalizer) {
            $normalizer->preProcess($website, $localization, $entityClass, $entityConfig, $indexData);
        }

        foreach ($indexData as $entityId => $fieldsValues) {
            $preparedIndexData[$entityId] = $preparedIndexData[$entityId] ?? [];

            foreach ($this->normalizers as $normalizer) {
                $normalizer->normalize(
                    $website,
                    $entityClass,
                    $entityId,
                    $fieldsValues,
                    $preparedIndexData[$entityId],
                );
            }

            /**
             * @var string $fieldName
             * @var array $values
             */
            foreach ($this->toArray($fieldsValues) as $fieldName => $values) {
                $singleValueFieldName = $this->cleanFieldName((string) $fieldName);
                foreach ($this->toArray($values) as $value) {
                    $value = $value['value'];
                    $placeholders = [];

                    if ($value instanceof PlaceholderValue) {
                        $placeholders = $value->getPlaceholders();
                        $value = $value->getValue();
                    }

                    if (\array_key_exists(LocalizationIdPlaceholder::NAME, $placeholders)) {
                        if ($localization->getId() != $placeholders[LocalizationIdPlaceholder::NAME]) {
                            continue;
                        }
                    }

                    if (str_starts_with($fieldName, 'ordered_at_by')) {
                        // Todo
                    } elseif (!str_starts_with($fieldName, self::ALL_TEXT_PREFIX)) {
                        if (null === $value || '' === $value || [] === $value) {
                            continue;
                        }
                        $singleValueFieldName = $this->placeholder->replace($singleValueFieldName, $placeholders);
                        $preparedIndexData[$entityId][$singleValueFieldName] = $value;
                    }
                }
            }

            $preparedIndexData[$entityId] = $preparedIndexData[$entityId] ?? [];

            $preparedIndexData[$entityId]['id'] = (string) $entityId;
            if (\array_key_exists('image_product_medium', $preparedIndexData[$entityId])) {
                $preparedIndexData[$entityId]['image'] = $preparedIndexData[$entityId]['image_product_medium'];
            }
        }

        foreach ($this->normalizers as $normalizer) {
            $normalizer->postProcess($website, $entityClass, $preparedIndexData);
        }

        return $preparedIndexData;
    }

    private function cleanFieldName(string $fieldName): string
    {
        $fieldName = trim(
            $this->placeholder->replace($fieldName, [LocalizationIdPlaceholder::NAME => null]),
            '_.'
        );

        return $this->attributeMapping[$fieldName] ?? $fieldName;
    }

    private function toArray($value): array
    {
        if (\is_array($value) && !\array_key_exists('value', $value)) {
            return $value;
        }

        return [$value];
    }
}
