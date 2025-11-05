<?php

/**
 * Attendance Modal Content Template
 * 
 * This template renders just the modal content (header, body, footer)
 * for dynamic loading via AJAX in the calendar view.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringAttendance|null $userAttendance
 * @var \App\Model\Entity\Member $currentUser
 */

$isEdit = !empty($userAttendance);
$formId = 'attendanceModalForm';
?>

<?php if ($isEdit): ?>
    <?= $this->Form->create(null, [
        'type' => 'post',
        'url' => ['controller' => 'GatheringAttendances', 'action' => 'edit', $userAttendance->id],
        'id' => $formId
    ]) ?>
<?php else: ?>
    <?= $this->Form->create(null, [
        'type' => 'post',
        'url' => ['controller' => 'GatheringAttendances', 'action' => 'add'],
        'id' => $formId
    ]) ?>
<?php endif; ?>

<div class="modal-header">
    <h5 class="modal-title">
        <?= $isEdit ? __('Update Your Attendance') : __('Mark Your Attendance') ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="alert alert-secondary" role="alert">
        <small>Share with the crown, your friends, or the gathering hosts that you plan to attend this
            gathering (not a replacement for paypal prereg).</small>
    </div>

    <?php if (!$isEdit): ?>
        <?= $this->Form->hidden('member_id', ['value' => $currentUser->id]) ?>
        <?= $this->Form->hidden('gathering_id', ['value' => $gathering->id]) ?>
    <?php else: ?>
        <?= $this->Form->hidden('id', ['value' => $userAttendance->id]) ?>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong><?= h($gathering->name) ?></strong><br>
        <small>
            <?= $this->Timezone->format($gathering->start_date, $gathering, 'F j, Y') ?>
            <?php if (!$gathering->start_date->equals($gathering->end_date)): ?>
                - <?= $this->Timezone->format($gathering->end_date, $gathering, 'F j, Y') ?>
            <?php endif; ?>
        </small>
    </div>

    <?= $this->Form->control('public_note', [
        'type' => 'textarea',
        'label' => 'Public Note',
        'placeholder' => 'Optional note to share...',
        'rows' => 3,
        'class' => 'form-control',
        'tooltip' => 'Optional note viewable by those that you select below.',
        'value' => $isEdit ? $userAttendance->public_note : ''
    ]) ?>

    <div class="mt-3">
        <label class="form-label">Share Information With:</label>
        <small class="form-text text-muted d-block mb-2">
            The count of RSVPs is always visible to everyone. But only those below will be able to see your
            SCA name and note.
        </small>

        <?= $this->Form->control('share_with_kingdom', [
            'type' => 'checkbox',
            'label' => 'Share with Kingdom',
            'class' => 'form-check-input',
            'switch' => true,
            'checked' => $isEdit ? $userAttendance->share_with_kingdom : true,
            'tooltip' => 'Other users of AMP in your kingdom will be able to see your name and public note on the gathering page.'
        ]) ?>

        <?= $this->Form->control('share_with_hosting_group', [
            'type' => 'checkbox',
            'label' => 'Share with Hosting Group',
            'class' => 'form-check-input',
            'switch' => true,
            'checked' => $isEdit ? $userAttendance->share_with_hosting_group : false,
            'tooltip' => 'Hosts of the event will be able to see your name and public note on the gathering page.'
        ]) ?>

        <?= $this->Form->control('share_with_crown', [
            'type' => 'checkbox',
            'label' => 'Share with Nobility/Crown',
            'class' => 'form-check-input',
            'switch' => true,
            'checked' => $isEdit ? $userAttendance->share_with_crown : false,
            'tooltip' => 'The nobility and crown will be able to see your attendance information in regards to managing award recommendations.'
        ]) ?>

        <?= $this->Form->control('is_public', [
            'type' => 'checkbox',
            'label' => 'Make Public (SCA name only)',
            'class' => 'form-check-input',
            'switch' => true,
            'checked' => $isEdit ? $userAttendance->is_public : false,
            'tooltip' => 'If a gathering uses the landing page feature your name (but not note) will be visible to all visitors.'
        ]) ?>
    </div>
</div>

<?= $this->Form->end() ?>

<div class="modal-footer">
    <?php if ($isEdit): ?>
        <button type="button" class="btn btn-outline-danger btn-sm me-auto"
            onclick="if (confirm('<?= h(__('Are you sure you want to remove your attendance registration?')) ?>')) { document.getElementById('deleteAttendanceForm_<?= $userAttendance->id ?>').submit(); }">
            <?= __('Remove My Attendance') ?>
        </button>
    <?php endif; ?>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary" form="<?= $formId ?>">
        <?= $isEdit ? __('Update') : __('Register') ?>
    </button>
</div>

<?php if ($isEdit): ?>
    <!-- Separate hidden delete form -->
    <?= $this->Form->create(null, [
        'type' => 'post',
        'url' => ['controller' => 'GatheringAttendances', 'action' => 'delete', $userAttendance->id],
        'id' => 'deleteAttendanceForm_' . $userAttendance->id,
        'style' => 'display: none;'
    ]) ?>
    <?= $this->Form->end() ?>
<?php endif; ?>