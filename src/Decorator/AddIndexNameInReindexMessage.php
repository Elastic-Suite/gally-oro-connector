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

use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexerInputValidator;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Add gally specific data validation for reindex messages.
 */
class AddIndexNameInReindexMessage extends IndexerInputValidator
{
    use ContextTrait;

    public function __construct(
        private IndexerInputValidator $decorated,
    ) {
    }

    public function validateRequestParameters(array|string|null $classOrClasses, array $context): array
    {
        $parameters = $this->validateClassAndContext(['class' => $classOrClasses, 'context' => $context]);

        return [$parameters['class'], $this->getContextWebsiteIds($parameters['context'])];
    }

    public function validateClassAndContext(array $parameters): array
    {
        $resolver = $this->decorated->getOptionResolver();
        $this->configureClassOptions($resolver);
        $this->configureGranulizeOptions($resolver);
        $this->configureContextOptions($resolver);

        return $resolver->resolve($parameters);
    }

    public function configureContextOptions(OptionsResolver $optionsResolver): void
    {
        $this->decorated->configureContextOptions($optionsResolver);

        $optionsResolver->setDefault('context', function (OptionsResolver $resolver) {
            $resolver->setDefined('indices_by_locale');
            $resolver->setDefined('message_count');
            $resolver->setDefined('is_full_indexation');
            $resolver->setAllowedTypes('indices_by_locale', ['array']);
            $resolver->setAllowedTypes('message_count', ['int']);
            $resolver->setAllowedTypes('is_full_indexation', ['bool']);
        });
    }

    public function configureClassOptions(OptionsResolver $optionsResolver): void
    {
        $this->decorated->configureClassOptions($optionsResolver);
    }

    public function configureEntityOptions(OptionsResolver $optionsResolver): void
    {
        $this->decorated->configureEntityOptions($optionsResolver);
    }

    public function configureGranulizeOptions(OptionsResolver $optionsResolver): void
    {
        $this->decorated->configureGranulizeOptions($optionsResolver);
    }
}
