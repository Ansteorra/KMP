<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\ApprovalContext;

use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantRoster;
use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\WarrantRosterApprovalContextRenderer;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

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

    public function testContextCountsOnlyPendingWarrantsInSharedRoster(): void
    {
        $rosters = $this->getTableLocator()->get('WarrantRosters');
        $roster = $rosters->saveOrFail($rosters->newEntity([
            'name' => 'Mixed status roster',
            'approvals_required' => 2,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $warrants = $this->getTableLocator()->get('Warrants');
        foreach ([Warrant::PENDING_STATUS, Warrant::REPLACED_STATUS] as $index => $status) {
            $warrants->saveOrFail($warrants->newEntity([
                'name' => 'Mixed status warrant ' . $index,
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'warrant_roster_id' => $roster->id,
                'entity_type' => 'WarrantRosterApprovalContextTests',
                'entity_id' => 992000 + $index,
                'status' => $status,
                'start_on' => DateTime::now(),
                'expires_on' => DateTime::now()->addMonths(6),
                'created_by' => self::ADMIN_MEMBER_ID,
            ], ['accessibleFields' => ['*' => true]]));
        }
        $instance = new WorkflowInstance([
            'entity_type' => 'WarrantRosters',
            'entity_id' => $roster->id,
        ]);

        $context = (new WarrantRosterApprovalContextRenderer())->render($instance);
        $fields = array_column($context->getFields(), 'value', 'label');

        $this->assertSame('1', $fields['Warrant Count']);
        $this->assertStringContainsString('1 warrant(s)', $context->getDescription());
    }

    public function testCompletedContextRetainsHistoricalWarrantCount(): void
    {
        $rosters = $this->getTableLocator()->get('WarrantRosters');
        $roster = $rosters->saveOrFail($rosters->newEntity([
            'name' => 'Completed mixed status roster',
            'approvals_required' => 2,
            'status' => WarrantRoster::STATUS_APPROVED,
            'created_by' => self::ADMIN_MEMBER_ID,
        ], ['accessibleFields' => ['*' => true]]));
        $warrants = $this->getTableLocator()->get('Warrants');
        foreach ([Warrant::CURRENT_STATUS, Warrant::REPLACED_STATUS] as $index => $status) {
            $warrants->saveOrFail($warrants->newEntity([
                'name' => 'Completed status warrant ' . $index,
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'warrant_roster_id' => $roster->id,
                'entity_type' => 'WarrantRosterApprovalContextTests',
                'entity_id' => 993000 + $index,
                'status' => $status,
                'start_on' => DateTime::now(),
                'expires_on' => DateTime::now()->addMonths(6),
                'created_by' => self::ADMIN_MEMBER_ID,
            ], ['accessibleFields' => ['*' => true]]));
        }
        $instance = new WorkflowInstance([
            'entity_type' => 'WarrantRosters',
            'entity_id' => $roster->id,
        ]);

        $context = (new WarrantRosterApprovalContextRenderer())->render($instance);
        $fields = array_column($context->getFields(), 'value', 'label');

        $this->assertSame('2', $fields['Warrant Count']);
        $this->assertStringContainsString('2 warrant(s)', $context->getDescription());
    }
}
