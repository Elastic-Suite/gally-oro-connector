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

namespace Gally\OroPlugin\Search\Filter\Form;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Dummy choice loader class for gally filter.
 * With gally, we cannot validate facet option before running the search request.
 * This class disabled these check and let gally manage wrong value.
 */
class ChoiceLoader implements ChoiceLoaderInterface
{
    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        return new ArrayChoiceList([]);
    }

    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        return $values;
    }

    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        return $choices;
    }
}
