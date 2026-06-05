<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use DateTimeImmutable;

class RecommendationsGridTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testGridDataIncludesAwardLevelFilterAndAppliesIt(): void
    {
        [$matchingAward, $otherAward] = $this->getAwardsWithDifferentLevels();

        $matchingReason = 'award-level-filter-match-' . uniqid();
        $otherReason = 'award-level-filter-other-' . uniqid();

        $this->createRecommendation($matchingAward->id, $matchingReason);
        $this->createRecommendation($otherAward->id, $otherReason);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-all',
            'search' => 'award-level-filter-',
            'filter' => [
                'level_name' => (string)$matchingAward->level_id,
            ],
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Award Level');
        $this->assertResponseContains($matchingReason);
        $this->assertResponseNotContains($otherReason);
    }

    public function testGridDataShowsOrderOfPrecedenceLink(): void
    {
        $appSettings = $this->getTableLocator()->get('AppSettings');
        $members = $this->getTableLocator()->get('Members');
        $awards = $this->getTableLocator()->get('Awards.Awards');

        $appSettings->setAppSetting(
            'Member.ExternalLink.Order of Precedence',
            'https://op.example.test/people/id/{{additional_info->OrderOfPrecedence_Id}}',
            'string',
            false,
        );

        $member = $members->get(self::ADMIN_MEMBER_ID);
        $member->additional_info = ['OrderOfPrecedence_Id' => '424242'];
        $savedMember = $members->save($member);
        $this->assertNotFalse($savedMember, 'Failed to save test member OP data');

        $award = $awards->find()->select(['id'])->firstOrFail();
        $reason = 'op-link-grid-test-' . uniqid();
        $this->createRecommendation($award->id, $reason);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-all',
            'search' => $reason,
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('https://op.example.test/people/id/424242');
    }

    public function testInApprovalSystemViewUsesApprovalQueueState(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $queuedReason = 'approval-queue-filter-match-' . uniqid();
        $closedRunReason = 'approval-queue-filter-closed-' . uniqid();
        $noQueueReason = 'approval-queue-filter-none-' . uniqid();

        $queuedRecommendation = $this->createRecommendation($award->id, $queuedReason);
        $closedRunRecommendation = $this->createRecommendation($award->id, $closedRunReason);
        $this->createRecommendation($award->id, $noQueueReason);

        $this->createApprovalRun(
            (int)$queuedRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $this->createApprovalRun(
            (int)$closedRunRecommendation->id,
            RecommendationApprovalRun::STATUS_APPROVED,
            'Approved',
        );

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-in-approval',
            'search' => 'approval-queue-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('In Approval');
        $this->assertResponseContains('Crown Review');
        $this->assertResponseContains($queuedReason);
        $this->assertResponseNotContains($closedRunReason);
        $this->assertResponseNotContains($noQueueReason);
    }

    /**
     * @return array{0: \Awards\Model\Entity\Award, 1: \Awards\Model\Entity\Award}
     */
    private function getAwardsWithDifferentLevels(): array
    {
        $awards = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id', 'level_id'])
            ->where(['Awards.deleted IS' => null])
            ->orderByAsc('Awards.id')
            ->all()
            ->toList();

        $firstAward = null;
        $secondAward = null;

        foreach ($awards as $award) {
            if ($firstAward === null) {
                $firstAward = $award;
                continue;
            }

            if ($award->level_id !== $firstAward->level_id) {
                $secondAward = $award;
                break;
            }
        }

        $this->assertNotNull($firstAward, 'Expected at least one seeded award');
        $this->assertNotNull($secondAward, 'Expected seeded awards with different levels');

        return [$firstAward, $secondAward];
    }

    private function createRecommendation(int $awardId, string $reason): Recommendation
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $states = Recommendation::getStates();
        $this->assertNotEmpty($states, 'Expected configured recommendation states');

        $rankResult = $recommendations->find()
            ->select(['max_rank' => $recommendations->find()->func()->max('stack_rank')])
            ->disableHydration()
            ->first();
        $maxStackRank = (int)($rankResult['max_rank'] ?? 0);

        $recommendation = $recommendations->newEntity([
            'stack_rank' => $maxStackRank + 1,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'state' => $states[0],
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => $reason,
            'call_into_court' => 'Never',
            'court_availability' => 'Any',
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]);

        $saved = $recommendations->save($recommendation);
        $this->assertNotFalse($saved, 'Failed to save test recommendation');

        return $saved;
    }

    private function createApprovalRun(int $recommendationId, string $status, string $stepLabel): void
    {
        $workflowDefinitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflowDefinition = $workflowDefinitions->newEntity([
            'name' => 'Recommendation Approval Queue Test ' . uniqid(),
            'slug' => 'recommendation-approval-queue-test-' . uniqid(),
            'trigger_type' => 'manual',
            'entity_type' => 'Awards.Recommendations',
            'is_active' => true,
            'execution_mode' => 'durable',
        ]);
        $workflowDefinition = $workflowDefinitions->saveOrFail($workflowDefinition);

        $workflowVersions = $this->getTableLocator()->get('WorkflowVersions');
        $workflowVersion = $workflowVersions->newEntity([
            'workflow_definition_id' => $workflowDefinition->id,
            'version_number' => 1,
            'definition' => ['nodes' => [], 'connections' => []],
            'status' => 'published',
            'published_by' => self::ADMIN_MEMBER_ID,
        ]);
        $workflowVersion = $workflowVersions->saveOrFail($workflowVersion);

        $workflowInstances = $this->getTableLocator()->get('WorkflowInstances');
        $workflowInstance = $workflowInstances->newEntity([
            'workflow_definition_id' => $workflowDefinition->id,
            'workflow_version_id' => $workflowVersion->id,
            'entity_type' => 'Awards.Recommendations',
            'entity_id' => $recommendationId,
            'status' => WorkflowInstance::STATUS_WAITING,
            'started_by' => self::ADMIN_MEMBER_ID,
        ]);
        $workflowInstance = $workflowInstances->saveOrFail($workflowInstance);

        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $approvalProcess = $approvalProcesses->newEntity([
            'name' => 'Recommendation Approval Queue Test ' . uniqid(),
            'description' => 'Test approval queue process',
            'is_active' => true,
        ]);
        $approvalProcess = $approvalProcesses->saveOrFail($approvalProcess);

        $approvalRuns = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $approvalRun = $approvalRuns->newEntity([
            'recommendation_id' => $recommendationId,
            'approval_process_id' => $approvalProcess->id,
            'workflow_instance_id' => $workflowInstance->id,
            'status' => $status,
            'current_step_key' => 'crown_review',
            'current_step_label' => $stepLabel,
            'started' => new DateTimeImmutable(),
        ]);
        $approvalRuns->saveOrFail($approvalRun);
    }
}
