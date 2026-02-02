<?php

/**
 * Mobile Authorization Request Template
 * 
 * Mobile-optimized interface for requesting new activity authorizations.
 * Uses the mobile_app layout for consistent PWA experience.
 * All PWA infrastructure, menu, and styling is provided by the mobile_app layout.
 * This view only contains the form-specific content.
 */
?>

<div class="card cardbox mx-3 mt-3" data-section="request" data-controller="mobile-request-auth"
    data-mobile-request-auth-approvers-url-value="<?= $this->Url->build(['controller' => 'Activities', 'action' => 'approversList', 'plugin' => 'Activities']) ?>"
    data-mobile-request-auth-member-id-value="<?= h($memberId) ?>">
    <div class="card-body">
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'Authorizations', 'action' => 'add', 'plugin' => 'Activities'],
            'data-mobile-request-auth-target' => 'form'
        ]); ?>

        <?= $this->Form->hidden('member_id', ['value' => $memberId]) ?>

        <div class="mb-4">
            <label for="activity" class="form-label">Select Activity</label>
            <?= $this->Form->select(
                'activity',
                $activities,
                [
                    'empty' => '-- Choose an activity --',
                    'class' => 'form-select form-select-lg',
                    'id' => 'activity',
                    'data-mobile-request-auth-target' => 'activitySelect',
                    'data-action' => 'change->mobile-request-auth#loadApprovers'
                ]
            ) ?>
            <div class="form-text">What activity do you want to be authorized for?</div>
        </div>

        <div class="mb-4">
            <label for="approver" class="form-label">Send Request To</label>
            <?= $this->Form->select(
                'approver_id',
                [],
                [
                    'empty' => '-- Select activity first --',
                    'class' => 'form-select form-select-lg',
                    'id' => 'approver',
                    'disabled' => true,
                    'data-mobile-request-auth-target' => 'approverSelect',
                    'data-action' => 'change->mobile-request-auth#validateForm'
                ]
            ) ?>
            <div class="form-text" data-mobile-request-auth-target="approverHelp">
                Loading approvers...
            </div>
        </div>

        <div class="d-grid gap-3 mt-4">
            <button type="submit" class="btn btn-success btn-lg" data-mobile-request-auth-target="submitBtn" disabled>
                <i class="bi bi-send me-2"></i>
                <span data-mobile-request-auth-target="submitText">Submit Request</span>
            </button>

            <a href="javascript:history.back()" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left me-2"></i>Cancel
            </a>
        </div>

        <?= $this->Form->end() ?>
    </div>
</div>