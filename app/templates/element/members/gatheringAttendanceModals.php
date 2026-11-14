<?php
declare(strict_types=1);

/**
 * Gathering Attendance Modals for Member View
 *
 * Contains:
 * - Add modal: For RSVPing to a new gathering (with gathering selector dropdown)
 * - Edit modal: Shell that dynamically loads content from attendance_modal.php (same as calendar)
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $member
 * @var array $availableGatherings
 */

$isMinor = $member && $member->age !== null && $member->age < 18;
?>

<!-- Add Gathering Attendance Modal -->
<div class="modal fade" id="addGatheringAttendanceModal" tabindex="-1"
    aria-labelledby="addGatheringAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'GatheringAttendances', 'action' => 'add'],
                'id' => 'addGatheringAttendanceForm',
                'data-controller' => 'turbo-modal', 'data-turbo' => 'true',
                'data-action' => 'submit->turbo-modal#submitAsTurboStream',
                'data-turbo-modal-changed-value' => 'attendance',
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="addGatheringAttendanceModalLabel">RSVP for Gathering</h5><br />
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light-subtle">
                <div class="alert alert-secondary border-start border-secondary border-4" role="alert">
                    <small>Share with the crown, your friends, or the gathering hosts that you plan to attend this
                        gathering (not a replacement for paypal prereg).</small>
                </div>
                <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>
                <?= $this->Form->hidden('member_id', ['value' => $member->id]) ?>

                <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <kmp-icon name="calendar-event" class="text-primary me-1" aria-hidden="true"></kmp-icon>
                        <?= __('Gathering and Note') ?>
                    </legend>
                <?= $this->Form->control('gathering_id', [
                    'type' => 'select',
                    'label' => 'Gathering',
                    'options' => $availableGatherings,
                    'empty' => '-- Select a Gathering --',
                    'required' => true,
                    'class' => 'form-select',
                ]) ?>

                <?= $this->Form->control('public_note', [
                    'type' => 'textarea',
                    'label' => 'Public Note',
                    'placeholder' => 'Optional note to share...',
                    'tooltip' => 'Optional note to viewable by those that you select below.',
                    'rows' => 3,
                    'class' => 'form-control',
                ]) ?>

                </fieldset>


                <fieldset class="border rounded-3 bg-white shadow-sm p-3">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <kmp-icon name="share" class="text-primary me-1" aria-hidden="true"></kmp-icon>
                        <?= __('Sharing') ?>
                    </legend>
                <div>
                    <label class="form-label">Share Information With:</label>
                    <small class="form-text text-muted d-block mb-2">
                        The count of RSVPs is always visible to everyone. But only those below will be able to see your
                        SCA name and note.
                    </small>

                    <?php if (!$isMinor) : ?>
                        <?= $this->Form->control('share_with_kingdom', [
                        'type' => 'checkbox',
                        'label' => 'Share with Kingdom',
                        'class' => 'form-check-input',
                        'switch' => true,
                        'tooltip' => 'Signed-in members can see your name and note on the public gathering page, '
                        . 'and the host group and crown can view it in AMP.',
                    ]) ?>
                    <?php else : ?>
                        <?= $this->Form->hidden('share_with_kingdom', ['value' => '0']) ?>
                    <?php endif; ?>

                    <?= $this->Form->control('share_with_hosting_group', [
                        'type' => 'checkbox',
                        'label' => 'Share with Hosting Group',
                        'class' => 'form-check-input',
                        'switch' => true,
                        'tooltip' => 'Hosts of the event will be able to see your name and public note '
                    . 'on the gathering page.',
                    ]) ?>

                    <?= $this->Form->control('share_with_crown', [
                        'type' => 'checkbox',
                        'label' => 'Share with Nobility/Crown',
                        'class' => 'form-check-input',
                        'switch' => true,
                        'tooltip' => 'The nobility and crown will be able to see your attendance information '
                    . 'in regards to managing award recommendations.',
                    ]) ?>

                </div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <?= $this->Form->button('Register', ['class' => 'btn btn-primary']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Edit Gathering Attendance Modal - Content loaded dynamically via AJAX -->
<div class="modal fade" id="editGatheringAttendanceModal" tabindex="-1"
    data-controller="attendance-dialog"
    data-attendance-dialog-loading-value="<?= h(__('Loading attendance form…')) ?>"
    data-attendance-dialog-error-value="<?= h(__(
        'Unable to load the attendance form. Close this dialog and try again.',
    )) ?>"
    data-attendance-dialog-close-value="<?= h(__('Close')) ?>"
    aria-labelledby="editGatheringAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content" id="editGatheringAttendanceModalContent" data-attendance-dialog-target="content">
            <!-- Content will be loaded dynamically -->
            <div class="modal-header">
                <h5 class="modal-title" id="editGatheringAttendanceModalLabel">Loading...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>
