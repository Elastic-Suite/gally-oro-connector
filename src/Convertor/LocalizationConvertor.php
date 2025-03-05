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

namespace Gally\OroPlugin\Convertor;

use Oro\Bundle\LocaleBundle\Entity\Localization;

class LocalizationConvertor
{
    public static function getLocaleFormattingCode(Localization $localization): string
    {
        $pattern = '/^[a-z]{2}_[A-Z]{2}$/';

        if (preg_match($pattern, $localization->getFormattingCode())) {
            return $localization->getFormattingCode();
        }

        $code = $localization->getParentLocalization()?->getFormattingCode();

        if ($code && preg_match($pattern, $code)) {
            return $code;
        }

        return 'en_US';
    }
}
