<?php

use Awards\Model\Entity\ApprovalProcessStep;

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\ApprovalProcess $approvalProcess
 * @var array $roles
 * @var array $permissions
 * @var array $offices
 * @var array $members
 * @var array $awards
 * @var array $branchTypes
 * @var array $approverTypeOptions
 * @var array $branchModeOptions
 * @var array $thresholdModeOptions
 * @var array $actionOptions
 * @var array|null $preview
 * @var string|int|null $previewAwardId
 * @var string $previewFrameId
 */

$user = $this->request->getAttribute('identity');
$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': View Award Approval Process - ' . $approvalProcess->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($approvalProcess->name) ?>
<?php if ($approvalProcess->is_active) : ?>
<span class="badge bg-success"><?= __('Active') ?></span>
<?php else : ?>
<span class="badge bg-secondary"><?= __('Inactive') ?></span>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordActions') ?>
<?php if ($user->checkCan('edit', $approvalProcess)) : ?>
<?= $this->Html->link(
        __('Edit'),
        ['action' => 'edit', $approvalProcess->id],
        ['class' => 'btn btn-primary btn-sm'],
    ) ?>
<?php endif; ?>
<?php if (empty($approvalProcess->awards) && $user->checkCan('delete', $approvalProcess)) : ?>
<?= $this->Form->postLink(
        __('Delete'),
        ['action' => 'delete', $approvalProcess->id],
        [
            'confirm' => __('Are you sure you want to delete {0}?', $approvalProcess->name),
            'class' => 'btn btn-danger btn-sm',
        ],
    ) ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('recordDetails') ?>
<tr>
    <th scope="row"><?= __('Description') ?></th>
    <td>
        <?php if ($approvalProcess->description) : ?>
        <?= $this->Text->autoParagraph(h($approvalProcess->description)) ?>
        <?php else : ?>
        <span class="text-muted"><?= __('No description') ?></span>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Assigned Awards') ?></th>
    <td><?= count($approvalProcess->awards ?? []) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Step Summary') ?></th>
    <td><?= h($approvalProcess->step_summary) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabButtons') ?>
<button class="nav-link active" id="nav-steps-tab" data-bs-toggle="tab" data-bs-target="#nav-steps" type="button"
    role="tab" aria-controls="nav-steps" aria-selected="true" data-detail-tabs-target="tabBtn" data-tab-order="10"
    style="order: 10;">
    <?= __('Steps') ?>
</button>
<button class="nav-link" id="nav-preview-tab" data-bs-toggle="tab" data-bs-target="#nav-preview" type="button"
    role="tab" aria-controls="nav-preview" aria-selected="false" data-detail-tabs-target="tabBtn" data-tab-order="20"
    style="order: 20;">
    <?= __('Preview') ?>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('tabContent') ?>
