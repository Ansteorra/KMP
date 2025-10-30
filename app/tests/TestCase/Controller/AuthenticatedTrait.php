<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;

/**
 * App\Controller\AppSettingsController Test Case
 *
 * @uses \App\Controller\AppSettingsController
 */

trait AuthenticatedTrait
{
    use IntegrationTestTrait;

    //before tests do the following
    // 1. Create a member with the required permissions

    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $membersTable = $this->getTableLocator()->get('Members');
        // Look up by email instead of hardcoded ID to work with auto-increment fixtures
        $member = $membersTable->findByEmailAddress('admin@test.com')->firstOrFail();
        $member->warrantableReview();

        // For tests, manually load the super user permission to enable authorization
        // This simulates what happens in production when permissions are loaded dynamically
        $permissionsTable = $this->getTableLocator()->get('Permissions');
        $superUserPermission = $permissionsTable->findByName('Is Super User')->first();
        if ($superUserPermission) {
            // Manually set permissions on the member entity for testing
            $member->set('permissions', [$superUserPermission]);
        }

        // Save without triggering beforeSave to avoid recursion
        $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);
        $this->session([
            'Auth' => $member,
        ]);
    }
}
