<?php

declare(strict_types=1);

namespace Officers\Services\Api;

use App\KMP\KmpIdentityInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\ORM\TableRegistry;

class DefaultReadOnlyOfficeService implements ReadOnlyOfficeServiceInterface
{
    public function list(KmpIdentityInterface $identity, array $filters, int $page, int $limit): array
    {
        $table = TableRegistry::getTableLocator()->get('Officers.Offices');
        $query = $table->find()
            ->contain(['Departments'])
            ->orderBy(['Offices.name' => 'ASC']);

        if (!empty($filters['department_id'])) {
            $query->where(['Offices.department_id' => (int)$filters['department_id']]);
        }
        if (array_key_exists('requires_warrant', $filters) && $filters['requires_warrant'] !== '') {
            $query->where(['Offices.requires_warrant' => filter_var($filters['requires_warrant'], FILTER_VALIDATE_BOOLEAN)]);
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->where(['Offices.name LIKE' => $search]);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->all();

        $data = [];
        foreach ($rows as $row) {
            $this->assertCanView($identity, $row);
            $data[] = $this->formatOffice($row);
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
        $row = TableRegistry::getTableLocator()->get('Officers.Offices')->find()
            ->where(['Offices.id' => $id])
            ->contain(['Departments', 'ReportsTo', 'DeputyTo', 'GrantsRole'])
            ->first();

        if ($row === null) {
            return null;
        }

        $this->assertCanView($identity, $row);

        $payload = $this->formatOffice($row);
        $payload['reports_to'] = $row->reports_to ? ['id' => $row->reports_to->id, 'name' => $row->reports_to->name] : null;
        $payload['deputy_to'] = $row->deputy_to ? ['id' => $row->deputy_to->id, 'name' => $row->deputy_to->name] : null;
        $payload['grants_role'] = $row->grants_role ? ['id' => $row->grants_role->id, 'name' => $row->grants_role->name] : null;

        return $payload;
    }

    protected function formatOffice(object $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'department_id' => $row->department_id,
            'department_name' => $row->department?->name,
            'requires_warrant' => (bool)$row->requires_warrant,
            'required_office' => (bool)$row->required_office,
            'only_one_per_branch' => (bool)$row->only_one_per_branch,
            'can_skip_report' => (bool)$row->can_skip_report,
            'term_length' => (int)$row->term_length,
            'created' => $row->created?->toIso8601String(),
            'modified' => $row->modified?->toIso8601String(),
        ];
    }

    protected function assertCanView(KmpIdentityInterface $identity, object $entity): void
    {
        if (!$identity->can('view', $entity)) {
            throw new ForbiddenException('Not authorized to view office');
        }
    }
}

