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

namespace Gally\OroPlugin\Async\Processor;

use Gally\OroPlugin\Async\Topic\GallyReindexFinishedTopic;
use Gally\OroPlugin\Async\Topic\WebsiteSearchReindexGranulizedJobAwareTopic;
use Gally\OroPlugin\Indexer\Registry\IndexRegistry;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;
use Oro\Bundle\WebsiteSearchBundle\Async\Topic\WebsiteSearchReindexGranulizedTopic;
use Oro\Bundle\WebsiteSearchBundle\Async\WebsiteSearchEngineExceptionAwareProcessorTrait;
use Oro\Bundle\WebsiteSearchBundle\Async\WebsiteSearchReindexGranulizedProcessor;
use Oro\Bundle\WebsiteSearchBundle\Async\WebsiteSearchReindexProcessor as BaseWebsiteSearchReindexProcessor;
use Oro\Bundle\WebsiteSearchBundle\Engine\AsyncMessaging\ReindexMessageGranularizer;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Event\BeforeReindexEvent;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\DelayedJobRunnerDecoratingProcessor;
use Oro\Component\MessageQueue\Job\DependentJobService;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Decorate BaseWebsiteSearchReindexProcessor because we need to know when all the reindex messages are done, the goal is to install the index after all re-indexations.
 * A unique job is created (gally:reindex:job-parent), this job has for children all the reindex messages/jobs (gally:reindex:job-child:{count}),
 * in addition another message/job (gally:reindex:finished) is called when all children message/job are done, in this job/message we install all the indices created during the indexation.
 */
class WebsiteSearchReindexProcessor extends BaseWebsiteSearchReindexProcessor
{
    use ContextTrait;
    use LoggerAwareTrait;
    use WebsiteSearchEngineExceptionAwareProcessorTrait;

    public function __construct(
        protected MessageProcessorInterface $delayedJobRunnerProcessor,
        protected WebsiteSearchReindexGranulizedProcessor $websiteSearchReindexGranulizedProcessor,
        protected ReindexMessageGranularizer $reindexMessageGranularizer,
        protected MessageProducerInterface $messageProducer,
        protected EventDispatcherInterface $eventDispatcher,
        protected JobRunner $jobRunner,
        protected DependentJobService $dependentJob,
        protected IndexRegistry $indexRegistry,
        protected EngineParameters $engineParameters,
        protected BaseWebsiteSearchReindexProcessor $decorated,
    ) {
        parent::__construct(
            $delayedJobRunnerProcessor,
            $websiteSearchReindexGranulizedProcessor,
            $reindexMessageGranularizer,
            $messageProducer,
            $eventDispatcher,
        );
    }

    public function process(MessageInterface $message, SessionInterface $session): string
    {
        $messageBody = $message->getBody();
        if (isset($messageBody['jobId'])) {
            return $this->delayedJobRunnerProcessor->process($message, $session);
        }

        return $this->doProcess(
            function () use (
                $messageBody,
                $message
            ) {
                $this->dispatchReindexEvent($messageBody['class'], $messageBody['context']);

                if ($messageBody['granulize']) {
                    if ($this->hasMoreThanOneChildMessages($messageBody)) {
                        return $this->jobRunner->runUnique(
                            $message->getMessageId(),
                            \sprintf('gally:reindex:job-parent:%s', $message->getMessageId()),
                            function (JobRunner $jobRunner, Job $job) use (
                                $messageBody,
                            ) {
                                $childMessages = $this->reindexMessageGranularizer
                                    ->process($messageBody['class'], $this->getContextWebsiteIds($messageBody['context']), $messageBody['context']);
                                $this->createFinishJobs($job, $this->indexRegistry->getIndicesByLocale(), $this->getContextEntityIds($messageBody['context']));
                                $count = 0;
                                foreach ($childMessages as $childMessageBody) {
                                    ++$count;
                                    $jobRunner->createDelayed(
                                        \sprintf('gally:reindex:job-child:%s', $count),
                                        function (JobRunner $jobRunner, Job $child) use (
                                            $childMessageBody,
                                        ): void {
                                            $this->messageProducer->send(
                                                WebsiteSearchReindexGranulizedJobAwareTopic::getName(),
                                                array_merge($childMessageBody, [DelayedJobRunnerDecoratingProcessor::JOB_ID => $child->getId()])
                                            );
                                        }
                                    );
                                }

                                return self::ACK;
                            }
                        );
                    } else {
                        return $this->produceChildMessages($messageBody['class'], $messageBody['context']);
                    }
                }

                return $this->websiteSearchReindexGranulizedProcessor->doReindex(
                    $messageBody['class'],
                    array_merge($messageBody['context'], ['skip_pre_processing' => true]),
                );
            },
            $this->eventDispatcher,
            $this->logger
        );
    }

    protected function createFinishJobs(Job $job, array $indiciesByLocale, array $entityIds): void
    {
        $context = $this->dependentJob->createDependentJobContext($job->getRootJob());
        $context->addDependentJob(
            GallyReindexFinishedTopic::getName(),
            [
                GallyReindexFinishedTopic::ROOT_JOB_ID => $job->getRootJob()->getId(),
                GallyReindexFinishedTopic::INDICIES_BY_LOCALE => $indiciesByLocale,
                GallyReindexFinishedTopic::IS_FULL_REINDEX => 0 === \count($entityIds),
            ],
        );

        $this->dependentJob->saveDependentJob($context);
    }

    protected function hasMoreThanOneChildMessages(array $messageBody): bool
    {
        $childMessages = $this->reindexMessageGranularizer
            ->process($messageBody['class'], $this->getContextWebsiteIds($messageBody['context']), $messageBody['context']);

        $moreThanOnChild = false;
        $childrenCount = 0;
        foreach ($childMessages as $childMessage) {
            ++$childrenCount;
            if ($childrenCount > 1) {
                $moreThanOnChild = true;
                break;
            }
        }

        return $moreThanOnChild;
    }

    /**
     * @return string Message status
     */
    private function produceChildMessages(array|string $class, array $context): string
    {
        /**
         * For Gally re-indexation, this function will be used in case we have only one message.
         */
        $childMessages = $this->reindexMessageGranularizer
            ->process($class, $this->getContextWebsiteIds($context), $context);

        $firstMessageBody = [];
        foreach ($childMessages as $childMessageBody) {
            if ([] === $firstMessageBody) {
                // Adds the first message body to a buffer to check if it is the only one - to process instantly.
                $firstMessageBody = $childMessageBody;
                continue;
            }

            if ($firstMessageBody) {
                // Sends the first message body to MQ as there is definitely the second one
                // because we reached 2nd iteration.
                $this->messageProducer->send(WebsiteSearchReindexGranulizedTopic::getName(), $firstMessageBody);
                // Clears a buffer as we don't need it anymore.
                $firstMessageBody = null;
            }

            $this->messageProducer->send(WebsiteSearchReindexGranulizedTopic::getName(), $childMessageBody);
        }

        if ($firstMessageBody) {
            // Processes first message body instantly as it is happened to be the only one to process.
            return $this->websiteSearchReindexGranulizedProcessor->doReindex(
                $firstMessageBody['class'],
                array_merge($firstMessageBody['context'], ['skip_pre_processing' => true])
            );
        }

        return self::ACK;
    }

    private function dispatchReindexEvent(array|string $class, array $context): void
    {
        $event = new BeforeReindexEvent($class, $context);
        $this->eventDispatcher->dispatch($event, BeforeReindexEvent::EVENT_NAME);
    }
}