<div class="related tab-pane fade show active m-3" id="nav-steps" role="tabpanel" aria-labelledby="nav-steps-tab"
    data-detail-tabs-target="tabContent" data-tab-order="10" style="order: 10;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0"><?= __('Approval Steps') ?></h5>
            <small class="text-muted">
                <?=
                __(
                    'Configure each queue in order. Role, permission, and office approvers are resolved ' .
                        'within the selected branch scope.',
                )
                ?>
            </small>
        </div>
        <?php if ($user->checkCan('edit', $approvalProcess)) : ?>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStepModal">
            <i class="bi bi-plus-circle" aria-hidden="true"></i> <?= __('Add Step') ?>
        </button>
        <?php endif; ?>
    </div>

    <?php
    $approverSourceOptions = [
        ApprovalProcessStep::APPROVER_TYPE_ROLE => $roles,
        ApprovalProcessStep::APPROVER_TYPE_PERMISSION => $permissions,
        ApprovalProcessStep::APPROVER_TYPE_OFFICE => $offices,
        ApprovalProcessStep::APPROVER_TYPE_MEMBER => $members,
    ];
    $approverSourceLabel = function (ApprovalProcessStep $step) use (
        $approverTypeOptions,
        $approverSourceOptions,
    ): string {
        $type = $approverTypeOptions[$step->approver_type] ?? (string)$step->approver_type;
        if ($step->approver_type === ApprovalProcessStep::APPROVER_TYPE_DYNAMIC) {
            $source = $step->approver_source_key ?: __('Unconfigured resolver');

            return sprintf('%s: %s', $type, $source);
        }

        $sourceOptions = $approverSourceOptions[$step->approver_type] ?? [];
        $source = $sourceOptions[(int)$step->approver_source_id] ?? __('Unconfigured {0}', strtolower($type));

        return sprintf('%s: %s', $type, $source);
    };
    ?>
    <?php if (!empty($approvalProcess->approval_process_steps)) : ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th scope="col"><?= __('Order') ?></th>
                    <th scope="col"><?= __('Label') ?></th>
                    <th scope="col"><?= __('Approver') ?></th>
                    <th scope="col"><?= __('Branch Scope') ?></th>
                    <th scope="col"><?= __('Threshold') ?></th>
                    <th scope="col" class="actions text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($approvalProcess->approval_process_steps as $step) : ?>
                <tr>
                    <td><?= h($step->sequence) ?></td>
                    <td>
                        <strong><?= h($step->label) ?></strong><br>
                        <small class="text-muted"><?= h($step->step_key) ?></small>
                    </td>
                    <td><?= h($approverSourceLabel($step)) ?></td>
                    <td>
                        <?= h($branchModeOptions[$step->branch_mode] ?? $step->branch_mode) ?>
                        <?php if ($step->branch_type) : ?>
                        <br><small class="text-muted"><?= h($step->branch_type) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= h($step->threshold_summary) ?></td>
                    <td class="actions text-end text-nowrap">
                        <?php if ($user->checkCan('edit', $approvalProcess)) : ?>
                        <button type="button" class="btn btn-sm btn-secondary bi bi-pencil-fill" data-bs-toggle="modal"
                            data-bs-target="#editStepModal-<?= $step->id ?>"
                            aria-label="<?= __('Edit {0}', $step->label) ?>"></button>
                        <?= $this->Form->postLink(
                                        '',
                                        ['action' => 'delete-step', $step->id],
                                        [
                                            'confirm' => __('Remove approval step "{0}"?', $step->label),
                                            'title' => __('Remove'),
                                            'aria-label' => __('Remove {0}', $step->label),
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
                'No approval steps are configured yet. Add at least one step before assigning this process ' .
                    'to awards.',
            )
            ?>
    </div>
    <?php endif; ?>
</div>

<div class="related tab-pane fade m-3" id="nav-preview" role="tabpanel" aria-labelledby="nav-preview-tab"
    data-detail-tabs-target="tabContent" data-tab-order="20" style="order: 20;">
    <?= $this->element('ApprovalProcesses/approver_preview', [
        'approvalProcess' => $approvalProcess,
        'awards' => $awards,
        'preview' => $preview,
        'previewAwardId' => $previewAwardId,
        'previewFrameId' => $previewFrameId,
    ]) ?>
</div>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock('modals') ?>
<?php if ($user->checkCan('edit', $approvalProcess)) : ?>
<?php
    $renderStepFields = function ($step = null) use (
        $approverTypeOptions,
        $branchModeOptions,
        $thresholdModeOptions,
        $actionOptions,
        $branchTypes,
        $roles,
        $permissions,
        $offices,
        $members,
    ): void {
        $fieldPrefix = $step?->id ? 'step-' . $step->id : 'new-step';
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
                'data-awards-approval-step-form-target' => 'sourceControl',
            ];
            $selection = $initSelection($value, $options);
            if ($selection !== null) {
                $attrs['data-ac-init-selection-value'] = $selection;
            }

            return $attrs;
        };
        $selectedApproverType = $step?->approver_type ?? ApprovalProcessStep::APPROVER_TYPE_ROLE;
        $selectedSourceId = function (string $type) use ($step): int|string|null {
            return $step?->approver_type === $type ? $step?->approver_source_id : null;
        };
        $selectedRole = $selectedSourceId(ApprovalProcessStep::APPROVER_TYPE_ROLE);
        $selectedPermission = $selectedSourceId(ApprovalProcessStep::APPROVER_TYPE_PERMISSION);
        $selectedOffice = $selectedSourceId(ApprovalProcessStep::APPROVER_TYPE_OFFICE);
        $selectedMember = $selectedSourceId(ApprovalProcessStep::APPROVER_TYPE_MEMBER);

        echo $this->Form->control('label', ['value' => $step?->label]);
        echo $this->Form->control('step_key', [
            'value' => $step?->step_key,
            'help' => __(
                'Use a stable key like local_approver or crown. Lowercase letters, numbers, and underscores only.',
            ),
        ]);
        echo $this->Form->control('sequence', ['type' => 'number', 'value' => $step?->sequence ?? 1]);
        echo $this->Form->control('approver_type', [
            'options' => $approverTypeOptions,
            'value' => $selectedApproverType,
            'label' => __('Approver Source Type'),
            'data-awards-approval-step-form-target' => 'approverType',
            'data-action' => 'awards-approval-step-form#sync',
        ]);
        echo '<div class="border rounded p-3 mb-3">';
        echo '<p class="fw-semibold mb-2">' . __('Typed Approver Source') . '</p>';
        echo '<p class="text-muted small mb-3">' .
            __(
                'Choose the source matching the selected type. Hidden source controls are disabled so only ' .
                    'the active source is saved.',
            ) .
            '</p>';
        echo '<div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="' .
            h(ApprovalProcessStep::APPROVER_TYPE_ROLE) . '">';
        echo $this->element('comboBoxControl', [
            'Form' => $this->Form,
            'inputField' => 'role_source',
            'resultField' => 'role_source_id',
            'data' => $roles,
            'label' => __('Role'),
            'required' => false,
            'allowOtherValues' => false,
            'additionalAttrs' => $sourceControlAttrs(
                ApprovalProcessStep::APPROVER_TYPE_ROLE,
                $selectedRole,
                $roles,
            ),
        ]);
        echo '</div>';
        echo '<div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="' .
            h(ApprovalProcessStep::APPROVER_TYPE_PERMISSION) . '">';
        echo $this->element('comboBoxControl', [
            'Form' => $this->Form,
            'inputField' => 'permission_source',
            'resultField' => 'permission_source_id',
            'data' => $permissions,
            'label' => __('Permission'),
            'required' => false,
            'allowOtherValues' => false,
            'additionalAttrs' => $sourceControlAttrs(
                ApprovalProcessStep::APPROVER_TYPE_PERMISSION,
                $selectedPermission,
                $permissions,
            ),
        ]);
        echo '</div>';
        echo '<div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="' .
            h(ApprovalProcessStep::APPROVER_TYPE_OFFICE) . '">';
        echo $this->element('comboBoxControl', [
            'Form' => $this->Form,
            'inputField' => 'office_source',
            'resultField' => 'office_source_id',
            'data' => $offices,
            'label' => __('Office'),
            'required' => false,
            'allowOtherValues' => false,
            'additionalAttrs' => $sourceControlAttrs(
                ApprovalProcessStep::APPROVER_TYPE_OFFICE,
                $selectedOffice,
                $offices,
            ),
        ]);
        echo '</div>';
        echo '<div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="' .
            h(ApprovalProcessStep::APPROVER_TYPE_MEMBER) . '">';
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
                ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                $selectedMember,
                $members,
            ),
        ]);
        echo '</div>';
        echo '<div data-awards-approval-step-form-target="sourceGroup" data-approver-source-type="' .
            h(ApprovalProcessStep::APPROVER_TYPE_DYNAMIC) . '">';
        echo $this->Form->control('approver_source_key', [
            'value' => $step?->approver_source_key,
            'label' => __('Dynamic Resolver Key'),
            'placeholder' => __('Awards.ResolveApprovalStepApprovers'),
            'aria-describedby' => $fieldPrefix . '-dynamic-resolver-help',
        ]);
        echo '<div id="' . h($fieldPrefix) . '-dynamic-resolver-help" class="form-text">' .
            __(
                'Enter a registered workflow approver resolver key. Dynamic resolvers receive the recommendation, ' .
                    'award, process step, and resolved branch context; use this only for cases that cannot be ' .
                    'represented by role, permission, office, or member sources.',
            ) .
            '</div>';
        echo '</div>';
        echo '</div>';
        echo $this->Form->control('branch_mode', [
            'options' => $branchModeOptions,
            'value' => $step?->branch_mode ?? ApprovalProcessStep::BRANCH_MODE_AWARD,
            'label' => __('Branch Scope'),
            'help' => __('Role, permission, and office approvers resolve only inside this branch scope.'),
            'data-awards-approval-step-form-target' => 'branchMode',
            'data-action' => 'awards-approval-step-form#sync',
        ]);
        echo '<div data-awards-approval-step-form-target="branchTypeGroup">';
        echo $this->Form->control('branch_type', [
            'options' => $branchTypes,
            'empty' => __('-- Select only for ancestor branch type --'),
            'value' => $step?->branch_type,
            'label' => __('Ancestor Branch Type'),
        ]);
        echo '</div>';
        echo $this->Form->control('threshold_mode', [
            'options' => $thresholdModeOptions,
            'value' => $step?->threshold_mode ?? ApprovalProcessStep::THRESHOLD_ANY,
            'label' => __('Approval Threshold'),
            'data-awards-approval-step-form-target' => 'thresholdMode',
            'data-action' => 'awards-approval-step-form#sync',
        ]);
        echo '<div data-awards-approval-step-form-target="requiredCountGroup">';
        echo $this->Form->control('required_count', [
            'type' => 'number',
            'min' => 1,
            'value' => $step?->required_count,
            'label' => __('Required Count'),
            'help' => __('Only used when threshold is Specific number of approvers.'),
        ]);
        echo '</div>';
        echo $this->Form->control('on_reject', [
            'options' => $actionOptions,
            'value' => $step?->on_reject ?? ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'label' => __('On Rejection'),
        ]);
        echo $this->Form->control('on_request_changes', [
            'options' => $actionOptions,
            'value' => $step?->on_request_changes ?? ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'label' => __('On Request Changes'),
        ]);
        echo $this->Form->control('retain_read_visibility', [
            'type' => 'checkbox',
            'switch' => true,
            'checked' => $step?->retain_read_visibility ?? true,
            'label' => __('Prior approvers retain read-only visibility'),
        ]);
    };
    ?>
