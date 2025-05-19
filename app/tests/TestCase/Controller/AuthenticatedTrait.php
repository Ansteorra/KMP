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
        $member = $membersTable->get(1);
        $member->warrantableReview();
        // Save without triggering beforeSave to avoid recursion
        $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);
        $this->session([
            'Auth' => $member,
        ]);
    }
}
