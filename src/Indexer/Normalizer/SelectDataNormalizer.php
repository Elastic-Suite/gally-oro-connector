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

use Gally\OroPlugin\Convertor\LocalizationConvertor;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
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
            $enumValueClassName = ExtendHelper::buildEnumValueClassName($enumCode);
            $translationRepo = $this->doctrineHelper->getEntityRepositoryForClass(EnumValueTranslation::class);
            $translations = $translationRepo->findBy([
                'objectClass' => $enumValueClassName,
                'field' => 'name',
                'foreignKey' => $optionCodes,
                'locale' => LocalizationConvertor::getLocaleFormattingCode($localization),
            ]);
            foreach ($translations as $translation) {
                $this->translatedOptionsByField[$fieldName][$translation->getForeignKey()] = $translation->getContent();
            }
            $enumOptionRepo = $this->doctrineHelper->getEntityRepositoryForClass($enumValueClassName);
            $fallbacks = $enumOptionRepo->findAll();
            foreach ($fallbacks as $fallback) {
                if (!isset($this->translatedOptionsByField[$fieldName][$fallback->getId()])) {
                    $this->translatedOptionsByField[$fieldName][$fallback->getId()] = $fallback->getName();
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