<?= $this->Form->create(null, ['url' => ['action' => 'add-step', $approvalProcess->id]]) ?>
<?= $this->Modal->create(__('Add Approval Step'), ['id' => 'addStepModal', 'close' => true]) ?>
<fieldset data-controller="awards-approval-step-form">
    <?php $renderStepFields(); ?>
</fieldset>
<?= $this->Modal->end([
        $this->Form->button(__('Add Step'), ['class' => 'btn btn-primary']),
        $this->Form->button(
            __('Close'),
            ['type' => 'button', 'class' => 'btn btn-secondary', 'data-bs-dismiss' => 'modal'],
        ),
    ]) ?>
<?= $this->Form->end() ?>

<?php foreach ($approvalProcess->approval_process_steps ?? [] as $step) : ?>
<?= $this->Form->create($step, ['url' => ['action' => 'edit-step', $step->id]]) ?>
<?= $this->Modal->create(
            __('Edit Approval Step: {0}', $step->label),
            ['id' => 'editStepModal-' . $step->id, 'close' => true],
        ) ?>
<fieldset data-controller="awards-approval-step-form">
    <?php $renderStepFields($step); ?>
</fieldset>
<?= $this->Modal->end([
            $this->Form->button(__('Save Step'), ['class' => 'btn btn-primary']),
            $this->Form->button(
                __('Close'),
                ['type' => 'button', 'class' => 'btn btn-secondary', 'data-bs-dismiss' => 'modal'],
            ),
        ]) ?>
<?= $this->Form->end() ?>
<?php endforeach; ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>