<?php

declare(strict_types=1);

namespace Activities\Test\TestCase\Services;

use Activities\Model\Entity\Authorization;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use App\Services\ActiveWindowManager\DefaultActiveWindowManager;

/**
 * Activities\Services\DefaultAuthorizationManager Test Case
 *
 * Tests the DefaultAuthorizationManager service including request, approve,
 * deny, revoke, and retract workflows for activity authorizations.
 *
 * @uses \Activities\Services\DefaultAuthorizationManager
 */
class DefaultAuthorizationManagerTest extends BaseTestCase
{
    /**
     * Service under test (mock with mailer stubbed)
     *
     * @var \Activities\Services\DefaultAuthorizationManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $authManager;

    /**
     * Authorizations table
     *
     * @var \Activities\Model\Table\AuthorizationsTable
     */
    protected $Authorizations;

    /**
     * AuthorizationApprovals table
     *
     * @var \Activities\Model\Table\AuthorizationApprovalsTable
     */
    protected $AuthorizationApprovals;

    /**
     * Activities table
     *
     * @var \Activities\Model\Table\ActivitiesTable
     */
    protected $Activities;

    /**
     * Members table
     *
     * @var \App\Model\Table\MembersTable
     */
    protected $Members;

    /**
     * Test activity ID from seed data (Armored, id=1)
     */
    private const TEST_ACTIVITY_ID = 1;

    /**
     * Test activity that requires 1 approver
     */
    private const TEST_SINGLE_APPROVAL_ACTIVITY_ID = 1;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->Authorizations = TableRegistry::getTableLocator()->get('Activities.Authorizations');
        $this->AuthorizationApprovals = TableRegistry::getTableLocator()->get('Activities.AuthorizationApprovals');
        $this->Activities = TableRegistry::getTableLocator()->get('Activities.Activities');
        $this->Members = TableRegistry::getTableLocator()->get('Members');

        $activeWindowManager = new DefaultActiveWindowManager();

        // Create partial mock to stub out mailer (avoids email transport issues in tests)
        $this->authManager = $this->getMockBuilder(\Activities\Services\DefaultAuthorizationManager::class)
            ->setConstructorArgs([$activeWindowManager])
            ->onlyMethods(['getMailer'])
            ->getMock();

