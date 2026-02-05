<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiController;
use Cake\Http\Exception\NotFoundException;

/**
 * Branches API Controller
 *
 * Provides read-only access to branch data for API clients.
 */
class BranchesController extends ApiController
{
    /**
     * List branches with pagination and filtering.
     *
     * @return void
     */
    public function index(): void
    {
        $this->paginate = [
            'limit' => 50,
            'maxLimit' => 200,
            'order' => ['Branches.name' => 'asc'],
        ];

        $query = $this->fetchTable('Branches')->find();

        // Apply authorization scope
        $identity = $this->Authentication->getIdentity();
        $query = $identity->applyScope('index', $query);

        // Optional filters
        if ($this->request->getQuery('parent_id')) {
            $query->where(['Branches.parent_id' => $this->request->getQuery('parent_id')]);
        }

        if ($this->request->getQuery('type')) {
            $query->where(['Branches.type' => $this->request->getQuery('type')]);
        }

        $branches = $this->paginate($query);

        // Transform to API response format
        $data = [];
        foreach ($branches as $branch) {
            $data[] = $this->formatBranch($branch);
        }

        $this->apiSuccess($data, $this->getPaginationMeta());
    }

    /**
     * View a single branch.
     *
     * @param int $id Branch ID
     * @return void
     */
    public function view(int $id): void
    {
        $branch = $this->fetchTable('Branches')->find()
            ->where(['Branches.id' => $id])
            ->contain(['ParentBranches'])
            ->first();

        if (!$branch) {
            throw new NotFoundException('Branch not found');
        }

        // Check authorization
        $this->Authorization->authorize($branch, 'view');

        $this->apiSuccess($this->formatBranch($branch, true));
    }

    /**
     * Format branch data for API response.
     *
     * @param \App\Model\Entity\Branch $branch Branch entity
     * @param bool $detailed Include detailed information
     * @return array
     */
    protected function formatBranch($branch, bool $detailed = false): array
    {
        $data = [
            'id' => $branch->id,
            'name' => $branch->name,
            'type' => $branch->type ?? null,
            'parent_id' => $branch->parent_id,
        ];

        if ($detailed) {
            $data += [
                'location' => $branch->location ?? null,
                'domain' => $branch->domain ?? null,
                'created' => $branch->created?->toIso8601String(),
                'modified' => $branch->modified?->toIso8601String(),
            ];

            if ($branch->parent_branch ?? null) {
                $data['parent'] = [
                    'id' => $branch->parent_branch->id,
                    'name' => $branch->parent_branch->name,
                ];
            }
        }

        return $data;
    }
}
