<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Award;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Table\BestowalsTable;

class BestowalsTableTest extends BaseTestCase
{
    private BestowalsTable $Bestowals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Bestowals = $this->getTableLocator()->get('Awards.Bestowals');
    }

    public function testAddBranchScopeQueryFiltersOnlyByAwardBranch(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $memberBranchId = (int)$member->branch_id;
        $this->assertGreaterThan(0, $memberBranchId);
        $this->assertNotSame(self::KINGDOM_BRANCH_ID, $memberBranchId);

        $inScopeAward = $this->createAward($memberBranchId, 'In Scope Bestowal Award');
        $outOfScopeAward = $this->createAward(self::KINGDOM_BRANCH_ID, 'Out of Scope Bestowal Award');

        $inScopeBestowal = $this->Bestowals->saveOrFail($this->Bestowals->newEntity([
            'member_id' => $member->id,
            'member_sca_name' => $member->sca_name,
            'award_id' => $inScopeAward->id,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]));
        $outOfScopeBestowal = $this->Bestowals->saveOrFail($this->Bestowals->newEntity([
            'member_id' => $member->id,
            'member_sca_name' => $member->sca_name,
            'award_id' => $outOfScopeAward->id,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]));

        $ids = $this->Bestowals->addBranchScopeQuery(
            $this->Bestowals->find()->where([
                'Bestowals.id IN' => [$inScopeBestowal->id, $outOfScopeBestowal->id],
            ]),
            [$memberBranchId],
        )
            ->select(['Bestowals.id'])
            ->orderByAsc('Bestowals.id')
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $this->assertSame([(int)$inScopeBestowal->id], $ids);
    }

    private function createAward(int $branchId, string $namePrefix): Award
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => $namePrefix . ' ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => $branchId,
            'is_active' => true,
        ]));
    }
}
