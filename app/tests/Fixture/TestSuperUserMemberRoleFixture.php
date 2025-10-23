<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\I18n\DateTime;

/**
 * TestSuperUserMemberRoleFixture
 * 
 * Assigns the TestSuperUser role to the test super user member.
 * This completes the permission chain: Member -> Role -> Permission
 */
class TestSuperUserMemberRoleFixture extends BaseTestFixture
{
    /**
     * The table this fixture is responsible for
     *
     * @var string
     */
    public string $table = 'member_roles';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        // Assign TestSuperUser role to test super user member
        $this->records = [
            [
                'member_id' => 2, // Test super user (after admin)
                'role_id' => 2, // TestSuperUser role
                'expires_on' => null,
                'start_on' => DateTime::now(),
                'approver_id' => 1, // Admin
                'entity_type' => 'Direct Grant',
                'entity_id' => null,
                'created' => DateTime::now(),
                'created_by' => 1,
            ]
        ];
        parent::init();
    }
}
