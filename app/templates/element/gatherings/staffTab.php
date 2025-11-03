<?php

/**
 * Gathering Staff Tab
 *
 * Displays stewards and other staff for a gathering
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\Member $user
 */

// Separate stewards from other staff
$stewards = [];
$otherStaff = [];
foreach ($gathering->gathering_staff as $staff) {
    if ($staff->is_steward) {
        $stewards[] = $staff;
    } else {
        $otherStaff[] = $staff;
    }
}
?>
<div class="related tab-pane fade m-3" id="nav-staff" role="tabpanel" aria-labelledby="nav-staff-tab"
    data-detail-tabs-target="tabContent" data-tab-order="3" style="order: 3;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?= __('Gathering Staff') ?></h4>
        <?php if ($user->checkCan('edit', $gathering)) : ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="bi bi-plus-circle"></i> <?= __('Add Staff Member') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (empty($stewards) && empty($otherStaff)) : ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('No staff members have been assigned yet. Click "Add Staff Member" to add stewards and other staff.') ?>
        </div>
    <?php else : ?>

        <!-- Stewards Section -->
        <?php if (!empty($stewards)) : ?>
            <div class="mb-4">
                <h5 class="border-bottom pb-2">
                    <i class="bi bi-star-fill text-warning"></i> <?= __('Stewards') ?>
                </h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?= __('Name') ?></th>
                                <th><?= __('Email') ?></th>
                                <th><?= __('Phone') ?></th>
                                <th><?= __('Contact Notes') ?></th>
                                <th><?= __('Public Visibility') ?></th>
                                <?php if ($user->checkCan('edit', $gathering)) : ?>
                                    <th class="actions"><?= __('Actions') ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stewards as $staff) : ?>
                                <tr>
                                    <td>
                                        <?= h($staff->display_name) ?>
                                        <?php if ($staff->member_id) : ?>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-person-check"></i> AMP Member
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff->email)) : ?>
                                            <a href="mailto:<?= h($staff->email) ?>">
                                                <?= h($staff->email) ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff->phone)) : ?>
                                            <a href="tel:<?= h($staff->phone) ?>">
                                                <?= h($staff->phone) ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff->contact_notes)) : ?>
                                            <small><?= h($staff->contact_notes) ?></small>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staff->show_on_public_page) : ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-globe"></i> <?= __('Visible') ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-lock"></i> <?= __('Private') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($user->checkCan('edit', $gathering)) : ?>
                                        <td class="actions">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editStaffModal"
                                                data-staff-id="<?= $staff->id ?>"
                                                data-staff-name="<?= h($staff->display_name) ?>"
                                                data-staff-role="<?= h($staff->role) ?>"
                                                data-staff-email="<?= h($staff->email ?? '') ?>"
                                                data-staff-phone="<?= h($staff->phone ?? '') ?>"
                                                data-staff-notes="<?= h($staff->contact_notes ?? '') ?>"
                                                data-is-steward="<?= $staff->is_steward ? '1' : '0' ?>"
                                                data-show-on-public-page="<?= $staff->show_on_public_page ? '1' : '0' ?>">
                                                <?= __('Edit') ?>
                                            </button>
                                            <?= $this->Form->postLink(
                                                __('Remove'),
                                                ['controller' => 'GatheringStaff', 'action' => 'delete', $staff->id],
                                                [
                                                    'confirm' => __('Remove {0} as a steward?', $staff->display_name),
                                                    'class' => 'btn btn-sm btn-outline-danger'
                                                ]
                                            ) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else : ?>
            <div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle"></i>
                <strong><?= __('No Stewards Assigned') ?></strong> - At least one steward should be assigned to this gathering.
            </div>
        <?php endif; ?>

        <!-- Other Staff Section -->
        <?php if (!empty($otherStaff)) : ?>
            <div class="mb-4">
                <h5 class="border-bottom pb-2">
                    <i class="bi bi-people-fill"></i> <?= __('Other Staff') ?>
                </h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?= __('Name') ?></th>
                                <th><?= __('Role') ?></th>
                                <th><?= __('Email') ?></th>
                                <th><?= __('Phone') ?></th>
                                <th><?= __('Public Visibility') ?></th>
                                <?php if ($user->checkCan('edit', $gathering)) : ?>
                                    <th class="actions"><?= __('Actions') ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($otherStaff as $staff) : ?>
                                <tr>
                                    <td>
                                        <?= h($staff->display_name) ?>
                                        <?php if ($staff->member_id) : ?>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-person-check"></i> AMP Member
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($staff->role) ?></td>
                                    <td>
                                        <?php if (!empty($staff->email)) : ?>
                                            <a href="mailto:<?= h($staff->email) ?>">
                                                <?= h($staff->email) ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff->phone)) : ?>
                                            <a href="tel:<?= h($staff->phone) ?>">
                                                <?= h($staff->phone) ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staff->show_on_public_page) : ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-globe"></i> <?= __('Visible') ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-lock"></i> <?= __('Private') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($user->checkCan('edit', $gathering)) : ?>
                                        <td class="actions">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editStaffModal"
                                                data-staff-id="<?= $staff->id ?>"
                                                data-staff-name="<?= h($staff->display_name) ?>"
                                                data-staff-role="<?= h($staff->role) ?>"
                                                data-staff-email="<?= h($staff->email ?? '') ?>"
                                                data-staff-phone="<?= h($staff->phone ?? '') ?>"
                                                data-staff-notes="<?= h($staff->contact_notes ?? '') ?>"
                                                data-is-steward="<?= $staff->is_steward ? '1' : '0' ?>"
                                                data-show-on-public-page="<?= $staff->show_on_public_page ? '1' : '0' ?>">
                                                <?= __('Edit') ?>
                                            </button>
                                            <?= $this->Form->postLink(
                                                __('Remove'),
                                                ['controller' => 'GatheringStaff', 'action' => 'delete', $staff->id],
                                                [
                                                    'confirm' => __('Remove {0} from staff?', $staff->display_name),
                                                    'class' => 'btn btn-sm btn-outline-danger'
                                                ]
                                            ) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($user->checkCan('edit', $gathering)) : ?>
        <div class="alert alert-info mt-4">
            <h6><i class="bi bi-info-circle"></i> <?= __('Staff Management Tips') ?></h6>
            <ul class="mb-0">
                <li><?= __('Stewards must be AMP members and require contact information (email or phone)') ?></li>
                <li><?= __('Other staff can be AMP members or non-members with optional contact information') ?></li>
                <li><?= __('Contact information for stewards is copied from their AMP account but can be edited for privacy') ?></li>
                <li><?= __('Use contact notes to specify preferences like "text only" or "no calls after 9 PM"') ?></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Add Staff Modal -->
