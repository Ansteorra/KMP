<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
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
        WorkflowConditionRegistry::clear();
        WorkflowEntityRegistry::clear();
    }

    protected function tearDown(): void
    {
        WorkflowTriggerRegistry::clear();
        WorkflowActionRegistry::clear();
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
