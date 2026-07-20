<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\Providers;

use Activities\Model\Entity\Authorization;
use Activities\Services\ActivitiesWorkflowActions;
use Activities\Services\ActivitiesWorkflowConditions;
use Activities\Services\AuthorizationManagerInterface;
use App\Services\ServiceResult;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * Tests for Activities plugin workflow actions and conditions.
 */
class ActivitiesWorkflowActionsTest extends BaseTestCase
{
    private ActivitiesWorkflowActions $actions;
    private ActivitiesWorkflowConditions $conditions;
    private $mockAuthManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAuthManager = $this->createMock(AuthorizationManagerInterface::class);
        $this->actions = new ActivitiesWorkflowActions($this->mockAuthManager);
        $this->conditions = new ActivitiesWorkflowConditions();
    }

    /**
     * Create a test authorization record directly (bypassing mass-assignment guards).
     */
    private function createTestAuthorization(int $memberId, int $activityId, string $status, ?string $expiresModifier = '+30 days'): object
    {
        $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');
        $auth = $authTable->newEmptyEntity();
        $auth->member_id = $memberId;
        $auth->activity_id = $activityId;
        $auth->status = $status;
        $auth->start_on = DateTime::now();
        $auth->expires_on = DateTime::now()->modify($expiresModifier);
        $authTable->saveOrFail($auth);
        return $auth;
    }

    // ==========================================================
    // RevokeAuthorization Action Tests
    // ==========================================================

    public function testRevokeAuthorizationSuccess(): void
    {
        $this->mockAuthManager->method('revoke')
            ->willReturn(new ServiceResult(true));

        $result = $this->actions->revokeAuthorization(
            ['triggeredBy' => 1],
            [
                'authorizationId' => 99,
                'revokerId' => 1,
                'revokedReason' => 'No longer qualified',
            ]
        );

        $this->assertTrue($result['revoked']);
    }

    public function testRevokeAuthorizationFailure(): void
    {
        $this->mockAuthManager->method('revoke')
            ->willReturn(new ServiceResult(false, 'Authorization not found'));

        $result = $this->actions->revokeAuthorization(
            [],
            [
                'authorizationId' => 999,
                'revokerId' => 1,
                'revokedReason' => 'Test',
            ]
        );

        $this->assertFalse($result['revoked']);
    }

    public function testRevokeAuthorizationResolvesContextPaths(): void
    {
        $this->mockAuthManager->expects($this->once())
            ->method('revoke')
            ->with(42, 7, 'Context reason')
            ->willReturn(new ServiceResult(true));

        $context = [
            'entity' => ['id' => 42],
            'actor' => ['id' => 7],
            'reason' => 'Context reason',
        ];

        $result = $this->actions->revokeAuthorization($context, [
            'authorizationId' => '$.entity.id',
            'revokerId' => '$.actor.id',
            'revokedReason' => '$.reason',
        ]);

        $this->assertTrue($result['revoked']);
    }

    // ==========================================================
    // RetractAuthorization Action Tests
    // ==========================================================

    public function testRetractAuthorizationSuccess(): void
    {
        $this->mockAuthManager->expects($this->once())
            ->method('retract')
            ->with(50, 10)
            ->willReturn(new ServiceResult(true));

        $result = $this->actions->retractAuthorization(
            [],
            ['authorizationId' => 50, 'requesterId' => 10]
        );

        $this->assertTrue($result['retracted']);
    }

    public function testRetractAuthorizationFailure(): void
    {
        $this->mockAuthManager->method('retract')
            ->willReturn(new ServiceResult(false, 'Not owner'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not owner');

        $this->actions->retractAuthorization(
            [],
            ['authorizationId' => 50, 'requesterId' => 99]
        );
    }

    public function testRetractAuthorizationResolvesContextPaths(): void
    {
        $this->mockAuthManager->expects($this->once())
            ->method('retract')
            ->with(77, 33)
            ->willReturn(new ServiceResult(true));

        $context = ['auth' => ['id' => 77], 'member' => ['id' => 33]];

        $result = $this->actions->retractAuthorization($context, [
            'authorizationId' => '$.auth.id',
            'requesterId' => '$.member.id',
        ]);

        $this->assertTrue($result['retracted']);
    }

    // ==========================================================
    // ValidateRenewalEligibility Action Tests
    // ==========================================================

    public function testValidateRenewalEligibilityWithNoActiveAuth(): void
    {
        $this->skipIfPostgres();

        // Use a member/activity combo that won't have an approved authorization
        $result = $this->actions->validateRenewalEligibility(
            [],
            ['memberId' => self::TEST_MEMBER_EIRIK_ID, 'activityId' => 999999]
        );

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('No active authorization', $result['reason']);
    }

    public function testValidateRenewalEligibilityWithActiveAuth(): void
    {
        $this->skipIfPostgres();

        $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');

        $activity = $activityTable->find()->first();
        if (!$activity) {
            $this->markTestSkipped('No activities in test database');
        }

        $memberId = self::TEST_MEMBER_EIRIK_ID;

        $auth = $this->createTestAuthorization($memberId, $activity->id, Authorization::APPROVED_STATUS);

        $result = $this->actions->validateRenewalEligibility(
            [],
            ['memberId' => $memberId, 'activityId' => $activity->id]
        );

        $this->assertTrue($result['eligible']);
        $this->assertStringContainsString('eligible', $result['reason']);
    }

    public function testValidateRenewalEligibilityWithPendingRequest(): void
    {
        $this->skipIfPostgres();

        $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');

        $activity = $activityTable->find()->first();
        if (!$activity) {
            $this->markTestSkipped('No activities in test database');
        }

        $memberId = self::TEST_MEMBER_EIRIK_ID;

        // Create approved authorization
        $this->createTestAuthorization($memberId, $activity->id, Authorization::APPROVED_STATUS);

        // Create pending authorization (blocks renewal)
        $this->createTestAuthorization($memberId, $activity->id, Authorization::PENDING_STATUS);

        $result = $this->actions->validateRenewalEligibility(
            [],
            ['memberId' => $memberId, 'activityId' => $activity->id]
        );

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('pending', $result['reason']);
    }

    // ==========================================================
    // IsRenewalEligible Condition Tests
    // ==========================================================

    public function testIsRenewalEligibleConditionReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->isRenewalEligible([], []);
        $this->assertFalse($result);
    }

    public function testIsRenewalEligibleConditionReturnsFalseWithNoActiveAuth(): void
    {
        $this->skipIfPostgres();

        $result = $this->conditions->isRenewalEligible(
            [],
            ['memberId' => self::ADMIN_MEMBER_ID, 'activityId' => 999999]
        );

        $this->assertFalse($result);
    }

    // ==========================================================
    // HasRequiredApprovals Condition Tests
    // ==========================================================

    public function testHasRequiredApprovalsReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->hasRequiredApprovals([], []);
        $this->assertFalse($result);
    }

    public function testHasRequiredApprovalsReturnsFalseForNonexistentAuth(): void
    {
        $this->skipIfPostgres();

        $result = $this->conditions->hasRequiredApprovals(
            [],
            ['authorizationId' => 999999]
        );

        $this->assertFalse($result);
    }

    public function testHasRequiredApprovalsWithSufficientApprovals(): void
    {
        $this->skipIfPostgres();

        $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');
        $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');

        $activity = $activityTable->find()->first();
        if (!$activity) {
            $this->markTestSkipped('No activities in test database');
        }

        $memberId = self::TEST_MEMBER_EIRIK_ID;

        // Create authorization with enough approval_count to meet the requirement
        $requiredCount = $activity->num_required_authorizors ?? 1;
        $auth = $this->createTestAuthorization($memberId, $activity->id, Authorization::PENDING_STATUS);
        $auth->approval_count = $requiredCount;
        $authTable->saveOrFail($auth);

        $result = $this->conditions->hasRequiredApprovals(
            [],
            ['authorizationId' => $auth->id]
        );

        $this->assertTrue($result);
    }

    public function testHasRequiredApprovalsWithInsufficientApprovals(): void
    {
        $this->skipIfPostgres();

        $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');
        $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');

        $activity = $activityTable->find()->first();
        if (!$activity) {
            $this->markTestSkipped('No activities in test database');
        }

        $memberId = self::TEST_MEMBER_EIRIK_ID;

        // Create authorization with zero approvals
        $auth = $this->createTestAuthorization($memberId, $activity->id, Authorization::PENDING_STATUS);

        $result = $this->conditions->hasRequiredApprovals(
            [],
            ['authorizationId' => $auth->id]
        );

        $this->assertFalse($result);
    }

    // ==========================================================
    // MemberMeetsAgeRequirement Condition Tests
    // ==========================================================

    public function testMemberMeetsAgeRequirementReturnsFalseForMissingParams(): void
    {
        $result = $this->conditions->memberMeetsAgeRequirement([], []);
        $this->assertFalse($result);
    }

    public function testMemberMeetsAgeRequirementWithNoAgeRestrictions(): void
    {
        $this->skipIfPostgres();

        $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');

        // Find or create activity with no age limits
        $activity = $activityTable->find()
            ->where([
                'minimum_age IS' => null,
                'maximum_age IS' => null,
            ])
            ->first();

        if (!$activity) {
            // Create one without age restrictions
            $activity = $activityTable->find()->first();
            if (!$activity) {
                $this->markTestSkipped('No activities in test database');
            }
            $activity->minimum_age = null;
            $activity->maximum_age = null;
            $activityTable->saveOrFail($activity);
        }

        $result = $this->conditions->memberMeetsAgeRequirement(
            [],
            ['memberId' => self::ADMIN_MEMBER_ID, 'activityId' => $activity->id]
        );

        $this->assertTrue($result);
    }

    public function testMemberMeetsAgeRequirementResolvesContextPaths(): void
    {
        $this->skipIfPostgres();

        $activityTable = TableRegistry::getTableLocator()->get('Activities.Activities');
        $activity = $activityTable->find()->first();
        if (!$activity) {
            $this->markTestSkipped('No activities in test database');
        }

        $context = [
            'member' => ['id' => self::ADMIN_MEMBER_ID],
            'activity' => ['id' => $activity->id],
        ];

        // Should not throw - tests that context path resolution works
        $result = $this->conditions->memberMeetsAgeRequirement($context, [
            'memberId' => '$.member.id',
            'activityId' => '$.activity.id',
        ]);

        $this->assertIsBool($result);
    }
}
