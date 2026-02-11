<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Services\OfficerManagerInterface;
use Officers\Model\Entity\Officer;

/**
 * Officers\Services\DefaultOfficerManager Test Case
 *
 * Tests the DefaultOfficerManager service including the new
 * recalculateOfficersForOffice functionality for office configuration changes.
 *
 * @uses \Officers\Services\DefaultOfficerManager
 */
class DefaultOfficerManagerTest extends BaseTestCase
{
    /**
     * Service under test
     *
     * @var \Officers\Services\OfficerManagerInterface
     */
    protected $officerManager;

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
     * Members table
     *
     * @var \App\Model\Table\MembersTable
     */
    protected $Members;

    /**
     * Branches table
     *
     * @var \App\Model\Table\BranchesTable
     */
    protected $Branches;

    /**
     * MemberRoles table
     *
     * @var \App\Model\Table\MemberRolesTable
     */
    protected $MemberRoles;

    /**
     * Roles table
     *
     * @var \App\Model\Table\RolesTable
     */
    protected $Roles;

    /**
     * Test branch entity
     *
     * @var \App\Model\Entity\Branch
     */
    protected $testBranch;

    /**
     * Test office ID for testing
     */
    private const TEST_OFFICE_ID = 1;

    /**
     * Test member ID for testing
     */
    private const TEST_MEMBER_ID = self::ADMIN_MEMBER_ID;

    /**
     * Test updater ID
     */
    private const TEST_UPDATER_ID = self::ADMIN_MEMBER_ID;

    /**
     * Test branch ID
     *
     * @var int
     */
    private static int $testBranchId;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Get table instances
        $this->Offices = TableRegistry::getTableLocator()->get('Officers.Offices');
        $this->Officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $this->Members = TableRegistry::getTableLocator()->get('Members');
        $this->Branches = TableRegistry::getTableLocator()->get('Branches');
        $this->MemberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
        $this->Roles = TableRegistry::getTableLocator()->get('Roles');

        // Create test branch with unique name
        $branch = $this->Branches->newEntity([
            'name' => 'Test Kingdom ' . uniqid(),
            'branch_type' => 'kingdom',
            'location' => 'Test Location',
            'parent_id' => null,
        ]);
        $this->testBranch = $this->Branches->save($branch);
        if (!$this->testBranch) {
            throw new \RuntimeException('Failed to create test branch: ' . json_encode($branch->getErrors()));
        }

        // Manually create service for testing
        $activeWindowManager = new \App\Services\ActiveWindowManager\DefaultActiveWindowManager();
        $warrantManager = new \App\Services\WarrantManager\DefaultWarrantManager($activeWindowManager);

        // Create a partial mock that doesn't actually queue mail (to avoid Queue plugin config issues)
        $this->officerManager = $this->getMockBuilder(\Officers\Services\DefaultOfficerManager::class)
            ->setConstructorArgs([$activeWindowManager, $warrantManager])
            ->onlyMethods(['queueMail'])
            ->getMock();

