<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoMaterializationService;

class BestowalTodoTemplatesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();
    }

    public function testIndexRendersConfirmedPostSyncAction(): void
    {
        $this->get('/awards/bestowal-todo-templates');

        $this->assertResponseOk();
        $this->assertResponseContains('Sync Open Bestowals Now');
        $this->assertResponseContains('action="/awards/bestowal-todo-templates/sync-open-bestowals"');
        $this->assertResponseContains('data-confirm-message=');
        $this->assertResponseContains('data-confirm-title="Synchronize open bestowals"');
        $this->assertResponseContains('data-confirm-label="Sync Now"');
        $this->assertResponseContains('Synchronization never marks a bestowal Given.');
        $this->assertResponseContains('data-turbo-frame="_top"');
    }

    public function testSyncOpenBestowalsUsesActorAndFlashesDetailedSummary(): void
    {
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->once())
            ->method('syncOpenBestowals')
            ->with(self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(false, 'One bestowal needs attention.', [
                'processedCount' => 6,
                'changedCount' => 3,
                'unchangedCount' => 1,
                'skippedCount' => 1,
                'failedCount' => 1,
                'failures' => [['bestowalId' => 99, 'reason' => '<strong>Invalid</strong> template.']],
                'skips' => [[
                    'bestowalId' => 88,
                    'templateId' => null,
                    'reason' => 'No template assigned.',
                ]],
                'createdCount' => 4,
                'updatedCount' => 3,
                'cancelledCount' => 2,
                'reopenedCount' => 1,
                'requiredCompletedCount' => 2,
                'requiredReopenedCount' => 1,
                'requiredSkippedCount' => 5,
            ]));
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );

        $this->post('/awards/bestowal-todo-templates/sync-open-bestowals');

        $this->assertRedirect(['controller' => 'BestowalTodoTemplates', 'action' => 'index']);
        $this->assertFlashMessage(
            'Processed 6 open bestowal(s): 3 changed, 1 unchanged, 1 skipped, and 1 failed. '
            . 'To-do changes: 4 created, 3 updated, 2 cancelled, and 1 reopened. '
            . 'Required-field checks: 2 completed, 1 reopened, and 5 skipped. '
            . 'Bestowals needing attention: #99. '
            . 'Failure categories: Bestowal to-do synchronization error (1). '
            . 'Skipped bestowals: #88. '
            . 'Skip reasons: No template assigned (1). '
            . 'One bestowal needs attention.',
            'flash',
        );
    }

    public function testSyncOpenBestowalsRejectsGet(): void
    {
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->never())->method('syncOpenBestowals');
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );

        $this->get('/awards/bestowal-todo-templates/sync-open-bestowals');

        $this->assertResponseCode(405);
    }

    public function testAddItemRestoresSoftDeletedDefinitionWithSubmittedFields(): void
    {
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $template = $templates->saveOrFail($templates->newEntity([
            'name' => 'Soft-delete item restore regression ' . uniqid('', true),
            'description' => 'Template for restoring a stable to-do key.',
            'is_active' => true,
        ]));
        $items = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $original = $items->saveOrFail($items->newEntity([
            'template_id' => (int)$template->id,
            'item_key' => 'agenda_restore_regression',
            'label' => 'Original Agenda Check',
            'description' => 'Original definition that will be soft deleted.',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_DYNAMIC,
            'assignee_source_key' => 'Awards.OriginalAgendaResolver',
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 10,
        ]));
        $originalId = (int)$original->id;
        $this->assertTrue($items->delete($original));
        $this->assertSame(1, $items->find('onlyTrashed')->where([
            'template_id' => (int)$template->id,
            'item_key' => 'agenda_restore_regression',
        ])->count());

        $this->post('/awards/bestowal-todo-templates/add-item/' . $template->id, [
            'item_key' => 'agenda_restore_regression',
            'label' => 'Restored Agenda Check',
            'description' => 'Updated definition submitted when the key was re-added.',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_DYNAMIC,
            'assignee_source_key' => 'Awards.UpdatedAgendaResolver',
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_ANCESTOR_TYPE,
            'branch_type' => 'Kingdom',
            'is_gating' => '0',
            'required_field' => BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
            'conditional_complete_on_assign' => '1',
            'auto_complete_when_satisfied' => '1',
            'sort_order' => '42',
        ]);

        $this->assertRedirect([
            'controller' => 'BestowalTodoTemplates',
            'action' => 'view',
            (int)$template->id,
        ]);
        $matchingItems = $items->find('withTrashed')->where([
            'template_id' => (int)$template->id,
            'item_key' => 'agenda_restore_regression',
        ])->all()->toList();
        $this->assertCount(1, $matchingItems);
        $restored = $matchingItems[0];
        $this->assertSame($originalId, (int)$restored->id);
        $this->assertNull($restored->deleted);
        $this->assertSame('Restored Agenda Check', $restored->label);
        $this->assertSame('Updated definition submitted when the key was re-added.', $restored->description);
        $this->assertSame(BestowalTodoTemplateItem::ASSIGNEE_TYPE_DYNAMIC, $restored->assignee_type);
        $this->assertNull($restored->assignee_source_id);
        $this->assertSame('Awards.UpdatedAgendaResolver', $restored->assignee_source_key);
        $this->assertSame(BestowalTodoTemplateItem::BRANCH_MODE_ANCESTOR_TYPE, $restored->branch_mode);
        $this->assertSame('Kingdom', $restored->branch_type);
        $this->assertFalse((bool)$restored->is_gating);
        $this->assertSame(BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT, $restored->required_field);
        $this->assertSame(42, (int)$restored->sort_order);
        $this->assertTrue((bool)$restored->required_field_config['conditional_complete_on_assign']);
        $this->assertTrue((bool)$restored->required_field_config['auto_complete_when_satisfied']);
    }

    public function testAddItemCannotMoveRestoredDefinitionToSubmittedTemplate(): void
    {
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');
        $authorizedTemplate = $templates->saveOrFail($templates->newEntity([
            'name' => 'Authorized restore template ' . uniqid('', true),
            'is_active' => true,
        ]));
        $submittedTemplate = $templates->saveOrFail($templates->newEntity([
            'name' => 'Submitted target template ' . uniqid('', true),
            'is_active' => true,
        ]));
        $items = $this->getTableLocator()->get('Awards.BestowalTodoTemplateItems');
        $deletedItem = $items->saveOrFail($items->newEntity([
            'template_id' => (int)$authorizedTemplate->id,
            'item_key' => 'restore_scope_regression',
            'label' => 'Original scoped item',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => true,
            'sort_order' => 1,
        ]));
        $this->assertTrue($items->delete($deletedItem));

        $this->post('/awards/bestowal-todo-templates/add-item/' . $authorizedTemplate->id, [
            'template_id' => (int)$submittedTemplate->id,
            'item_key' => 'restore_scope_regression',
            'label' => 'Restored scoped item',
            'assignee_type' => BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            'member_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
            'is_gating' => '1',
            'sort_order' => '1',
        ]);

        $this->assertRedirect([
            'controller' => 'BestowalTodoTemplates',
            'action' => 'view',
            (int)$authorizedTemplate->id,
        ]);
        $this->assertFlashMessage('The to-do item has been added.', 'flash');
        $restored = $items->find()->where(['id' => (int)$deletedItem->id])->firstOrFail();
        $this->assertSame((int)$authorizedTemplate->id, (int)$restored->template_id);
        $this->assertSame(0, $items->find()->where([
            'template_id' => (int)$submittedTemplate->id,
            'item_key' => 'restore_scope_regression',
        ])->count());
    }
}
