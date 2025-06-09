<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

require_once __DIR__ . '/Lib/SeedHelpers.php'; // Added

/**
 * Roles seed.
 */
class InitWarrantsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        $adminMemberId = SeedHelpers::getMemberId('admin@test.com'); // Was 1

        $rosters = [
            [
                // 'id' => 1, // Removed
                'name' => 'System Admin Warrant Set',
                'approvals_required' => 1,
                'approval_count' => 1,
                'created_by' => $adminMemberId,
                'status' => 'Approved',
                'created' => DateTime::now(),
            ],
        ];

        // Create a starter period startng today and ending in 6 months.
        $today = new DateTime();
        $sixMonthsFromNow = (clone $today)->modify('+6 months')->format('Y-m-d H:i:s');
        $periods = [
            [
                'start_date' => $today->format('Y-m-d H:i:s'),
                'end_date' => $sixMonthsFromNow,
                'created_by' => $adminMemberId,
                'created' => DateTime::now(),
            ],
        ];

        // Warrants and Approvals will be handled in the run() method after rosters are created.

        $permissions = [
            [
                'name' => 'Can View Warrants',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $adminMemberId, // Was '1'
            ],
            [
                'name' => 'Can Manage Warrants',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $adminMemberId, // Was '1'
            ],
            [
                'name' => 'Can View Branches',
                'require_active_membership' => 1,
                'require_active_background_check' => 0,
                'require_min_age' => 0,
                'is_system' => 1,
                'is_super_user' => 0,
                'requires_warrant' => 1,
                'created' => DateTime::now(),
                'created_by' => $adminMemberId, // Was '1'
            ],
        ];

        return [
            'warrant_rosters' => $rosters,
            'permissions' => $permissions,
            'warrant_periods' => $periods,
        ];
    }

    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $adminMemberId = SeedHelpers::getMemberId('admin@test.com');
        $adminRoleId = SeedHelpers::getRoleId('Admin'); // Assuming 'Admin' role exists

        $rosterData = $this->getData()["warrant_rosters"];
        $rosterTable = $this->table('warrant_rosters');
        $rosterTable->insert($rosterData)->save();

        $periodData = $this->getData()["warrant_periods"];
        $periodTable = $this->table('warrant_periods');
        $periodTable->insert($periodData)->save();
        // Ensure the System Admin Warrant Set exists

        $warrantRostersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('WarrantRosters');
        $systemAdminRoster = $warrantRostersTable->find()->where(['name' => 'System Admin Warrant Set'])->firstOrFail();
        $systemAdminRosterId = $systemAdminRoster->id;

        // Ensure the Admin member role exists or create it before creating the warrant
        $memberRolesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('MemberRoles');
        $adminMemberRole = $memberRolesTable->find()->where(['member_id' => $adminMemberId, 'role_id' => $adminRoleId])->first();
        if (!$adminMemberRole) {
            $adminMemberRole = $memberRolesTable->find()->where(['member_id' => $adminMemberId, 'role_id' => $adminRoleId])->firstOrFail();
        }
        $adminMemberRoleId = $adminMemberRole->id;

        $warrantData = [
            [
                'name' => 'System Admin Warrant',
                'member_id' => $adminMemberId,
                'warrant_roster_id' => $systemAdminRosterId,
                'entity_type' => 'Direct Grant',
                'entity_id' => -1,
                'member_role_id' => $adminMemberRoleId,
                'expires_on' => '2100-10-10 00:00:00',
                'start_on' => '2020-01-01 00:00:00',
                'approved_date' => '2020-01-01 00:00:00',
                'status' => 'Current',
                'revoked_reason' => NULL,
                'revoker_id' => NULL,
                'created_by' => $adminMemberId,
                'created' => DateTime::now(),
            ],
        ];
        $warrantTable = $this->table('warrants');
        $warrantTable->insert($warrantData)->save();

        $approvalData = [
            [
                'warrant_roster_id' => $systemAdminRosterId,
                'approver_id' => $adminMemberId,
                'approved_on' => DateTime::now(),
            ],
        ];
        $approvalTable = $this->table('warrant_roster_approvals');
        $approvalTable->insert($approvalData)->save();

        $permissionData = $this->getData()["permissions"];
        $permissionTable = $this->table('permissions');
        $permissionTable->insert($permissionData)->save();
    }
}