        // Stub getMailer to return a mock mailer that does nothing on send()
        $mockMailer = $this->createMock(\Cake\Mailer\Mailer::class);
        $mockMailer->method('send')->willReturn([]);
        $this->authManager->method('getMailer')->willReturn($mockMailer);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->authManager);
        unset($this->Authorizations);
        unset($this->AuthorizationApprovals);
        unset($this->Activities);
        unset($this->Members);

        parent::tearDown();
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Find a member ID that has no pending authorization for the given activity
     *
     * @param int $activityId
     * @return int
     */
    private function findMemberWithoutPending(int $activityId): int
    {
        // Use Agatha (2871) — check if she has a pending request for this activity
        $pending = $this->Authorizations->find()
            ->where([
                'member_id' => self::TEST_MEMBER_AGATHA_ID,
                'activity_id' => $activityId,
                'status' => Authorization::PENDING_STATUS,
            ])
            ->count();

        if ($pending === 0) {
            return self::TEST_MEMBER_AGATHA_ID;
        }

        // Try Bryce
        $pending = $this->Authorizations->find()
            ->where([
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'activity_id' => $activityId,
                'status' => Authorization::PENDING_STATUS,
            ])
            ->count();

        if ($pending === 0) {
            return self::TEST_MEMBER_BRYCE_ID;
        }

        // Use a high-numbered activity that is less likely to have pending
        $this->fail('Could not find a member without a pending authorization for activity ' . $activityId);

        return 0;
    }

    /**
     * Create a fresh authorization request for testing
     *
     * @param int $requesterId
     * @param int $activityId
     * @param int $approverId
     * @return array{auth: \Activities\Model\Entity\Authorization, approval: mixed}
     */
    private function createTestAuthorization(int $requesterId, int $activityId, int $approverId): array
    {
        $result = $this->authManager->request($requesterId, $activityId, $approverId, false);
        $this->assertTrue($result->success, 'Test setup: request should succeed - ' . ($result->reason ?? ''));

        $auth = $this->Authorizations->find()
            ->where([
                'member_id' => $requesterId,
                'activity_id' => $activityId,
                'status' => Authorization::PENDING_STATUS,
            ])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($auth, 'Test setup: authorization should exist');

        $approval = $this->AuthorizationApprovals->find()
            ->where(['authorization_id' => $auth->id])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($approval, 'Test setup: approval should exist');

        return ['auth' => $auth, 'approval' => $approval];
    }

    // =========================================================================
    // Tests for request()
    // =========================================================================

    /**
     * Test creating a new authorization request succeeds
     */
    public function testRequestNewAuthorizationSuccess(): void
    {
        // Use an activity unlikely to have a pending request for Agatha
        // Pick a higher-numbered activity
        $activity = $this->Activities->find()
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($activity, 'Need at least one activity in seed data');

        $requesterId = $this->findMemberWithoutPending($activity->id);

        $result = $this->authManager->request(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
            false,
        );

        $this->assertTrue($result->success, 'New authorization request should succeed');

        // Verify authorization record was created
        $auth = $this->Authorizations->find()
            ->where([
                'member_id' => $requesterId,
                'activity_id' => $activity->id,
                'status' => Authorization::PENDING_STATUS,
            ])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($auth, 'Authorization record should exist');
        $this->assertEquals(Authorization::PENDING_STATUS, $auth->status);
        $this->assertFalse((bool)$auth->is_renewal);

        // Verify approval record was created
        $approval = $this->AuthorizationApprovals->find()
            ->where(['authorization_id' => $auth->id])
            ->first();
        $this->assertNotNull($approval, 'Approval record should exist');
        $this->assertEquals(self::ADMIN_MEMBER_ID, $approval->approver_id);
        $this->assertNotEmpty($approval->authorization_token, 'Token should be generated');
        $this->assertEquals(32, strlen($approval->authorization_token), 'Token should be 32 chars');
    }

    /**
     * Test duplicate pending request is rejected
     */
    public function testRequestDuplicatePendingFails(): void
    {
        $activity = $this->Activities->find()->orderBy(['id' => 'DESC'])->first();
        $requesterId = $this->findMemberWithoutPending($activity->id);

        // First request
        $result1 = $this->authManager->request(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
            false,
        );
        $this->assertTrue($result1->success, 'First request should succeed');

        // Duplicate request
        $result2 = $this->authManager->request(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
            false,
        );

        $this->assertFalse($result2->success, 'Duplicate should be rejected');
        $this->assertStringContainsString('already a pending request', $result2->reason);
    }

    /**
     * Test renewal request without existing approved authorization fails
     */
    public function testRequestRenewalWithoutExistingApprovedFails(): void
    {
        // Use an activity that member has NO approved authorization for
        $activity = $this->Activities->find()->orderBy(['id' => 'DESC'])->first();
        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Make sure no approved auth exists for this member/activity
        $approvedCount = $this->Authorizations->find()
            ->where([
                'member_id' => $requesterId,
                'activity_id' => $activity->id,
                'status' => Authorization::APPROVED_STATUS,
                'expires_on >' => DateTime::now(),
            ])
            ->count();

        if ($approvedCount > 0) {
            $this->markTestSkipped('Member already has approved auth for this activity');
        }

        $result = $this->authManager->request(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
            true,
        );

        $this->assertFalse($result->success, 'Renewal without existing auth should fail');
        $this->assertStringContainsString('no existing authorization', $result->reason);
    }

    // =========================================================================
    // Tests for approve()
    // =========================================================================

    /**
     * Test approving a single-approver authorization completes the workflow
     */
    public function testApproveSingleApproverSuccess(): void
    {
        // Find an activity requiring only 1 approver
        $activity = $this->Activities->find()
            ->where(['num_required_authorizors' => 1])
            ->first();
        $this->assertNotNull($activity, 'Need an activity requiring 1 approver');

        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Create request
        $testData = $this->createTestAuthorization(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
        );

        // Approve
        $result = $this->authManager->approve(
            $testData['approval']->id,
            self::ADMIN_MEMBER_ID,
            null,
        );

        $this->assertTrue($result->success, 'Approval should succeed');

        // Verify authorization status is now approved
        $updatedAuth = $this->Authorizations->get($testData['auth']->id);
        $this->assertEquals(
            Authorization::APPROVED_STATUS,
            $updatedAuth->status,
            'Authorization should be approved',
        );

        // Verify approval record was updated
        $updatedApproval = $this->AuthorizationApprovals->get($testData['approval']->id);
        $this->assertTrue((bool)$updatedApproval->approved, 'Approval should be marked approved');
        $this->assertNotNull($updatedApproval->responded_on, 'Response date should be set');
    }

    // =========================================================================
    // Tests for deny()
    // =========================================================================

    /**
     * Test denying an authorization request
     */
    public function testDenyAuthorizationSuccess(): void
    {
        // Find an activity with few pending auths
        $activity = $this->Activities->find()->where(['id' => 2])->first();
        $this->assertNotNull($activity);

        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Create request
        $testData = $this->createTestAuthorization(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
        );

        // Deny
        $denyReason = 'Insufficient training documentation';
        $result = $this->authManager->deny(
            $testData['approval']->id,
            self::ADMIN_MEMBER_ID,
            $denyReason,
        );

        $this->assertTrue($result->success, 'Denial should succeed');

        // Verify authorization status
        $updatedAuth = $this->Authorizations->get($testData['auth']->id);
        $this->assertEquals(Authorization::DENIED_STATUS, $updatedAuth->status);
        $this->assertEquals(self::ADMIN_MEMBER_ID, $updatedAuth->revoker_id);
        $this->assertEquals($denyReason, $updatedAuth->revoked_reason);

        // Verify dates are set to past (immediately expired)
        $this->assertLessThan(DateTime::now(), $updatedAuth->start_on, 'Start should be in past');
        $this->assertLessThan(DateTime::now(), $updatedAuth->expires_on, 'Expiry should be in past');

        // Verify approval record
        $updatedApproval = $this->AuthorizationApprovals->get($testData['approval']->id);
        $this->assertFalse((bool)$updatedApproval->approved, 'Approval should be marked denied');
        $this->assertNotNull($updatedApproval->responded_on, 'Response date should be set');
        $this->assertEquals($denyReason, $updatedApproval->approver_notes);
    }

    // =========================================================================
    // Tests for revoke()
    // =========================================================================

    /**
     * Test revoking an approved authorization
     */
    public function testRevokeApprovedAuthorizationSuccess(): void
    {
        // Find an existing approved authorization from seed data
        $approvedAuth = $this->Authorizations->find()
            ->where([
                'status' => Authorization::APPROVED_STATUS,
                'expires_on >' => DateTime::now(),
            ])
            ->first();

        if (!$approvedAuth) {
            $this->markTestSkipped('No approved authorization in seed data to revoke');
        }

        $revokedReason = 'Safety policy violation';
        $result = $this->authManager->revoke(
            $approvedAuth->id,
            self::ADMIN_MEMBER_ID,
            $revokedReason,
        );

        $this->assertTrue($result->success, 'Revocation should succeed');

        // Verify authorization status changed
        $updatedAuth = $this->Authorizations->get($approvedAuth->id);
        $this->assertEquals(Authorization::REVOKED_STATUS, $updatedAuth->status);
    }

    // =========================================================================
    // Tests for retract()
    // =========================================================================

    /**
     * Test retracting a pending authorization by the requester
     */
    public function testRetractPendingAuthorizationSuccess(): void
    {
        // Use activity 3 for this test
        $activity = $this->Activities->find()->where(['id' => 3])->first();
        $this->assertNotNull($activity);

        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Create request
        $testData = $this->createTestAuthorization(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
        );

        // Retract
        $result = $this->authManager->retract(
            $testData['auth']->id,
            $requesterId,
        );

        $this->assertTrue($result->success, 'Retraction should succeed');
        $this->assertArrayHasKey('authorization', $result->data, 'Result data should contain authorization');

        // Verify authorization status
        $updatedAuth = $this->Authorizations->get($testData['auth']->id);
        $this->assertEquals(Authorization::RETRACTED_STATUS, $updatedAuth->status);

        // Verify the pending approval was closed
        $approval = $this->AuthorizationApprovals->get($testData['approval']->id);
        $this->assertNotNull($approval->responded_on, 'Approval should have responded_on set');
        $this->assertFalse((bool)$approval->approved, 'Approval should be marked false');
        $this->assertEquals('Retracted by requester', $approval->approver_notes);
    }

    /**
     * Test retracting a non-pending authorization fails
     */
    public function testRetractNonPendingFails(): void
    {
        // Find an approved authorization
        $approvedAuth = $this->Authorizations->find()
            ->where(['status' => Authorization::APPROVED_STATUS])
            ->first();

        if (!$approvedAuth) {
            $this->markTestSkipped('No approved authorization in seed data');
        }

        $result = $this->authManager->retract(
            $approvedAuth->id,
            $approvedAuth->member_id,
        );

        $this->assertFalse($result->success, 'Should fail for non-pending authorization');
        $this->assertStringContainsString('pending', $result->reason);
    }

    /**
     * Test retracting by a different member fails
     */
    public function testRetractByDifferentMemberFails(): void
    {
        // Use activity 5 for this test
        $activity = $this->Activities->find()->where(['id' => 5])->first();
        $this->assertNotNull($activity);

        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Create request
        $testData = $this->createTestAuthorization(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
        );

        // Try to retract as a different member
        $differentMemberId = ($requesterId === self::TEST_MEMBER_AGATHA_ID)
            ? self::TEST_MEMBER_BRYCE_ID
            : self::TEST_MEMBER_AGATHA_ID;

        $result = $this->authManager->retract(
            $testData['auth']->id,
            $differentMemberId,
        );

        $this->assertFalse($result->success, 'Retraction by non-owner should fail');
        $this->assertStringContainsString('your own', $result->reason);
    }

    /**
     * Test retracting a non-existent authorization fails
     */
    public function testRetractNonExistentFails(): void
    {
        $result = $this->authManager->retract(999999, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->success, 'Should fail for non-existent authorization');
        $this->assertStringContainsString('not found', $result->reason);
    }

    // =========================================================================
    // Tests for full request → approve workflow
    // =========================================================================

    /**
     * Test complete workflow: request → approve for single-approver activity
     */
    public function testFullRequestApproveWorkflow(): void
    {
        // Find activity with 1 required approver
        $activity = $this->Activities->find()
            ->where(['num_required_authorizors' => 1])
            ->first();
        $this->assertNotNull($activity);

        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Step 1: Request
        $requestResult = $this->authManager->request(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
            false,
        );
        $this->assertTrue($requestResult->success, 'Request should succeed');

        // Verify pending state
        $auth = $this->Authorizations->find()
            ->where([
                'member_id' => $requesterId,
                'activity_id' => $activity->id,
                'status' => Authorization::PENDING_STATUS,
            ])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($auth);
        $this->assertEquals(Authorization::PENDING_STATUS, $auth->status);

        $approval = $this->AuthorizationApprovals->find()
            ->where(['authorization_id' => $auth->id])
            ->first();
        $this->assertNotNull($approval);

        // Step 2: Approve
        $approveResult = $this->authManager->approve(
            $approval->id,
            self::ADMIN_MEMBER_ID,
            null,
        );
        $this->assertTrue($approveResult->success, 'Approve should succeed');

        // Verify final state
        $finalAuth = $this->Authorizations->get($auth->id);
        $this->assertEquals(Authorization::APPROVED_STATUS, $finalAuth->status);
        $this->assertNotNull($finalAuth->start_on, 'Should have start date');
        $this->assertNotNull($finalAuth->expires_on, 'Should have expiry date');
    }

    /**
     * Test complete workflow: request → deny
     */
    public function testFullRequestDenyWorkflow(): void
    {
        $activity = $this->Activities->find()->where(['id' => 4])->first();
        $this->assertNotNull($activity);

        $requesterId = $this->findMemberWithoutPending($activity->id);

        // Step 1: Request
        $testData = $this->createTestAuthorization(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
        );

        // Verify pending
        $this->assertEquals(Authorization::PENDING_STATUS, $testData['auth']->status);

        // Step 2: Deny
        $denyResult = $this->authManager->deny(
            $testData['approval']->id,
            self::ADMIN_MEMBER_ID,
            'Denied for testing purposes',
        );
        $this->assertTrue($denyResult->success, 'Deny should succeed');

        // Verify final state
        $finalAuth = $this->Authorizations->get($testData['auth']->id);
        $this->assertEquals(Authorization::DENIED_STATUS, $finalAuth->status);

        // Verify the requester can now create a NEW request (not blocked as duplicate)
        $newResult = $this->authManager->request(
            $requesterId,
            $activity->id,
            self::ADMIN_MEMBER_ID,
            false,
        );
        $this->assertTrue($newResult->success, 'Should be able to re-request after denial');
    }
}
