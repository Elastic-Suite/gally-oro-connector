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

namespace Gally\OroPlugin\Indexer\Event;

use Symfony\Contracts\EventDispatcher\Event;

class BeforeSaveIndexDataEvent extends Event
{
    public const NAME = 'gally.indexer.before_save_index_data';

    public function __construct(
        private string $class,
        private array $data
    ) {
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
