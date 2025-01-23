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

namespace Gally\OroPlugin\Indexer\Registry;

/**
 * This registry allow to keep trace of the used indices when indexing request come from the message queue system.
 */
class IndexRegistry
{
    private array $indicesByLocale = [];

    public function getIndicesByLocale(): array
    {
        return $this->indicesByLocale;
    }

    public function setIndicesByLocale(array $indicesByLocale): void
    {
        $this->indicesByLocale = $indicesByLocale;
    }
}
