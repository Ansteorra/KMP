<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiController;
use App\Services\ApiDataRegistry;

/**
 * Branches API Controller
 *
 * Provides public, read-only access to branch data.
 * No authentication required — branch information is public.
 */
class BranchesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->addUnauthenticatedActions(['index', 'view']);
    }

    /**
     * List branches with pagination and filtering.
     *
     * @return void
     */
    public function index(): void
    {
        $this->Authorization->skipAuthorization();

        $this->paginate = [
            'limit' => 50,
            'maxLimit' => 200,
            'order' => ['Branches.name' => 'asc'],
        ];

        $query = $this->fetchTable('Branches')->find()
            ->select(['id', 'public_id', 'name', 'location', 'type', 'parent_id'])
            ->contain(['Parent' => fn($q) => $q->select(['id', 'public_id'])])
            ->whereNotNull('Branches.type');

        if ($this->request->getQuery('parent')) {
            $parentBranch = $this->fetchTable('Branches')
                ->find('byPublicId', [$this->request->getQuery('parent')])
                ->select(['id'])
                ->first();
            if ($parentBranch) {
                $query->where(['Branches.parent_id' => $parentBranch->id]);
            } else {
                // Unknown parent — return empty result
                $query->where(['1 = 0']);
            }
        }

        if ($this->request->getQuery('type')) {
            $query->where(['Branches.type' => $this->request->getQuery('type')]);
        }

        $branches = $this->paginate($query);

        $data = [];
        foreach ($branches as $branch) {
            $data[] = $this->formatBranch($branch);
        }

        $this->apiSuccess($data, $this->getPaginationMeta());
    }

    /**
     * View a single branch with all public information.
     *
     * @param string $id Branch public_id
     * @return void
     */
    public function view(string $id): void
    {
        $this->Authorization->skipAuthorization();

        $branchesTable = $this->fetchTable('Branches');

        $branch = $branchesTable->find('byPublicId', [$id])
            ->contain(['Parent'])
            ->first();

        if (!$branch) {
            $this->apiError('NOT_FOUND', 'Branch not found', [], 404);
            return;
        }

        // Load direct children
        $children = $branchesTable->find()
            ->select(['id', 'public_id', 'name', 'type', 'location'])
            ->where(['parent_id' => $branch->id])
            ->orderBy(['name' => 'asc'])
            ->all();

        $detail = $this->formatBranchDetail($branch, $children);

        // Let plugins inject additional data
        $pluginData = ApiDataRegistry::collect('Branches', 'view', $branch);
        $detail = array_merge($detail, $pluginData);

        $this->apiSuccess($detail);
    }

    /**
     * Format branch summary for list responses.
     *
     * @param \App\Model\Entity\Branch $branch Branch entity
     * @return array
     */
    protected function formatBranch($branch): array
    {
        return [
            'id' => $branch->public_id,
            'name' => $branch->name,
            'location' => $branch->location ?? null,
            'type' => $branch->type,
            'parent_id' => $branch->parent->public_id ?? null,
        ];
    }

    /**
     * Format branch detail with all public information.
     *
     * @param \App\Model\Entity\Branch $branch Branch entity with Parent contain
     * @param iterable $children Direct child branches
     * @return array
     */
    protected function formatBranchDetail($branch, iterable $children): array
    {
        $data = [
            'id' => $branch->public_id,
            'name' => $branch->name,
            'location' => $branch->location ?? null,
            'type' => $branch->type ?? null,
            'domain' => $branch->domain ?? null,
            'links' => $branch->links ?? null,
            'can_have_members' => (bool)$branch->can_have_members,
            'created' => $branch->created?->toIso8601String(),
            'modified' => $branch->modified?->toIso8601String(),
        ];

        if ($branch->parent ?? null) {
            $data['parent'] = [
                'id' => $branch->parent->public_id,
                'name' => $branch->parent->name,
                'type' => $branch->parent->type ?? null,
            ];
        } else {
            $data['parent'] = null;
        }

        $data['children'] = [];
        foreach ($children as $child) {
            $data['children'][] = [
                'id' => $child->public_id,
                'name' => $child->name,
                'type' => $child->type ?? null,
                'location' => $child->location ?? null,
            ];
        }

        return $data;
    }
}
