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

namespace Gally\OroPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entity that record the messages received for an index in order to know when to install it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'gally_index_batch_count')]
class IndexBatchCount
{
    #[ORM\Id]
    #[ORM\Column(name: 'index_name', type: 'string', length: 255)]
    private string $indexName;

    #[ORM\Column(name: 'message_count', type: 'integer')]
    private int $messageCount = 1;

    public function __construct(string $indexName)
    {
        $this->indexName = $indexName;
    }

    public function increment(): void
    {
        ++$this->messageCount;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function setIndexName(string $indexName): void
    {
        $this->indexName = $indexName;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    public function setMessageCount(int $messageCount): void
    {
        $this->messageCount = $messageCount;
    }
}
