<?php

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

<!-- Edit Gathering Attendance Modal - Content loaded dynamically via AJAX -->
<div class="modal fade" id="editGatheringAttendanceModal" tabindex="-1"
    aria-labelledby="editGatheringAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" id="editGatheringAttendanceModalContent">
            <!-- Content will be loaded dynamically -->
            <div class="modal-header">
                <h5 class="modal-title">Loading...</h5>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editGatheringAttendanceModal');
    const modalContent = document.getElementById('editGatheringAttendanceModalContent');

    if (editModal && modalContent) {
        editModal.addEventListener('show.bs.modal', async function(event) {
            const button = event.relatedTarget;
            const attendanceId = button.getAttribute('data-attendance-id');
            const gatheringId = button.getAttribute('data-gathering-id');

            if (!gatheringId || !attendanceId) {
                console.error('Missing gathering ID or attendance ID');
                return;
            }

            // Show loading state
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h5 class="modal-title">Loading...</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;

            try {
                // Fetch the modal content from the same endpoint used by the calendar
                const url = `/gatherings/attendance-modal/${gatheringId}?attendance_id=${attendanceId}`;
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const html = await response.text();
                modalContent.innerHTML = html;

                // Manually attach click handler to close button since Bootstrap's event delegation
                // doesn't work on dynamically inserted content
                const closeButton = modalContent.querySelector('.btn-close');
                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        const bsModal = bootstrap.Modal.getInstance(editModal);
                        if (bsModal) {
                            bsModal.hide();
                        }
                    });
                }

            } catch (error) {
                console.error('Error loading attendance modal:', error);
                modalContent.innerHTML = `
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">Error</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            Failed to load attendance form. Please try again or refresh the page.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                `;
            }
        });
    }
});
</script>
