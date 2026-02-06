<?php

declare(strict_types=1);

namespace Officers\Services\Api;

use Cake\ORM\TableRegistry;

/**
 * Provides current officer data for branch API detail responses.
 */
class OfficersBranchApiDataProvider
{
    /**
     * Return current officers for a branch entity.
     *
     * @param string $controller Controller name
     * @param string $action Action name
     * @param mixed $entity Branch entity (must have id and type)
     * @return array{officers: array}
     */
    public static function provide(string $controller, string $action, mixed $entity): array
    {
        $officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');

        $officers = $officersTable->find('current')
            ->contain([
                'Members' => fn($q) => $q->select(['id', 'sca_name', 'public_id']),
                'Offices' => fn($q) => $q->select(['id', 'name']),
                'Offices.Departments' => fn($q) => $q->select(['id', 'name']),
            ])
            ->where(['Officers.branch_id' => $entity->id])
            ->select([
                'Officers.id',
                'Officers.office_id',
                'Officers.member_id',
                'Officers.start_on',
                'Officers.expires_on',
                'Officers.email_address',
            ])
            ->orderBy(['Offices.name' => 'ASC'])
            ->all();

        $data = [];
        foreach ($officers as $officer) {
            $data[] = [
                'office' => $officer->office->name ?? null,
                'department' => $officer->office->department->name ?? null,
                'member' => [
                    'id' => $officer->member->public_id ?? null,
                    'sca_name' => $officer->member->sca_name ?? null,
                ],
                'email_address' => $officer->email_address ?? null,
                'start_on' => $officer->start_on?->toIso8601String(),
                'expires_on' => $officer->expires_on?->toIso8601String(),
            ];
        }

        return ['officers' => $data];
    }
}
