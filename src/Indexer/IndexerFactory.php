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

namespace Gally\OroPlugin\Indexer;

use Gally\OroPlugin\Search\SearchEngine;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;
use Oro\Bundle\SearchBundle\Engine\IndexerInterface;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractIndexer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Factory to create targeted search engine indexer instance(orm, elastic_search).
 */
class IndexerFactory
{
    /**
     * @throws UnexpectedTypeException
     */
    public static function create(
        ServiceLocator $locator,
        EngineParameters $engineParameters
    ): IndexerInterface {
        $fallbackIndexer = $locator->get($engineParameters->getEngineName());
        if (!$fallbackIndexer instanceof AbstractIndexer) {
            throw new UnexpectedTypeException($fallbackIndexer, AbstractIndexer::class);
        }

        /** @var Indexer $gallyIndex */
        $gallyIndex = $locator->get(SearchEngine::ENGINE_NAME);
        $gallyIndex->setFallBackIndexer($fallbackIndexer);

        return $gallyIndex;
    }
}
