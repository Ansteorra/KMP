<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Award;
use Awards\Services\AwardApprovalResolverService;

class AwardApprovalResolverServiceTest extends BaseTestCase
{
    private AwardApprovalResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AwardApprovalResolverService();
    }

    public function testExplicitMemberApproverDoesNotRequireBranchResolution(): void
    {
        $step = new ApprovalProcessStep([
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
            'approver_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
        ]);
        $award = new Award(['branch_id' => null]);

        $members = $this->service->resolveApprovers($step, $award);

        $this->assertCount(1, $members);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$members[0]->id);
        $this->assertNull($this->service->resolveBranch($step, $award));
    }

    public function testRoleApproverResolutionIsScopedToAwardBranch(): void
    {
        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $memberRole = $memberRoles->find('current')
            ->where(['MemberRoles.branch_id IS NOT' => null])
            ->contain(['Members'])
            ->first();
        if ($memberRole === null) {
            $this->markTestSkipped('No branch-scoped current member role is available in seed data.');
        }

        $step = new ApprovalProcessStep([
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_ROLE,
            'approver_source_id' => (int)$memberRole->role_id,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
        ]);
        $award = new Award(['branch_id' => (int)$memberRole->branch_id]);

        $memberIds = array_map(
            static fn($member): int => (int)$member->id,
            $this->service->resolveApprovers($step, $award),
        );

        $this->assertContains((int)$memberRole->member_id, $memberIds);
    }
}
