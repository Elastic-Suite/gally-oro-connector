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

namespace Gally\OroPlugin\Decorator;

use Gally\OroPlugin\Indexer\Registry\IndexRegistry;
use Oro\Bundle\WebsiteSearchBundle\Engine\AsyncMessaging\ReindexMessageGranularizer;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;

/**
 * Add index name and message count in message before add them in the queue.
 */
class AddIndexNameInContext extends ReindexMessageGranularizer
{
    use ContextTrait;

    public function __construct(
        private ReindexMessageGranularizer $decorated,
        private IndexRegistry $indexRegistry,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function setChunkSize($chunkSize)
    {
        $this->decorated->setChunkSize($chunkSize);
    }

    /**
     * {@inheritDoc}
     */
    public function process($entities, array $websites, array $context): iterable
    {
        $entityIds = $this->getContextEntityIds($context);
        $isFullIndexation = empty($entityIds);

        // Add index names in queue message in order to be able to update existing index.
        if (!isset($context['indices_by_locale'])) {
            $context['indices_by_locale'] = $this->indexRegistry->getIndicesByLocale();
        }

        $childMessages = iterator_to_array($this->decorated->process($entities, $websites, $context));
        $messageCount = \count($childMessages);

        foreach ($childMessages as $message) {
            $message['context']['indices_by_locale'] = $context['indices_by_locale'];
            $message['context']['message_count'] = $messageCount;
            $message['context']['is_full_indexation'] = $isFullIndexation;
            yield $message;
        }
    }
}
