<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\ApprovalContext;

use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\WarrantRosterApprovalContextRenderer;
use App\Test\TestCase\BaseTestCase;

class WarrantRosterApprovalContextRendererTest extends BaseTestCase
{
    public function testContextIncludesRosterCreatorMemberId(): void
    {
        $rosters = $this->getTableLocator()->get('WarrantRosters');
        $roster = $rosters->saveOrFail($rosters->newEntity([
            'name' => 'Requester Snapshot Roster',
            'approvals_required' => 1,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $instance = new WorkflowInstance([
            'entity_type' => 'WarrantRosters',
            'entity_id' => $roster->id,
        ]);

        $context = (new WarrantRosterApprovalContextRenderer())->render($instance);

        $this->assertSame(self::ADMIN_MEMBER_ID, $context->getRequesterMemberId());
        $this->assertSame('Admin von Admin', $context->getRequester());
    }

    public function testContextOmitsInvalidCreatorIdWhenDisplayNameCannotBeLoaded(): void
    {
        $rosters = $this->getTableLocator()->get('WarrantRosters');
        $roster = $rosters->saveOrFail($rosters->newEntity([
            'name' => 'Missing Requester Name Roster',
            'approvals_required' => 1,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $rosters->updateAll(['created_by' => 999999], ['id' => $roster->id]);
        $instance = new WorkflowInstance([
            'entity_type' => 'WarrantRosters',
            'entity_id' => $roster->id,
        ]);

        $context = (new WarrantRosterApprovalContextRenderer())->render($instance);

        $this->assertNull($context->getRequesterMemberId());
        $this->assertNull($context->getRequester());
    }
}
