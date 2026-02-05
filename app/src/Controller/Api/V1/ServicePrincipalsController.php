<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiController;

/**
 * Service Principals API Controller
 *
 * Provides self-service endpoints for service principals.
 */
class ServicePrincipalsController extends ApiController
{
    /**
     * Get current service principal details.
     *
     * @return void
     */
    public function me(): void
    {
        // Skip authorization for this endpoint - it's self-service
        $this->Authorization->skipAuthorization();

        $servicePrincipal = $this->getServicePrincipal();

        $data = [
            'id' => $servicePrincipal->id,
            'name' => $servicePrincipal->name,
            'description' => $servicePrincipal->description,
            'client_id' => $servicePrincipal->client_id,
            'is_active' => $servicePrincipal->is_active,
            'last_used_at' => $servicePrincipal->last_used_at?->toIso8601String(),
            'created' => $servicePrincipal->created?->toIso8601String(),
        ];

        // Include permissions info
        $permissions = $servicePrincipal->getPermissions();
        $data['permissions'] = array_map(function ($perm) {
            return [
                'id' => $perm->id,
                'name' => $perm->name,
                'scoping_rule' => $perm->scoping_rule,
            ];
        }, $permissions);

        $this->apiSuccess($data);
    }
}
