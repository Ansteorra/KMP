<?php

declare(strict_types=1);

namespace Officers\Services\Api;

use App\KMP\KmpIdentityInterface;
use Cake\ORM\TableRegistry;

class DefaultReadOnlyDepartmentService implements ReadOnlyDepartmentServiceInterface
{
    /**
     * List all records.
     *
     * @param KmpIdentityInterface $identity
     * @param array $filters
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function list(KmpIdentityInterface $identity, array $filters, int $page, int $limit): array
    {
        $table = TableRegistry::getTableLocator()->get('Officers.Departments');
        $query = $table->find()->orderBy(['Departments.name' => 'ASC']);

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->where(['Departments.name LIKE' => $search]);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->all();

        $data = [];
        foreach ($rows as $row) {
            $this->assertCanView($identity, $row);
            $data[] = $this->formatDepartment($row);
        }

        return [
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $limit,
                    'total_pages' => (int)max(1, ceil($total / $limit)),
                ],
            ],
        ];
    }

    /**
     * Get by id.
     *
     * @param KmpIdentityInterface $identity
     * @param int $id
     * @return ?array
     */
    public function getById(KmpIdentityInterface $identity, int $id): ?array
    {
        $row = TableRegistry::getTableLocator()
            ->get('Officers.Departments')
            ->find()
            ->where(['Departments.id' => $id])
            ->contain(['Offices'])
            ->first();

        if ($row === null) {
            return null;
        }

        $this->assertCanView($identity, $row);

        $payload = $this->formatDepartment($row);
        $payload['offices'] = [];
        foreach ($row->offices ?? [] as $office) {
            $payload['offices'][] = [
                'id' => $office->id,
                'name' => $office->name,
            ];
        }

        return $payload;
    }

    /**
     * Format department.
     *
     * @param object $row
     * @return array
     */
    protected function formatDepartment(object $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'domain' => $row->domain,
            'created' => $row->created?->toIso8601String(),
            'modified' => $row->modified?->toIso8601String(),
        ];
    }

    /**
     * Assert that can view.
     *
     * @param KmpIdentityInterface $identity
     * @param object $entity
     * @return void
     */
    protected function assertCanView(KmpIdentityInterface $identity, object $entity): void
    {
        if (!$identity->can('view', $entity)) {
            throw new \Cake\Http\Exception\ForbiddenException('Not authorized to view department');
        }
    }
}

