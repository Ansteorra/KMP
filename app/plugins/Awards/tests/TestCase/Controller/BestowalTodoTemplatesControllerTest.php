<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\Permission;
use App\Services\ServiceResult;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\BestowalTodoTemplate;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Services\BestowalTodoMaterializationService;
use Cake\Cache\Cache;
use Cake\Http\Exception\MethodNotAllowedException;

class BestowalTodoTemplatesControllerTest extends HttpIntegrationTestCase
{
    private const CROWN_EMAIL = 'forest@ampdemo.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();
    }

    public function testIndexDoesNotRenderGlobalBestowalSyncAction(): void
    {
        $this->get('/awards/bestowal-todo-templates');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Sync Open Bestowals Now');
        $this->assertResponseNotContains('/sync-open-bestowals');
        $this->assertResponseContains('Add To-Do Template');
    }

    public function testCrownCanOpenIndexWithoutGlobalSyncAction(): void
    {
        $crown = $this->getTableLocator()->get('Members')->find()
            ->select(['id'])
            ->where(['email_address' => self::CROWN_EMAIL])
            ->firstOrFail();
        $this->grantTemplateSynchronizationPermission((int)$crown->id);
        $this->authenticateAsMember((int)$crown->id);

        $this->get('/awards/bestowal-todo-templates');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Sync Open Bestowals Now');
        $this->assertResponseNotContains('Add To-Do Template');
    }

    public function testViewShowsTemplateScopedSyncCountAndConfirmation(): void
    {
        $template = $this->createTemplateForSyncTest();
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->once())
            ->method('countOutdatedOpenBestowals')
            ->with((int)$template->id)
            ->willReturn(3);
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );

        $this->get('/awards/bestowal-todo-templates/view/' . $template->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Sync Outdated Bestowals (3)');
        $this->assertResponseContains(
            'action="/awards/bestowal-todo-templates/sync-template/' . $template->id . '"',
        );
        $this->assertResponseContains('data-confirm-message=');
        $this->assertResponseContains('data-confirm-title="Synchronize ' . h($template->name) . '"');
        $this->assertResponseContains('data-confirm-label="Sync Now"');
        $this->assertResponseContains('Synchronization never marks a bestowal Given.');
    }

    public function testViewDisablesSyncWhenTemplateBestowalsAreCurrent(): void
    {
        $template = $this->createTemplateForSyncTest();
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->method('countOutdatedOpenBestowals')->willReturn(0);
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );

        $this->get('/awards/bestowal-todo-templates/view/' . $template->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Sync Outdated Bestowals');
        $this->assertResponseContains('disabled');
        $this->assertResponseContains('aria-describedby="bestowal-sync-status-' . $template->id . '"');
        $this->assertResponseContains('All open bestowals assigned to this template are current.');
        $this->assertResponseNotContains('/sync-template/' . $template->id);
    }

    public function testSyncTemplateFlashesDetailedFailureSummary(): void
    {
        $template = $this->createTemplateForSyncTest();
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->once())
            ->method('syncOpenBestowalsForTemplate')
            ->with((int)$template->id, self::ADMIN_MEMBER_ID)
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

        $this->post('/awards/bestowal-todo-templates/sync-template/' . $template->id);

        $this->assertRedirect([
            'controller' => 'BestowalTodoTemplates',
            'action' => 'view',
            (int)$template->id,
        ]);
        $this->assertFlashMessage(
            'Processed 6 outdated open bestowal(s) for ' . $template->name
            . ': 3 changed, 1 unchanged, 1 skipped, and 1 failed. '
            . 'To-do changes: 4 created, 3 updated, 2 cancelled, and 1 reopened. '
            . 'Required-field checks: 2 completed, 1 reopened, and 5 skipped. '
            . 'Bestowals needing attention: #99. '
            . 'Failure categories: Bestowal to-do synchronization error (1). '
            . 'Skipped bestowals: #88. '
            . 'Skip reasons: No template assigned (1). '
            . 'One bestowal needs attention.',
            'flash',
        );
        $this->assertFlashElement('flash/error');
    }

    public function testSyncTemplateUsesActorAndFlashesSuccessSummary(): void
    {
        $template = $this->createTemplateForSyncTest();
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->once())
            ->method('syncOpenBestowalsForTemplate')
            ->with((int)$template->id, self::ADMIN_MEMBER_ID)
            ->willReturn(new ServiceResult(true, null, [
                'processedCount' => 2,
                'changedCount' => 1,
                'unchangedCount' => 1,
                'skippedCount' => 0,
                'failedCount' => 0,
                'failures' => [],
                'skips' => [],
                'createdCount' => 1,
                'updatedCount' => 0,
                'cancelledCount' => 0,
                'reopenedCount' => 0,
                'requiredCompletedCount' => 0,
                'requiredReopenedCount' => 0,
                'requiredSkippedCount' => 0,
            ]));
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );

        $this->post('/awards/bestowal-todo-templates/sync-template/' . $template->id);

        $this->assertRedirect([
            'controller' => 'BestowalTodoTemplates',
            'action' => 'view',
            (int)$template->id,
        ]);
        $this->assertFlashMessage(
            'Processed 2 outdated open bestowal(s) for ' . $template->name
            . ': 1 changed, 1 unchanged, 0 skipped, and 0 failed. '
            . 'To-do changes: 1 created, 0 updated, 0 cancelled, and 0 reopened. '
            . 'Required-field checks: 0 completed, 0 reopened, and 0 skipped.',
            'flash',
        );
        $this->assertFlashElement('flash/success');
    }

    public function testSyncTemplateRejectsGet(): void
    {
        $template = $this->createTemplateForSyncTest();
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->never())->method('syncOpenBestowalsForTemplate');
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );
        $this->disableErrorHandlerMiddleware();
        $this->expectException(MethodNotAllowedException::class);

        $this->get('/awards/bestowal-todo-templates/sync-template/' . $template->id);
    }

    public function testSyncTemplateRequiresExplicitAuthorization(): void
    {
        $template = $this->createTemplateForSyncTest();
        $service = $this->createMock(BestowalTodoMaterializationService::class);
        $service->expects($this->never())->method('syncOpenBestowalsForTemplate');
        $this->mockService(
            BestowalTodoMaterializationService::class,
            static fn() => $service,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->post('/awards/bestowal-todo-templates/sync-template/' . $template->id);

        $this->assertRedirectContains('/pages/unauthorized');
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

    private function grantTemplateSynchronizationPermission(int $memberId): void
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Bestowal Template Sync Controller Test ' . uniqid('', true),
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
            'scoping_rule' => Permission::SCOPE_GLOBAL,
        ]));

        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        foreach (['canIndex', 'canGridData'] as $policyMethod) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'Awards\\Policy\\BestowalTodoTemplatesTablePolicy',
                'policy_method' => $policyMethod,
            ]));
        }
        foreach (['canView', 'canSyncOpenBestowals'] as $policyMethod) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'Awards\\Policy\\BestowalTodoTemplatePolicy',
                'policy_method' => $policyMethod,
            ]));
        }

        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Bestowal Template Sync Controller Test Role ' . uniqid('', true),
        ]));
        $connection = $roles->getConnection();
        $connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
             VALUES (?, ?, NOW(), ?)',
            [(int)$role->id, (int)$permission->id, self::ADMIN_MEMBER_ID],
        );
        $connection->execute(
            'INSERT INTO member_roles
             (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type,
              created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)',
            [
                $memberId,
                (int)$role->id,
                self::KINGDOM_BRANCH_ID,
                '2020-01-01 00:00:00',
                '2100-01-01',
                self::ADMIN_MEMBER_ID,
                'Direct Grant',
                self::ADMIN_MEMBER_ID,
                self::ADMIN_MEMBER_ID,
            ],
        );
        Cache::clearGroup('security');
    }

    private function createTemplateForSyncTest(): BestowalTodoTemplate
    {
        $templates = $this->getTableLocator()->get('Awards.BestowalTodoTemplates');

        return $templates->saveOrFail($templates->newEntity([
            'name' => 'Template Scoped Sync ' . uniqid('', true),
            'description' => 'Controller test template.',
            'is_active' => true,
        ]));
    }
}
