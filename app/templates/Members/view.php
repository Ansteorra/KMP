<?php

use \App\Model\Entity\Member;

$this->extend("/layout/TwitterBootstrap/view_record");
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\member $member
 */
?>
<?php



$needVerification = false;
$needsParentVerification = false;
$needsMemberCardVerification = false;

$aiFormConfig = $this->KMP->getAppSettingsStartWith('Member.AdditionalInfo.');
$aiForm = [];
if (!empty($aiFormConfig)) {
    foreach ($aiFormConfig as $key => $value) {
        $shortKey = str_replace('Member.AdditionalInfo.', '', $key);
        $aiForm[$shortKey] = $value;
    }
}
$canViewAdditionalInformation = $canViewAdditionalInformation ?? ($user->checkCan('viewAdditionalInformation', $member));
$canManageMember = $canManageMember ?? ($user && method_exists($user, 'canManageMember') ? $user->canManageMember($member) : false);
$canManageQuickLoginDevices = $canManageQuickLoginDevices ?? false;
$quickLoginDevices = $quickLoginDevices ?? [];
$children = $children ?? [];
switch ($member->status) {
    case Member::STATUS_ACTIVE:
        $needVerification = true;
        $needsMemberCardVerification = true;
        $needsParentVerification = false;
        break;
    case Member::STATUS_UNVERIFIED_MINOR:
        $needVerification = true;
        $needsParentVerification = true;
        $needsMemberCardVerification = true;
        break;
    case Member::STATUS_MINOR_MEMBERSHIP_VERIFIED:
        $needVerification = true;
        $needsMemberCardVerification = false;
        $needsParentVerification = true;
        break;
    case Member::STATUS_MINOR_PARENT_VERIFIED:
        $needVerification = true;
        $needsMemberCardVerification = true;
        $needsParentVerification = false;
        break;
    default:
        $needVerification = false;
        $needsMemberCardVerification = false;
        $needsParentVerification = false;
        break;
}
if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
    $needVerification = true;
    $needsMemberCardVerification = true;
}

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($member->sca_name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock('recordActions') ?>
<?php if ($user->checkCan('verifyMembership', 'Members') && $needVerification) { ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
    data-bs-target="#verifyMembershipModal">Verify Membership</button>
<?php } ?>
<?php if (
    $user->checkCan('partialEdit', $member) && ($member->membership_card_path == null || strlen($member->membership_card_path) < 1)
) { ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#submitMemberCardModal"
    id='scaCardUploadBtn'>Submit Updated SCA Info</button>
<?php }
if ($user->checkCan('partialEdit', $member)) { ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#profilePhotoModal"
    id='profilePhotoBtn'>Update Profile Photo</button>
<?php }
if (
    $user->checkCan('edit', $member) ||
    $user->checkCan('partialEdit', $member)
) { ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"
    id='editModalBtn'>Edit</button>
<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#passwordModal"
    id='passwordModalBtn'>Change Password</button>
<?php } ?>
<?php if (!empty($user) && method_exists($user, 'isSuperUser') && $user->isSuperUser() && empty($impersonationState) && $user->id !== $member->id) : ?>
<?= $this->Form->postLink(
        __('Impersonate Member'),
        ['action' => 'impersonate', $member->id],
        [
            'class' => 'btn btn-warning btn-sm',
            'confirm' => __('Impersonate {0}? You will assume their permissions until you stop.', h($member->sca_name)),
            'data-bs-toggle' => 'tooltip',
            'title' => __('Operate as this member'),
        ],
    ); ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>

<?php echo $this->KMP->startBlock('recordDetails');
echo $this->element('members/memberDetails', [
    'user' => $user,
    'canManageMember' => $canManageMember,
]);
$this->KMP->endBlock() ?>
<?php $this->KMP->startBlock('tabButtons') ?>
<!-- Base template tabs can specify order to interleave with plugin tabs
     Plugin tabs typically use orders 1-10, so choose accordingly
     Lower numbers appear first (left), higher numbers appear later (right) -->
<button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button" role="tab"
    aria-controls="nav-roles" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="10"
    style="order: 10;"><?= __('Roles') ?>
</button>
<button class="nav-link" id="nav-gatherings-tab" data-bs-toggle="tab" data-bs-target="#nav-gatherings" type="button"
    role="tab" aria-controls="nav-gatherings" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="15"
    style="order: 15;"><?= __('Gatherings') ?>
</button>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="20"
    style="order: 20;"><?= __('Notes') ?>
</button>
<?php if (!empty($children)) : ?>
<button class="nav-link" id="nav-children-tab" data-bs-toggle="tab" data-bs-target="#nav-children" type="button"
    role="tab" aria-controls="nav-children" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="25"
    style="order: 25;"><?= __('Children') ?>
</button>
<?php endif; ?>
<?php if (!empty($aiForm) && $canViewAdditionalInformation) : ?>
<button class="nav-link" id="nav-add-info-tab" data-bs-toggle="tab" data-bs-target="#nav-add-info" type="button"
    role="tab" aria-controls="nav-add-info" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="30"
    style="order: 30;">
    <?= __('Additional Info') ?>
</button>
<?php endif; ?>
<?php if (!empty($quickLoginDevices)) : ?>
<button class="nav-link" id="nav-quick-login-devices-tab" data-bs-toggle="tab" data-bs-target="#nav-quick-login-devices"
    type="button" role="tab" aria-controls="nav-quick-login-devices" aria-selected="false"
    data-detail-tabs-target='tabBtn' data-tab-order="35" style="order: 35;">
    <?= __('Quick login devices') ?>
</button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock('tabContent') ?>
<!-- Tab content panels with order matching tab buttons for consistent presentation -->
<div class="related tab-pane fade m-3" id="nav-roles" role="tabpanel" aria-labelledby="nav-roles-tab"
    data-detail-tabs-target="tabContent" data-tab-order="10" style="order: 10;">
    <?= $this->element('dv_grid', [
        'gridKey' => "Members.roles.{$member->id}",
        'frameId' => "member-roles-grid-{$member->id}",
        'dataUrl' => $this->Url->build(['action' => 'rolesGridData', $member->id]),
    ]) ?>
</div>
<div class="related tab-pane fade m-3" id="nav-gatherings" role="tabpanel" aria-labelledby="nav-gatherings-tab"
    data-detail-tabs-target="tabContent" data-tab-order="15" style="order: 15;">
    <?php if ($canManageMember || $user->checkCan('add', 'GatheringAttendances')): ?>
    <div class="mb-3">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
            data-bs-target="#addGatheringAttendanceModal">
            <i class="bi bi-plus-circle"></i> RSVP for Gathering
        </button>
    </div>
    <?php endif; ?>
    <?= $this->element('dv_grid', [
        'gridKey' => "Members.gatherings.{$member->id}",
        'frameId' => "member-gatherings-grid-{$member->id}",
        'dataUrl' => $this->Url->build(['action' => 'gatheringsGridData', $member->id]),
    ]) ?>
</div>
<div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab"
    data-detail-tabs-target="tabContent" data-tab-order="20" style="order: 20;">
    <?= $this->cell('Notes', [
        'entity_id' => $member->id,
        'entity_type' => 'Members',
        'viewPrivate' => $user->checkCan('viewPrivateNotes', 'Members'),
    ]) ?>
</div>
<?php if (!empty($children)) : ?>
<div class="related tab-pane fade m-3" id="nav-children" role="tabpanel" aria-labelledby="nav-children-tab"
    data-detail-tabs-target="tabContent" data-tab-order="25" style="order: 25;">
    <?= $this->element('members/children', [
        'children' => $children,
    ]) ?>
</div>
<?php endif; ?>
<?php if (!empty($aiForm) && $canViewAdditionalInformation) : ?>
<div class="related tab-pane fade m-3" id="nav-add-info" role="tabpanel" aria-labelledby="nav-add-info-tab"
    data-detail-tabs-target="tabContent" data-tab-order="30" style="order: 30;">
    <?php
        $appInfo = $member->additional_info;
        $userEditableOnly = !$user->checkCan('edit', $member);
        if ($user->checkCan('editAdditionalInfo', $member)) {
            echo $this->Form->create(null, [
                //"align" => "horizontal",
                'url' => ['controller' => 'Members', 'action' => 'editAdditionalInfo', $member->id],
            ]);
            foreach ($aiForm as $fieldKey => $fieldType) {
                if (!isset($appInfo[$fieldKey])) {
                    $appInfo[$fieldKey] = '';
                }
                //check if the field contains a pipe
                $pipePos = strpos($fieldType, '|');
                $managerOnly = false;
                $userEditable = false;
                if ($pipePos !== false) {
                    $fieldSecDetails = explode('|', $fieldType);
                    $fieldType = $fieldSecDetails[0];
                    $userEditable = $fieldSecDetails[1] == 'user';
                    $editorOnly = $fieldSecDetails[1] == 'manager_only';
                }
                $disabled = false;
                if ($userEditableOnly && !$userEditable) {
                    $disabled = true;
                }
                if ($managerOnly && $userEditableOnly) {
                    continue;
                }
                //check if the fieldType contains a :
                $colonPos = strpos($fieldType, ':');
                $aiOptions = [];
                if ($colonPos !== false) {
                    $fieldDetails = explode(':', $fieldType);
                    $fieldType =  $fieldDetails[0];
                    $aiOptions = explode(',', $fieldDetails[1]);
                }
                switch ($fieldType) {
                    case 'text':
                        echo $this->Form->control($fieldKey, [
                            'type' => 'text',
                            'value' => $appInfo[$fieldKey],
                            'disabled' => $disabled,
                        ]);
                        break;
                    case 'date':
                        echo $this->Form->control($fieldKey, [
                            'type' => $fieldType,
                            'value' => $appInfo[$fieldKey],
                            'disabled' => $disabled,
                        ]);
                        break;
                    case 'number':
                        echo $this->Form->control($fieldKey, [
                            'type' => 'number',
                            'value' => $appInfo[$fieldKey],
                            'disabled' => $disabled,
                        ]);
                        break;
                    case 'bool':
                        if ($appInfo[$fieldKey]) {
                            echo $this->Form->control($fieldKey, [
                                'type' => 'checkbox',
                                'checked' => 'checked',
                                'switch' => true,
                                'disabled' => $disabled,
                            ]);
                        } else {
                            echo $this->Form->control($fieldKey, [
                                'type' => 'checkbox',
                                'switch' => true,
                                'disabled' => $disabled,
                            ]);
                        }
                        break;
                    case 'select':
                        $selectOptions = [];
                        foreach ($aiOptions as $option) {
                            $selectOptions[$option] = $option;
                        }
                        echo $this->Form->control($fieldKey, [
                            'type' => 'select',
                            'empty' => true,
                            'options' => $selectOptions,
                            'value' => $appInfo[$fieldKey],
                            'disabled' => $disabled,
                        ]);
                        break;
                    default:
                        echo $this->Form->control($fieldKey, [
                            'type' => 'text',
                            'value' => $appInfo[$fieldKey],
                            'disabled' => $disabled,
                        ]);
                        break;
                }
            }
            echo $this->Form->button('Submit', [
                'class' => 'btn btn-primary',
            ]);
            echo $this->form->end();
        } else { ?>
    <table class='table table-striped'>
        <?php foreach ($aiForm as $fieldKey => $fieldType) { ?>
        <tr scope="row">
            <th class="col"><?= str_replace('_', ' ', $fieldKey) ?></th>
            <td class="col-10">
                <?php
                            $pipePos = strpos($fieldType, '|');
                            $managerOnly = false;
                            $userEditable = false;
                            if ($pipePos !== false) {
                                $fieldSecDetails = explode('|', $fieldType);
                                $fieldType = $fieldSecDetails[0];
                                $editorOnly = $fieldSecDetails[1] == 'manager_only';
                            }
                            $disabled = false;
                            if ($managerOnly && $userEditableOnly) {
                                continue;
                            }
                            //check if the fieldType contains a :
                            $colonPos = strpos($fieldType, ':');
                            $aiOptions = [];
                            if ($colonPos !== false) {
                                $fieldDetails = explode(':', $fieldType);
                                $fieldType =  $fieldDetails[0];
                            }
                            $value = null;
                            if (isset($appInfo[$fieldKey])) {
                                $value = $appInfo[$fieldKey];
                            }
                            if ($value == null) {
                                $value = '';
                            }
                            switch ($fieldType) {
                                case 'bool':
                                    echo $this->KMP->bool($value, $this->Html);
                                    break;
                                default:
                                    echo h($value);
                                    break;
                            }
                            ?>
            </td>
        </tr>
        <?php } ?>
    </table>
    <?php } ?>
</div>
<?php endif; ?>
<?php if (!empty($quickLoginDevices)) : ?>
<div class="related tab-pane fade m-3" id="nav-quick-login-devices" role="tabpanel"
    aria-labelledby="nav-quick-login-devices-tab" data-detail-tabs-target="tabContent" data-tab-order="35"
    style="order: 35;">
    <p class="text-muted">
        <?= __("These devices can sign in with Quick login PIN. Disable any device you don't recognize.") ?>
    </p>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th><?= __('Device') ?></th>
                    <th><?= __('Configured') ?></th>
                    <th><?= __('Last used') ?></th>
                    <th><?= __('Network') ?></th>
                    <th class="text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quickLoginDevices as $quickLoginDevice) : ?>
                <?php
                        $deviceId = (string)$quickLoginDevice->device_id;
                        $shortDeviceId = strlen($deviceId) > 18
                            ? substr($deviceId, 0, 8) . '...' . substr($deviceId, -6)
                            : $deviceId;
                        $deviceSignature = trim((string)implode(' / ', array_filter([
                            $quickLoginDevice->configured_os,
                            $quickLoginDevice->configured_browser,
                        ])));
                        $configuredAt = $quickLoginDevice->created?->i18nFormat('yyyy-MM-dd HH:mm');
                        $lastUsedAt = $quickLoginDevice->last_used?->i18nFormat('yyyy-MM-dd HH:mm');
                        $configuredNetwork = trim((string)($quickLoginDevice->configured_location_hint ?? ''));
                        if ($configuredNetwork === '') {
                            $configuredNetwork = trim((string)($quickLoginDevice->configured_ip_address ?? ''));
                        }
                        $lastUsedNetwork = trim((string)($quickLoginDevice->last_used_location_hint ?? ''));
                        if ($lastUsedNetwork === '') {
                            $lastUsedNetwork = trim((string)($quickLoginDevice->last_used_ip_address ?? ''));
                        }
                    ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <?= h($deviceSignature !== '' ? $deviceSignature : __('Unknown device')) ?>
                        </div>
                        <div class="text-muted small" title="<?= h($deviceId) ?>">
                            <?= h($shortDeviceId) ?>
                        </div>
                    </td>
                    <td><?= h($configuredAt ?? __('Unknown')) ?></td>
                    <td><?= h($lastUsedAt ?? __('Never')) ?></td>
                    <td>
                        <div class="small">
                            <span class="text-muted"><?= __('Configured:') ?></span>
                            <?= h($configuredNetwork !== '' ? $configuredNetwork : __('Unknown')) ?>
                        </div>
                        <div class="small">
                            <span class="text-muted"><?= __('Last used:') ?></span>
                            <?= h($lastUsedNetwork !== '' ? $lastUsedNetwork : __('Unknown')) ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <?php if ($canManageQuickLoginDevices) : ?>
                        <?= $this->Form->postLink(
                                __('Disable'),
                                ['action' => 'removeQuickLoginDevice', $quickLoginDevice->id],
                                [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'confirm' => __('Disable quick login on this device?'),
                                ],
                            ) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock('modals');
// Start writing to modal block in layout
echo $this->element('members/editModal', [
    'user' => $user,
]);
echo $this->element('members/changePasswordModal', [
    'user' => $user,
]);
echo $this->element('members/verifyMembershipModal', [
    'user' => $user,
    'needVerification' => $needVerification,
    'needsParentVerification' => $needsParentVerification,
    'needsMemberCardVerification' => $needsMemberCardVerification,
]);
echo $this->element('members/submitMemberCard', [
    'user' => $user,
]);
echo $this->element('members/profilePhotoModal', [
    'user' => $user,
]);
echo $this->element('members/gatheringAttendanceModals', [
    'user' => $user,
    'member' => $member,
    'availableGatherings' => $availableGatherings,
]);
// End writing to modal block in layout
$this->KMP->endBlock(); ?>
