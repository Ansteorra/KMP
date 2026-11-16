<?php
declare(strict_types=1);

namespace Officers\Services;

use App\Services\UiUpdateProviderInterface;

/** Invalidation metadata is resolved against the active tenant at use time. */
final class UiUpdateProvider implements UiUpdateProviderInterface
{
    /** @inheritDoc */
    public static function definitions(): array
    {
        return [
            'Members' => ['invalidatesScreens' => ['Officers.Officers', 'Officers.Offices']],
            'Branches' => ['invalidatesScreens' => ['Officers.Officers', 'Officers.Offices']],
            'Officers.Offices' => ['controller' => 'Offices',
                'invalidatesScreens' => ['Members', 'Branches', 'Officers.Officers']],
            'Officers.Departments' => ['controller' => 'Departments',
                'invalidatesScreens' => ['Members', 'Branches', 'Officers.Officers', 'Officers.Offices']],
            'Officers.Officers' => [
                'controller' => 'Officers',
                'parents' => ['member_id' => 'Members', 'branch_id' => 'Branches'],
                'changes' => ['officers'],
            ],
        ];
    }
}