        // Make queueMail do nothing (it returns void)
        $this->officerManager->method('queueMail')->willReturnCallback(function () {
            // Do nothing - mock the mail queue
        });
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->officerManager);
        unset($this->Offices);
        unset($this->Officers);
        unset($this->Members);
        unset($this->Branches);
        unset($this->MemberRoles);
        unset($this->Roles);
        unset($this->testBranch);

        parent::tearDown();
    }



    /**
     * Test recalculateOfficersForOffice with reports_to_id change
     *
     * When an office's reports_to_id changes, all current and upcoming officers
     * should have their reports_to_office_id and reports_to_branch_id recalculated.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeWithReportsToChange(): void
    {
        // Create a test office that reports to another office
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Local Officer ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1, // Initial reports-to
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current officer for this office
        $currentOfficer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'email_address' => '',
        ]);
        $savedCurrent = $this->Officers->save($currentOfficer);
        $this->assertNotFalse($savedCurrent, 'Current officer should save successfully');

        // Create an upcoming officer for this office
        $upcomingOfficer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->addMonths(1),
            'expires_on' => DateTime::now()->addMonths(13),
            'status' => Officer::UPCOMING_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'email_address' => '',
        ]);
        $savedUpcoming = $this->Officers->save($upcomingOfficer);
        $this->assertNotFalse($savedUpcoming, 'Upcoming officer should save successfully');

        // Change the office's reports_to_id
        $office->reports_to_id = 2;
        $this->Offices->save($office);

        // Recalculate officers
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(2, $result->data['updated_count'], 'Should update 2 officers');
        $this->assertEquals(1, $result->data['current_count'], 'Should update 1 current officer');
        $this->assertEquals(1, $result->data['upcoming_count'], 'Should update 1 upcoming officer');

        // Verify officers were updated
        $updatedCurrent = $this->Officers->get($currentOfficer->id);
        $this->assertEquals(2, $updatedCurrent->reports_to_office_id, 'Current officer should report to new office');

        $updatedUpcoming = $this->Officers->get($upcomingOfficer->id);
        $this->assertEquals(2, $updatedUpcoming->reports_to_office_id, 'Upcoming officer should report to new office');
    }

    /**
     * Test recalculateOfficersForOffice with deputy_to_id change
     *
     * When an office's deputy_to_id changes, all deputy officers should have
     * their deputy_to and reports_to relationships recalculated.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeWithDeputyToChange(): void
    {
        // Create a test office that is a deputy to another office
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Deputy Officer ' . time(),
            'department_id' => 1,
            'deputy_to_id' => 1, // Initial deputy-to
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current deputy officer
        $deputyOfficer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'deputy_description' => 'Test Deputy',
            'deputy_to_office_id' => 1,
            'deputy_to_branch_id' => $this->testBranch->id,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'email_address' => '',
        ]);
        $savedDeputy = $this->Officers->save($deputyOfficer);
        $this->assertNotFalse($savedDeputy, 'Deputy officer should save successfully');

        // Change the office's deputy_to_id
        $office->deputy_to_id = 2;
        $this->Offices->save($office);

        // Recalculate officers
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(1, $result->data['updated_count'], 'Should update 1 officer');

        // Verify deputy relationships were updated
        $updatedDeputy = $this->Officers->get($deputyOfficer->id);
        $this->assertEquals(2, $updatedDeputy->deputy_to_office_id, 'Deputy should report to new office');
        $this->assertEquals(2, $updatedDeputy->reports_to_office_id, 'Deputy reports-to should match deputy-to');
    }

    /**
     * Test recalculateOfficersForOffice with grants_role_id added
     *
     * When an office starts granting a role, all current and upcoming officers
     * should receive the new member role.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeWithRoleAdded(): void
    {
        // Create a test office without a role
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Officer No Role ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'grants_role_id' => null, // No role initially
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create a current officer without a role
        $officer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'granted_member_role_id' => null,
            'email_address' => '',
        ]);
        $savedOfficer = $this->Officers->save($officer);
        $this->assertNotFalse($savedOfficer, 'Officer should save successfully');

        // Add a role to the office
        $office->grants_role_id = self::ADMIN_ROLE_ID;
        $this->Offices->save($office);

        // Recalculate officers
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(1, $result->data['updated_count'], 'Should update 1 officer');

        // Verify officer now has a role
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertNotNull($updatedOfficer->granted_member_role_id, 'Officer should have a role');

        // Verify the member role was created
        $memberRole = $this->MemberRoles->get($updatedOfficer->granted_member_role_id);
        $this->assertEquals(self::TEST_MEMBER_ID, $memberRole->member_id, 'Role should be for correct member');
        $this->assertEquals(self::ADMIN_ROLE_ID, $memberRole->role_id, 'Role should be correct role');
        $this->assertEquals('Officers.Officers', $memberRole->entity_type, 'Role should reference officer');
        $this->assertEquals($officer->id, $memberRole->entity_id, 'Role should reference correct officer');
    }

    /**
     * Test recalculateOfficersForOffice with grants_role_id removed
     *
     * When an office stops granting a role, all current and upcoming officers
     * should have their member roles ended.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeWithRoleRemoved(): void
    {
        // Create a test office with a role
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Officer With Role ' . time(),
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

        // Create member role for the officer
        $memberRole = $this->MemberRoles->newEmptyEntity();
        $memberRole->member_id = self::TEST_MEMBER_ID;
        $memberRole->role_id = self::ADMIN_ROLE_ID;
        $memberRole->start(DateTime::now()->subDays(30), DateTime::now()->addMonths(6), 0);
        $memberRole->entity_type = 'Officers.Officers';
        $memberRole->approver_id = self::TEST_UPDATER_ID;
        $memberRole->branch_id = $this->testBranch->id;
        $this->MemberRoles->save($memberRole);

        // Create officer with the role
        $officer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'granted_member_role_id' => $memberRole->id,
            'email_address' => '',
        ]);
        $savedOfficer = $this->Officers->save($officer);
        $this->assertNotFalse($savedOfficer, 'Officer should save successfully');

        // Update role entity_id now that we have the officer id
        $memberRole->entity_id = $officer->id;
        $this->MemberRoles->save($memberRole);

        // Remove the role from the office
        $office->grants_role_id = null;
        $this->Offices->save($office);

        // Recalculate officers
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(1, $result->data['updated_count'], 'Should update 1 officer');

        // Store the original role ID before it's cleared
        $originalRoleId = $officer->granted_member_role_id;

        // Verify officer no longer has a role
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertNull($updatedOfficer->granted_member_role_id, 'Officer should not have a role');

        // Verify the member role was ended
        $endedRole = $this->MemberRoles->get($originalRoleId);
        $this->assertNotNull($endedRole->revoker_id, 'Role should have been revoked');
        $this->assertLessThanOrEqual(DateTime::now(), $endedRole->expires_on, 'Role should be expired');
    }

    /**
     * Test recalculateOfficersForOffice with grants_role_id changed
     *
     * When an office changes which role it grants, officers should have
     * the old role ended and a new role created.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeWithRoleChanged(): void
    {
        // Get a different role ID for testing (assume role ID 2 exists)
        $roles = $this->getTableLocator()->get('Roles');
        $alternateRole = $roles->find()->where(['id !=' => self::ADMIN_ROLE_ID])->first();
        $this->assertNotNull($alternateRole, 'Need an alternate role for testing');

        // Create a test office with initial role
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Officer Role Change ' . time(),
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

        // Create member role for the officer
        $memberRole = $this->MemberRoles->newEmptyEntity();
        $memberRole->member_id = self::TEST_MEMBER_ID;
        $memberRole->role_id = self::ADMIN_ROLE_ID;
        $memberRole->start(DateTime::now()->subDays(30), DateTime::now()->addMonths(6), 0);
        $memberRole->entity_type = 'Officers.Officers';
        $memberRole->approver_id = self::TEST_UPDATER_ID;
        $memberRole->branch_id = $this->testBranch->id;
        $this->MemberRoles->save($memberRole);

        // Create officer with the initial role
        $officer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'granted_member_role_id' => $memberRole->id,
            'email_address' => '',
        ]);
        $savedOfficer = $this->Officers->save($officer);
        $this->assertNotFalse($savedOfficer, 'Officer should save successfully');

        // Update role entity_id now that we have the officer id
        $memberRole->entity_id = $officer->id;
        $this->MemberRoles->save($memberRole);

        $oldRoleId = $memberRole->id;

        // Change the role on the office
        $office->grants_role_id = $alternateRole->id;
        $this->Offices->save($office);

        // Recalculate officers
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(1, $result->data['updated_count'], 'Should update 1 officer');

        // Verify officer has a different role
        $updatedOfficer = $this->Officers->get($officer->id);
        $this->assertNotNull($updatedOfficer->granted_member_role_id, 'Officer should have a role');
        $this->assertNotEquals($oldRoleId, $updatedOfficer->granted_member_role_id, 'Officer should have a new role');

        // Verify old role was ended
        $oldRole = $this->MemberRoles->get($oldRoleId);
        $this->assertNotNull($oldRole->revoker_id, 'Old role should have been revoked');
        $this->assertLessThanOrEqual(DateTime::now(), $oldRole->expires_on, 'Old role should be expired');

        // Verify new role was created with correct role_id
        $newRole = $this->MemberRoles->get($updatedOfficer->granted_member_role_id);
        $this->assertEquals($alternateRole->id, $newRole->role_id, 'New role should have correct role_id');
        $this->assertGreaterThan(DateTime::now(), $newRole->expires_on, 'New role should not be expired');
    }

    /**
     * Test recalculateOfficersForOffice excludes expired officers
     *
     * Officers with expired status should not be recalculated (preserve history).
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeExcludesExpiredOfficers(): void
    {
        // Create a test office
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Officer Expired ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Create an expired officer
        $expiredOfficer = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::TEST_UPDATER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subMonths(24),
            'expires_on' => DateTime::now()->subMonths(12),
            'status' => Officer::EXPIRED_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
            'email_address' => '',
        ]);
        $savedOfficer = $this->Officers->save($expiredOfficer);
        $this->assertNotFalse($savedOfficer, 'Expired officer should save successfully');
        $this->assertNotNull($expiredOfficer->id, 'Expired officer should have an ID');

        // Change the office's reports_to_id
        $office->reports_to_id = 2;
        $this->Offices->save($office);

        // Recalculate officers
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success but no updates
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(0, $result->data['updated_count'], 'Should not update expired officers');

        // Verify expired officer was NOT updated
        $unchangedOfficer = $this->Officers->get($expiredOfficer->id);
        $this->assertEquals(1, $unchangedOfficer->reports_to_office_id, 'Expired officer should keep old reports-to');
    }

    /**
     * Test recalculateOfficersForOffice with no officers
     *
     * When an office has no current or upcoming officers, recalculation
     * should succeed with zero updates.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeWithNoOfficers(): void
    {
        // Create a test office with no officers
        $office = $this->Offices->newEntity([
            'department_id' => 1,
            'name' => 'Test Officer No Officers ' . time(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'branch_types' => ['kingdom', 'principality', 'barony'],
        ]);
        $this->Offices->save($office);

        // Recalculate officers (there are none)
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::TEST_UPDATER_ID
        );

        // Assert success with zero updates
        $this->assertTrue($result->success, 'Recalculation should succeed');
        $this->assertEquals(0, $result->data['updated_count'], 'Should update 0 officers');
        $this->assertEquals(0, $result->data['current_count'], 'Should update 0 current officers');
        $this->assertEquals(0, $result->data['upcoming_count'], 'Should update 0 upcoming officers');
    }

    /**
     * Test recalculateOfficersForOffice fail-fast on officer save failure
     *
     * When an officer save fails, the method should return error immediately
     * without processing remaining officers.
     *
     * @return void
     */
    public function testRecalculateOfficersForOfficeFailFastOnSaveFailure(): void
    {
        // Create office for recalculation
        $office = $this->Offices->newEntity([
            'name' => 'Test FailFast ' . uniqid(),
            'department_id' => 1,
            'reports_to_id' => 1,
            'term_length' => 12,
            'requires_warrant' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => false,
            'applicable_branch_types' => '["kingdom"]',
        ]);
        $this->Offices->saveOrFail($office);

        // Create two current officers
        $officer1 = $this->Officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
        ]);
        $this->Officers->saveOrFail($officer1);

        $officer2 = $this->Officers->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => $this->testBranch->id,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(15),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => 1,
            'reports_to_branch_id' => $this->testBranch->id,
        ]);
        $this->Officers->saveOrFail($officer2);

        // Attach a beforeSave listener that forces save failure on officer updates
        $this->Officers->getEventManager()->on(
            'Model.beforeSave',
            function ($event, $entity) {
                if ($entity->isDirty('reports_to_office_id')) {
                    return false;
                }
            }
        );

        // Change reports_to_id on the office to trigger recalculation
        $office->reports_to_id = 2;
        $this->Offices->saveOrFail($office);

        // Attempt recalculation — should fail fast on first officer save
        $result = $this->officerManager->recalculateOfficersForOffice(
            $office->id,
            self::ADMIN_MEMBER_ID
        );

        $this->assertFalse($result->success, 'Recalculation should fail when officer save fails');
        $this->assertStringContainsString('Failed to update officer', $result->reason);
    }

    // ============================================================================
    // Tests for assign() method - Using seed data
    // ============================================================================

    /**
     * Test successful assignment of officer to office without warrant requirement
     * 
     * Uses existing seed data:
     * - Member ID 1 (Admin von Admin)
     * - Office ID 1 (Crown - no warrant required)
     * - Creates a test branch since branches table is empty in seed
     */
    public function testAssignOfficerSuccessfully(): void
    {
        // Create a test branch (branches table is empty in seed data)
        $Branches = $this->getTableLocator()->get('Branches');
        $branch = $Branches->newEntity([
            'name' => 'Kingdom of Test ' . uniqid(),
            'location' => 'Test Kingdom',
            'parent_id' => null,
        ]);
        $Branches->saveOrFail($branch);

        // Use existing members and office from seed data
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $approver = $this->Members->get(self::ADMIN_MEMBER_ID);
        $office = $this->Offices->get(1); // Crown - no warrant required

        // Assign the officer
        $startOn = DateTime::now();
        $endOn = DateTime::now()->addMonths(12); // 12 months from now

        $result = $this->officerManager->assign(
            $office->id,
            $member->id,
            $branch->id,
            $startOn,
            $endOn,
            null, // no deputy description
            $approver->id,
            'officer@example.com'
        );

        // Verify success
        $this->assertTrue($result->success, 'Assignment should succeed');

        // Verify officer was created
        $officers = $this->Officers->find()
            ->where([
                'office_id' => $office->id,
                'member_id' => $member->id,
                'status IN' => ['current', 'upcoming'],
            ])
            ->orderBy(['created' => 'DESC'])
            ->all();
        $this->assertGreaterThan(0, count($officers), 'Should have created officer record');

        $officer = $officers->first();
        $this->assertEquals($branch->id, $officer->branch_id);
        $this->assertEquals($approver->id, $officer->approver_id);
        $this->assertEquals('officer@example.com', $officer->email_address);
        $this->assertNotNull($officer->approval_date);
    }

    /**
     * Test assignment to office requiring warrant for non-warrantable member fails
     * 
     * NOTE: This test is marked incomplete because `warrantable` is a virtual calculated
     * field based on multiple factors (background check, membership status, etc.), not a
     * simple database column that can be directly set. To properly test this scenario,
     * we would need to set up all the conditions that make a member non-warrantable
     * (expired background check, expired membership, etc.).
     * 
     * The validation logic IS present in DefaultOfficerManager::assign() lines 228-233,
     * which checks if office requires_warrant and member is not warrantable.
     */
    public function testAssignNonWarrantableMemberToWarrantRequiredOfficeFails(): void
    {
        // Set admin member as non-warrantable via direct SQL (bypasses beforeSave recalculation)
        $this->Members->getConnection()->execute(
            'UPDATE members SET warrantable = 0 WHERE id = ?',
            [self::ADMIN_MEMBER_ID]
        );

        // Office 2 (Kingdom Earl Marshal) requires_warrant = true in seed data
        $warrantOffice = $this->Offices->get(2);
        $this->assertTrue((bool)$warrantOffice->requires_warrant, 'Office should require warrant');

        // Attempt to assign non-warrantable member to warrant-required office
        $result = $this->officerManager->assign(
            $warrantOffice->id,
            self::ADMIN_MEMBER_ID,
            $this->testBranch->id,
            DateTime::now(),
            DateTime::now()->addMonths(12),
            null,
            self::ADMIN_MEMBER_ID,
            'test@example.com'
        );

        $this->assertFalse($result->success, 'Assignment should fail for non-warrantable member');
        $this->assertStringContainsString('not warrantable', $result->reason);
    }

    /**
     * Test assignment calculates end date from term_length when not provided
     * 
     * Uses Kingdom Earl Marshal (office ID 2) which has a 24-month term
     */
    public function testAssignCalculatesEndDateFromTermLength(): void
    {
        // Create a test branch (branches table is empty in seed data)
        $Branches = $this->getTableLocator()->get('Branches');
        $branch = $Branches->newEntity([
            'name' => 'Kingdom of Test 3 ' . uniqid(),
            'location' => 'Test Kingdom 3',
            'parent_id' => null,
        ]);
        $Branches->saveOrFail($branch);

        // Use existing seed data
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $approver = $this->Members->get(self::ADMIN_MEMBER_ID);
        $office = $this->Offices->get(2); // Kingdom Earl Marshal with 24-month term

        // Assign officer with no end date
        $startOn = DateTime::now();
        $result = $this->officerManager->assign(
            $office->id,
            $member->id,
            $branch->id,
            $startOn,
            null, // Let it calculate from term_length
            null,
            $approver->id,
            'officer@example.com'
        );

        // Verify success
        $this->assertTrue($result->success, 'Assignment should succeed');

        // Verify officer has calculated end date
        $officer = $this->Officers->find()
            ->where([
                'office_id' => $office->id,
                'member_id' => $member->id,
            ])
            ->orderBy(['created' => 'DESC'])
            ->first();

        $this->assertNotNull($officer);
        $this->assertNotNull($officer->expires_on);

        // The end date should be approximately term_length months from start
        // Office 2 has term_length = 24 months
        $expectedEnd = $startOn->addMonths($office->term_length);
        $daysDiff = abs($expectedEnd->diffInDays($officer->expires_on));
        $this->assertLessThanOrEqual(1, $daysDiff, 'End date should be calculated from term_length');
    }

    // ============================================================================
    // Tests for release() method - Using seed data
    // ============================================================================

    /**
     * Test successful release of officer
     * 
     * First assigns an officer, then releases them
     */
    public function testReleaseOfficerSuccessfully(): void
    {
        // Create a test branch (branches table is empty in seed data)
        $Branches = $this->getTableLocator()->get('Branches');
        $branch = $Branches->newEntity([
            'name' => 'Kingdom of Test 4 ' . uniqid(),
            'location' => 'Test Kingdom 4',
            'parent_id' => null,
        ]);
        $Branches->saveOrFail($branch);

        // Setup: Create an officer to release
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $approver = $this->Members->get(self::ADMIN_MEMBER_ID);
        $office = $this->Offices->get(2); // Kingdom Earl Marshal

        // First assign the officer
        $startOn = DateTime::now();
        $endOn = DateTime::now()->addMonths(24);
        $assignResult = $this->officerManager->assign(
            $office->id,
            $member->id,
            $branch->id,
            $startOn,
            $endOn,
            null,
            $approver->id,
            'release@example.com'
        );
        $this->assertTrue($assignResult->success, 'Assignment should succeed');

        // Get the created officer
        $officer = $this->Officers->find()
            ->where([
                'office_id' => $office->id,
                'member_id' => $member->id,
                'status IN' => ['current', 'upcoming'],
            ])
            ->orderBy(['created' => 'DESC'])
            ->first();
        $this->assertNotNull($officer, 'Officer should have been created');

        // Now release the officer
        $revokedOn = DateTime::now();
        $result = $this->officerManager->release(
            $officer->id,
            $approver->id,
            $revokedOn,
            'Resigned from position',
            Officer::RELEASED_STATUS
        );

        // Verify success
        $this->assertTrue($result->success, 'Release should succeed');

        // Verify officer status was updated
        $releasedOfficer = $this->Officers->get($officer->id);
        $this->assertEquals(Officer::RELEASED_STATUS, $releasedOfficer->status);
        $this->assertNotNull($releasedOfficer->expires_on);
        $this->assertEquals($approver->id, $releasedOfficer->revoker_id);
        $this->assertEquals('Resigned from position', $releasedOfficer->revoked_reason);
    }

    /**
     * Test release with custom status (REPLACED_STATUS)
     * 
     * Assigns an officer then releases with "replaced" status
     */
    public function testReleaseOfficerWithReplacedStatus(): void
    {
        // Create a test branch (branches table is empty in seed data)
        $Branches = $this->getTableLocator()->get('Branches');
        $branch = $Branches->newEntity([
            'name' => 'Kingdom of Test 5 ' . uniqid(),
            'location' => 'Test Kingdom 5',
            'parent_id' => null,
        ]);
        $Branches->saveOrFail($branch);

        // Setup: Create an officer to replace
        $member = $this->Members->get(self::ADMIN_MEMBER_ID);
        $approver = $this->Members->get(self::ADMIN_MEMBER_ID);
        $office = $this->Offices->get(2); // Kingdom Earl Marshal

        // Assign officer
        $assignResult = $this->officerManager->assign(
            $office->id,
            $member->id,
            $branch->id,
            DateTime::now()->subMonths(3),
            DateTime::now()->addMonths(9),
            null,
            $approver->id,
            'replaced@example.com'
        );
        $this->assertTrue($assignResult->success);

        // Get the officer
        $officer = $this->Officers->find()
            ->where([
                'office_id' => $office->id,
                'member_id' => $member->id,
                'status IN' => ['current', 'upcoming'],
            ])
            ->orderBy(['created' => 'DESC'])
            ->first();

        // Release with REPLACED status
        $revokedOn = DateTime::now();
        $result = $this->officerManager->release(
            $officer->id,
            $approver->id,
            $revokedOn,
            'Replaced by new officer',
            Officer::REPLACED_STATUS
        );

        // Verify success
        $this->assertTrue($result->success, 'Release with replaced status should succeed');

        // Verify officer status is 'Replaced' (the constant value)
        $releasedOfficer = $this->Officers->get($officer->id);
        $this->assertEquals(Officer::REPLACED_STATUS, $releasedOfficer->status);
        $this->assertEquals('Replaced by new officer', $releasedOfficer->revoked_reason);
    }
}
