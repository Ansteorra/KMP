<?php

use Awards\Model\Entity\BestowalTodoTemplateItem;

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\BestowalTodoTemplate $template
 * @var array $roles
 * @var array $permissions
 * @var array $offices
 * @var array $members
 * @var array $branchTypes
 * @var array $assigneeTypeOptions
 * @var array $branchModeOptions
 */

$user = $this->request->getAttribute('identity');
$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': View Bestowal To-Do Template - ' . $template->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($template->name) ?>
<?php if ($template->is_active) : ?>
<span class="badge bg-success"><?= __('Active') ?></span>
<?php else : ?>
<span class="badge bg-secondary"><?= __('Inactive') ?></span>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordActions') ?>
<?php if ($user->checkCan('edit', $template)) : ?>
    <?= $this->Html->link(
        __('Edit'),
        ['action' => 'edit', $template->id],
        ['class' => 'btn btn-primary btn-sm'],
    ) ?>
<?php endif; ?>
<?php if (empty($template->awards) && $user->checkCan('delete', $template)) : ?>
    <?= $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $template->id],
        [
        'confirm' => __('Are you sure you want to delete {0}?', $template->name),
        'class' => 'btn btn-danger btn-sm',
        ],
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('recordDetails') ?>
<tr>
    <th scope="row"><?= __('Description') ?></th>
    <td>
        <?php if ($template->description) : ?>
            <?= $this->Text->autoParagraph(h($template->description)) ?>
        <?php else : ?>
        <span class="text-muted"><?= __('No description') ?></span>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Assigned Awards') ?></th>
    <td><?= count($template->awards ?? []) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabButtons') ?>
<button class="nav-link active" id="nav-items-tab" data-bs-toggle="tab" data-bs-target="#nav-items" type="button"
    role="tab" aria-controls="nav-items" aria-selected="true" data-detail-tabs-target="tabBtn" data-tab-order="10"
    style="order: 10;">
    <?= __('Checks') ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabContent') ?>
