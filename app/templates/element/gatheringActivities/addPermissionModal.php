<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var array $permissionOptions
 */
echo $this->Form->create(null, [
    'url' => ['action' => 'addAssignmentPermission', $gatheringActivity->id],
    'data-controller' => 'role-add-permission',
    'data-role-add-permission-target' => 'form',
]);
echo $this->Modal->create(__('Add Permission to Activity'), [
    'id' => 'addPermissionModal', 'close' => true, 'form' => true, 'size' => 'modal-lg',
]);
?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <kmp-icon name="shield-plus" class="text-primary me-1" aria-hidden="true"></kmp-icon>
        <?= __('Activity Assignment Permission') ?>
    </legend>
    <p><?= __('Add a permission that allows members to manage this activity within its existing branch scope.') ?></p>
    <?= $this->KMP->comboBoxControl(
        $this->Form,
        'permission_name',
        'permission_id',
        $permissionOptions,
        __('Permission'),
        true,
        false,
        [
            'idPrefix' => 'activity-assignment',
            'data-role-add-permission-target' => 'permission',
            'data-action' => 'change->role-add-permission#checkSubmitEnable',
        ],
    ) ?>
</fieldset>
<?php
echo $this->Modal->end([
    $this->Form->button(__('Submit'), [
        'class' => 'btn btn-primary', 'disabled' => true, 'data-role-add-permission-target' => 'submitBtn',
    ]),
    $this->Form->button(__('Close'), ['data-bs-dismiss' => 'modal', 'type' => 'button']),
]);
echo $this->Form->end();
