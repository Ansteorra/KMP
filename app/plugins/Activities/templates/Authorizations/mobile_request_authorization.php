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

<div class="card cardbox mx-3"
    data-controller="mobile-request-auth"
    data-mobile-request-auth-approvers-url-value="<?= $this->Url->build(['controller' => 'Activities', 'action' => 'approversList', 'plugin' => 'Activities']) ?>"
    data-mobile-request-auth-member-id-value="<?= h($memberId) ?>">
    <div class="card-body">
        <h3 class="card-title text-center display-6">
            <i class="bi bi-file-earmark-check me-2"></i>Request Authorization
        </h3>

        <!-- Online Status Indicator -->
        <div class="alert alert-warning d-flex align-items-center"
            data-mobile-request-auth-target="onlineStatus"
            hidden>
            <i class="bi bi-wifi me-2" style="font-size: 24px;"></i>
            <div>
                <strong>Online Required:</strong> You must be online to request authorizations.
            </div>
        </div>

        <?= $this->Form->create(null, [
            'url' => ['controller' => 'Authorizations', 'action' => 'add', 'plugin' => 'Activities'],
            'data-mobile-request-auth-target' => 'form'
        ]); ?>

        <?= $this->Form->hidden('member_id', ['value' => $memberId]) ?>

        <div class="mb-3">
            <label for="activity" class="form-label fw-bold">Select Activity</label>
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
            <label for="approver" class="form-label fw-bold">Send Request To</label>
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

        <div class="d-grid gap-2">
            <button type="submit"
                class="btn btn-success btn-lg"
                data-mobile-request-auth-target="submitBtn"
                disabled>
                <span data-mobile-request-auth-target="submitText">Submit Request</span>
            </button>

            <a href="javascript:history.back()" class="btn btn-secondary btn-lg">
                Cancel
            </a>
        </div>

        <?= $this->Form->end() ?>
    </div>
</div>