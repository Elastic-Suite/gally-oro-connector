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

namespace Gally\OroPlugin\Indexer\Provider;

use Gally\Sdk\Entity\Label;
use Gally\Sdk\Entity\LocalizedCatalog;
use Gally\Sdk\Entity\Metadata;
use Gally\Sdk\Entity\SourceField;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityConfigBundle\Exception\RuntimeException;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\SearchBundle\Provider\SearchMappingProvider;
use Oro\Bundle\WebsiteElasticSearchBundle\Entity\SavedSearch;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\PlaceholderRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gally Catalog data provider.
 */
class SourceFieldProvider implements ProviderInterface
{
    /** @var LocalizedCatalog[] */
    private array $localizedCatalogs = [];

    /** @var string[] */
    // todo conf ??
    private array $fieldToSkip = [
        'visibility_customer.CUSTOMER_ID', // Field managed manually @see src/Resources/config/oro/website_search.yml
        'inv_status', // Field managed manually @see oro/src/packages/GallyPlugin/src/Engine/IndexDataProvider.php
        'inv_qty', // Field managed manually @see oro/src/packages/GallyPlugin/src/Engine/IndexDataProvider.php
        'brand_LOCALIZATION_ID', // Brand field is managed as a select
    ];

    private array $oroSystemAttribute = [ // todo conf
        'status',
        'assigned_to',
        'manually_added_to',
        'category_path',
        'category_paths',
        'is_variant',
        'is_visible_by_default',
        'visibility_anonymous',
        'visibility_new',
        'visible_for_customer',
        'hidden_for_customer',
    ];

    public function __construct(
        private SearchMappingProvider $mappingProvider,
        private EntityAliasResolver $entityAliasResolver,
        private ConfigProvider $configProvider,
        private CatalogProvider $catalogProvider,
        private PlaceholderRegistry $placeholderRegistry,
        private TranslatorInterface $translator,
        private LocaleSettings $localeSettings,
        private array $entityCodeMapping,
        private array $attributeMapping,
        private array $typeMapping,
    ) {
        foreach ($this->catalogProvider->provide() as $localizedCatalog) {
            $this->localizedCatalogs[] = $localizedCatalog;
        }
    }

    /**
     * @return iterable<SourceField>
     *
     * @see \Oro\Bundle\ProductBundle\EventListener\WebsiteSearchMappingListener:54
     */
    public function provide(): iterable
    {
        foreach ($this->mappingProvider->getEntityClasses() as $entityClass) {
            if (SavedSearch::class === $entityClass) {
                // Todo managed savedSearch https://doc.oroinc.com/user/storefront/account/saved-search/
                continue;
            }

            $metadata = $this->getMetadataFromEntityClass($entityClass);
            $entityConfig = $this->mappingProvider->getEntityConfig($entityClass);

            foreach ($entityConfig['fields'] as $fieldData) {
                if (\in_array($fieldData['name'], $this->fieldToSkip, true)) {
                    continue;
                }

                $fieldName = $this->cleanFieldName($fieldData['name']);

                try {
                    $fieldConfig = $this->configProvider->getConfig($entityClass, $fieldName);
                    $labelKey = $fieldConfig->get('label');
                } catch (RuntimeException) {
                    $fieldConfig = null;
                    $labelKey = $fieldName;
                }

                $fieldType = $this->getGallyType(
                    $fieldData['name'],
                    $fieldConfig ? $fieldConfig->getId()->getFieldType() : $fieldData['type']
                );
                $defaultLabel = $this->translator->trans($labelKey, [], null, $this->getDefaultLocale());

                if (!\array_key_exists($fieldData['type'], $this->typeMapping)) {
                    throw new \LogicException(sprintf('Type %s not managed for field %s of entity %s.', $fieldData['type'], $fieldName, $entityClass));
                }

                yield new SourceField(
                    $metadata,
                    $fieldName,
                    $fieldType,
                    $defaultLabel,
                    $this->getLabels($labelKey, $defaultLabel),
                    \in_array($fieldName, $this->oroSystemAttribute, true)
                );
            }
        }
    }

    public function getMetadataFromEntityClass(string $entityClass): Metadata
    {
        $entityCode = $this->entityAliasResolver->getAlias($entityClass);

        return new Metadata($this->entityCodeMapping[$entityCode] ?? $entityCode);
    }

    public function cleanFieldName(string $fieldName): string
    {
        foreach ($this->placeholderRegistry->getPlaceholders() as $placeholder) {
            $fieldName = $placeholder->replace($fieldName, [$placeholder->getPlaceholder() => null]);
        }

        $fieldName = trim($fieldName, '._-');

        if (str_ends_with($fieldName, '_enum')) {
            $fieldName = preg_replace('/_enum$/', '', $fieldName);
        }

        return $this->attributeMapping[$fieldName] ?? $fieldName;
    }

    private function getGallyType(string $fieldName, string $fieldType): string
    {
        return match (true) {
            'brand' === $fieldName => SourceField::TYPE_SELECT,
            str_ends_with($fieldName, '_enum') => SourceField::TYPE_SELECT,
            str_starts_with($fieldName, 'image_') => SourceField::TYPE_IMAGE,
            default => $this->typeMapping[$fieldType] ?? SourceField::TYPE_TEXT,
        };
    }

    private function getDefaultLocale(): string
    {
        return $this->localeSettings->getLocaleWithRegion();
    }

    /**
     * @return Label[]
     */
    private function getLabels(string $labelKey, string $defaultLabel): array
    {
        $defaultLocale = $this->getDefaultLocale();
        $labels = [];
        foreach ($this->localizedCatalogs as $localizedCatalog) {
            if ($localizedCatalog->getLocale() != $defaultLocale) {
                $label = $this->translator->trans($labelKey, [], null, $localizedCatalog->getLocale());
                if ($label !== $defaultLabel) {
                    $labels[] = new Label($localizedCatalog, $label);
                }
            }
        }

        return $labels;
    }
}