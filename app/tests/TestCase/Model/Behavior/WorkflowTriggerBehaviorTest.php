<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Behavior;

use App\Model\Behavior\WorkflowTriggerBehavior;
use ArrayObject;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;

/**
 * @covers \App\Model\Behavior\WorkflowTriggerBehavior
 */
class WorkflowTriggerBehaviorTest extends TestCase
{
    protected Table $table;
    protected WorkflowTriggerBehavior $behavior;

    /**
     * Captured workflow trigger events during a test.
     *
     * @var array<array{eventName: string, eventData: array, triggeredBy: int|null}>
     */
    protected array $dispatched = [];

    public function setUp(): void
    {
        parent::setUp();

        WorkflowTriggerBehavior::$suppressTriggers = false;
        $this->dispatched = [];

        // Listen for dispatched workflow triggers
        EventManager::instance()->on('Workflow.trigger', function (EventInterface $event) {
            $this->dispatched[] = $event->getData();
        });
    }

    public function tearDown(): void
    {
        WorkflowTriggerBehavior::$suppressTriggers = false;
        EventManager::instance()->off('Workflow.trigger');

        parent::tearDown();
    }

    /**
     * Create a table with the behavior attached using the given config.
     */
    protected function setupBehavior(array $config = []): void
    {
        $this->table = new Table([
            'table' => 'members',
            'alias' => 'Members',
        ]);
        $this->table->setPrimaryKey('id');
        $this->table->setSchema([
            'id' => ['type' => 'integer'],
            'sca_name' => ['type' => 'string'],
            'email_address' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'branch_id' => ['type' => 'integer'],
        ]);
        $this->table->addBehavior('WorkflowTrigger', $config);
    }

    /**
     * Build and fire afterSave event on the table.
     */
    protected function fireAfterSave(Entity $entity, array $options = []): void
    {
        $this->table->dispatchEvent('Model.afterSave', [
            'entity' => $entity,
            'options' => new ArrayObject($options),
        ]);
    }

