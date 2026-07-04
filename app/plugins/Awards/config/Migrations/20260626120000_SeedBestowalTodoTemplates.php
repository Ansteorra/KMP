<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Seed the default bestowal to-do templates, their permission-gated checklist
 * items, the matching tier permissions, and assign each template to existing
 * awards based on the award's owning branch type.
 *
 * Three system-wide templates are seeded, one per award tier:
 *
 *   - "Kingdom Award"      -> awards owned by a `Kingdom` branch     (Crown perms)
 *   - "Principality Award" -> awards owned by a `Principality` branch (Principality perms)
 *   - "Baronial Award"     -> awards owned by a `Local Group` branch  (Baronial perms)
 *
 * checks; "Event Scheduled", "Added to Agenda", and "Given" are gating (they
 * must be complete before a bestowal can be marked given). Each check is gated by a tier-specific
 * permission so admins can wire the appropriate roles/offices later.
 */
class SeedBestowalTodoTemplates extends BaseMigration
{
    /**
     * Tier definition: template name => [description, branch types, permission prefix].
     *
     * @var array<string, array{description: string, branch_types: list<string>, prefix: string}>
     */
    private const TEMPLATES = [
        'Kingdom Award' => [
            'description' => 'Parallel coordination checklist for kingdom-level award bestowals. '
                . 'Event Scheduled, Added to Agenda, and Given are required to mark the bestowal given.',
            'branch_types' => ['Kingdom'],
            'prefix' => 'Crown',
        ],
        'Principality Award' => [
            'description' => 'Parallel coordination checklist for principality-level award bestowals. '
                . 'Event Scheduled, Added to Agenda, and Given are required to mark the bestowal given.',
            'branch_types' => ['Principality'],
            'prefix' => 'Principality',
        ],
        'Baronial Award' => [
            'description' => 'Parallel coordination checklist for baronial (local group) award bestowals. '
                . 'Event Scheduled, Added to Agenda, and Given are required to mark the bestowal given.',
            'branch_types' => ['Local Group'],
            'prefix' => 'Baronial',
        ],
    ];

    /**
     * Checklist item definition shared by every template. The owning template's
     * permission prefix is combined with `permission_suffix` to resolve the
     * tier-specific gating permission.
     *
     * @var list<array{key: string, label: string, permission_suffix: string, is_gating: bool, sort: int}>
     */
    private const ITEMS = [
        [
            'key' => 'has_scroll',
            'label' => 'Has Scroll',
            'permission_suffix' => 'Scroll Management',
            'is_gating' => false,
            'sort' => 10,
        ],
        [
            'key' => 'regalia_allotted',
            'label' => 'Regalia Allotted For',
            'permission_suffix' => 'Regalia Management',
            'is_gating' => false,
            'sort' => 20,
        ],
        [
            'key' => 'event_scheduled',
            'label' => 'Event Scheduled',
            'permission_suffix' => 'Award Schedule Management',
            'is_gating' => true,
            'sort' => 30,
        ],
        [
            'key' => 'added_to_agenda',
            'label' => 'Added to Agenda',
            'permission_suffix' => 'Court Management',
            'is_gating' => true,
            'sort' => 40,
        ],
        [
            'key' => 'given',
            'label' => 'Given',
            'permission_suffix' => 'Court Reporter',
            'is_gating' => true,
            'sort' => 50,
        ],
    ];

    /**
     * Permission prefixes paired with the five suffixes seed the 15 tier
     * permissions ("Crown Scroll Management", "Baronial Court Reporter", ...).
     *
     * @var list<string>
     */
    private const PERMISSION_PREFIXES = ['Crown', 'Principality', 'Baronial'];

