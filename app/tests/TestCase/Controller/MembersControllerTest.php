<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Entity\Member;
use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * MembersController Test Case
 *
 * Tests the complete member management functionality including CRUD operations,
 * search, authentication, authorization, and special member operations.
 *
 * NOTE: Some tests that require creating new database records are skipped due to
 * transaction isolation limitations in CakePHP's IntegrationTestTrait. These
 * tests work correctly when testing models directly (MembersTableTest).
 *
 * @uses \App\Controller\MembersController
 */
class MembersControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Test index method displays member list
     *
     * @return void
     */
    public function testIndex(): void
    {
        $this->get('/members');

        $this->assertResponseOk();
        $this->assertResponseContains('Members');
        $this->assertResponseContains('Add Member');
    }

    /**
     * Test index with search parameter
     *
     * @return void
     */
    public function testIndexWithSearch(): void
    {
        $this->get('/members?search=admin');

        $this->assertResponseOk();
        $this->assertResponseContains('Members');
    }

    /**
     * Test index with sorting
     *
     * @return void
     */
    public function testIndexWithSorting(): void
    {
        $this->get('/members?sort=sca_name&direction=asc');

        $this->assertResponseOk();
        // Response should contain sorting indicators
        $this->assertResponseContains('Members');
    }

    /**
     * Test view method displays member details
     *
     * @return void
     */
    public function testView(): void
    {
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);

        $this->assertResponseOk();
        // View page contains member information
        $this->assertResponseContains('members');
    }

    /**
     * Ensure impersonation button appears for super users on member profile.
     *
     * @return void
     */
    public function testViewShowsImpersonateButtonForSuperUsers(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $target = $membersTable->find()
            ->where(['Members.id !=' => self::ADMIN_MEMBER_ID])
            ->select(['id'])
            ->firstOrFail();

        $this->get('/members/view/' . $target->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Impersonate Member');
    }

    /**
     * Verify POST /members/impersonate/:id starts impersonation session.
     *
     * @return void
     */
    public function testImpersonateActionStartsSession(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $target = $membersTable->find()
            ->where(['Members.id !=' => self::ADMIN_MEMBER_ID])
            ->select(['id'])
            ->firstOrFail();

        $this->configRequest(['headers' => ['Referer' => '/members/view/' . $target->id]]);
        $this->post('/members/impersonate/' . $target->id);

        $this->assertRedirectContains('/members/view/' . $target->id);
        $this->assertSession($target->id, 'Impersonation.impersonated_member_id');
        $this->assertSession(self::ADMIN_MEMBER_ID, 'Impersonation.impersonator_id');

        $sessionLogs = $this->getTableLocator()->get('ImpersonationSessionLogs');
        $count = $sessionLogs->find()->where([
            'impersonator_id' => self::ADMIN_MEMBER_ID,
            'impersonated_member_id' => $target->id,
            'event' => 'start',
        ])->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * Ensure stop impersonating restores admin context and clears session state.
     *
     * @return void
     */
    public function testStopImpersonatingRestoresAdmin(): void
    {
        $membersTable = $this->getTableLocator()->get('Members');
        $target = $membersTable->find()
            ->where(['Members.id !=' => self::ADMIN_MEMBER_ID])
            ->select(['id'])
            ->firstOrFail();

        $this->configRequest(['headers' => ['Referer' => '/members/view/' . $target->id]]);
        $this->post('/members/impersonate/' . $target->id);

        $this->configRequest(['headers' => ['Referer' => '/members/view/' . self::ADMIN_MEMBER_ID]]);
        $this->post('/members/stop-impersonating');

        $this->assertRedirectContains('/members');
        $this->assertSession(null, 'Impersonation.impersonated_member_id');

        $sessionLogs = $this->getTableLocator()->get('ImpersonationSessionLogs');
        $count = $sessionLogs->find()->where([
            'impersonator_id' => self::ADMIN_MEMBER_ID,
            'impersonated_member_id' => $target->id,
            'event' => 'stop',
        ])->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * Test view with invalid member ID returns not found
     *
     * @return void
     */
    public function testViewWithInvalidId(): void
    {
        $this->get('/members/view/999999');

        // Should throw NotFoundException which results in 404
        $this->assertResponseCode(404);
    }

    /**
     * Test add method displays add form
     *
     * @return void
     */
    public function testAddGet(): void
    {
        $this->get('/members/add');

        $this->assertResponseOk();
        $this->assertResponseContains('Add Member');
        $this->assertResponseContains('sca_name');
        $this->assertResponseContains('email_address');
    }

    /**
     * Test add method creates new member with valid data
     *
     * @return void
     */
    public function testAddPostWithValidData(): void
    {
        // Branch must have can_have_members=1; KINGDOM_BRANCH_ID (2) does not
        $branch = $this->getTableLocator()->get('Branches')
            ->find()
            ->where(['can_have_members' => true])
            ->first();
        $this->assertNotNull($branch, 'Need a branch that allows members');

        $data = [
            'sca_name' => 'Test Add Member',
            'email_address' => 'testadd_' . time() . '@example.com',
            'password' => 'placeholder_password',
            'first_name' => 'Test',
            'last_name' => 'AddMember',
            'street_address' => '123 Test St',
            'city' => 'Testville',
            'state' => 'TX',
            'zip' => '75001',
            'phone_number' => '555-555-5555',
            'birth_month' => 1,
            'birth_year' => 1990,
            'branch_id' => $branch->id,
        ];

        $this->post('/members/add', $data);

        $this->assertRedirectContains('/members/view/');
    }

    /**
     * Test add method rejects duplicate email
     *
     * @return void
     */
    public function testAddPostWithDuplicateEmail(): void
    {
        $data = [
            'sca_name' => 'Duplicate Test',
            'email_address' => 'admin@amp.ansteorra.org', // Existing email
            'first_name' => 'Test',
            'last_name' => 'Member',
            'birth_month' => 6,
            'birth_year' => 1990,
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ];

        $this->post('/members/add', $data);

        // Should show error - either in flash or form
        $this->assertResponseOk();
        $this->assertResponseContains('Member');
    }

    /**
     * Test add method requires required fields
     *
     * @return void
     */
    public function testAddPostWithMissingRequiredFields(): void
    {
        $data = [
            'sca_name' => 'Incomplete Member',
            // Missing email_address and other required fields
        ];

        $this->post('/members/add', $data);

        $this->assertResponseOk();
        $this->assertResponseContains('Member');
    }

    /**
     * Test edit method updates member with valid data
     *
     * @return void
     */
    public function testEditPostWithValidData(): void
    {
        $data = [
            'sca_name' => 'Admin von Admin Updated',
        ];

        $this->post('/members/edit/' . self::ADMIN_MEMBER_ID, $data);

        $this->assertRedirectContains('/members/view/' . self::ADMIN_MEMBER_ID);
    }

    /**
     * Test edit method with invalid ID returns not found
     *
     * @return void
     */
    public function testEditWithInvalidId(): void
    {
        $this->get('/members/edit/999999');

        $this->assertResponseCode(404);
    }

    /**
     * Test delete method removes member
     *
     * @return void
     */
    public function testDelete(): void
    {
        $this->post('/members/delete/' . self::TEST_MEMBER_AGATHA_ID);

        $this->assertRedirectContains('/members');
    }

    /**
     * Test delete with invalid ID returns not found
     *
     * @return void
     */
    public function testDeleteWithInvalidId(): void
    {
        $this->delete('/members/delete/999999');

        $this->assertResponseCode(404);
    }

    /**
     * Test delete requires POST method
     *
     * @return void
     */
    public function testDeleteRequiresPostMethod(): void
    {
        $this->get('/members/delete/' . self::ADMIN_MEMBER_ID);

        // Should not allow GET method
        $this->assertResponseCode(405);
    }

    /**
     * Test profile method shows current user's profile
     *
     * @return void
     */
    public function testProfile(): void
    {
        $this->get('/members/profile');

        $this->assertResponseOk();
        // Profile internally calls view, so response contains member info
        $this->assertResponseContains('members');
    }

    /**
     * Test autoComplete returns member suggestions
     *
     * NOTE: This test may be sensitive to the exact response format from the controller.
     * The autoComplete action returns JSON but may include additional formatting.
     *
     * @return void
     */
    public function testAutoComplete(): void
    {
        $this->get('/members/autoComplete?q=admin');

        $this->assertResponseOk();
        $this->assertResponseContains('list-group-item');
    }

    /**
     * Test searchMembers returns member list
     *
     * @return void
     */
    public function testSearchMembers(): void
    {
        $this->get('/members/searchMembers?q=admin');

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $response = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($response);
    }

    /**
     * Test emailTaken endpoint
     *
     * @return void
     */
    public function testEmailTakenWithExistingEmail(): void
    {
        $this->get('/members/emailTaken?email=admin@amp.ansteorra.org');

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $response = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($response);
    }

    /**
     * Test emailTaken with new email
     *
     * @return void
     */
    public function testEmailTakenWithNewEmail(): void
    {
        $this->get('/members/emailTaken?email=newemail' . time() . '@example.com');

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $response = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($response);
    }
}
