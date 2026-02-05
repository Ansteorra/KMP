<?php

declare(strict_types=1);

namespace Officers\Services\Api;

use App\KMP\KmpIdentityInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\ORM\TableRegistry;

class DefaultReadOnlyOfficerRosterService implements ReadOnlyOfficerRosterServiceInterface
{
    public function list(KmpIdentityInterface $identity, array $filters, int $page, int $limit): array
    {
        $table = TableRegistry::getTableLocator()->get('Officers.Officers');
        $query = $table->find()
            ->contain(['Members', 'Branches', 'Offices'])
            ->orderBy(['Officers.id' => 'DESC']);

        if (!empty($filters['branch_id'])) {
            $query->where(['Officers.branch_id' => (int)$filters['branch_id']]);
        }
        if (!empty($filters['office_id'])) {
            $query->where(['Officers.office_id' => (int)$filters['office_id']]);
        }
        if (!empty($filters['status'])) {
            $query->where(['Officers.status' => (string)$filters['status']]);
        } else {
            $query->where(['Officers.status IN' => ['current', 'upcoming']]);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->all();

        $data = [];
        foreach ($rows as $row) {
            $this->assertCanView($identity, $row);
            $data[] = $this->formatOfficer($row);
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
        $row = TableRegistry::getTableLocator()->get('Officers.Officers')->find()
            ->where(['Officers.id' => $id])
            ->contain(['Members', 'Branches', 'Offices'])
            ->first();

        if ($row === null) {
            return null;
        }

        $this->assertCanView($identity, $row);

        return $this->formatOfficer($row, true);
    }

    protected function formatOfficer(object $row, bool $detailed = false): array
    {
        $payload = [
            'id' => $row->id,
            'member_id' => $row->member_id,
            'member_name' => $row->member?->sca_name,
            'branch_id' => $row->branch_id,
            'branch_name' => $row->branch?->name,
            'office_id' => $row->office_id,
            'office_name' => $row->office?->name,
            'status' => $row->status,
            'start_on' => $row->start_on?->toDateString(),
            'expires_on' => $row->expires_on?->toDateString(),
        ];

        if ($detailed) {
            $payload['deputy_description'] = $row->deputy_description;
            $payload['email_address'] = $row->email_address;
            $payload['revoked_reason'] = $row->revoked_reason;
            $payload['created'] = $row->created?->toIso8601String();
            $payload['modified'] = $row->modified?->toIso8601String();
        }

        return $payload;
    }

    protected function assertCanView(KmpIdentityInterface $identity, object $entity): void
    {
        if (!$identity->can('view', $entity)) {
            throw new ForbiddenException('Not authorized to view officer roster');
        }
    }
}

