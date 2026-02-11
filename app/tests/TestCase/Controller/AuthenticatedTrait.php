<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;

/**
 * AuthenticatedTrait
 *
 * @deprecated Use TestAuthenticationHelper (via HttpIntegrationTestCase) instead.
 *   This trait writes to the database and is incompatible with transaction rollback.
 *   Extend HttpIntegrationTestCase and call $this->authenticateAsSuperUser() instead.
 * @see \App\Test\TestCase\TestAuthenticationHelper
 * @see \App\Test\TestCase\Support\HttpIntegrationTestCase
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
        $member = $membersTable->findByEmailAddress('admin@amp.ansteorra.org')->firstOrFail();
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
