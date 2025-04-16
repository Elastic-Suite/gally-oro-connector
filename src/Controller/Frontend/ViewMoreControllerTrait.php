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

namespace Gally\OroPlugin\Controller\Frontend;

use Composer\InstalledVersions;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor as AclAncestorAnnotation;
use Oro\Bundle\SecurityBundle\Attribute\AclAncestor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route as RouteAnnotation;
use Symfony\Component\Routing\Attribute\Route;

/*
 * Manage annotation vs attribute controller declaration between oro version.
 * (Oro 5.1 don't support Attribute notation)
 */
if (version_compare(InstalledVersions::getVersion('oro/commerce'), '6.0.0', '<')) {
    trait ViewMoreControllerTrait
    {
        /**
         * @RouteAnnotation("/filter_view_more", name="gally_filter_view_more", methods={"GET"}, options={"expose"=true})
         *
         * @AclAncestorAnnotation("oro_product_frontend_view")
         */
        public function getDataAction(Request $request): JsonResponse
        {
            return $this->buildResponse($request);
        }
    }
} else {
    trait ViewMoreControllerTrait
    {
        #[Route('/filter_view_more', name: 'gally_filter_view_more', methods: ['GET'], options: ['expose' => true])]
        #[AclAncestor('oro_product_frontend_view')]
        public function getDataAction(Request $request): JsonResponse
        {
            return $this->buildResponse($request);
        }
    }
}
