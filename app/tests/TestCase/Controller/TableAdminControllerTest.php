<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\ORM\TableRegistry;

class TableAdminControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    public function testIndexAccessibleToSuperUser(): void
    {
        $this->authenticateAsSuperUser();

        $this->get('/table-admin');

        $this->assertResponseOk();
        $this->assertResponseContains('Table Admin');
    }

    public function testIndexDeniedForNonSuperUser(): void
    {
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->find()
            ->where(['email_address NOT IN' => ['admin@amp.ansteorra.org', 'admin@test.com']])
            ->first();

        if ($member === null) {
            $this->markTestSkipped('No non-super-user fixture member available.');
        }

        $this->session(['Auth' => $member]);

        $this->get('/table-admin');

        $this->assertResponseCode(302);
    }

    public function testPostSqlAsSuperUserShowsResults(): void
    {
        $this->authenticateAsSuperUser();

        $this->post('/table-admin', [
            'sql_query' => 'SELECT 1 AS one',
            'selected_table' => '',
            'selected_page' => 1,
            'selected_limit' => 50,
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Returned 1 row');
        $this->assertResponseContains('one');
    }

    public function testMutationQueryRequiresConfirmation(): void
    {
        $this->authenticateAsSuperUser();

        $this->post('/table-admin', [
            'sql_query' => 'UPDATE app_settings SET name = name WHERE 1=0',
            'selected_table' => '',
            'selected_page' => 1,
            'selected_limit' => 50,
            'confirm_mutation' => '0',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Confirmation required before running UPDATE statements.');
    }

    public function testMutationQueryRunsInTransactionWhenConfirmed(): void
    {
        $this->authenticateAsSuperUser();

        $this->post('/table-admin', [
            'sql_query' => 'UPDATE app_settings SET name = name WHERE 1=0',
            'selected_table' => '',
            'selected_page' => 1,
            'selected_limit' => 50,
            'confirm_mutation' => '1',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('UPDATE was wrapped in a transaction.');
    }

    public function testTruncateQueryRequiresConfirmation(): void
    {
        $this->authenticateAsSuperUser();

        $this->post('/table-admin', [
            'sql_query' => 'TRUNCATE app_settings',
            'selected_table' => '',
            'selected_page' => 1,
            'selected_limit' => 50,
            'confirm_mutation' => '0',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Confirmation required before running TRUNCATE statements.');
    }
}
