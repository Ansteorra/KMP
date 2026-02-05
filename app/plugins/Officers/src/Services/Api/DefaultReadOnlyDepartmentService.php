<?php

declare(strict_types=1);

namespace Officers\Services\Api;

use App\KMP\KmpIdentityInterface;
use Cake\ORM\TableRegistry;

class DefaultReadOnlyDepartmentService implements ReadOnlyDepartmentServiceInterface
{
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

    protected function assertCanView(KmpIdentityInterface $identity, object $entity): void
    {
        if (!$identity->can('view', $entity)) {
            throw new \Cake\Http\Exception\ForbiddenException('Not authorized to view department');
        }
    }
}

