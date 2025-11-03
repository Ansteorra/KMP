<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringStaff $staff
 * @var \Cake\Collection\CollectionInterface|string[] $members
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Back to Gathering'), ['controller' => 'Gatherings', 'action' => 'view', $staff->gathering_id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $staff->id],
                ['confirm' => __('Are you sure you want to remove this staff member?'), 'class' => 'side-nav-item text-danger']
            ) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="gatheringStaff form content">
            <?= $this->Form->create($staff) ?>
            <fieldset>
                <legend><?= __('Edit Staff Member') ?></legend>

                <?php if ($staff->is_steward): ?>
                    <div class="alert alert-info">
                        <strong>Steward:</strong> Contact information (email or phone) is required.
                    </div>
                <?php endif; ?>

                <?php
                echo $this->Form->control('is_steward', [
                    'type' => 'checkbox',
                    'label' => 'This person is a Steward',
                    'id' => 'is-steward-checkbox'
                ]);

                echo $this->Form->control('role', [
                    'label' => 'Role',
                    'placeholder' => 'e.g., Steward, Herald, List Master, Water Bearer'
                ]);
                ?>

                <div class="staff-type-fields">
                    <h4>Staff Member</h4>
                    <?php if ($staff->member_id): ?>
                        <div class="form-group">
                            <label>AMP Member</label>
                            <div class="form-control-plaintext">
                                <?= h($staff->member->sca_name ?? 'Unknown') ?>
                            </div>
                            <?= $this->Form->hidden('member_id') ?>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>SCA Name</label>
                            <div class="form-control-plaintext">
                                <?= h($staff->sca_name) ?>
                            </div>
                            <?= $this->Form->hidden('sca_name') ?>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted">
                        To change the staff member, please remove and add a new entry.
                    </small>
                </div>

                <div class="mt-4">
                    <h4>Contact Information</h4>
                    <?php if ($staff->is_steward): ?>
                        <div class="alert alert-warning">
                            <strong>Note:</strong> At least one contact method (email or phone) is required for stewards.
                        </div>
                    <?php endif; ?>

                    <?php
                    echo $this->Form->control('email', [
                        'type' => 'email',
                        'label' => 'Email Address'
                    ]);

                    echo $this->Form->control('phone', [
                        'type' => 'text',
                        'label' => 'Phone Number'
                    ]);

                    echo $this->Form->control('contact_notes', [
                        'type' => 'textarea',
                        'label' => 'Contact Notes',
                        'rows' => 3,
                        'placeholder' => 'e.g., "Please text, no calls after 9 PM", "Emergency contact only"'
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
                        'disabled' => $staff->is_steward,
                        'help' => $staff->is_steward ? 'Stewards are always shown on the public page.' : 'When checked, this staff member will be visible on the public event page.'
                    ]);
                    ?>
                </div>
            </fieldset>
            <?= $this->Form->button(__('Save Changes'), ['class' => 'btn btn-primary']) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>