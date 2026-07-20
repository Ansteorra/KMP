<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowRegistry;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

/**
 * Unit tests for all workflow registry classes: Action, Condition, Trigger, Entity.
 */
class WorkflowRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WorkflowActionRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowTriggerRegistry::clear();
        WorkflowEntityRegistry::clear();
    }

    protected function tearDown(): void
    {
        WorkflowActionRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowTriggerRegistry::clear();
        WorkflowEntityRegistry::clear();
        parent::tearDown();
    }

    /**
     * Build a valid action entry with all required fields.
     */
    private function makeAction(string $name = 'Test.DoSomething'): array
    {
        return [
            'action' => $name,
            'label' => 'Do Something',
            'description' => 'Performs an action',
            'inputSchema' => ['field' => ['type' => 'string']],
            'outputSchema' => ['result' => ['type' => 'boolean']],
            'serviceClass' => 'App\\Services\\TestService',
            'serviceMethod' => 'doSomething',
        ];
    }

    /**
     * Build a valid condition entry with all required fields.
     */
    private function makeCondition(string $name = 'Test.CheckSomething'): array
    {
        return [
            'condition' => $name,
            'label' => 'Check Something',
            'description' => 'Evaluates a condition',
            'inputSchema' => ['field' => ['type' => 'string']],
            'evaluatorClass' => 'App\\Services\\TestCondition',
            'evaluatorMethod' => 'check',
        ];
    }

    /**
     * Build a valid trigger entry with all required fields.
     */
    private function makeTrigger(string $event = 'Test.SomethingHappened'): array
    {
        return [
            'event' => $event,
            'label' => 'Something Happened',
            'description' => 'Fires when something happens',
            'payloadSchema' => ['entityId' => ['type' => 'integer']],
        ];
    }

    /**
     * Build a valid entity entry with all required fields.
     */
    private function makeEntity(string $type = 'Test.TestEntity'): array
    {
        return [
            'entityType' => $type,
            'label' => 'Test Entity',
            'description' => 'A test entity',
            'tableClass' => 'TestEntities',
            'fields' => ['name' => ['type' => 'string', 'label' => 'Name']],
        ];
    }

    // =====================================================
    // WorkflowActionRegistry
    // =====================================================

    public function testActionRegistryRegisterAndLookup(): void
    {
        $action = $this->makeAction('MyPlugin.CreateRecord');
        WorkflowActionRegistry::register('MyPlugin', [$action]);

        $found = WorkflowActionRegistry::getAction('MyPlugin.CreateRecord');
        $this->assertNotNull($found);
        $this->assertEquals('MyPlugin.CreateRecord', $found['action']);
        $this->assertEquals('MyPlugin', $found['source']);
    }

    public function testActionRegistryGetActionReturnsNullForMissing(): void
    {
        $this->assertNull(WorkflowActionRegistry::getAction('NonExistent.Action'));
    }

    public function testActionRegistryRegisterThrowsOnMissingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'serviceMethod'");

        $incomplete = $this->makeAction();
        unset($incomplete['serviceMethod']);
        WorkflowActionRegistry::register('Bad', [$incomplete]);
    }

    public function testActionRegistryDuplicateSourceOverwrites(): void
    {
        $action1 = $this->makeAction('Plugin.ActionV1');
        WorkflowActionRegistry::register('Plugin', [$action1]);

        $action2 = $this->makeAction('Plugin.ActionV2');
        WorkflowActionRegistry::register('Plugin', [$action2]);

        $this->assertNull(WorkflowActionRegistry::getAction('Plugin.ActionV1'));
        $this->assertNotNull(WorkflowActionRegistry::getAction('Plugin.ActionV2'));
    }

    public function testActionRegistryGetAllActionsGroupedBySource(): void
    {
        WorkflowActionRegistry::register('PluginA', [$this->makeAction('A.Action1')]);
        WorkflowActionRegistry::register('PluginB', [$this->makeAction('B.Action1'), $this->makeAction('B.Action2')]);

        $all = WorkflowActionRegistry::getAllActions();
        $this->assertArrayHasKey('PluginA', $all);
        $this->assertArrayHasKey('PluginB', $all);
        $this->assertCount(1, $all['PluginA']);
        $this->assertCount(2, $all['PluginB']);
    }

    public function testActionRegistryGetActionsBySource(): void
    {
        WorkflowActionRegistry::register('PluginA', [$this->makeAction('A.Action1')]);

        $actions = WorkflowActionRegistry::getActionsBySource('PluginA');
        $this->assertCount(1, $actions);

        $empty = WorkflowActionRegistry::getActionsBySource('NonExistent');
        $this->assertEmpty($empty);
    }

    public function testActionRegistryGetRegisteredSources(): void
    {
        WorkflowActionRegistry::register('Alpha', [$this->makeAction('Alpha.A')]);
        WorkflowActionRegistry::register('Beta', [$this->makeAction('Beta.B')]);

        $sources = WorkflowActionRegistry::getRegisteredSources();
        $this->assertContains('Alpha', $sources);
        $this->assertContains('Beta', $sources);
    }

    public function testActionRegistryUnregister(): void
    {
        WorkflowActionRegistry::register('ToRemove', [$this->makeAction('ToRemove.A')]);
        $this->assertTrue(WorkflowActionRegistry::isRegistered('ToRemove'));

        WorkflowActionRegistry::unregister('ToRemove');
        $this->assertFalse(WorkflowActionRegistry::isRegistered('ToRemove'));
    }

    public function testActionRegistryClear(): void
    {
        WorkflowActionRegistry::register('PluginA', [$this->makeAction('A.A')]);
        WorkflowActionRegistry::clear();

        $this->assertEmpty(WorkflowActionRegistry::getAllActions());
        $this->assertFalse(WorkflowActionRegistry::isRegistered('PluginA'));
    }

    public function testActionRegistryIsRegistered(): void
    {
        $this->assertFalse(WorkflowActionRegistry::isRegistered('NotHere'));
        WorkflowActionRegistry::register('Here', [$this->makeAction('Here.A')]);
        $this->assertTrue(WorkflowActionRegistry::isRegistered('Here'));
    }

    public function testActionRegistryGetDebugInfo(): void
    {
        WorkflowActionRegistry::register('Debug', [
            $this->makeAction('Debug.A1'),
            $this->makeAction('Debug.A2'),
        ]);

        $debug = WorkflowActionRegistry::getDebugInfo();
        $this->assertEquals(2, $debug['total_actions']);
        $this->assertArrayHasKey('Debug', $debug['sources']);
        $this->assertEquals(2, $debug['sources']['Debug']['action_count']);
        $this->assertContains('Debug.A1', $debug['sources']['Debug']['actions']);
    }

    public function testActionRegistryGetForDesigner(): void
    {
        WorkflowActionRegistry::register('Designer', [$this->makeAction('Designer.Action')]);

        $designer = WorkflowActionRegistry::getForDesigner();
        $this->assertCount(1, $designer);
        $this->assertEquals('Designer.Action', $designer[0]['action']);
        $this->assertEquals('Designer', $designer[0]['source']);
        $this->assertArrayHasKey('inputSchema', $designer[0]);
        $this->assertArrayHasKey('outputSchema', $designer[0]);
        $this->assertArrayNotHasKey('serviceClass', $designer[0]);
    }

    // =====================================================
    // WorkflowConditionRegistry
    // =====================================================

    public function testConditionRegistryRegisterAndLookup(): void
    {
        WorkflowConditionRegistry::register('Plugin', [$this->makeCondition('Plugin.Check')]);

        $found = WorkflowConditionRegistry::getCondition('Plugin.Check');
        $this->assertNotNull($found);
        $this->assertEquals('Plugin.Check', $found['condition']);
        $this->assertEquals('Plugin', $found['source']);
    }

    public function testConditionRegistryGetConditionReturnsNullForMissing(): void
    {
        $this->assertNull(WorkflowConditionRegistry::getCondition('Missing.Condition'));
    }

    public function testConditionRegistryRegisterThrowsOnMissingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'evaluatorMethod'");

        $incomplete = $this->makeCondition();
        unset($incomplete['evaluatorMethod']);
        WorkflowConditionRegistry::register('Bad', [$incomplete]);
    }

    public function testConditionRegistryDuplicateSourceOverwrites(): void
    {
        WorkflowConditionRegistry::register('Plugin', [$this->makeCondition('Plugin.V1')]);
        WorkflowConditionRegistry::register('Plugin', [$this->makeCondition('Plugin.V2')]);

        $this->assertNull(WorkflowConditionRegistry::getCondition('Plugin.V1'));
        $this->assertNotNull(WorkflowConditionRegistry::getCondition('Plugin.V2'));
    }

    public function testConditionRegistryGetAllConditions(): void
    {
        WorkflowConditionRegistry::register('A', [$this->makeCondition('A.C1')]);
        WorkflowConditionRegistry::register('B', [$this->makeCondition('B.C1')]);

        $all = WorkflowConditionRegistry::getAllConditions();
        $this->assertCount(2, $all);
    }

    public function testConditionRegistryUnregister(): void
    {
        WorkflowConditionRegistry::register('Remove', [$this->makeCondition('Remove.C')]);
        $this->assertTrue(WorkflowConditionRegistry::isRegistered('Remove'));

        WorkflowConditionRegistry::unregister('Remove');
        $this->assertFalse(WorkflowConditionRegistry::isRegistered('Remove'));
    }

    public function testConditionRegistryClear(): void
    {
        WorkflowConditionRegistry::register('X', [$this->makeCondition('X.C')]);
        WorkflowConditionRegistry::clear();
        $this->assertEmpty(WorkflowConditionRegistry::getAllConditions());
    }

    public function testConditionRegistryGetDebugInfo(): void
    {
        WorkflowConditionRegistry::register('Debug', [
            $this->makeCondition('Debug.C1'),
            $this->makeCondition('Debug.C2'),
        ]);

        $debug = WorkflowConditionRegistry::getDebugInfo();
        $this->assertEquals(2, $debug['total_conditions']);
        $this->assertContains('Debug.C1', $debug['sources']['Debug']['conditions']);
    }

    public function testConditionRegistryGetForDesigner(): void
    {
        WorkflowConditionRegistry::register('Des', [$this->makeCondition('Des.C')]);

        $designer = WorkflowConditionRegistry::getForDesigner();
        $this->assertCount(1, $designer);
        $this->assertEquals('Des.C', $designer[0]['condition']);
        $this->assertArrayNotHasKey('evaluatorClass', $designer[0]);
    }

    // =====================================================
    // WorkflowTriggerRegistry
    // =====================================================

    public function testTriggerRegistryRegisterAndLookup(): void
    {
        WorkflowTriggerRegistry::register('Plugin', [$this->makeTrigger('Plugin.Event')]);

        $found = WorkflowTriggerRegistry::getTrigger('Plugin.Event');
        $this->assertNotNull($found);
        $this->assertEquals('Plugin.Event', $found['event']);
        $this->assertEquals('Plugin', $found['source']);
    }

    public function testTriggerRegistryGetTriggerReturnsNullForMissing(): void
    {
        $this->assertNull(WorkflowTriggerRegistry::getTrigger('Missing.Event'));
    }

    public function testTriggerRegistryRegisterThrowsOnMissingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'payloadSchema'");

        $incomplete = $this->makeTrigger();
        unset($incomplete['payloadSchema']);
        WorkflowTriggerRegistry::register('Bad', [$incomplete]);
    }

    public function testTriggerRegistryDuplicateSourceOverwrites(): void
    {
        WorkflowTriggerRegistry::register('Plugin', [$this->makeTrigger('Plugin.V1')]);
        WorkflowTriggerRegistry::register('Plugin', [$this->makeTrigger('Plugin.V2')]);

        $this->assertNull(WorkflowTriggerRegistry::getTrigger('Plugin.V1'));
        $this->assertNotNull(WorkflowTriggerRegistry::getTrigger('Plugin.V2'));
    }

    public function testTriggerRegistryGetAllTriggers(): void
    {
        WorkflowTriggerRegistry::register('A', [$this->makeTrigger('A.E1')]);
        WorkflowTriggerRegistry::register('B', [$this->makeTrigger('B.E1')]);

        $all = WorkflowTriggerRegistry::getAllTriggers();
        $this->assertCount(2, $all);
    }

    public function testTriggerRegistryGetTriggersBySource(): void
    {
        WorkflowTriggerRegistry::register('Src', [
            $this->makeTrigger('Src.E1'),
            $this->makeTrigger('Src.E2'),
        ]);

        $triggers = WorkflowTriggerRegistry::getTriggersBySource('Src');
        $this->assertCount(2, $triggers);

        $empty = WorkflowTriggerRegistry::getTriggersBySource('None');
        $this->assertEmpty($empty);
    }

    public function testTriggerRegistryUnregister(): void
    {
        WorkflowTriggerRegistry::register('Rem', [$this->makeTrigger('Rem.E')]);
        WorkflowTriggerRegistry::unregister('Rem');
        $this->assertFalse(WorkflowTriggerRegistry::isRegistered('Rem'));
    }

    public function testTriggerRegistryClear(): void
    {
        WorkflowTriggerRegistry::register('X', [$this->makeTrigger('X.E')]);
        WorkflowTriggerRegistry::clear();
        $this->assertEmpty(WorkflowTriggerRegistry::getAllTriggers());
    }

    public function testTriggerRegistryGetDebugInfo(): void
    {
        WorkflowTriggerRegistry::register('Debug', [
            $this->makeTrigger('Debug.E1'),
        ]);

        $debug = WorkflowTriggerRegistry::getDebugInfo();
        $this->assertEquals(1, $debug['total_triggers']);
        $this->assertContains('Debug.E1', $debug['sources']['Debug']['events']);
    }

    public function testTriggerRegistryGetForDesigner(): void
    {
        WorkflowTriggerRegistry::register('Des', [$this->makeTrigger('Des.E')]);

        $designer = WorkflowTriggerRegistry::getForDesigner();
        $this->assertCount(1, $designer);
        $this->assertEquals('Des.E', $designer[0]['event']);
        $this->assertArrayHasKey('payloadSchema', $designer[0]);
    }

    // =====================================================
    // WorkflowEntityRegistry
    // =====================================================

    public function testEntityRegistryRegisterAndLookup(): void
    {
        WorkflowEntityRegistry::register('Plugin', [$this->makeEntity('Plugin.Entity')]);

        $found = WorkflowEntityRegistry::getEntity('Plugin.Entity');
        $this->assertNotNull($found);
        $this->assertEquals('Plugin.Entity', $found['entityType']);
        $this->assertEquals('Plugin', $found['source']);
    }

    public function testEntityRegistryGetEntityReturnsNullForMissing(): void
    {
        $this->assertNull(WorkflowEntityRegistry::getEntity('Missing.Entity'));
    }

    public function testEntityRegistryRegisterThrowsOnMissingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required field 'fields'");

        $incomplete = $this->makeEntity();
        unset($incomplete['fields']);
        WorkflowEntityRegistry::register('Bad', [$incomplete]);
    }

    public function testEntityRegistryDuplicateSourceOverwrites(): void
    {
        WorkflowEntityRegistry::register('Plugin', [$this->makeEntity('Plugin.V1')]);
        WorkflowEntityRegistry::register('Plugin', [$this->makeEntity('Plugin.V2')]);

        $this->assertNull(WorkflowEntityRegistry::getEntity('Plugin.V1'));
        $this->assertNotNull(WorkflowEntityRegistry::getEntity('Plugin.V2'));
    }

    public function testEntityRegistryGetAllEntities(): void
    {
        WorkflowEntityRegistry::register('A', [$this->makeEntity('A.E')]);
        WorkflowEntityRegistry::register('B', [$this->makeEntity('B.E')]);

        $all = WorkflowEntityRegistry::getAllEntities();
        $this->assertCount(2, $all);
    }

    public function testEntityRegistryGetEntitiesBySource(): void
    {
        WorkflowEntityRegistry::register('Src', [
            $this->makeEntity('Src.E1'),
            $this->makeEntity('Src.E2'),
        ]);

        $entities = WorkflowEntityRegistry::getEntitiesBySource('Src');
        $this->assertCount(2, $entities);
    }

    public function testEntityRegistryUnregister(): void
    {
        WorkflowEntityRegistry::register('Rem', [$this->makeEntity('Rem.E')]);
        WorkflowEntityRegistry::unregister('Rem');
        $this->assertFalse(WorkflowEntityRegistry::isRegistered('Rem'));
    }

    public function testEntityRegistryClear(): void
    {
        WorkflowEntityRegistry::register('X', [$this->makeEntity('X.E')]);
        WorkflowEntityRegistry::clear();
        $this->assertEmpty(WorkflowEntityRegistry::getAllEntities());
    }

    public function testEntityRegistryGetDebugInfo(): void
    {
        WorkflowEntityRegistry::register('Debug', [
            $this->makeEntity('Debug.E1'),
            $this->makeEntity('Debug.E2'),
        ]);

        $debug = WorkflowEntityRegistry::getDebugInfo();
        $this->assertEquals(2, $debug['total_entities']);
        $this->assertContains('Debug.E1', $debug['sources']['Debug']['entity_types']);
    }

    public function testEntityRegistryGetForDesigner(): void
    {
        WorkflowEntityRegistry::register('Des', [$this->makeEntity('Des.E')]);

        $designer = WorkflowEntityRegistry::getForDesigner();
        $this->assertCount(1, $designer);
        $this->assertEquals('Des.E', $designer[0]['entityType']);
        $this->assertArrayHasKey('fields', $designer[0]);
        $this->assertArrayNotHasKey('tableClass', $designer[0]);
    }

    public function testEntityRegistryFieldsIncludedInDesigner(): void
    {
        $entity = $this->makeEntity('Des.WithFields');
        $entity['fields'] = [
            'name' => ['type' => 'string', 'label' => 'Name'],
            'status' => ['type' => 'string', 'label' => 'Status'],
        ];
        WorkflowEntityRegistry::register('Des', [$entity]);

        $designer = WorkflowEntityRegistry::getForDesigner();
        $this->assertCount(2, $designer[0]['fields']);
    }

    public function testEntityRegistryCanIncludeReflectedModelSchemasForDesigner(): void
    {
        $designer = WorkflowEntityRegistry::getForDesigner(true);
        $members = array_values(array_filter(
            $designer,
            fn(array $entity): bool => $entity['entityType'] === 'Core.Members',
        ));

        $this->assertNotEmpty($members);
        $this->assertArrayHasKey('id', $members[0]['fields']);
        $this->assertArrayHasKey('sca_name', $members[0]['fields']);
        $this->assertArrayNotHasKey('password', $members[0]['fields']);
        $this->assertArrayNotHasKey('password_token', $members[0]['fields']);
    }

    public function testEntityRegistryDoesNotAutoDiscoverSensitiveOperationalTables(): void
    {
        $designer = WorkflowEntityRegistry::getForDesigner(true);
        $entityTypes = array_column($designer, 'entityType');

        $this->assertNotContains('Core.ServicePrincipalTokens', $entityTypes);
        $this->assertNotContains('Core.ServicePrincipalAuditLogs', $entityTypes);
        $this->assertNotContains('Core.Notes', $entityTypes);
    }

    public function testEntityRegistryFiltersSensitiveFieldsFromRegisteredEntities(): void
    {
        $entity = $this->makeEntity('Core.Members');
        $entity['tableClass'] = 'Members';
        $entity['fields'] = [
            'sca_name' => ['type' => 'string', 'label' => 'SCA Name'],
            'password' => ['type' => 'string', 'label' => 'Password'],
            'password_token' => ['type' => 'string', 'label' => 'Password Token'],
        ];
        WorkflowEntityRegistry::register('Core', [$entity]);

        $found = WorkflowEntityRegistry::getEntityWithSchema('Core.Members');

        $this->assertNotNull($found);
        $this->assertArrayHasKey('sca_name', $found['fields']);
        $this->assertArrayNotHasKey('password', $found['fields']);
        $this->assertArrayNotHasKey('password_token', $found['fields']);
    }

    // =====================================================
    // Cross-registry independence
    // =====================================================

    public function testRegistriesAreIndependent(): void
    {
        WorkflowActionRegistry::register('Shared', [$this->makeAction('Shared.A')]);
        WorkflowConditionRegistry::register('Shared', [$this->makeCondition('Shared.C')]);
        WorkflowTriggerRegistry::register('Shared', [$this->makeTrigger('Shared.E')]);
        WorkflowEntityRegistry::register('Shared', [$this->makeEntity('Shared.Ent')]);

        WorkflowActionRegistry::clear();

        // Other registries should still have data
        $this->assertNotNull(WorkflowConditionRegistry::getCondition('Shared.C'));
        $this->assertNotNull(WorkflowTriggerRegistry::getTrigger('Shared.E'));
        $this->assertNotNull(WorkflowEntityRegistry::getEntity('Shared.Ent'));
    }

    public function testRegistryMultipleActionsFromSameSource(): void
    {
        WorkflowActionRegistry::register('Multi', [
            $this->makeAction('Multi.A1'),
            $this->makeAction('Multi.A2'),
            $this->makeAction('Multi.A3'),
        ]);

        $this->assertNotNull(WorkflowActionRegistry::getAction('Multi.A1'));
        $this->assertNotNull(WorkflowActionRegistry::getAction('Multi.A2'));
        $this->assertNotNull(WorkflowActionRegistry::getAction('Multi.A3'));

        $sources = WorkflowActionRegistry::getActionsBySource('Multi');
        $this->assertCount(3, $sources);
    }

    // =====================================================
    // Missing required field variations
    // =====================================================

    public function testActionRegistryMissingActionFieldThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $entry = $this->makeAction();
        unset($entry['action']);
        WorkflowActionRegistry::register('Bad', [$entry]);
    }

    public function testConditionRegistryMissingConditionFieldThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $entry = $this->makeCondition();
        unset($entry['condition']);
        WorkflowConditionRegistry::register('Bad', [$entry]);
    }

    public function testTriggerRegistryMissingEventFieldThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $entry = $this->makeTrigger();
        unset($entry['event']);
        WorkflowTriggerRegistry::register('Bad', [$entry]);
    }

    public function testEntityRegistryMissingEntityTypeFieldThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $entry = $this->makeEntity();
        unset($entry['entityType']);
        WorkflowEntityRegistry::register('Bad', [$entry]);
    }
}