<div class="related tab-pane fade show active m-3" id="nav-items" role="tabpanel" aria-labelledby="nav-items-tab"
    data-detail-tabs-target="tabContent" data-tab-order="10" style="order: 10;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0"><?= __('To-Do Checks') ?></h5>
            <small class="text-muted">
                <?=
                __(
                    'Checks are worked in parallel. Each check is completed by the configured assignee ' .
                        'within the resolved branch scope. Gating checks must all be complete before a ' .
                        'bestowal can be marked given.',
                )
                ?>
            </small>
        </div>
        <?php if ($user->checkCan('edit', $template)) : ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= __('Add Check') ?>
        </button>
        <?php endif; ?>
    </div>

    <?php
    $assigneeSourceOptions = [
        BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE => $roles,
        BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION => $permissions,
        BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE => $offices,
        BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER => $members,
    ];
    $assigneeSourceLabel = function (BestowalTodoTemplateItem $item) use (
        $assigneeTypeOptions,
        $assigneeSourceOptions,
    ): string {
        $type = $assigneeTypeOptions[$item->assignee_type] ?? (string)$item->assignee_type;
        if ($item->assignee_type === BestowalTodoTemplateItem::ASSIGNEE_TYPE_DYNAMIC) {
            $source = $item->assignee_source_key ?: __('Unconfigured resolver');

            return sprintf('%s: %s', $type, $source);
        }

        $sourceOptions = $assigneeSourceOptions[$item->assignee_type] ?? [];
        $source = $sourceOptions[(int)$item->assignee_source_id] ?? __('Unconfigured {0}', strtolower($type));

        return sprintf('%s: %s', $type, $source);
    };
    ?>
    <?php if (!empty($template->bestowal_todo_template_items)) : ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th scope="col"><?= __('Order') ?></th>
                    <th scope="col"><?= __('Label') ?></th>
                    <th scope="col"><?= __('Assignee') ?></th>
                    <th scope="col"><?= __('Branch Scope') ?></th>
                    <th scope="col"><?= __('Gating') ?></th>
                    <th scope="col" class="actions text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($template->bestowal_todo_template_items as $item) : ?>
                <tr>
                    <td><?= h($item->sort_order) ?></td>
                    <td>
                        <strong><?= h($item->label) ?></strong><br>
                        <small class="text-muted"><?= h($item->item_key) ?></small>
                    </td>
                    <td><?= h($assigneeSourceLabel($item)) ?></td>
                    <td>
                        <?= h($branchModeOptions[$item->branch_mode] ?? $item->branch_mode) ?>
                        <?php if ($item->branch_type) : ?>
                        <br><small class="text-muted"><?= h($item->branch_type) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item->is_gating) : ?>
                        <span class="badge bg-warning text-dark"><?= __('Gating') ?></span>
                        <?php else : ?>
                        <span class="badge bg-light text-dark"><?= __('Optional') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="actions text-end text-nowrap">
                        <?php if ($user->checkCan('edit', $template)) : ?>
                        <button type="button" class="btn btn-sm btn-secondary bi bi-pencil-fill" data-bs-toggle="modal"
                            data-bs-target="#editItemModal-<?= $item->id ?>"
                            aria-label="<?= __('Edit {0}', $item->label) ?>"></button>
                            <?= $this->Form->postLink(
                                '',
                                ['action' => 'delete-item', $item->id],
                                [
                                'confirm' => __('Remove check "{0}"?', $item->label),
                                'title' => __('Remove'),
                                'aria-label' => __('Remove {0}', $item->label),
                                'class' => 'btn-sm btn btn-outline-danger bi bi-trash-fill',
                                ],
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
    <div class="alert alert-warning" role="status">
        <?=
        __(
            'No checks are configured yet. Add at least one check before assigning this template ' .
                'to awards.',
        )
        ?>
    </div>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('modals') ?>
<?php if ($user->checkCan('edit', $template)) : ?>
    <?php
    $renderItemFields = function ($item = null) use (
        $assigneeTypeOptions,
        $branchModeOptions,
        $branchTypes,
        $roles,
        $permissions,
        $offices,
        $members,
): void {
        $fieldPrefix = $item?->id ? 'item-' . $item->id : 'new-item';
        $memberSourceUrl = $this->Url->build(['action' => 'member-source-auto-complete']);
        $initSelection = function ($value, array $options): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            return json_encode([
            'value' => (string)$value,
            'text' => (string)($options[(int)$value] ?? $value),
            ]);
        };
        $sourceControlAttrs = function (
            string $sourceType,
            int|string|null $value,
            array $options,
        ) use (
            $fieldPrefix,
            $initSelection,
        ): array {
            $attrs = [
            'idPrefix' => $fieldPrefix . '-' . $sourceType,
            'data-awards-bestowal-todo-item-form-target' => 'sourceControl',
            ];
            $selection = $initSelection($value, $options);
            if ($selection !== null) {
                $attrs['data-ac-init-selection-value'] = $selection;
            }

            return $attrs;
        };
        $selectedAssigneeType = $item?->assignee_type ?? BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE;
        $selectedSourceId = function (string $type) use ($item): int|string|null {
            return $item?->assignee_type === $type ? $item?->assignee_source_id : null;
        };
        $selectedRole = $selectedSourceId(BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE);
        $selectedPermission = $selectedSourceId(BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION);
        $selectedOffice = $selectedSourceId(BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE);
        $selectedMember = $selectedSourceId(BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER);

        echo '<div class="row g-3">';
        echo '<div class="col-12 col-lg-6">';
        echo '<fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">';
        echo '<legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">' .
        '<i class="bi bi-list-check text-primary me-1" aria-hidden="true"></i>' .
        __('Check Identity') . '</legend>';
        echo $this->Form->control('label', ['value' => $item?->label]);
        echo $this->Form->control('item_key', [
        'value' => $item?->item_key,
        'help' => __(
            'Use a stable key like scroll_finished or regalia_ready. Lowercase letters, numbers, ' .
                'and underscores only.',
        ),
        ]);
        echo $this->Form->control('sort_order', ['type' => 'number', 'value' => $item?->sort_order ?? 1]);
        echo $this->Form->control('is_gating', [
        'type' => 'checkbox',
        'switch' => true,
        'checked' => $item?->is_gating ?? true,
        'label' => __('Gating (must be complete before Mark Given)'),
        ]);
        echo '</fieldset>';
        echo '</div>';
        echo '<div class="col-12 col-lg-6">';
        echo '<fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">';
        echo '<legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">' .
        '<i class="bi bi-person-check text-success me-1" aria-hidden="true"></i>' .
        __('Assignee Source') . '</legend>';
        echo $this->Form->control('assignee_type', [
        'options' => $assigneeTypeOptions,
        'value' => $selectedAssigneeType,
        'label' => __('Assignee Source Type'),
        'data-awards-bestowal-todo-item-form-target' => 'assigneeType',
        'data-action' => 'awards-bestowal-todo-item-form#sync',
        ]);
        echo '<div class="border rounded p-3 mb-3 bg-light-subtle">';
        echo '<p class="fw-semibold mb-2">' . __('Typed Assignee Source') . '</p>';
        echo '<p class="text-muted small mb-3">' .
        __(
            'Choose the source matching the selected type. Hidden source controls are disabled so only ' .
                'the active source is saved.',
        ) .
        '</p>';
        echo '<div data-awards-bestowal-todo-item-form-target="sourceGroup" data-assignee-source-type="' .
        h(BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE) . '">';
        echo $this->element('comboBoxControl', [
        'Form' => $this->Form,
        'inputField' => 'role_source',
        'resultField' => 'role_source_id',
        'data' => $roles,
        'label' => __('Role'),
        'required' => false,
        'allowOtherValues' => false,
        'additionalAttrs' => $sourceControlAttrs(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_ROLE,
            $selectedRole,
            $roles,
        ),
        ]);
        echo '</div>';
        echo '<div data-awards-bestowal-todo-item-form-target="sourceGroup" data-assignee-source-type="' .
        h(BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION) . '">';
        echo $this->element('comboBoxControl', [
        'Form' => $this->Form,
        'inputField' => 'permission_source',
        'resultField' => 'permission_source_id',
        'data' => $permissions,
        'label' => __('Permission'),
        'required' => false,
        'allowOtherValues' => false,
        'additionalAttrs' => $sourceControlAttrs(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_PERMISSION,
            $selectedPermission,
            $permissions,
        ),
        ]);
        echo '</div>';
        echo '<div data-awards-bestowal-todo-item-form-target="sourceGroup" data-assignee-source-type="' .
        h(BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE) . '">';
        echo $this->element('comboBoxControl', [
        'Form' => $this->Form,
        'inputField' => 'office_source',
        'resultField' => 'office_source_id',
        'data' => $offices,
        'label' => __('Office'),
        'required' => false,
        'allowOtherValues' => false,
        'additionalAttrs' => $sourceControlAttrs(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_OFFICE,
            $selectedOffice,
            $offices,
        ),
        ]);
        echo '</div>';
        echo '<div data-awards-bestowal-todo-item-form-target="sourceGroup" data-assignee-source-type="' .
        h(BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER) . '">';
        echo $this->element('autoCompleteControl', [
        'Form' => $this->Form,
        'inputField' => 'member_source',
        'resultField' => 'member_source_id',
        'url' => $memberSourceUrl,
        'label' => __('Member'),
        'required' => false,
        'allowOtherValues' => false,
        'minLength' => 2,
        'additionalAttrs' => $sourceControlAttrs(
            BestowalTodoTemplateItem::ASSIGNEE_TYPE_MEMBER,
            $selectedMember,
            $members,
        ),
        ]);
        echo '</div>';
        echo '<div data-awards-bestowal-todo-item-form-target="sourceGroup" data-assignee-source-type="' .
        h(BestowalTodoTemplateItem::ASSIGNEE_TYPE_DYNAMIC) . '">';
        echo $this->Form->control('assignee_source_key', [
        'value' => $item?->assignee_source_key,
        'label' => __('Dynamic Resolver Key'),
        'placeholder' => __('Awards.ResolveBestowalTodoAssignees'),
        'aria-describedby' => $fieldPrefix . '-dynamic-resolver-help',
        ]);
        echo '<div id="' . h($fieldPrefix) . '-dynamic-resolver-help" class="form-text">' .
        __(
            'Enter a registered assignee resolver key. Office assignees use a dynamic resolver under the ' .
                'hood; use this directly only for cases that cannot be represented by role, permission, ' .
                'office, or member sources.',
        ) .
        '</div>';
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';
        echo '</div>';
        echo '<div class="col-12">';
        echo '<fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">';
        echo '<legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">' .
        '<i class="bi bi-sliders text-info me-1" aria-hidden="true"></i>' .
        __('Branch Scope') . '</legend>';
        echo $this->Form->control('branch_mode', [
        'options' => $branchModeOptions,
        'value' => $item?->branch_mode ?? BestowalTodoTemplateItem::BRANCH_MODE_AWARD,
        'label' => __('Branch Scope'),
        'help' => __('Role, permission, and office assignees resolve only inside this branch scope.'),
        'data-awards-bestowal-todo-item-form-target' => 'branchMode',
        'data-action' => 'awards-bestowal-todo-item-form#sync',
        ]);
        echo '<div data-awards-bestowal-todo-item-form-target="branchTypeGroup">';
        echo $this->Form->control('branch_type', [
        'options' => $branchTypes,
        'empty' => __('-- Select only for ancestor branch type --'),
        'value' => $item?->branch_type,
        'label' => __('Ancestor Branch Type'),
        ]);
        echo '</div>';
        echo '</fieldset>';
        echo '</div>';
        echo '</div>';
    };
    ?>
    <?= $this->Form->create(null, ['url' => ['action' => 'add-item', $template->id]]) ?>
    <?= $this->Modal->create(__('Add Check'), ['id' => 'addItemModal', 'close' => true, 'form' => true]) ?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3" data-controller="awards-bestowal-todo-item-form">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-list-check text-primary me-1" aria-hidden="true"></i>
        <?= __('To-Do Check') ?>
    </legend>
    <?php $renderItemFields(); ?>
