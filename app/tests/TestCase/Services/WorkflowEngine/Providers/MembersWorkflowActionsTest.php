<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use App\Model\Entity\Member;
use App\Services\MemberAuthenticationService;
use App\Services\WorkflowEngine\Providers\MembersWorkflowActions;
use App\Services\WorkflowEngine\Providers\MembersWorkflowConditions;
use App\Services\WorkflowEngine\Providers\MembersWorkflowProvider;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use App\Test\TestCase\BaseTestCase;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Tests for Members workflow actions, conditions, and provider registration.
 */
class MembersWorkflowActionsTest extends BaseTestCase
{
    private MembersWorkflowActions $actions;
    private MembersWorkflowConditions $conditions;
    private $membersTable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actions = new MembersWorkflowActions();
        $this->conditions = new MembersWorkflowConditions();
        $this->membersTable = TableRegistry::getTableLocator()->get('Members');
    }

    /**
     * Return the last inserted member id across supported database engines.
     *
     * @return int
     */
    private function lastInsertedMemberId(): int
    {
        $conn = $this->membersTable->getConnection();
        if ($conn->getDriver() instanceof Postgres) {
            return (int)$conn->execute("SELECT currval(pg_get_serial_sequence('members', 'id'))")->fetchColumn(0);
        }

        return (int)$conn->execute('SELECT LAST_INSERT_ID() AS id')->fetchColumn(0);
    }

    // ==========================================================
    // Register Action Tests
    // ==========================================================

    public function testRegisterActionCreatesAdultMember(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->register(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'scaName' => 'New Adult',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'emailAddress' => 'register_adult_' . uniqid() . '@example.com',
                'branchId' => self::KINGDOM_BRANCH_ID,
                'birthMonth' => 1,
                'birthYear' => 1990,
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']['memberId']);
        $this->assertEquals(Member::STATUS_ACTIVE, $result['data']['status']);
    }

    public function testRegisterActionCreatesMinorMember(): void
    {
        $this->skipIfPostgres();

        $currentYear = (int)date('Y');

        $result = $this->actions->register(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'scaName' => 'New Minor',
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'emailAddress' => 'register_minor_' . uniqid() . '@example.com',
                'branchId' => self::KINGDOM_BRANCH_ID,
                'birthMonth' => 1,
                'birthYear' => $currentYear - 10,
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']['memberId']);
        $this->assertEquals(Member::STATUS_UNVERIFIED_MINOR, $result['data']['status']);
    }

    public function testSendRegistrationNotificationsQueuesAdultSelfRegisterEmails(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->register(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'scaName' => 'Adult Notify',
                'firstName' => 'Adult',
                'lastName' => 'Notify',
                'emailAddress' => 'notify_adult_' . uniqid() . '@example.com',
                'branchId' => self::KINGDOM_BRANCH_ID,
                'birthMonth' => 1,
                'birthYear' => 1990,
            ],
        );
        $memberId = (int)$result['data']['memberId'];

        $queued = [];
        $actions = $this->getMockBuilder(MembersWorkflowActions::class)
            ->onlyMethods(['queueMail'])
            ->getMock();
        $actions->method('queueMail')->willReturnCallback(
            function (string $mailer, string $action, string $to, array $vars) use (&$queued): void {
                $queued[] = [
                    'mailer' => $mailer,
                    'action' => $action,
                    'to' => $to,
                    'template' => $vars['_templateId'] ?? null,
                ];
            },
        );

        $notifyResult = $actions->sendRegistrationNotifications([], [
            'memberId' => $memberId,
            'source' => 'self-register',
        ]);

        $this->assertTrue($notifyResult['success']);
        $this->assertSame(
            ['member-registration-welcome', 'member-registration-secretary'],
            array_column($queued, 'template'),
        );
    }

    public function testSendRegistrationNotificationsSkipsAdminAddedAdultEmails(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->register(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'scaName' => 'Adult Admin Add',
                'firstName' => 'Adult',
                'lastName' => 'Admin',
                'emailAddress' => 'admin_adult_' . uniqid() . '@example.com',
                'branchId' => self::KINGDOM_BRANCH_ID,
                'birthMonth' => 1,
                'birthYear' => 1990,
            ],
        );
        $memberId = (int)$result['data']['memberId'];

        $actions = $this->getMockBuilder(MembersWorkflowActions::class)
            ->onlyMethods(['queueMail'])
            ->getMock();
        $actions->expects($this->never())->method('queueMail');

        $notifyResult = $actions->sendRegistrationNotifications([], [
            'memberId' => $memberId,
            'source' => 'admin-add',
        ]);

        $this->assertTrue($notifyResult['success']);
        $this->assertSame([], $notifyResult['data']['queuedTemplates']);
    }

    public function testSendRegistrationNotificationsQueuesMinorSecretaryEmail(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->register(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            [
                'scaName' => 'Minor Notify',
                'firstName' => 'Minor',
                'lastName' => 'Notify',
                'emailAddress' => 'notify_minor_' . uniqid() . '@example.com',
                'branchId' => self::KINGDOM_BRANCH_ID,
                'birthMonth' => 1,
                'birthYear' => (int)date('Y') - 10,
            ],
        );
        $memberId = (int)$result['data']['memberId'];

        $queued = [];
        $actions = $this->getMockBuilder(MembersWorkflowActions::class)
            ->onlyMethods(['queueMail'])
            ->getMock();
        $actions->method('queueMail')->willReturnCallback(
            function (string $mailer, string $action, string $to, array $vars) use (&$queued): void {
                $queued[] = [
                    'to' => $to,
                    'template' => $vars['_templateId'] ?? null,
                ];
            },
        );

        $notifyResult = $actions->sendRegistrationNotifications([], [
            'memberId' => $memberId,
            'source' => 'admin-add',
        ]);

        $this->assertTrue($notifyResult['success']);
        $this->assertSame(['member-registration-secretary-minor'], array_column($queued, 'template'));
    }

    // ==========================================================
    // Activate Action Tests
    // ==========================================================

    public function testActivateActionSetsStatusToActive(): void
    {
        $this->skipIfPostgres();

        // Use existing seeded member, change status directly
        $member = $this->membersTable->get(self::TEST_MEMBER_AGATHA_ID);

        // Set to deactivated first
        $member->status = Member::STATUS_DEACTIVATED;
        $this->membersTable->save($member, ['checkRules' => false, 'validate' => false]);

        $result = $this->actions->activate(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['memberId' => self::TEST_MEMBER_AGATHA_ID],
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(Member::STATUS_ACTIVE, $result['data']['status']);
    }

    // ==========================================================
    // Deactivate Action Tests
    // ==========================================================

    public function testDeactivateActionSetsStatusToDeactivated(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->deactivate(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['memberId' => self::TEST_MEMBER_DEVON_ID],
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(Member::STATUS_DEACTIVATED, $result['data']['status']);

        $updated = $this->membersTable->get(self::TEST_MEMBER_DEVON_ID);
        $this->assertEquals(Member::STATUS_DEACTIVATED, $updated->status);
        $this->assertStringStartsWith('Deleted: ', $updated->email_address);
    }

    // ==========================================================
    // SendPasswordReset Action Tests
    // ==========================================================

    public function testSendPasswordResetGeneratesToken(): void
    {
        $this->skipIfPostgres();

        $mockAuthService = $this->createMock(MemberAuthenticationService::class);
        $mockAuthService->method('generatePasswordResetToken')
            ->with('bryce@ampdemo.com')
            ->willReturn([
                'found' => true,
                'email' => 'bryce@ampdemo.com',
                'resetUrl' => 'http://localhost/members/reset-password/test-token',
            ]);

        $actionsWithMock = new MembersWorkflowActions($mockAuthService);

        $result = $actionsWithMock->sendPasswordReset(
            [],
            ['emailAddress' => 'bryce@ampdemo.com'],
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('bryce@ampdemo.com', $result['data']['email']);
        $this->assertNotEmpty($result['data']['resetUrl']);
    }

    public function testSendPasswordResetQueuesSluggedTemplate(): void
    {
        $mockAuthService = $this->createMock(MemberAuthenticationService::class);
        $mockAuthService->method('generatePasswordResetToken')
            ->willReturn([
                'found' => true,
                'email' => 'bryce@ampdemo.com',
                'resetUrl' => 'http://localhost/members/reset-password/test-token',
            ]);

        $actionsWithMock = $this->getMockBuilder(MembersWorkflowActions::class)
            ->setConstructorArgs([$mockAuthService])
            ->onlyMethods(['queueMail'])
            ->getMock();

        $actionsWithMock->expects($this->once())
            ->method('queueMail')
            ->with(
                'KMP',
                'sendFromTemplate',
                'bryce@ampdemo.com',
                $this->callback(function (array $vars): bool {
                    return ($vars['_templateId'] ?? null) === 'password-reset'
                        && ($vars['email'] ?? null) === 'bryce@ampdemo.com'
                        && ($vars['passwordResetUrl'] ?? null) === 'http://localhost/members/reset-password/test-token'
                        && array_key_exists('siteAdminSignature', $vars);
                }),
            );

        $actionsWithMock->sendPasswordReset([], ['emailAddress' => 'bryce@ampdemo.com']);
    }

    public function testSendPasswordResetFailsForUnknownEmail(): void
    {
        $mockAuthService = $this->createMock(MemberAuthenticationService::class);
        $mockAuthService->method('generatePasswordResetToken')
            ->willReturn([
                'found' => false,
                'secretaryEmail' => 'secretary@example.com',
            ]);

        $actionsWithMock = new MembersWorkflowActions($mockAuthService);

        $result = $actionsWithMock->sendPasswordReset(
            [],
            ['emailAddress' => 'nonexistent@example.com'],
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    // ==========================================================
    // AgeUpMember Action Tests
    // ==========================================================

    public function testAgeUpMemberTransitionsMinorToActive(): void
    {
        $this->skipIfPostgres();

        // Use direct SQL to insert a member with minor status and adult age,
        // bypassing beforeSave which would auto-transition via ageUpReview().
        $uid = substr(md5(uniqid()), 0, 8);
        $conn = $this->membersTable->getConnection();
        $conn->execute(
            "INSERT INTO members (public_id, sca_name, first_name, last_name, email_address, password, status, birth_month, birth_year, branch_id, created)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $uid,
                'AgeUp Test Minor',
                'AgeUp',
                'Tester',
                'ageup_minor_' . $uid . '@example.com',
                password_hash('test12345', PASSWORD_DEFAULT),
                Member::STATUS_UNVERIFIED_MINOR,
                1,
                (int)date('Y') - 20,
                self::KINGDOM_BRANCH_ID,
            ],
        );
        $memberId = $this->lastInsertedMemberId();

        $result = $this->actions->ageUpMember(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['memberId' => $memberId],
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['transitioned']);
        $this->assertEquals(Member::STATUS_ACTIVE, $result['data']['status']);
        $this->assertEquals(Member::STATUS_UNVERIFIED_MINOR, $result['data']['previousStatus']);
    }

    public function testAssignStatusAndTokensPreservesExistingAdultResetToken(): void
    {
        $this->skipIfPostgres();

        $uid = substr(md5(uniqid('assign')), 0, 8);
        $conn = $this->membersTable->getConnection();
        $existingToken = 'keep-existing-token';
        $futureExpiry = DateTime::now()->addDays(1)->format('Y-m-d H:i:s');
        $conn->execute(
            "INSERT INTO members (
                public_id, sca_name, first_name, last_name, email_address, password,
                password_token, password_token_expires_on, status, birth_month, birth_year, branch_id, created
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $uid,
                'Preserve Token Adult',
                'Preserve',
                'Token',
                'preserve_' . $uid . '@example.com',
                password_hash('test12345', PASSWORD_DEFAULT),
                $existingToken,
                $futureExpiry,
                Member::STATUS_DEACTIVATED,
                1,
                (int)date('Y') - 30,
                self::KINGDOM_BRANCH_ID,
            ],
        );
        $memberId = $this->lastInsertedMemberId();

        $result = $this->actions->assignStatusAndTokens([], ['memberId' => $memberId]);

        $this->assertTrue($result['success']);

        $updated = $this->membersTable->get($memberId);
        $this->assertSame($existingToken, $updated->password_token);
        $this->assertEquals(Member::STATUS_ACTIVE, $updated->status);
    }

    public function testAgeUpMemberTransitionsVerifiedMinorToVerifiedMembership(): void
    {
        $this->skipIfPostgres();

        $uid = substr(md5(uniqid('b')), 0, 8);
        $conn = $this->membersTable->getConnection();
        $conn->execute(
            "INSERT INTO members (public_id, sca_name, first_name, last_name, email_address, password, status, birth_month, birth_year, branch_id, created)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $uid,
                'AgeUp Verified Minor',
                'AgeUp',
                'Verified',
                'ageup_vminor_' . $uid . '@example.com',
                password_hash('test12345', PASSWORD_DEFAULT),
                Member::STATUS_VERIFIED_MINOR,
                1,
                (int)date('Y') - 20,
                self::KINGDOM_BRANCH_ID,
            ],
        );
        $memberId = $this->lastInsertedMemberId();

        $result = $this->actions->ageUpMember(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['memberId' => $memberId],
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['transitioned']);
        $this->assertEquals(Member::STATUS_VERIFIED_MEMBERSHIP, $result['data']['status']);
    }

    public function testAgeUpMemberNoTransitionForAdult(): void
    {
        $this->skipIfPostgres();

        // Use existing adult seeded member — no transition should occur
        $result = $this->actions->ageUpMember(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['memberId' => self::TEST_MEMBER_EIRIK_ID],
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['transitioned']);
    }

    // ==========================================================
    // SyncWarrantableStatus Action Tests
    // ==========================================================

    public function testSyncWarrantableStatusRecalculatesFlag(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->syncWarrantableStatus(
            ['triggeredBy' => self::ADMIN_MEMBER_ID],
            ['memberId' => self::TEST_MEMBER_EIRIK_ID],
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('warrantable', $result['data']);
        $this->assertArrayHasKey('reasons', $result['data']);
    }

    // ==========================================================
    // UpdateMemberField Action Tests
    // ==========================================================

    public function testUpdateMemberFieldUpdatesFields(): void
    {
        $this->skipIfPostgres();

        $result = $this->actions->updateMemberField(
            [],
            [
                'memberId' => self::TEST_MEMBER_AGATHA_ID,
                'fields' => ['sca_name' => 'Updated SCA Name'],
            ],
        );

        $this->assertTrue($result['success']);

        $updated = $this->membersTable->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertEquals('Updated SCA Name', $updated->sca_name);
    }

    public function testUpdateMemberFieldFailsWithNoFields(): void
    {
        $result = $this->actions->updateMemberField(
            [],
            ['memberId' => self::ADMIN_MEMBER_ID, 'fields' => []],
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No fields', $result['error']);
    }

    // ==========================================================
    // Condition Tests
    // ==========================================================

    public function testIsMinorConditionForYoungMember(): void
    {
        $this->skipIfPostgres();

        // Insert minor via raw SQL to avoid beforeSave ageUpReview
        $uid = substr(md5(uniqid('c')), 0, 8);
        $conn = $this->membersTable->getConnection();
        $conn->execute(
            "INSERT INTO members (public_id, sca_name, first_name, last_name, email_address, password, status, birth_month, birth_year, branch_id, created)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $uid,
                'Young Test',
                'Young',
                'Tester',
                'young_' . $uid . '@example.com',
                password_hash('test12345', PASSWORD_DEFAULT),
                Member::STATUS_UNVERIFIED_MINOR,
                1,
                (int)date('Y') - 10,
                self::KINGDOM_BRANCH_ID,
            ],
        );
        $memberId = $this->lastInsertedMemberId();

        $result = $this->conditions->isMinor([], ['memberId' => $memberId]);
        $this->assertTrue($result);

        $adultResult = $this->conditions->isAdult([], ['memberId' => $memberId]);
        $this->assertFalse($adultResult);
    }

    public function testIsAdultConditionForAdultMember(): void
    {
        $this->skipIfPostgres();

        // Use existing adult seeded member
        $result = $this->conditions->isAdult([], ['memberId' => self::TEST_MEMBER_EIRIK_ID]);
        $this->assertTrue($result);

        $minorResult = $this->conditions->isMinor([], ['memberId' => self::TEST_MEMBER_EIRIK_ID]);
        $this->assertFalse($minorResult);
    }

    public function testHasValidMembershipConditionValid(): void
    {
        $this->skipIfPostgres();

        // Set valid membership directly via SQL
        $conn = $this->membersTable->getConnection();
        $futureDate = DateTime::now()->addDays(30)->format('Y-m-d');
        $conn->execute(
            'UPDATE members SET membership_expires_on = ? WHERE id = ?',
            [$futureDate, self::TEST_MEMBER_AGATHA_ID],
        );

        $this->assertTrue(
            $this->conditions->hasValidMembership([], ['memberId' => self::TEST_MEMBER_AGATHA_ID]),
        );
    }

    public function testHasValidMembershipConditionExpired(): void
    {
        $this->skipIfPostgres();

        // Set expired membership directly via SQL
        $conn = $this->membersTable->getConnection();
        $pastDate = DateTime::now()->subDays(30)->format('Y-m-d');
        $conn->execute(
            'UPDATE members SET membership_expires_on = ? WHERE id = ?',
            [$pastDate, self::TEST_MEMBER_DEVON_ID],
        );

        $this->assertFalse(
            $this->conditions->hasValidMembership([], ['memberId' => self::TEST_MEMBER_DEVON_ID]),
        );
    }

    public function testIsActiveCondition(): void
    {
        $this->skipIfPostgres();

        // Existing seeded member should be active
        $this->assertTrue(
            $this->conditions->isActive([], ['memberId' => self::TEST_MEMBER_EIRIK_ID]),
        );

        // Deactivate a member and check
        $member = $this->membersTable->get(self::TEST_MEMBER_DEVON_ID);
        $member->status = Member::STATUS_DEACTIVATED;
        $this->membersTable->save($member, ['checkRules' => false, 'validate' => false]);

        $this->assertFalse(
            $this->conditions->isActive([], ['memberId' => self::TEST_MEMBER_DEVON_ID]),
        );
    }

    public function testHasEmailAddressCondition(): void
    {
        $this->skipIfPostgres();

        // Existing seeded member should have email
        $this->assertTrue(
            $this->conditions->hasEmailAddress([], ['memberId' => self::TEST_MEMBER_EIRIK_ID]),
        );
    }

    // ==========================================================
    // Context Path Resolution Tests
    // ==========================================================

    public function testActivateResolvesContextPaths(): void
    {
        $this->skipIfPostgres();

        // Deactivate first
        $member = $this->membersTable->get(self::TEST_MEMBER_BRYCE_ID);
        $member->status = Member::STATUS_DEACTIVATED;
        $this->membersTable->save($member, ['checkRules' => false, 'validate' => false]);

        $context = ['entity' => ['id' => self::TEST_MEMBER_BRYCE_ID]];
        $result = $this->actions->activate($context, ['memberId' => '$.entity.id']);

        $this->assertTrue($result['success']);
        $this->assertEquals(Member::STATUS_ACTIVE, $result['data']['status']);
    }

    public function testConditionResolvesContextPaths(): void
    {
        $this->skipIfPostgres();

        $context = ['trigger' => ['memberId' => self::TEST_MEMBER_EIRIK_ID]];
        $result = $this->conditions->isAdult($context, ['memberId' => '$.trigger.memberId']);

        $this->assertTrue($result);
    }

    // ==========================================================
    // Error Handling Tests
    // ==========================================================

    public function testActivateFailsForNonExistentMember(): void
    {
        $result = $this->actions->activate(
            [],
            ['memberId' => 999999999],
        );

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    public function testConditionReturnsFalseForMissingMemberId(): void
    {
        $this->assertFalse($this->conditions->isMinor([], []));
        $this->assertFalse($this->conditions->isAdult([], []));
        $this->assertFalse($this->conditions->hasValidMembership([], []));
        $this->assertFalse($this->conditions->isWarrantable([], []));
        $this->assertFalse($this->conditions->isActive([], []));
        $this->assertFalse($this->conditions->hasEmailAddress([], []));
    }

    // ==========================================================
    // Provider Registration Tests
    // ==========================================================

    public function testProviderRegistersTriggersActionsAndConditions(): void
    {
        WorkflowTriggerRegistry::clear();
        WorkflowActionRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowEntityRegistry::clear();

        MembersWorkflowProvider::register();

        $triggers = WorkflowTriggerRegistry::getTriggersBySource('Members');
        $this->assertNotEmpty($triggers);
        $triggerEvents = array_column($triggers, 'event');
        $this->assertContains('Members.Registered', $triggerEvents);
        $this->assertContains('Members.PasswordResetRequested', $triggerEvents);
        $this->assertContains('Members.MembershipVerified', $triggerEvents);
        $this->assertContains('Members.AgeUpTriggered', $triggerEvents);
        $this->assertContains('Members.WarrantableSyncTriggered', $triggerEvents);

        $actions = WorkflowActionRegistry::getActionsBySource('Members');
        $this->assertNotEmpty($actions);
        $actionNames = array_column($actions, 'action');
        $this->assertContains('Members.Register', $actionNames);
        $this->assertContains('Members.Activate', $actionNames);
        $this->assertContains('Members.Deactivate', $actionNames);
        $this->assertContains('Members.SendPasswordReset', $actionNames);
        $this->assertContains('Members.AgeUpMember', $actionNames);
        $this->assertContains('Members.SyncWarrantableStatus', $actionNames);
        $this->assertContains('Members.VerifyMembership', $actionNames);
        $this->assertContains('Members.UpdateMemberField', $actionNames);
        $this->assertContains('Members.SendRegistrationNotifications', $actionNames);

        $conditions = WorkflowConditionRegistry::getConditionsBySource('Members');
        $this->assertNotEmpty($conditions);
        $conditionNames = array_column($conditions, 'condition');
        $this->assertContains('Members.IsMinor', $conditionNames);
        $this->assertContains('Members.IsAdult', $conditionNames);
        $this->assertContains('Members.HasValidMembership', $conditionNames);
        $this->assertContains('Members.IsWarrantable', $conditionNames);
        $this->assertContains('Members.IsActive', $conditionNames);
        $this->assertContains('Members.HasEmailAddress', $conditionNames);

        $entities = WorkflowEntityRegistry::getEntitiesBySource('Members');
        $this->assertNotEmpty($entities);
    }
}
