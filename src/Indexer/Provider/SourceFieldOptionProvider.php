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

use Doctrine\ORM\EntityManagerInterface;
use Gally\Sdk\Entity\Label;
use Gally\Sdk\Entity\LocalizedCatalog;
use Gally\Sdk\Entity\SourceField;
use Gally\Sdk\Entity\SourceFieldOption;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Entity\EnumValueTranslation;
use Oro\Bundle\EntityExtendBundle\Form\Util\EnumTypeHelper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\SearchBundle\Provider\SearchMappingProvider;
use Oro\Bundle\WebsiteBundle\Provider\AbstractWebsiteLocalizationProvider;

/**
 * Gally Catalog data provider.
 */
class SourceFieldOptionProvider implements ProviderInterface
{
    protected AbstractWebsiteLocalizationProvider $websiteLocalizationProvider;

    /** @var LocalizedCatalog[][] */
    private array $localizedCatalogsByLocale = [];

    public function __construct(
        private SearchMappingProvider $mappingProvider,
        private CatalogProvider $catalogProvider,
        private LocaleSettings $localeSettings,
        private EntityManagerInterface $entityManager,
        private EnumTypeHelper $enumTypeHelper,
        private SourceFieldProvider $sourceFieldProvider,
    ) {
        $defaultLocale = $this->localeSettings->getLocale();
        foreach ($this->catalogProvider->provide() as $localizedCatalog) {
            if ($localizedCatalog->getLocale() !== $defaultLocale) {
                $this->localizedCatalogsByLocale[$localizedCatalog->getLocale()][] = $localizedCatalog;
            }
        }
    }

    /**
     * @return iterable<SourceFieldOption>
     */
    public function provide(): iterable
    {
        foreach ($this->mappingProvider->getEntityClasses() as $entityClass) {
            // Use class path in a string because this class might not exist if enterprise bundles are not installed.
            if ('Oro\Bundle\WebsiteElasticSearchBundle\Entity\SavedSearch' === $entityClass) {
                // Todo managed savedSearch https://doc.oroinc.com/user/storefront/account/saved-search/
                continue;
            }

            $metadata = $this->sourceFieldProvider->getMetadataFromEntityClass($entityClass);
            $entityConfig = $this->mappingProvider->getEntityConfig($entityClass);

            foreach ($entityConfig['fields'] as $fieldData) {
                if (!str_ends_with($fieldData['name'], '_enum.ENUM_ID')) {
                    // Get options only for select attributes.
                    continue;
                }

                $fieldName = $this->sourceFieldProvider->cleanFieldName($fieldData['name']);
                $sourceField = new SourceField($metadata, $fieldName, '', '', []);
                $enumCode = $this->enumTypeHelper->getEnumCode(Product::class, $fieldName);
                $enumValueClassName = ExtendHelper::buildEnumValueClassName($enumCode);
                $enumValueRepo = $this->entityManager->getRepository($enumValueClassName);
                $labels = $this->getLabels($enumValueClassName);

                /** @var AbstractEnumValue $value */
                foreach ($enumValueRepo->findAll() as $value) {
                    yield new SourceFieldOption(
                        $sourceField,
                        (string) $value->getId(),
                        $value->getPriority(),
                        $value->getName(),
                        $labels[$value->getId()] ?? [],
                    );
                }
            }
        }

        return [];
    }

    /**
     * @return Label[][]
     */
    private function getLabels(string $objectClass): array
    {
        $translationRepo = $this->entityManager->getRepository(EnumValueTranslation::class);
        $translations = $translationRepo->findBy([
            'objectClass' => $objectClass,
            'field' => 'name',
        ]);
        $labels = [];

        foreach ($translations as $translation) {
            foreach ($this->localizedCatalogsByLocale[$translation->getLocale()] ?? [] as $localizedCatalog) {
                $labels[$translation->getForeignKey()][] = new Label(
                    $localizedCatalog,
                    $translation->getContent()
                );
            }
        }

        return $labels;
    }
}
