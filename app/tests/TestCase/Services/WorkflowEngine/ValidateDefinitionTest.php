<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Services\WorkflowEngine\DefaultWorkflowVersionManager;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

/**
 * Unit tests for DefaultWorkflowVersionManager::validateDefinition().
 *
 * Uses reflection to call the protected method directly without DI.
 */
class ValidateDefinitionTest extends TestCase
{
    private DefaultWorkflowVersionManager $manager;
    private ReflectionMethod $validateMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new DefaultWorkflowVersionManager();
        $this->validateMethod = new ReflectionMethod($this->manager, 'validateDefinition');
        $this->validateMethod->setAccessible(true);
    }

    /**
     * Invoke the protected validateDefinition method.
     */
    private function validate(array $definition): array
    {
        $definition += ['schemaVersion' => '1.0'];

        return $this->validateMethod->invoke($this->manager, $definition);
    }

    public function testMissingSchemaVersionReturnsError(): void
    {
        $errors = $this->validateMethod->invoke($this->manager, ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]]);

        $this->assertContainsString('schemaVersion "1.0"', $errors);
    }

    // =====================================================
    // Empty / missing nodes
    // =====================================================

    public function testEmptyNodesArrayReturnsError(): void
    {
        $errors = $this->validate(['nodes' => []]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('non-empty "nodes"', $errors[0]);
    }

    public function testMissingNodesKeyReturnsError(): void
    {
        $errors = $this->validate([]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('non-empty "nodes"', $errors[0]);
    }

    public function testNodesNotArrayReturnsError(): void
    {
        $errors = $this->validate(['nodes' => 'not-an-array']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('non-empty "nodes"', $errors[0]);
    }

    // =====================================================
    // Trigger node validation
    // =====================================================

    public function testMissingTriggerNodeReturnsError(): void
    {
        $definition = ['nodes' => [
            'action1' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('exactly one trigger node', $errors);
    }

    public function testMultipleTriggerNodesReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'end1']]],
            'trigger2' => ['type' => 'trigger', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('exactly one trigger node', $errors);
    }

    // =====================================================
    // End node validation
    // =====================================================

    public function testMissingEndNodeReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'action1']]],
            'action1' => ['type' => 'action'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('at least one end node', $errors);
    }

    // =====================================================
    // Dangling target references
    // =====================================================

    public function testDanglingTargetReferenceReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'nonexistent']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('non-existent target', $errors);
    }

    public function testValidTargetReferencesNoError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'action1']]],
            'action1' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    // =====================================================
    // Orphan nodes
    // =====================================================

    public function testOrphanNodeReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'end1']]],
            'orphan' => ['type' => 'action', 'outputs' => []],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('not reachable from the trigger', $errors);
    }

    public function testAllNodesReachableNoOrphanError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'action1']]],
            'action1' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    // =====================================================
    // Loop node validation
    // =====================================================

    public function testLoopNodeWithoutMaxIterationsReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'loop1']]],
            'loop1' => ['type' => 'loop', 'config' => [], 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('maxIterations', $errors);
    }

    public function testLoopNodeWithMaxIterationsPasses(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'loop1']]],
            'loop1' => ['type' => 'loop', 'config' => ['maxIterations' => 10], 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    public function testLoopNodeWithMissingConfigReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'loop1']]],
            'loop1' => ['type' => 'loop', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertContainsString('maxIterations', $errors);
    }

    // =====================================================
    // Valid graphs
    // =====================================================

    public function testValidMinimalGraph(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'action1']]],
            'action1' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    public function testValidComplexMultiPathGraph(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'condition1']]],
            'condition1' => ['type' => 'condition', 'outputs' => [
                ['target' => 'action_approve'],
                ['target' => 'action_reject'],
            ]],
            'action_approve' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'action_reject' => ['type' => 'action', 'outputs' => [['target' => 'end2']]],
            'end1' => ['type' => 'end'],
            'end2' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    public function testValidGraphWithLoop(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'loop1']]],
            'loop1' => ['type' => 'loop', 'config' => ['maxIterations' => 5], 'outputs' => [['target' => 'action1']]],
            'action1' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    public function testMultipleEndNodesValid(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => [['target' => 'action1'], ['target' => 'action2']]],
            'action1' => ['type' => 'action', 'outputs' => [['target' => 'end1']]],
            'action2' => ['type' => 'action', 'outputs' => [['target' => 'end2']]],
            'end1' => ['type' => 'end'],
            'end2' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    // =====================================================
    // Edge cases
    // =====================================================

    public function testMultipleErrorsReturned(): void
    {
        // No trigger, no end → at least 2 errors
        $definition = ['nodes' => [
            'action1' => ['type' => 'action'],
        ]];
        $errors = $this->validate($definition);
        $this->assertGreaterThanOrEqual(2, count($errors));
    }

    public function testOutputAsPlainString(): void
    {
        // outputs can be plain strings (not wrapped in {target: ...})
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => ['action1']],
            'action1' => ['type' => 'action', 'outputs' => ['end1']],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $this->assertEmpty($errors);
    }

    public function testDanglingStringTargetReturnsError(): void
    {
        $definition = ['nodes' => [
            'trigger1' => ['type' => 'trigger', 'outputs' => ['missing_node']],
            'end1' => ['type' => 'end'],
        ]];
        $errors = $this->validate($definition);
        $danglingErrors = array_filter($errors, fn($e) => str_contains($e, 'non-existent target'));
        $this->assertNotEmpty($danglingErrors);
    }

    // =====================================================
    // Helper assertion
    // =====================================================

    /**
     * Assert that at least one error message contains the given substring.
     */
    private function assertContainsString(string $needle, array $errors): void
    {
        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error, $needle)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Expected an error containing '{$needle}'. Errors: " . implode('; ', $errors));
    }
}