</fieldset>
    <?= $this->Modal->end([
    $this->Form->button(__('Add Check'), ['class' => 'btn btn-primary']),
    $this->Form->button(
        __('Close'),
        ['type' => 'button', 'class' => 'btn btn-secondary', 'data-bs-dismiss' => 'modal'],
    ),
    ]) ?>
    <?= $this->Form->end() ?>

    <?php foreach ($template->bestowal_todo_template_items ?? [] as $item) : ?>
        <?= $this->Form->create($item, ['url' => ['action' => 'edit-item', $item->id]]) ?>
        <?= $this->Modal->create(
            __('Edit Check: {0}', $item->label),
            ['id' => 'editItemModal-' . $item->id, 'close' => true, 'form' => true],
        ) ?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3" data-controller="awards-bestowal-todo-item-form">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-list-check text-primary me-1" aria-hidden="true"></i>
        <?= __('To-Do Check') ?>
    </legend>
        <?php $renderItemFields($item); ?>
</fieldset>
        <?= $this->Modal->end([
        $this->Form->button(__('Save Check'), ['class' => 'btn btn-primary']),
        $this->Form->button(
            __('Close'),
            ['type' => 'button', 'class' => 'btn btn-secondary', 'data-bs-dismiss' => 'modal'],
        ),
    ]) ?>
        <?= $this->Form->end() ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
