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

use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Exception\RuntimeException;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class BooleanDataNormalizer extends AbstractNormalizer
{
    private array $booleanAttributes;

    public function __construct(
        private ConfigProvider $configProvider,
    ) {
    }

    public function preProcess(
        Website $website,
        Localization $localization,
        string $entityClass,
        array $entityConfig,
        array &$indexData,
    ): void {
        $this->booleanAttributes = [];
        foreach ($entityConfig['fields'] ?? [] as $fieldName => $fieldData) {
            if ('integer' === $fieldData['type']) {
                try {
                    $fieldConfig = $this->configProvider->getConfig($entityClass, $fieldName);
                } catch (RuntimeException) {
                    $fieldConfig = null;
                }
                /** @var FieldConfigId $fieldConfigId */
                $fieldConfigId = $fieldConfig?->getId();
                $type = $fieldConfigId ? $fieldConfigId->getFieldType() : $fieldData['type'];
                if ('boolean' === $type) {
                    $this->booleanAttributes[] = $fieldName;
                }
            }
        }
    }

    public function normalize(
        Website $website,
        string $entityClass,
        int|string $entityId,
        array &$fieldsValues,
        array &$preparedEntityData,
    ): void {
        foreach ($this->booleanAttributes as $attribute) {
            foreach ($this->toArray($fieldsValues[$attribute] ?? []) as $value) {
                $preparedEntityData[$attribute] = (bool) $value['value'];
            }
            unset($fieldsValues[$attribute]);
        }
    }
}
