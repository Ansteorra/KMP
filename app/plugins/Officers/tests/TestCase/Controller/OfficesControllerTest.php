<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;
use Officers\Services\OfficerManagerInterface;

/**
 * Officers\Controller\OfficesController Test Case
 *
 * Tests the OfficesController including the new transaction-wrapped
 * officer recalculation functionality for office configuration changes.
 *
 * @uses \Officers\Controller\OfficesController
 */
class OfficesControllerTest extends HttpIntegrationTestCase
{

    /**
     * Offices table
     *
     * @var \Officers\Model\Table\OfficesTable
     */
    protected $Offices;

    /**
     * Officers table
     *
     * @var \Officers\Model\Table\OfficersTable
     */
    protected $Officers;

    /**
     * MemberRoles table
     *
     * @var \App\Model\Table\MemberRolesTable
     */
    protected $MemberRoles;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();

        $this->Offices = TableRegistry::getTableLocator()->get('Officers.Offices');
        $this->Officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $this->MemberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Offices);
        unset($this->Officers);
        unset($this->MemberRoles);

        parent::tearDown();
    }

    /**
     * Test edit method with reports_to_id change recalculates officers
     *
     * When an office's reports_to_id changes, all current and upcoming officers
     * should be recalculated in a single transaction.
     *
     * @return void
     */
    public function testEditOfficeWithReportsToChangeRecalculatesOfficers(): void
    {
        // Create a test office
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit Office ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current officer
        $officer = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->Officers->save($officer);

        // Edit the office to change reports_to_id
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'reports_to_id' => 2, // Changed
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert redirect to view
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Assert flash message indicates officers were updated
        $flashMessages = $_SESSION['Flash']['flash'] ?? null;
        $this->assertNotEmpty($flashMessages, 'Should have flash message');
        $this->assertStringContainsString('officer', $flashMessages[0]['message'], 'Flash message should mention officers');

        // Verify officer was updated
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertEquals(2, $updatedOfficer->reports_to_office_id, 'Officer should report to new office');
    }

    /**
     * Test edit method with deputy_to_id change recalculates officers
     *
     * @return void
     */
    public function testEditOfficeWithDeputyToChangeRecalculatesOfficers(): void
    {
        // Create a test deputy office
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit Deputy ' . time(),
            'department_id' => 1,
            'deputy_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a deputy officer
        $officer = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'deputy_description' => 'Test Deputy',
            'deputy_to_office_id' => 1,
            'deputy_to_branch_id' => self::KINGDOM_BRANCH_ID,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->Officers->save($officer);

        // Edit the office to change deputy_to_id
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'deputy_to_id' => 2, // Changed
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert redirect to view
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Verify officer was updated
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertEquals(2, $updatedOfficer->deputy_to_office_id, 'Deputy should report to new office');
        $this->assertEquals(2, $updatedOfficer->reports_to_office_id, 'Reports-to should match deputy-to');
    }

    /**
     * Test edit method with grants_role_id added creates member roles
     *
     * @return void
     */
    public function testEditOfficeWithRoleAddedCreatesMemberRoles(): void
    {
        // Create a test office without a role
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit Add Role ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'grants_role_id' => null,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current officer without a role
        $officer = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
            'granted_member_role_id' => null,
        ]);
        $this->Officers->save($officer);

        // Edit the office to add a role
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'reports_to_id' => $office->reports_to_id,
            'grants_role_id' => self::ADMIN_ROLE_ID, // Added
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert redirect to view
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Verify officer now has a role
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertNotNull($updatedOfficer->granted_member_role_id, 'Officer should have a role');

        // Verify member role was created
        $memberRole = $this->MemberRoles->get($updatedOfficer->granted_member_role_id);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $memberRole->member_id, 'Role should be for correct member');
        $this->assertEquals(self::ADMIN_ROLE_ID, $memberRole->role_id, 'Role should be correct role');
    }

    /**
     * Test edit method with grants_role_id removed ends member roles
     *
     * @return void
     */
    public function testEditOfficeWithRoleRemovedEndsMemberRoles(): void
    {
        // Create a test office with a role
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit Remove Role ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'grants_role_id' => self::ADMIN_ROLE_ID,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a member role
        $memberRole = $this->MemberRoles->newEntity([
            'role_id' => self::ADMIN_ROLE_ID,
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => 'current',
            'approver_id' => self::ADMIN_MEMBER_ID,
            'entity_type' => 'Officers.Officers',
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $memberRole->member_id = self::ADMIN_MEMBER_ID;
        $this->MemberRoles->save($memberRole);

        // Create a current officer with the role
        $officer = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
            'granted_member_role_id' => $memberRole->id,
        ]);
        $this->Officers->save($officer);

        // Set entity_id on the role
        $memberRole->entity_id = $officer->id;
        $this->MemberRoles->save($memberRole);

        // Edit the office to remove the role
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'reports_to_id' => $office->reports_to_id,
            'grants_role_id' => '', // Removed (empty string)
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert redirect to view
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Verify officer no longer has a role
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertNull($updatedOfficer->granted_member_role_id, 'Officer should not have a role');

        // Verify member role was ended
        $endedRole = $this->MemberRoles->get($memberRole->id);
        $this->assertNotEquals('current', $endedRole->status, 'Role should no longer be current');
    }

    /**
     * Test edit method with no officer-impacting changes doesn't recalculate
     *
     * When an office's name or other non-officer fields change, no recalculation
     * should occur and the standard success message should display.
     *
     * @return void
     */
    public function testEditOfficeWithNoOfficerImpactingChangesDoesNotRecalculate(): void
    {
        // Create a test office
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit No Impact ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Edit the office but only change the name
        $data = [
            'name' => 'Test Edit No Impact CHANGED',
            'department_id' => $office->department_id,
            'reports_to_id' => $office->reports_to_id,
            'grants_role_id' => $office->grants_role_id,
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert redirect to view
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Assert flash message is standard success (no officer mention)
        $flashMessages = $_SESSION['Flash']['flash'] ?? null;
        $this->assertNotEmpty($flashMessages, 'Should have flash message');
        $this->assertEquals('The office has been saved.', $flashMessages[0]['message'], 'Should have standard message');
    }

    /**
     * Test edit method transaction rollback on recalculation failure
     *
     * When officer recalculation fails, the entire transaction including
     * office save should be rolled back.
     *
     * @return void
     */
    public function testEditOfficeTransactionRollbackOnRecalculationFailure(): void
    {
        // Mock OfficerManagerInterface to return failure from recalculate
        $this->mockService(OfficerManagerInterface::class, function () {
            $mock = $this->createMock(OfficerManagerInterface::class);
            $mock->method('recalculateOfficersForOffice')
                ->willReturn(new ServiceResult(false, 'Simulated recalculation failure'));
            return $mock;
        });

        // Use existing seed office (ID 1 = Crown) to avoid nested transaction issues
        $office = $this->Offices->get(1);
        $originalReportsToId = $office->reports_to_id;

        // Pick a different reports_to_id to trigger recalculation
        $newReportsToId = ($originalReportsToId === 2) ? 3 : 2;

        // Edit the office to change reports_to_id (triggers recalculation)
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'reports_to_id' => $newReportsToId,
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Should redirect with error
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Flash message should contain the failure reason
        $flashMessages = $_SESSION['Flash']['flash'] ?? null;
        $this->assertNotEmpty($flashMessages, 'Should have flash message');
        $this->assertStringContainsString('Simulated recalculation failure', $flashMessages[0]['message']);

        // Office should NOT have been changed (transaction rolled back)
        $unchangedOffice = $this->Offices->get($office->id);
        $this->assertEquals($originalReportsToId, $unchangedOffice->reports_to_id, 'Office should be unchanged after rollback');
    }

    /**
     * Test edit method shows current and upcoming officer counts
     *
     * Flash message should distinguish between current and upcoming officers updated.
     *
     * @return void
     */
    public function testEditOfficeShowsCurrentAndUpcomingOfficerCounts(): void
    {
        // Create a test office
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit Counts ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current officer
        $currentOfficer = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->Officers->save($currentOfficer);

        // Create an upcoming officer
        $upcomingOfficer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->addMonths(1),
            'expires_on' => DateTime::now()->addMonths(13),
            'status' => Officer::UPCOMING_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->Officers->save($upcomingOfficer);

        // Edit the office to change reports_to_id
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'reports_to_id' => 2, // Changed
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => $office->branch_types,
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert flash message mentions both current and upcoming
        $flashMessages = $_SESSION['Flash']['flash'] ?? null;
        $this->assertNotEmpty($flashMessages, 'Should have flash message');
        $this->assertStringContainsString('current', $flashMessages[0]['message'], 'Should mention current officers');
        $this->assertStringContainsString('upcoming', $flashMessages[0]['message'], 'Should mention upcoming officers');
    }

    /**
     * Test edit method with validation error doesn't start transaction
     *
     * When office validation fails (e.g., empty branch_types), no transaction
     * should be started and no officers should be affected.
     *
     * @return void
     */
    public function testEditOfficeValidationErrorDoesNotStartTransaction(): void
    {
        // Create a test office
        $office = $this->Offices->newEntity([
            'name' => 'Test Edit Validation ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current officer
        $officer = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => self::KINGDOM_BRANCH_ID,
        ]);
        $this->Officers->save($officer);

        // Edit the office with invalid data (empty branch_types)
        $data = [
            'name' => $office->name,
            'department_id' => $office->department_id,
            'reports_to_id' => 2,
            'term_length' => $office->term_length,
            'requires_warrant' => $office->requires_warrant,
            'can_skip_report' => $office->can_skip_report,
            'only_one_per_branch' => $office->only_one_per_branch,
            'branch_types' => [], // Invalid: empty
        ];

        $this->post("/officers/offices/edit/{$office->id}", $data);

        // Assert redirect to view (error handling)
        $this->assertRedirect(['controller' => 'Offices', 'action' => 'view', $office->id]);

        // Verify office was NOT updated
        $unchangedOffice = $this->Offices->get($office->id);
        $this->assertEquals(1, $unchangedOffice->reports_to_id, 'Office should not be changed');

        // Verify officer was NOT updated
        $unchangedOfficer = $this->Officers->get($officer->id);
        $this->assertEquals(1, $unchangedOfficer->reports_to_office_id, 'Officer should not be changed');
    }
}
