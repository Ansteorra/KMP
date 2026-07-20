<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine\StateMachine;

use App\Services\WorkflowEngine\StateMachine\StateMachineHandler;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for StateMachineHandler.
 */
class StateMachineHandlerTest extends TestCase
{
    private StateMachineHandler $handler;

    /**
     * Standard Awards-style config used across tests.
     */
    private array $awardsConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new StateMachineHandler();

        $this->awardsConfig = [
            'entityType' => 'Awards.Recommendations',
            'statusField' => 'status',
            'stateField' => 'state',
            'statuses' => [
                'In Progress' => [
                    'Submitted',
                    'In Consideration',
                    'Awaiting Feedback',
                    'Deferred till Later',
                    'King Approved',
                    'Queen Approved',
                ],
                'Scheduling' => ['Need to Schedule'],
                'To Give' => ['Scheduled', 'Announced Not Given'],
                'Closed' => ['Given', 'No Action'],
            ],
            'transitions' => [
                'Submitted' => ['In Consideration', 'No Action'],
                'In Consideration' => ['Awaiting Feedback', 'King Approved', 'Queen Approved', 'Deferred till Later', 'No Action'],
                'King Approved' => ['Queen Approved', 'Need to Schedule'],
                'Queen Approved' => ['King Approved', 'Need to Schedule'],
                'Need to Schedule' => ['Scheduled'],
                'Scheduled' => ['Given', 'Announced Not Given'],
                'Announced Not Given' => ['Need to Schedule', 'Given'],
            ],
            'stateRules' => [
                'Given' => [
                    'required' => ['gathering_id', 'given'],
                    'set' => ['close_reason' => 'Given'],
                ],
                'No Action' => [
                    'required' => ['close_reason'],
                ],
                'Scheduled' => [
                    'required' => ['gathering_id'],
                ],
            ],
            'auditLog' => [
                'table' => 'awards_recommendations_states_logs',
                'fields' => [
                    'from_state' => 'from_state',
                    'to_state' => 'to_state',
                    'from_status' => 'from_status',
                    'to_status' => 'to_status',
                ],
            ],
        ];
    }

    // =========================================================================
    // validateTransition()
    // =========================================================================

    public function testValidTransitionSubmittedToInConsideration(): void
    {
        $result = $this->handler->validateTransition('Submitted', 'In Consideration', $this->awardsConfig);
        $this->assertTrue($result);
    }

    public function testValidTransitionSubmittedToNoAction(): void
    {
        $result = $this->handler->validateTransition('Submitted', 'No Action', $this->awardsConfig);
        $this->assertTrue($result);
    }

    public function testInvalidTransitionSubmittedToGiven(): void
    {
        $result = $this->handler->validateTransition('Submitted', 'Given', $this->awardsConfig);
        $this->assertFalse($result);
    }

    public function testInvalidTransitionFromUnknownState(): void
    {
        $result = $this->handler->validateTransition('NonExistent', 'Given', $this->awardsConfig);
        $this->assertFalse($result);
    }

    public function testInvalidTransitionToSameState(): void
    {
        $result = $this->handler->validateTransition('Submitted', 'Submitted', $this->awardsConfig);
        $this->assertFalse($result);
    }

    public function testValidMultiStepPath(): void
    {
        // Submitted -> In Consideration -> King Approved -> Need to Schedule -> Scheduled
        $this->assertTrue($this->handler->validateTransition('Submitted', 'In Consideration', $this->awardsConfig));
        $this->assertTrue($this->handler->validateTransition('In Consideration', 'King Approved', $this->awardsConfig));
        $this->assertTrue($this->handler->validateTransition('King Approved', 'Need to Schedule', $this->awardsConfig));
        $this->assertTrue($this->handler->validateTransition('Need to Schedule', 'Scheduled', $this->awardsConfig));
    }

    public function testEmptyTransitionsConfig(): void
    {
        $config = ['transitions' => []];
        $result = $this->handler->validateTransition('Submitted', 'In Consideration', $config);
        $this->assertFalse($result);
    }

    // =========================================================================
    // resolveStatus()
    // =========================================================================

    public function testResolveStatusForSubmitted(): void
    {
        $result = $this->handler->resolveStatus('Submitted', $this->awardsConfig['statuses']);
        $this->assertSame('In Progress', $result);
    }

    public function testResolveStatusForGiven(): void
    {
        $result = $this->handler->resolveStatus('Given', $this->awardsConfig['statuses']);
        $this->assertSame('Closed', $result);
    }

    public function testResolveStatusForScheduled(): void
    {
        $result = $this->handler->resolveStatus('Scheduled', $this->awardsConfig['statuses']);
        $this->assertSame('To Give', $result);
    }

    public function testResolveStatusForNeedToSchedule(): void
    {
        $result = $this->handler->resolveStatus('Need to Schedule', $this->awardsConfig['statuses']);
        $this->assertSame('Scheduling', $result);
    }

    public function testResolveStatusReturnsNullForUnknownState(): void
    {
        $result = $this->handler->resolveStatus('Unknown', $this->awardsConfig['statuses']);
        $this->assertNull($result);
    }

    // =========================================================================
    // applySetRules()
    // =========================================================================

    public function testApplySetRulesSetsCloseReason(): void
    {
        $entityData = ['name' => 'Test Award'];
        $rules = ['set' => ['close_reason' => 'Given']];

        $result = $this->handler->applySetRules($entityData, $rules);

        $this->assertSame('Given', $result['close_reason']);
        $this->assertSame('Test Award', $result['name']);
    }

    public function testApplySetRulesOverwritesExistingField(): void
    {
        $entityData = ['close_reason' => 'Old reason'];
        $rules = ['set' => ['close_reason' => 'Given']];

        $result = $this->handler->applySetRules($entityData, $rules);

        $this->assertSame('Given', $result['close_reason']);
    }

    public function testApplySetRulesWithNoRules(): void
    {
        $entityData = ['name' => 'Test'];
        $rules = [];

        $result = $this->handler->applySetRules($entityData, $rules);

        $this->assertSame(['name' => 'Test'], $result);
    }

    public function testApplySetRulesMultipleFields(): void
    {
        $entityData = [];
        $rules = ['set' => ['field_a' => 'val_a', 'field_b' => 'val_b']];

        $result = $this->handler->applySetRules($entityData, $rules);

        $this->assertSame('val_a', $result['field_a']);
        $this->assertSame('val_b', $result['field_b']);
    }

    // =========================================================================
    // validateRequiredFields()
    // =========================================================================

    public function testValidateRequiredFieldsAllPresent(): void
    {
        $entityData = ['gathering_id' => 5, 'given' => '2025-01-01'];
        $result = $this->handler->validateRequiredFields($entityData, ['gathering_id', 'given']);

        $this->assertEmpty($result);
    }

    public function testValidateRequiredFieldsMissing(): void
    {
        $entityData = ['gathering_id' => 5];
        $result = $this->handler->validateRequiredFields($entityData, ['gathering_id', 'given']);

        $this->assertSame(['given'], $result);
    }

    public function testValidateRequiredFieldsEmptyString(): void
    {
        $entityData = ['close_reason' => ''];
        $result = $this->handler->validateRequiredFields($entityData, ['close_reason']);

        $this->assertSame(['close_reason'], $result);
    }

    public function testValidateRequiredFieldsNullValue(): void
    {
        $entityData = ['close_reason' => null];
        $result = $this->handler->validateRequiredFields($entityData, ['close_reason']);

        $this->assertSame(['close_reason'], $result);
    }

    public function testValidateRequiredFieldsEmptyList(): void
    {
        $entityData = [];
        $result = $this->handler->validateRequiredFields($entityData, []);

        $this->assertEmpty($result);
    }

    // =========================================================================
    // executeTransition() — full lifecycle
    // =========================================================================

    public function testExecuteTransitionSuccess(): void
    {
        $entityData = ['name' => 'Test Award', 'state' => 'Submitted', 'status' => 'In Progress'];

        $result = $this->handler->executeTransition($entityData, 'Submitted', 'In Consideration', $this->awardsConfig);

        $this->assertTrue($result['success']);
        $this->assertSame('In Consideration', $result['entityData']['state']);
        $this->assertSame('In Progress', $result['entityData']['status']);
        $this->assertSame('In Progress', $result['newStatus']);
    }

    public function testExecuteTransitionAppliesSetRules(): void
    {
        $entityData = [
            'state' => 'Scheduled',
            'status' => 'To Give',
            'gathering_id' => 5,
            'given' => '2025-06-15',
        ];

        $result = $this->handler->executeTransition($entityData, 'Scheduled', 'Given', $this->awardsConfig);

        $this->assertTrue($result['success']);
        $this->assertSame('Given', $result['entityData']['close_reason']);
        $this->assertSame('Given', $result['entityData']['state']);
        $this->assertSame('Closed', $result['entityData']['status']);
    }

    public function testExecuteTransitionFailsOnInvalidTransition(): void
    {
        $entityData = ['state' => 'Submitted'];

        $result = $this->handler->executeTransition($entityData, 'Submitted', 'Given', $this->awardsConfig);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not allowed', $result['error']);
    }

    public function testExecuteTransitionFailsOnMissingRequiredFields(): void
    {
        $entityData = ['state' => 'Scheduled', 'status' => 'To Give'];

        $result = $this->handler->executeTransition($entityData, 'Scheduled', 'Given', $this->awardsConfig);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required fields', $result['error']);
        $this->assertContains('gathering_id', $result['missingFields']);
        $this->assertContains('given', $result['missingFields']);
    }

    public function testExecuteTransitionChangesStatusCategory(): void
    {
        $entityData = ['state' => 'Queen Approved', 'status' => 'In Progress'];

        $result = $this->handler->executeTransition($entityData, 'Queen Approved', 'Need to Schedule', $this->awardsConfig);

        $this->assertTrue($result['success']);
        $this->assertSame('Need to Schedule', $result['entityData']['state']);
        $this->assertSame('Scheduling', $result['entityData']['status']);
        $this->assertSame('Scheduling', $result['newStatus']);
    }

    public function testExecuteTransitionSetRulesAppliedBeforeRequiredValidation(): void
    {
        // "Given" state requires 'gathering_id' and 'given', but also sets 'close_reason'.
        // Provide gathering_id and given, verify close_reason gets auto-set.
        $entityData = [
            'state' => 'Scheduled',
            'status' => 'To Give',
            'gathering_id' => 10,
            'given' => '2025-07-01',
        ];

        $result = $this->handler->executeTransition($entityData, 'Scheduled', 'Given', $this->awardsConfig);

        $this->assertTrue($result['success']);
        $this->assertSame('Given', $result['entityData']['close_reason']);
    }

    // =========================================================================
    // Custom config / edge cases
    // =========================================================================

    public function testCustomFieldNames(): void
    {
        $config = [
            'stateField' => 'workflow_state',
            'statusField' => 'workflow_status',
            'statuses' => ['Active' => ['Open', 'Pending']],
            'transitions' => ['Open' => ['Pending']],
            'stateRules' => [],
        ];

        $entityData = ['workflow_state' => 'Open', 'workflow_status' => 'Active'];

        $result = $this->handler->executeTransition($entityData, 'Open', 'Pending', $config);

        $this->assertTrue($result['success']);
        $this->assertSame('Pending', $result['entityData']['workflow_state']);
        $this->assertSame('Active', $result['entityData']['workflow_status']);
    }

    public function testTransitionWithNoStatusesConfig(): void
    {
        $config = [
            'transitions' => ['A' => ['B']],
            'stateRules' => [],
        ];

        $result = $this->handler->executeTransition(['state' => 'A'], 'A', 'B', $config);

        $this->assertTrue($result['success']);
        $this->assertNull($result['newStatus']);
    }

    public function testTerminalStateHasNoTransitions(): void
    {
        // "Given" has no outgoing transitions — verify it's a terminal state
        $result = $this->handler->validateTransition('Given', 'Submitted', $this->awardsConfig);
        $this->assertFalse($result);

        $result = $this->handler->validateTransition('No Action', 'Submitted', $this->awardsConfig);
        $this->assertFalse($result);
    }
}
