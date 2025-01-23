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

use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class DatetimeDataNormalizer extends AbstractNormalizer
{
    private array $datetimeAttribute;

    public function __construct(
    ) {
    }

    public function preProcess(
        Website $website,
        Localization $localization,
        string $entityClass,
        array $entityConfig,
        array &$indexData,
    ): void {
        $this->datetimeAttribute = [];
        foreach ($entityConfig['fields'] ?? [] as $fieldName => $fieldData) {
            if ('datetime' === $fieldData['type']) {
                $this->datetimeAttribute[] = $fieldName;
            }
        }
    }

    public function normalize(
        Website $website,
        string $entityClass,
        int|string $entityId,
        array &$fieldsValues,
        array &$preparedEntityData
    ): void {
        foreach ($this->datetimeAttribute as $attribute) {
            foreach ($this->toArray($fieldsValues[$attribute] ?? []) as $value) {
                if ($value['value'] instanceof \DateTime) {
                    $preparedEntityData[$attribute] = $value['value']->format('Y-m-d H:i:s');
                }
            }
            unset($fieldsValues[$attribute]);
        }
    }
}
