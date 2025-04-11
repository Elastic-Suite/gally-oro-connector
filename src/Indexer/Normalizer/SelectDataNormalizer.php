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

namespace Gally\OroPlugin\Indexer\Normalizer;

use Doctrine\ORM\EntityManagerInterface;
use Gally\OroPlugin\Convertor\LocalizationConvertor;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityExtendBundle\Entity\EnumOption;
use Oro\Bundle\EntityExtendBundle\Entity\EnumOptionTranslation;
use Oro\Bundle\EntityExtendBundle\Entity\EnumValueTranslation;
use Oro\Bundle\EntityExtendBundle\Form\Util\EnumTypeHelper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class SelectDataNormalizer extends AbstractNormalizer
{
    private array $translatedOptionsByField;

    public function __construct(
        private DoctrineHelper $doctrineHelper,
        private EnumTypeHelper $enumTypeHelper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function preProcess(
        Website $website,
        Localization $localization,
        string $entityClass,
        array $entityConfig,
        array &$indexData,
    ): void {
        $selectAttributes = [];
        foreach ($entityConfig['fields'] as $fieldData) {
            $fieldName = $fieldData['name'];

            if (!str_ends_with($fieldName, '_enum.ENUM_ID')) {
                // Get options only for select attributes.
                continue;
            }

            $fieldName = preg_replace('/_enum\.ENUM_ID$/', '', $fieldName);
            $selectAttributes[] = $fieldName;
        }

        $usedOptionsByField = [];
        $selectAttributesMap = array_fill_keys($selectAttributes, true);
        foreach ($indexData as $entityData) {
            foreach ($entityData as $fieldName => $value) {
                if (preg_match('/^(\w+)_enum\.(.+)$/', $fieldName, $matches)) {
                    [$fullMatch, $attribute, $optionCode] = $matches;
                    if (isset($selectAttributesMap[$attribute])) {
                        $usedOptionsByField[$attribute][$optionCode] = $optionCode;
                    }
                }
            }
        }

        $this->translatedOptionsByField = [];
        foreach ($usedOptionsByField as $fieldName => $optionCodes) {
            $enumCode = $this->enumTypeHelper->getEnumCode($entityClass, $fieldName);
            $translationRepo = $this->entityManager->getRepository(EnumOptionTranslation::class);

            $qb = $translationRepo->createQueryBuilder('t')
                ->where('t.field = :field')
                ->andWhere('t.foreignKey LIKE :enumCode')
                ->andWhere('t.objectClass = :objectClass')
                ->andWhere('t.locale = :localeCode')
                ->setParameter('field', 'name')
                ->setParameter('enumCode', $enumCode . '.%')
                ->setParameter('objectClass', EnumOption::class)
                ->setParameter('localeCode', LocalizationConvertor::getLocaleFormattingCode($localization));
            
            foreach ($qb->getQuery()->getResult() as $translation) {
                $code = str_replace($enumCode . '.', '', $translation->getForeignKey());
                $this->translatedOptionsByField[$fieldName][$code] = $translation->getContent();
            }
            $enumOptionRepo = $this->entityManager->getRepository(EnumOption::class);
            foreach ($enumOptionRepo->findBy(['enumCode' => $enumCode]) as $fallback) {
                if (!isset($this->translatedOptionsByField[$fieldName][$fallback->getInternalId()])) {
                    $this->translatedOptionsByField[$fieldName][$fallback->getInternalId()] = $fallback->getName();
                }
            }
        }
    }

    public function normalize(
        Website $website,
        string $entityClass,
        string|int $entityId,
        array &$fieldsValues,
        array &$preparedEntityData,
    ): void {
        foreach ($this->toArray($fieldsValues) as $fieldName => $values) {
            if (preg_match('/^(\w+)_enum\.(.+)$/', $fieldName, $matches)) {
                [$_, $cleanFieldName, $value] = $matches;
                foreach ($this->toArray($values) as $_) {
                    $preparedEntityData[$cleanFieldName][] = [
                        'label' => $this->translatedOptionsByField[$cleanFieldName][$value] ?? $value,
                        'value' => $value,
                    ];
                }
                unset($fieldsValues[$fieldName]);
                unset($fieldsValues[$cleanFieldName]);
            }
        }
    }
}
