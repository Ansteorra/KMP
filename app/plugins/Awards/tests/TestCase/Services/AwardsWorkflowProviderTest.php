<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowApproverResolverRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use Awards\Services\AwardsWorkflowProvider;
use Awards\Services\RecommendationFeedbackService;
use Cake\TestSuite\TestCase;

class AwardsWorkflowProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WorkflowTriggerRegistry::clear();
        WorkflowActionRegistry::clear();
        WorkflowApproverResolverRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowEntityRegistry::clear();
    }

    protected function tearDown(): void
    {
        WorkflowTriggerRegistry::clear();
        WorkflowActionRegistry::clear();
        WorkflowApproverResolverRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowEntityRegistry::clear();

        parent::tearDown();
    }

    public function testRecommendationFeedbackTriggersArePublishedForDesigner(): void
    {
        AwardsWorkflowProvider::register();

        $designerTriggers = WorkflowTriggerRegistry::getForDesigner();
        $triggersByEvent = array_column($designerTriggers, null, 'event');

        foreach ($this->feedbackTriggerEvents() as $eventName) {
            $this->assertArrayHasKey($eventName, $triggersByEvent);
            $this->assertSame('Awards', $triggersByEvent[$eventName]['source']);
            $this->assertArrayHasKey('feedbackRequestId', $triggersByEvent[$eventName]['payloadSchema']);
            $this->assertArrayHasKey('recipientId', $triggersByEvent[$eventName]['payloadSchema']);
            $this->assertArrayHasKey('requesterEmail', $triggersByEvent[$eventName]['payloadSchema']);
            $this->assertArrayHasKey('expires_on', $triggersByEvent[$eventName]['payloadSchema']);
            $this->assertArrayHasKey('expiresOn', $triggersByEvent[$eventName]['payloadSchema']);
            $this->assertArrayHasKey('recommendations', $triggersByEvent[$eventName]['payloadSchema']);
        }
    }

    public function testApprovalProcessActionsAndApproverResolverAreRegistered(): void
    {
        AwardsWorkflowProvider::register();

        $startAction = WorkflowActionRegistry::getAction('Awards.StartApprovalProcess');
        $this->assertNotNull($startAction);
        $this->assertSame('startApprovalProcess', $startAction['serviceMethod']);
        $this->assertArrayHasKey('approvalApproverConfig', $startAction['outputSchema']);

        $advanceAction = WorkflowActionRegistry::getAction('Awards.AdvanceApprovalProcess');
        $this->assertNotNull($advanceAction);
        $this->assertSame('advanceApprovalProcess', $advanceAction['serviceMethod']);

        $resolver = WorkflowApproverResolverRegistry::getResolver('Awards.ResolveApprovalStepApprovers');
        $this->assertNotNull($resolver);
        $this->assertSame('resolveConfiguredApproverIds', $resolver['serviceMethod']);
        $this->assertArrayHasKey('award_approval_approver_type', $resolver['configSchema']);
        $this->assertArrayHasKey('award_approval_approver_source_id', $resolver['configSchema']);
        $this->assertArrayNotHasKey('eligible_member_ids', $resolver['configSchema']);
    }

    public function testBestowalTransitionTriggerPublishesTransitionDataSchema(): void
    {
        AwardsWorkflowProvider::register();

        $designerTriggers = WorkflowTriggerRegistry::getForDesigner();
        $triggersByEvent = array_column($designerTriggers, null, 'event');
        $transitionTrigger = $triggersByEvent['Awards.BestowalTransitionRequested'];

        $this->assertSame(
            ['type' => 'object', 'label' => 'Transition Data'],
            $transitionTrigger['payloadSchema']['data'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function feedbackTriggerEvents(): array
    {
        return [
            RecommendationFeedbackService::EVENT_FEEDBACK_REQUESTED,
            RecommendationFeedbackService::EVENT_FEEDBACK_RETURNED,
            RecommendationFeedbackService::EVENT_FEEDBACK_RETRACTED,
            RecommendationFeedbackService::EVENT_FEEDBACK_EXPIRED,
        ];
    }
}
