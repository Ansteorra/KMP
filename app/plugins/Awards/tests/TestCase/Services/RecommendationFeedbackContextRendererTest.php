<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\RecommendationFeedbackRequest;
use Awards\Services\RecommendationApprovalContextRenderer;
use Awards\Services\RecommendationFeedbackContextRenderer;
use Cake\I18n\DateTime;
use Cake\ORM\Table;

class RecommendationFeedbackContextRendererTest extends BaseTestCase
{
    private Table $requestsTable;
    private Table $itemsTable;
    private RecommendationFeedbackContextRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestsTable = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequests');
        $this->itemsTable = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequestItems');
        $this->renderer = new RecommendationFeedbackContextRenderer();
    }

    public function testGroupedRecommendationContextIncludesEachSnapshotReasonAndSubmitter(): void
    {
        $recommendationIds = $this->getTableLocator()->get('Awards.Recommendations')
            ->find()
            ->select(['id'])
            ->orderByAsc('id')
            ->limit(2)
            ->all()
            ->extract('id')
            ->toList();
        $this->assertCount(2, $recommendationIds);
        $request = $this->requestsTable->saveOrFail($this->requestsTable->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'status' => RecommendationFeedbackRequest::STATUS_PENDING,
            'message' => 'Please comment on the full group.',
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $this->itemsTable->saveOrFail($this->itemsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recommendation_id' => $recommendationIds[0],
            'snapshot' => [
                'recommendationId' => $recommendationIds[0],
                'memberScaName' => 'Candidate Person',
                'requesterScaName' => 'First Submitter',
                'branchName' => 'First Branch',
                'awardName' => 'First Award',
                'awardDomain' => 'Service',
                'awardLevel' => 'Grant',
                'reason' => 'First grouped recommendation reason.',
                'gatherings' => ['First Event'],
            ],
        ]));
        $this->itemsTable->saveOrFail($this->itemsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recommendation_id' => $recommendationIds[1],
            'snapshot' => [
                'recommendationId' => $recommendationIds[1],
                'memberScaName' => 'Candidate Person',
                'requesterScaName' => 'Second Submitter',
                'branchName' => 'Second Branch',
                'awardName' => 'Second Award',
                'awardDomain' => 'Arts',
                'awardLevel' => 'AoA',
                'reason' => 'Second grouped recommendation reason.',
            ],
        ]));
        $instance = new WorkflowInstance([
            'entity_type' => 'Awards.RecommendationFeedbackRequests',
            'entity_id' => $request->id,
            'started_at' => DateTime::now(),
        ]);

        $context = $this->renderer->render($instance);
        $fieldText = implode("\n", array_map(
            static fn(array $field): string => $field['label'] . ': ' . $field['value'],
            $context->getFields(),
        ));

        $this->assertStringContainsString('Recommendation 1', $fieldText);
        $this->assertStringContainsString('First Submitter', $fieldText);
        $this->assertStringContainsString('First grouped recommendation reason.', $fieldText);
        $this->assertStringContainsString('Recommendation 2', $fieldText);
        $this->assertStringContainsString('Second Submitter', $fieldText);
        $this->assertStringContainsString('Second grouped recommendation reason.', $fieldText);
        $this->assertStringContainsString('Please comment on the full group.', $fieldText);
    }

    public function testRecommendationApprovalContextIncludesRecommendationDetailsAndSourceUrl(): void
    {
        $recommendation = $this->getTableLocator()->get('Awards.Recommendations')
            ->find()
            ->contain(['Awards', 'Branches'])
            ->orderByAsc('Recommendations.id')
            ->firstOrFail();
        $renderer = new RecommendationApprovalContextRenderer();
        $instance = new WorkflowInstance([
            'entity_type' => 'Awards.Recommendations',
            'entity_id' => $recommendation->id,
            'started_at' => DateTime::now(),
        ]);

        $context = $renderer->render($instance);
        $fieldText = implode("\n", array_map(
            static fn(array $field): string => $field['label'] . ': ' . $field['value'],
            $context->getFields(),
        ));

        $this->assertTrue($renderer->canRender($instance));
        $this->assertStringContainsString((string)$recommendation->member_sca_name, $context->getTitle());
        $this->assertStringContainsString((string)$recommendation->member_sca_name, $fieldText);
        $this->assertStringContainsString((string)$recommendation->requester_sca_name, $fieldText);
        $this->assertStringContainsString((string)$recommendation->reason, $fieldText);
        $this->assertStringContainsString((string)$recommendation->award->name, $fieldText);
        $this->assertStringContainsString((string)$recommendation->branch->name, $fieldText);
        $this->assertStringContainsString('/awards/recommendations/view/' . $recommendation->id, $context->getEntityUrl());
    }
}
