<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $member
 * @var array $availableGatherings
 */
?>

<!-- Add Gathering Attendance Modal -->
<div class="modal fade" id="addGatheringAttendanceModal" tabindex="-1"
    aria-labelledby="addGatheringAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'GatheringAttendances', 'action' => 'add'],
                'id' => 'addGatheringAttendanceForm'
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="addGatheringAttendanceModalLabel">RSVP for Gathering</h5><br />
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary" role="alert">
                    <small>Share with the crown, your friends, or the gathering hosts that you plan to attend this
                        gathering (not a replacement for paypal prereg).</small>
                </div>
                <?= $this->Form->hidden('member_id', ['value' => $member->id]) ?>

                <?= $this->Form->control('gathering_id', [
                    'type' => 'select',
                    'label' => 'Gathering',
                    'options' => $availableGatherings,
                    'empty' => '-- Select a Gathering --',
                    'required' => true,
                    'class' => 'form-select'
                ]) ?>

                <?= $this->Form->control('public_note', [
                    'type' => 'textarea',
                    'label' => 'Public Note',
                    'placeholder' => 'Optional note to share...',
                    'tooltip' => 'Optional note to viewable by those that you select below.',
                    'rows' => 3,
                    'class' => 'form-control'
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
                        'tooltip' => 'Signed-in members can see your name and note on the public gathering page, and the host group and crown can view it in AMP.'
                    ]) ?>

                    <?= $this->Form->control('share_with_hosting_group', [
                        'type' => 'checkbox',
                        'label' => 'Share with Hosting Group',
                        'class' => 'form-check-input',
                        'switch' => true,
                        'tooltip' => 'Hosts of the event will be able to see your name and public note on the gathering page.'
                    ]) ?>

                    <?= $this->Form->control('share_with_crown', [
                        'type' => 'checkbox',
                        'label' => 'Share with Nobility/Crown',
                        'class' => 'form-check-input',
                        'switch' => true,
                        'tooltip' => 'The nobility and crown will be able to see your attendance information in regards to managing award recommendations.'
                    ]) ?>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <?= $this->Form->button('Register', ['class' => 'btn btn-primary']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Edit Gathering Attendance Modal -->
<div class="modal fade" id="editGatheringAttendanceModal" tabindex="-1"
    aria-labelledby="editGatheringAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'GatheringAttendances', 'action' => 'edit'],
                'id' => 'editGatheringAttendanceForm'
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="editGatheringAttendanceModalLabel">
                    Edit Attendance: <span id="editGatheringName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <small>This will just allow you to show your intent to attend, but does not register you for the
                        event itself.</small>
                </div>
                <?= $this->Form->hidden('id', ['id' => 'editAttendanceId']) ?>

                <?= $this->Form->control('public_note', [
                    'type' => 'textarea',
                    'label' => 'Public Note',
                    'id' => 'editPublicNote',
                    'placeholder' => 'Optional note to share...',
                    'rows' => 3,
                    'class' => 'form-control'
                ]) ?>

                <div class="mt-3">
                    <label class="form-label">Share Information With:</label>
                    <small class="form-text text-muted d-block mb-2">
                        Select who can see that you plan to attend this gathering. If nothing is selected, your
                        attendance will be private.
                    </small>

                    <?= $this->Form->control('share_with_kingdom', [
                        'type' => 'checkbox',
                        'label' => 'Share with Kingdom',
                        'id' => 'editShareKingdom',
                        'class' => 'form-check-input',
                        'switch' => true
                    ]) ?>

                    <?= $this->Form->control('share_with_hosting_group', [
                        'type' => 'checkbox',
                        'label' => 'Share with Hosting Group',
                        'id' => 'editShareHosting',
                        'class' => 'form-check-input',
                        'switch' => true
                    ]) ?>

                    <?= $this->Form->control('share_with_crown', [
                        'type' => 'checkbox',
                        'label' => 'Share with Nobility/Crown',
                        'id' => 'editShareCrown',
                        'class' => 'form-check-input',
                        'switch' => true
                    ]) ?>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <?= $this->Form->button('Update', ['class' => 'btn btn-primary']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<script>
// Populate edit modal with attendance data
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editGatheringAttendanceModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const attendanceId = button.getAttribute('data-attendance-id');
            const gatheringName = button.getAttribute('data-gathering-name');
            const publicNote = button.getAttribute('data-public-note');
            const shareKingdom = button.getAttribute('data-share-kingdom') === '1';
            const shareHosting = button.getAttribute('data-share-hosting') === '1';
            const shareCrown = button.getAttribute('data-share-crown') === '1';

            // Update form action URL to include the ID
            const form = document.getElementById('editGatheringAttendanceForm');
            form.action = form.action.replace(/\/edit.*$/, '/edit/' + attendanceId);

            // Update modal content
            document.getElementById('editGatheringName').textContent = gatheringName;
            document.getElementById('editAttendanceId').value = attendanceId;
            document.getElementById('editPublicNote').value = publicNote || '';
            document.getElementById('editShareKingdom').checked = shareKingdom;
            document.getElementById('editShareHosting').checked = shareHosting;
            document.getElementById('editShareCrown').checked = shareCrown;
        });
    }
});
</script>