    /**
     * Build and fire afterDelete event on the table.
     */
    protected function fireAfterDelete(Entity $entity, array $options = []): void
    {
        $this->table->dispatchEvent('Model.afterDelete', [
            'entity' => $entity,
            'options' => new ArrayObject($options),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────

    public function testTriggerFiresOnEntityCreate(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Test']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('Members.Registered', $this->dispatched[0]['eventName']);
    }

    public function testTriggerFiresOnEntityUpdate(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => 'Members.Updated',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Updated Name']);
        $entity->setNew(false);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('Members.Updated', $this->dispatched[0]['eventName']);
    }

    public function testTriggerFiresOnEntityDelete(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterDelete' => 'Members.Deleted',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Deleted']);
        $entity->setNew(false);

        $this->fireAfterDelete($entity);

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('Members.Deleted', $this->dispatched[0]['eventName']);
    }

    public function testContextContainsEntityData(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
            ],
            'includeChangedFields' => false,
        ]);

        $entity = new Entity([
            'id' => 42,
            'sca_name' => 'Lord Test',
            'email_address' => 'test@example.com',
        ]);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $context = $this->dispatched[0]['eventData']['trigger'];
        $this->assertSame(42, $context['entity_id']);
        $this->assertSame('create', $context['event']);
        $this->assertSame('members', $context['table']);
        $this->assertSame('Lord Test', $context['entity']['sca_name']);
        $this->assertSame('test@example.com', $context['entity']['email_address']);
    }

    public function testContextContainsChangedFields(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => 'Members.Updated',
            ],
            'includeChangedFields' => true,
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Old Name', 'status' => 'active']);
        $entity->setNew(false);
        $entity->clean();
        // Now modify sca_name — this marks it dirty with original = 'Old Name'
        $entity->set('sca_name', 'New Name');

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $context = $this->dispatched[0]['eventData']['trigger'];
        $this->assertArrayHasKey('changes', $context);
        $this->assertArrayHasKey('sca_name', $context['changes']);
        $this->assertArrayHasKey('old', $context['changes']['sca_name']);
        $this->assertArrayHasKey('new', $context['changes']['sca_name']);
    }

    public function testContextFieldsFilteringLimitsEntityData(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
            ],
            'contextFields' => ['id', 'sca_name'],
            'includeChangedFields' => false,
        ]);

        $entity = new Entity([
            'id' => 1,
            'sca_name' => 'Lord Test',
            'email_address' => 'secret@example.com',
            'status' => 'active',
        ]);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $entityData = $this->dispatched[0]['eventData']['trigger']['entity'];
        $this->assertArrayHasKey('id', $entityData);
        $this->assertArrayHasKey('sca_name', $entityData);
        $this->assertArrayNotHasKey('email_address', $entityData);
        $this->assertArrayNotHasKey('status', $entityData);
    }

    public function testOnlyIfChangedFilteringWorks(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => [
                    'trigger' => 'Members.StatusChanged',
                    'onlyIfChanged' => ['status'],
                ],
            ],
        ]);

        // Update without changing 'status' — should NOT fire
        $entity = new Entity(['id' => 1, 'sca_name' => 'Updated', 'status' => 'active']);
        $entity->setNew(false);
        $entity->setDirty('sca_name', true);
        $entity->setDirty('status', false);

        $this->fireAfterSave($entity);

        $this->assertCount(0, $this->dispatched, 'Trigger should not fire when monitored field did not change');
    }

    public function testOnlyIfChangedFiresWhenFieldChanged(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => [
                    'trigger' => 'Members.StatusChanged',
                    'onlyIfChanged' => ['status'],
                ],
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Updated', 'status' => 'inactive']);
        $entity->setNew(false);
        $entity->setDirty('status', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('Members.StatusChanged', $this->dispatched[0]['eventName']);
    }

    public function testSuppressionFlagPreventsTriggersFromFiring(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
                'afterSave.existing' => 'Members.Updated',
                'afterDelete' => 'Members.Deleted',
            ],
        ]);

        WorkflowTriggerBehavior::$suppressTriggers = true;

        $entity = new Entity(['id' => 1, 'sca_name' => 'Test']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);
        $this->fireAfterDelete($entity);

        $this->assertCount(0, $this->dispatched, 'No triggers should fire when suppressed');
    }

    public function testNoTriggerWhenNoMatchingEventConfig(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterDelete' => 'Members.Deleted',
            ],
        ]);

        // afterSave with no afterSave config — should not fire
        $entity = new Entity(['id' => 1, 'sca_name' => 'Test']);
        $entity->setNew(true);

        $this->fireAfterSave($entity);

        $this->assertCount(0, $this->dispatched);
    }

    public function testAfterSaveNewDoesNotFireOnUpdate(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Existing']);
        $entity->setNew(false);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        $this->assertCount(0, $this->dispatched, 'afterSave.new should not fire on update');
    }

    public function testAfterSaveExistingDoesNotFireOnCreate(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => 'Members.Updated',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Brand New']);
        $entity->setNew(true);

        $this->fireAfterSave($entity);

        $this->assertCount(0, $this->dispatched, 'afterSave.existing should not fire on create');
    }

    public function testAfterSaveGenericFiresOnBothCreateAndUpdate(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave' => 'Members.Changed',
            ],
        ]);

        // Create
        $entity = new Entity(['id' => 1, 'sca_name' => 'New']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);
        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('create', $this->dispatched[0]['eventData']['trigger']['event']);

        // Reset dispatched
        $this->dispatched = [];

        // Update
        $entity2 = new Entity(['id' => 2, 'sca_name' => 'Updated']);
        $entity2->setNew(false);
        $entity2->setDirty('sca_name', true);
        $this->fireAfterSave($entity2);

        $this->assertCount(1, $this->dispatched);
        $this->assertSame('update', $this->dispatched[0]['eventData']['trigger']['event']);
    }

    public function testMultipleTriggersForSameEvent(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
                'afterSave' => 'Members.Changed',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'New Member']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        // Both 'afterSave.new' and 'afterSave' should fire
        $this->assertCount(2, $this->dispatched);
        $triggerNames = array_column($this->dispatched, 'eventName');
        $this->assertContains('Members.Registered', $triggerNames);
        $this->assertContains('Members.Changed', $triggerNames);
    }

    public function testNullUserIdWhenNotAuthenticated(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
            ],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Test']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $this->assertNull($this->dispatched[0]['triggeredBy']);
        $this->assertNull($this->dispatched[0]['eventData']['trigger']['user_id']);
    }

    public function testContextFieldsAlsoFiltersChanges(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => 'Members.Updated',
            ],
            'contextFields' => ['id', 'status'],
            'includeChangedFields' => true,
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'New Name', 'status' => 'inactive']);
        $entity->setNew(false);
        $entity->setDirty('sca_name', true);
        $entity->setDirty('status', true);

        $this->fireAfterSave($entity);

        $this->assertCount(1, $this->dispatched);
        $changes = $this->dispatched[0]['eventData']['trigger']['changes'];
        // 'status' should be in changes (it's in contextFields)
        $this->assertArrayHasKey('status', $changes);
        // 'sca_name' should NOT be in changes (not in contextFields)
        $this->assertArrayNotHasKey('sca_name', $changes);
    }

    public function testDeleteContextDoesNotIncludeChanges(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterDelete' => 'Members.Deleted',
            ],
            'includeChangedFields' => true,
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Deleted']);
        $entity->setNew(false);

        $this->fireAfterDelete($entity);

        $this->assertCount(1, $this->dispatched);
        $context = $this->dispatched[0]['eventData']['trigger'];
        $this->assertSame('delete', $context['event']);
        $this->assertArrayNotHasKey('changes', $context, 'Delete events should not include changes');
    }

    public function testContextAliasesExposeTopLevelPayloadKeys(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => 'Members.StatusChanged',
            ],
            'contextFields' => ['id', 'branch_id'],
            'contextAliases' => [
                'memberId' => 'id',
                'branchId' => 'branch_id',
            ],
            'includeChangedFields' => false,
        ]);

        $entity = new Entity(['id' => 42, 'branch_id' => 7, 'status' => 'active']);
        $entity->setNew(false);
        $entity->setDirty('status', true);

        $this->fireAfterSave($entity);

        $context = $this->dispatched[0]['eventData']['trigger'];
        $this->assertSame(42, $context['memberId']);
        $this->assertSame(7, $context['branchId']);
    }

    public function testEventDataKeyCanDispatchContextDirectly(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.existing' => 'Members.StatusChanged',
            ],
            'eventDataKey' => null,
            'includeChangedFields' => false,
        ]);

        $entity = new Entity(['id' => 42, 'status' => 'active']);
        $entity->setNew(false);
        $entity->setDirty('status', true);

        $this->fireAfterSave($entity);

        $eventData = $this->dispatched[0]['eventData'];
        $this->assertArrayNotHasKey('trigger', $eventData);
        $this->assertSame(42, $eventData['entity_id']);
        $this->assertSame('update', $eventData['event']);
    }

    public function testEmptyTriggersConfigFiresNothing(): void
    {
        $this->setupBehavior([
            'triggers' => [],
        ]);

        $entity = new Entity(['id' => 1, 'sca_name' => 'Test']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);

        $this->fireAfterSave($entity);
        $this->fireAfterDelete($entity);

        $this->assertCount(0, $this->dispatched);
    }

    public function testSuppressionFlagCanBeToggledBackOn(): void
    {
        $this->setupBehavior([
            'triggers' => [
                'afterSave.new' => 'Members.Registered',
            ],
        ]);

        // Suppress
        WorkflowTriggerBehavior::$suppressTriggers = true;

        $entity = new Entity(['id' => 1, 'sca_name' => 'Suppressed']);
        $entity->setNew(true);
        $entity->setDirty('sca_name', true);
        $this->fireAfterSave($entity);
        $this->assertCount(0, $this->dispatched);

        // Un-suppress
        WorkflowTriggerBehavior::$suppressTriggers = false;

        $entity2 = new Entity(['id' => 2, 'sca_name' => 'Not Suppressed']);
        $entity2->setNew(true);
        $entity2->setDirty('sca_name', true);
        $this->fireAfterSave($entity2);
        $this->assertCount(1, $this->dispatched);
        $this->assertSame('Members.Registered', $this->dispatched[0]['eventName']);
    }
}
