<?php

declare(strict_types=1);

namespace Activities\Controller\Api\V1;

use App\Controller\Api\ApiController;
use App\KMP\KmpIdentityInterface;
use Cake\Http\Exception\UnauthorizedException;

/**
 * Base controller for Activities plugin API v1 endpoints.
 */
class AppController extends ApiController
{
    /**
     * Return the authenticated KMP identity for authorization checks.
     *
     * @return \App\KMP\KmpIdentityInterface
     */
    protected function getKmpIdentity(): KmpIdentityInterface
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity instanceof KmpIdentityInterface) {
            return $identity;
        }

        if (is_object($identity) && method_exists($identity, 'getOriginalData')) {
            $originalData = $identity->getOriginalData();
            if ($originalData instanceof KmpIdentityInterface) {
                return $originalData;
            }
        }

        throw new UnauthorizedException('API requires a KMP identity');
    }
}
