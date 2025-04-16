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

use Composer\InstalledVersions;

/*
 * Manage different signature of setChunkSize according to Oro version.
 */
if (version_compare(InstalledVersions::getVersion('oro/commerce'), '6.0.0', '<')) {
    trait AddIndexNameInContextTrait
    {
        /**
         * {@inheritDoc}
         */
        public function setChunkSize($chunkSize)
        {
            $this->decorated->setChunkSize($chunkSize);
        }
    }
} else {
    trait AddIndexNameInContextTrait
    {
        /**
         * {@inheritDoc}
         */
        public function setChunkSize(int $chunkSize): void
        {
            $this->decorated->setChunkSize($chunkSize);
        }
    }
}