<?php if ($user->checkCan('edit', $gathering)) : ?>
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <?= $this->Form->create(null, [
                    'url' => ['controller' => 'GatheringStaff', 'action' => 'add', $gathering->id],
                    'id' => 'addStaffForm'
                ]) ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addStaffModalLabel">
                        <i class="bi bi-person-plus"></i> <?= __('Add Staff Member') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong><?= __('Staff Types:') ?></strong>
                        <ul class="mb-0">
                            <li><strong><?= __('Stewards') ?></strong> <?= __('must be AMP members and require contact information (email or phone)') ?></li>
                            <li><strong><?= __('Other Staff') ?></strong> <?= __('can be AMP members or non-members with optional contact info') ?></li>
                        </ul>
                    </div>

                    <?= $this->Form->control('is_steward', [
                        'type' => 'checkbox',
                        'label' => __('This person is a Steward'),
                        'id' => 'add-is-steward'
                    ]) ?>

                    <?= $this->Form->control('role', [
                        'label' => __('Role'),
                        'placeholder' => __('e.g., Steward, Herald, List Master, Water Bearer'),
                        'required' => true
                    ]) ?>

                    <hr>
                    <h6><?= __('Staff Member') ?></h6>

                    <?php
                    // Use autocomplete control like Awards recommendations
                    $memberUrl = $this->Url->build([
                        'controller' => 'Members',
                        'action' => 'AutoComplete',
                        'plugin' => null
                    ]);

                    echo $this->KMP->autoCompleteControl(
                        $this->Form,
                        'member_sca_name',
                        'member_public_id',
                        $memberUrl,
                        __('AMP Member or SCA Name'),
                        true,  // required
                        true,  // allowOtherValues - allows entering non-AMP names
                        3,     // minLength
                        [
                            'id' => 'add-member-autocomplete',
                            'data-action' => 'change->gathering-staff-add#memberSelected'
                        ]
                    );
                    ?>

                    <small class="text-muted">
                        <?= __('Start typing to search for an AMP member, or type any SCA name if they are not in the system.') ?>
                        <br>
                        <i class="bi bi-info-circle"></i> <?= __('When you select an AMP member, their email and phone will be automatically copied below.') ?>
                    </small>

                    <hr>
                    <h6><?= __('Contact Information') ?></h6>
                    <div class="alert alert-warning" id="add-steward-notice" style="display: none;">
                        <strong><?= __('Steward Requirement:') ?></strong> <?= __('At least one contact method (email or phone) is required for stewards.') ?>
                    </div>
                    <div class="alert alert-success" id="add-autofill-notice" style="display: none;">
                        <i class="bi bi-check-circle"></i> <strong><?= __('Contact Info Auto-Filled') ?></strong>
                        <br>
                        <?= __('Email and phone copied from AMP member account. You may edit these if needed (e.g., for privacy).') ?>
                    </div>

                    <?= $this->Form->control('email', [
                        'type' => 'email',
                        'label' => __('Email Address'),
                        'id' => 'add-email'
                    ]) ?>

                    <?= $this->Form->control('phone', [
                        'label' => __('Phone Number'),
                        'id' => 'add-phone'
                    ]) ?>

                    <?= $this->Form->control('contact_notes', [
                        'type' => 'textarea',
                        'label' => __('Contact Notes'),
                        'rows' => 3,
                        'placeholder' => __('e.g., "Please text, no calls after 9 PM", "Emergency contact only"')
                    ]) ?>

                    <hr>
                    <h6><?= __('Public Visibility') ?></h6>
                    <?= $this->Form->control('show_on_public_page', [
                        'type' => 'checkbox',
                        'label' => __('Show on Public Event Page'),
                        'id' => 'add-show-on-public-page',
                        'help' => __('When checked, this staff member will be visible on the public event page. Stewards are always shown.')
                    ]) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> <?= __('Add Staff Member') ?>
                    </button>
                </div>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <?= $this->Form->create(null, [
                    'url' => ['controller' => 'GatheringStaff', 'action' => 'edit', 'XXX'],
                    'id' => 'editStaffForm'
                ]) ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="editStaffModalLabel">
                        <i class="bi bi-pencil"></i> <?= __('Edit Staff Member') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" id="edit-steward-info" style="display: none;">
                        <strong><?= __('Steward:') ?></strong> <?= __('Contact information (email or phone) is required.') ?>
                    </div>

                    <?= $this->Form->control('is_steward', [
                        'type' => 'checkbox',
                        'label' => __('This person is a Steward'),
                        'id' => 'edit-is-steward'
                    ]) ?>

                    <?= $this->Form->control('role', [
                        'label' => __('Role'),
                        'id' => 'edit-role',
                        'required' => true
                    ]) ?>

                    <hr>
                    <h6><?= __('Staff Member') ?></h6>
                    <div class="form-group">
                        <label><?= __('Name') ?></label>
                        <div class="form-control-plaintext" id="edit-staff-name-display"></div>
                        <small class="text-muted"><?= __('To change the staff member, please remove and add a new entry.') ?></small>
                    </div>

                    <hr>
                    <h6><?= __('Contact Information') ?></h6>
                    <div class="alert alert-warning" id="edit-steward-notice" style="display: none;">
                        <strong><?= __('Note:') ?></strong> <?= __('At least one contact method (email or phone) is required for stewards.') ?>
                    </div>

                    <?= $this->Form->control('email', [
                        'type' => 'email',
                        'label' => __('Email Address'),
                        'id' => 'edit-email'
                    ]) ?>

                    <?= $this->Form->control('phone', [
                        'label' => __('Phone Number'),
                        'id' => 'edit-phone'
                    ]) ?>

                    <?= $this->Form->control('contact_notes', [
                        'type' => 'textarea',
                        'label' => __('Contact Notes'),
                        'rows' => 3,
                        'id' => 'edit-contact-notes'
                    ]) ?>

                    <hr>
                    <h6><?= __('Public Visibility') ?></h6>
                    <?= $this->Form->control('show_on_public_page', [
                        'type' => 'checkbox',
                        'label' => __('Show on Public Event Page'),
                        'id' => 'edit-show-on-public-page',
                        'help' => __('When checked, this staff member will be visible on the public event page. Stewards are always shown.')
                    ]) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> <?= __('Save Changes') ?>
                    </button>
                </div>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>

    <?php $this->start('script'); ?>
    <script>
        (function() {
            // Add Staff Modal Logic
            const addModal = document.getElementById('addStaffModal');
            if (addModal) {
                const isStewardCheckbox = document.getElementById('add-is-steward');
                const memberPublicIdInput = document.getElementById('member-public-id'); // Hidden field from autocomplete
                const memberAutocomplete = document.getElementById('add-member-autocomplete'); // Text field from autocomplete
                const emailInput = document.getElementById('add-email');
                const phoneInput = document.getElementById('add-phone');
                const stewardNotice = document.getElementById('add-steward-notice');
                const autoFillNotice = document.getElementById('add-autofill-notice');
                const showOnPublicPageCheckbox = document.getElementById('add-show-on-public-page');

                function updateAddStewardFields() {
                    if (isStewardCheckbox && isStewardCheckbox.checked) {
                        if (stewardNotice) stewardNotice.style.display = 'block';

                        // Stewards must always show on public page
                        if (showOnPublicPageCheckbox) {
                            showOnPublicPageCheckbox.checked = true;
                            showOnPublicPageCheckbox.disabled = true;
                        }

                        // If a member is already selected, fetch their contact info
                        if (memberPublicIdInput && memberPublicIdInput.value) {
                            fetchMemberContactInfo(memberPublicIdInput.value);
                        }
                    } else {
                        if (stewardNotice) stewardNotice.style.display = 'none';

                        // Non-stewards can choose
                        if (showOnPublicPageCheckbox) {
                            showOnPublicPageCheckbox.disabled = false;
                        }
                    }
                }

                function fetchMemberContactInfo(memberPublicId) {
                    if (!memberPublicId) return;

                    if (autoFillNotice) autoFillNotice.style.display = 'block';

                    // SECURITY: Use public IDs instead of internal database IDs
                    // Both member and gathering require public_id which prevents enumeration
                    const gatheringPublicId = '<?= $gathering->public_id ?>';

                    fetch('<?= $this->Url->build(['controller' => 'GatheringStaff', 'action' => 'getMemberContactInfo']) ?>?member_public_id=' + memberPublicId + '&gathering_public_id=' + gatheringPublicId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                console.error('Error fetching member info:', data.error);
                                return;
                            }

                            // Always populate email and phone from AMP member if available
                            // Only fill if fields are currently empty to avoid overwriting user edits
                            if (data.email && emailInput && !emailInput.value) {
                                emailInput.value = data.email;
                            }
                            if (data.phone && phoneInput && !phoneInput.value) {
                                phoneInput.value = data.phone;
                            }
                        })
                        .catch(error => console.error('Error fetching member info:', error));
                }

                // Listen for autocomplete selection
                if (memberAutocomplete) {
                    memberAutocomplete.addEventListener('autocomplete.change', function(event) {
                        // When an AMP member is selected from autocomplete
                        if (event.detail && event.detail.value) {
                            // Fetch contact info whenever an AMP member is selected
                            // This is especially important for stewards who require contact info
                            fetchMemberContactInfo(event.detail.value);
                        }
                    });
                }

                // Also listen on the parent element (the div with data-controller)
                const autocompleteContainer = memberAutocomplete?.closest('[data-controller="ac"]');
                if (autocompleteContainer) {
                    autocompleteContainer.addEventListener('autocomplete.change', function(event) {
                        if (event.detail && event.detail.value) {
                            fetchMemberContactInfo(event.detail.value);
                        }
                    });
                }

                if (isStewardCheckbox) {
                    isStewardCheckbox.addEventListener('change', updateAddStewardFields);
                }

                // Reset form when modal is closed
                addModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('addStaffForm').reset();
                    if (stewardNotice) stewardNotice.style.display = 'none';
                    if (autoFillNotice) autoFillNotice.style.display = 'none';

                    // Reset autocomplete
                    if (memberPublicIdInput) memberPublicIdInput.value = '';
                    if (memberAutocomplete) memberAutocomplete.value = '';
                });

                // Initialize on modal show
                addModal.addEventListener('shown.bs.modal', function() {
                    updateAddStewardFields();
                });
            }

            // Edit Staff Modal Logic
            const editModal = document.getElementById('editStaffModal');
            if (editModal) {
                const editIsStewardCheckbox = document.getElementById('edit-is-steward');
                const editShowOnPublicPageCheckbox = document.getElementById('edit-show-on-public-page');

                // Update show_on_public_page based on is_steward changes
                if (editIsStewardCheckbox) {
                    editIsStewardCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            // Stewards must always show on public page
                            if (editShowOnPublicPageCheckbox) {
                                editShowOnPublicPageCheckbox.checked = true;
                                editShowOnPublicPageCheckbox.disabled = true;
                            }
                        } else {
                            // Non-stewards can choose
                            if (editShowOnPublicPageCheckbox) {
                                editShowOnPublicPageCheckbox.disabled = false;
                            }
                        }
                    });
                }

                editModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const staffId = button.getAttribute('data-staff-id');
                    const staffName = button.getAttribute('data-staff-name');
                    const staffRole = button.getAttribute('data-staff-role');
                    const staffEmail = button.getAttribute('data-staff-email');
                    const staffPhone = button.getAttribute('data-staff-phone');
                    const staffNotes = button.getAttribute('data-staff-notes');
                    const isSteward = button.getAttribute('data-is-steward') === '1';
                    const showOnPublicPage = button.getAttribute('data-show-on-public-page') === '1';

                    // Update form action
                    const form = document.getElementById('editStaffForm');
                    form.action = '<?= $this->Url->build(['controller' => 'GatheringStaff', 'action' => 'edit', 'XXX']) ?>'.replace('XXX', staffId);

                    // Populate fields
                    document.getElementById('edit-staff-name-display').textContent = staffName;
                    document.getElementById('edit-is-steward').checked = isSteward;
                    document.getElementById('edit-role').value = staffRole;
                    document.getElementById('edit-email').value = staffEmail;
                    document.getElementById('edit-phone').value = staffPhone;
                    document.getElementById('edit-contact-notes').value = staffNotes;
                    document.getElementById('edit-show-on-public-page').checked = showOnPublicPage;

                    // Handle show_on_public_page checkbox state
                    if (isSteward) {
                        // Stewards must always show on public page
                        document.getElementById('edit-show-on-public-page').disabled = true;
                    } else {
                        // Non-stewards can choose
                        document.getElementById('edit-show-on-public-page').disabled = false;
                    }

                    // Show/hide steward notices
                    const stewardInfo = document.getElementById('edit-steward-info');
                    const stewardNotice = document.getElementById('edit-steward-notice');
                    if (isSteward) {
                        if (stewardInfo) stewardInfo.style.display = 'block';
                        if (stewardNotice) stewardNotice.style.display = 'block';
                    } else {
                        if (stewardInfo) stewardInfo.style.display = 'none';
                        if (stewardNotice) stewardNotice.style.display = 'none';
                    }
                });
            }
        })();
    </script>
    <?php $this->end(); ?>
<?php endif; ?>