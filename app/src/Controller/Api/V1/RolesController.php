<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiController;


/**
 * Roles API Controller
 *
 * Provides read-only access to role data for API clients.
 */
class RolesController extends ApiController
{
    /**
     * List roles with pagination.
     *
     * @return void
     */
    public function index(): void
    {
        $this->paginate = [
            'limit' => 50,
            'maxLimit' => 200,
            'order' => ['Roles.name' => 'asc'],
        ];

        $query = $this->fetchTable('Roles')->find();

        // Apply authorization scope
        $identity = $this->Authentication->getIdentity();
        $query = $identity->applyScope('index', $query);

        $roles = $this->paginate($query);

        // Transform to API response format
        $data = [];
        foreach ($roles as $role) {
            $data[] = $this->formatRole($role);
        }

        $this->apiSuccess($data, $this->getPaginationMeta());
    }

    /**
     * View a single role.
     *
     * @param int $id Role ID
     * @return void
     */
    public function view(int $id): void
    {
        $role = $this->fetchTable('Roles')->find()
            ->where(['Roles.id' => $id])
            ->contain(['Permissions'])
            ->first();

        if (!$role) {
            $this->apiError('NOT_FOUND', 'Role not found', [], 404);
            return;
        }

        // Check authorization
        $this->Authorization->authorize($role, 'view');

        $this->apiSuccess($this->formatRole($role, true));
    }

    /**
     * Format role data for API response.
     *
     * @param \App\Model\Entity\Role $role Role entity
     * @param bool $detailed Include detailed information
     * @return array
     */
    protected function formatRole($role, bool $detailed = false): array
    {
        $data = [
            'id' => $role->id,
            'name' => $role->name,
        ];

        if ($detailed) {
            $data += [
                'created' => $role->created?->toIso8601String(),
                'modified' => $role->modified?->toIso8601String(),
            ];

            if (!empty($role->permissions)) {
                $data['permissions'] = array_map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'scoping_rule' => $permission->scoping_rule,
                    ];
                }, $role->permissions);
            }
        }

        return $data;
    }
}
