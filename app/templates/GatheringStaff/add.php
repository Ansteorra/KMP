<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringStaff $staff
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Cake\Collection\CollectionInterface|string[] $members
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Back to Gathering'), ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="gatheringStaff form content">
            <?= $this->Form->create($staff) ?>
            <fieldset>
                <legend><?= __('Add Staff Member to {0}', h($gathering->name)) ?></legend>

                <div class="alert alert-info">
                    <strong>Staff Types:</strong>
                    <ul>
                        <li><strong>Stewards</strong> must be AMP members and require contact information (email or phone)</li>
                        <li><strong>Other Staff</strong> can be AMP members or non-members with optional contact info</li>
                    </ul>
                </div>

                <?php
                echo $this->Form->control('is_steward', [
                    'type' => 'checkbox',
                    'label' => 'This person is a Steward',
                    'id' => 'is-steward-checkbox'
                ]);

                echo $this->Form->control('role', [
                    'label' => 'Role',
                    'placeholder' => 'e.g., Steward, Herald, List Master, Water Bearer',
                    'help' => 'Enter the staff member\'s role'
                ]);
                ?>

                <div class="staff-type-fields">
                    <h4>Staff Member</h4>
                    <div id="member-fields">
                        <?php
                        echo $this->Form->control('member_id', [
                            'options' => $members,
                            'empty' => '-- Select AMP Member --',
                            'label' => 'AMP Member',
                            'id' => 'member-select'
                        ]);
                        ?>
                        <div class="text-muted">
                            <small>Select an AMP member, OR enter a name below for non-members</small>
                        </div>
                    </div>

                    <div id="sca-name-fields" class="mt-3">
                        <?php
                        echo $this->Form->control('sca_name', [
                            'label' => 'SCA Name (for non-AMP members)',
                            'placeholder' => 'e.g., Jane of Example',
                            'id' => 'sca-name-input'
                        ]);
                        ?>
                    </div>
                </div>

                <div id="contact-fields" class="mt-4">
                    <h4>Contact Information</h4>
                    <div class="alert alert-warning" id="steward-contact-notice" style="display: none;">
                        <strong>Note:</strong> Stewards must provide either an email address or phone number.
                    </div>
                    <div class="alert alert-info" id="contact-auto-fill-notice" style="display: none;">
                        <strong>Tip:</strong> Contact info will be automatically filled from the member's AMP account when you select a member.
                    </div>

                    <?php
                    echo $this->Form->control('email', [
                        'type' => 'email',
                        'label' => 'Email Address',
                        'id' => 'email-input'
                    ]);

                    echo $this->Form->control('phone', [
                        'type' => 'text',
                        'label' => 'Phone Number',
                        'id' => 'phone-input'
                    ]);

                    echo $this->Form->control('contact_notes', [
                        'type' => 'textarea',
                        'label' => 'Contact Notes',
                        'rows' => 3,
                        'placeholder' => 'e.g., "Please text, no calls after 9 PM", "Emergency contact only"',
                        'help' => 'Optional notes about how/when to contact this person'
                    ]);
                    ?>
                </div>

                <div class="mt-4">
                    <h4>Public Visibility</h4>
                    <?php
                    echo $this->Form->control('show_on_public_page', [
                        'type' => 'checkbox',
                        'label' => 'Show on Public Event Page',
                        'id' => 'show-on-public-page-checkbox',
                        'help' => 'When checked, this staff member will be visible on the public event page. Stewards are always shown.'
                    ]);
                    ?>
                </div>
            </fieldset>
            <?= $this->Form->button(__('Add Staff Member'), ['class' => 'btn btn-primary']) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<?php $this->start('script'); ?>
<script>
    (function() {
        const isStewardCheckbox = document.getElementById('is-steward-checkbox');
        const memberSelect = document.getElementById('member-select');
        const scaNameInput = document.getElementById('sca-name-input');
        const emailInput = document.getElementById('email-input');
        const phoneInput = document.getElementById('phone-input');
        const stewardNotice = document.getElementById('steward-contact-notice');
        const autoFillNotice = document.getElementById('contact-auto-fill-notice');
        const showOnPublicPageCheckbox = document.getElementById('show-on-public-page-checkbox');

        // Update UI based on steward status
        function updateStewardFields() {
            if (isStewardCheckbox.checked) {
                stewardNotice.style.display = 'block';
                // Stewards must be AMP members
                scaNameInput.disabled = true;
                scaNameInput.value = '';
                // Stewards must always show on public page
                showOnPublicPageCheckbox.checked = true;
                showOnPublicPageCheckbox.disabled = true;
            } else {
                stewardNotice.style.display = 'none';
                scaNameInput.disabled = false;
                showOnPublicPageCheckbox.disabled = false;
            }
        }

        // Update UI based on member selection
        function updateMemberFields() {
            if (memberSelect.value) {
                // AMP member selected - disable SCA name field
                scaNameInput.disabled = true;
                scaNameInput.value = '';
                autoFillNotice.style.display = 'block';

                // Fetch member contact info
                fetchMemberContactInfo(memberSelect.value);
            } else {
                // No member selected
                if (!isStewardCheckbox.checked) {
                    scaNameInput.disabled = false;
                }
                autoFillNotice.style.display = 'none';
            }
        }

        // Fetch member contact info via AJAX
        function fetchMemberContactInfo(memberId) {
            fetch('<?= $this->Url->build(['controller' => 'GatheringStaff', 'action' => 'getMemberContactInfo']) ?>?member_id=' + memberId)
                .then(response => response.json())
                .then(data => {
                    if (!data.error) {
                        if (data.email && !emailInput.value) {
                            emailInput.value = data.email;
                        }
                        if (data.phone && !phoneInput.value) {
                            phoneInput.value = data.phone;
                        }
                    }
                })
                .catch(error => console.error('Error fetching member info:', error));
        }

        // Event listeners
        isStewardCheckbox.addEventListener('change', updateStewardFields);
        memberSelect.addEventListener('change', updateMemberFields);

        // Initial state
        updateStewardFields();
        updateMemberFields();
    })();
</script>
<?php $this->end(); ?>