    /**
     * @return void
     */
    public function up(): void
    {
        $permissionsTbl = TableRegistry::getTableLocator()->get('Permissions');
        $templatesTbl = TableRegistry::getTableLocator()->get('Awards.BestowalTodoTemplates');
        $itemsTbl = TableRegistry::getTableLocator()->get('Awards.BestowalTodoTemplateItems');

        // 1. Seed the 15 tier permissions idempotently and capture their ids.
        $permissionIds = [];
        foreach (self::PERMISSION_PREFIXES as $prefix) {
            foreach (self::ITEMS as $item) {
                $name = $prefix . ' ' . $item['permission_suffix'];
                if (isset($permissionIds[$name])) {
                    continue;
                }

                $existing = $permissionsTbl->find()->where(['name' => $name])->first();
                if ($existing) {
                    if ($existing->requires_warrant) {
                        $existing = $permissionsTbl->patchEntity($existing, ['requires_warrant' => false]);
                        $permissionsTbl->saveOrFail($existing);
                    }
                    $permissionIds[$name] = $existing->id;
                    continue;
                }

                $perm = $permissionsTbl->newEntity([
                    'name' => $name,
                    'require_active_membership' => true,
                    'require_active_background_check' => false,
                    'require_min_age' => 0,
                    'is_system' => true,
                    'is_super_user' => false,
                    'requires_warrant' => false,
                ]);
                if (!$permissionsTbl->save($perm)) {
                    throw new RuntimeException(sprintf(
                        'Failed to seed permission "%s": %s',
                        $name,
                        json_encode($perm->getErrors()),
                    ));
                }
                $permissionIds[$name] = $perm->id;
            }
        }

        // 2. Seed the three templates and their five permission-gated items.
        $templateIds = [];
        foreach (self::TEMPLATES as $templateName => $meta) {
            $template = $templatesTbl->find()->where(['name' => $templateName])->first();
            if (!$template) {
                $template = $templatesTbl->newEntity([
                    'name' => $templateName,
                    'description' => $meta['description'],
                    'is_active' => true,
                    'branch_id' => null,
                ]);
                if (!$templatesTbl->save($template)) {
                    throw new RuntimeException(sprintf(
                        'Failed to seed template "%s": %s',
                        $templateName,
                        json_encode($template->getErrors()),
                    ));
                }
            }
            $templateIds[$templateName] = $template->id;

            foreach (self::ITEMS as $item) {
                $existingItem = $itemsTbl->find()
                    ->where(['template_id' => $template->id, 'item_key' => $item['key']])
                    ->first();
                if ($existingItem) {
                    continue;
                }

                $permissionName = $meta['prefix'] . ' ' . $item['permission_suffix'];
                $entity = $itemsTbl->newEntity([
                    'template_id' => $template->id,
                    'item_key' => $item['key'],
                    'label' => $item['label'],
                    'assignee_type' => 'permission',
                    'assignee_source_id' => $permissionIds[$permissionName],
                    'branch_mode' => 'award_branch',
                    'branch_type' => null,
                    'is_gating' => $item['is_gating'],
                    'sort_order' => $item['sort'],
                ]);
                if (!$itemsTbl->save($entity)) {
                    throw new RuntimeException(sprintf(
                        'Failed to seed item "%s" for template "%s": %s',
                        $item['key'],
                        $templateName,
                        json_encode($entity->getErrors()),
                    ));
                }
            }
        }

        // 3. Assign each template to existing awards by owning branch type.
        //    Only awards without a template yet are touched (idempotent).
        foreach (self::TEMPLATES as $templateName => $meta) {
            $templateId = $templateIds[$templateName];
            $typeList = "'" . implode("','", $meta['branch_types']) . "'";
            $this->execute(
                'UPDATE awards_awards SET bestowal_todo_template_id = ' . $templateId . ' ' .
                'WHERE deleted IS NULL AND bestowal_todo_template_id IS NULL ' .
                'AND branch_id IN (SELECT id FROM branches WHERE deleted IS NULL AND type IN (' . $typeList . '))',
            );
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $templateNames = array_keys(self::TEMPLATES);
        $nameList = "'" . implode("','", $templateNames) . "'";

        // Detach awards, then remove items and templates.
        $this->execute(
            'UPDATE awards_awards SET bestowal_todo_template_id = NULL ' .
            'WHERE bestowal_todo_template_id IN ' .
            '(SELECT id FROM awards_bestowal_todo_templates WHERE name IN (' . $nameList . '))',
        );
        $this->execute(
            'DELETE FROM awards_bestowal_todo_template_items ' .
            'WHERE template_id IN (SELECT id FROM awards_bestowal_todo_templates WHERE name IN (' . $nameList . '))',
        );
        $this->execute('DELETE FROM awards_bestowal_todo_templates WHERE name IN (' . $nameList . ')');

        // Remove the seeded tier permissions.
        $permissionsTbl = TableRegistry::getTableLocator()->get('Permissions');
        foreach (self::PERMISSION_PREFIXES as $prefix) {
            foreach (self::ITEMS as $item) {
                $name = $prefix . ' ' . $item['permission_suffix'];
                $perm = $permissionsTbl->find()->where(['name' => $name])->first();
                if ($perm) {
                    $permissionsTbl->delete($perm);
                }
            }
        }
    }
}
