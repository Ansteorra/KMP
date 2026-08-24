<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Services;

use Activities\Services\ActivitiesApprovalContextRenderer;
use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;

class ActivitiesApprovalContextRendererTest extends BaseTestCase
{
    public function testContextIncludesAuthorizationMemberId(): void
    {
        $activity = $this->getTableLocator()->get('Activities.Activities')
            ->find()
            ->select(['id'])
            ->orderByAsc('id')
            ->firstOrFail();
        $instance = new WorkflowInstance([
            'entity_type' => 'Activities.Authorizations',
            'entity_id' => 999999,
            'context' => [
                'trigger' => [
                    'authorizationId' => 999999,
                    'activityId' => (int)$activity->id,
                    'memberId' => self::ADMIN_MEMBER_ID,
                    'isRenewal' => false,
                ],
            ],
        ]);

        $context = (new ActivitiesApprovalContextRenderer())->render($instance);

        $this->assertSame(self::ADMIN_MEMBER_ID, $context->getRequesterMemberId());
        $this->assertSame('Admin von Admin', $context->getRequester());
    }

    public function testContextOmitsInvalidMemberIdWhenDisplayNameCannotBeLoaded(): void
    {
        $instance = new WorkflowInstance([
            'entity_type' => 'Activities.Authorizations',
            'entity_id' => 999999,
            'context' => [
                'trigger' => [
                    'memberId' => 999999,
                ],
            ],
        ]);

        $context = (new ActivitiesApprovalContextRenderer())->render($instance);

        $this->assertNull($context->getRequesterMemberId());
        $this->assertNull($context->getRequester());
    }
}
