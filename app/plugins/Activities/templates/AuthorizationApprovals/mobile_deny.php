<?php

/**
 * Mobile Authorization Denial Form
 * 
 * Mobile-optimized interface for denying activity authorization requests.
 * Uses the mobile_app layout for consistent PWA experience.
 * 
 * @var \App\View\AppView $this
 * @var \Activities\Model\Entity\AuthorizationApproval $authorizationApproval
 */
?>

<div class="card cardbox mx-3 mt-3" data-section="approvals">
    <div class="card-body">
        <!-- Authorization Request Details -->
        <div class="alert alert-warning mb-4">
            <h5 class="alert-heading mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Authorization Request to Deny</h5>
            <dl class="row mb-0">
                <dt class="col-4">Requester:</dt>
                <dd class="col-8"><strong><?= h($authorizationApproval->authorization->member->sca_name) ?></strong></dd>

                <dt class="col-4">Activity:</dt>
                <dd class="col-8"><strong><?= h($authorizationApproval->authorization->activity->name) ?></strong></dd>

                <dt class="col-4">Requested:</dt>
                <dd class="col-8"><?= $this->Timezone->format($authorizationApproval->requested_on) ?></dd>
            </dl>
        </div>

        <!-- Member Details -->
        <div class="card mb-4 member-info-card">
            <div class="card-header">
                <i class="bi bi-person me-2"></i><strong>Member Information</strong>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Legal Name:</dt>
                    <dd class="col-7"><?= h($authorizationApproval->authorization->member->legal_name) ?></dd>

                    <dt class="col-5">Member #:</dt>
                    <dd class="col-7"><?= h($authorizationApproval->authorization->member->membership_number ?: 'N/A') ?></dd>

                    <dt class="col-5">Member Exp:</dt>
                    <dd class="col-7">
                        <?= $authorizationApproval->authorization->member->membership_expires_on
                            ? $this->Timezone->format($authorizationApproval->authorization->member->membership_expires_on, null, 'n/j/Y')
                            : 'N/A' ?>
                    </dd>

                    <dt class="col-5">Bg Check Exp:</dt>
                    <dd class="col-7">
                        <?= $authorizationApproval->authorization->member->background_check_expires_on
                            ? $this->Timezone->format($authorizationApproval->authorization->member->background_check_expires_on, null, 'n/j/Y')
                            : 'N/A' ?>
                    </dd>

                    <dt class="col-5">Branch:</dt>
                    <dd class="col-7"><?= h($authorizationApproval->authorization->member->branch->name ?? 'N/A') ?></dd>
                </dl>
            </div>
        </div>

        <!-- Denial Form -->
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'AuthorizationApprovals', 'action' => 'mobileDeny', $authorizationApproval->id, 'plugin' => 'Activities']
        ]); ?>

        <?= $this->Form->hidden('id', ['value' => $authorizationApproval->id]) ?>

        <!-- Denial Reason -->
        <div class="mb-4">
            <label for="denial-reason" class="form-label">
                <i class="bi bi-chat-text me-2"></i>Reason for Denial <span class="text-danger">*</span>
            </label>
            <?= $this->Form->textarea(
                'approver_notes',
                [
                    'class' => 'form-control',
                    'id' => 'denial-reason',
                    'rows' => 5,
                    'required' => true,
                    'placeholder' => 'Please explain why you are denying this authorization request...'
                ]
            ) ?>
            <div class="form-text">
                <strong>Note:</strong> This message will be visible to the requester. Please be clear and professional.
            </div>
        </div>

        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>Warning:</strong> Denying this authorization cannot be undone. The member will need to submit a new request.
        </div>

        <!-- Action Buttons -->
        <div class="d-grid gap-3 mt-4">
            <button type="submit" class="btn btn-danger btn-lg">
                <i class="bi bi-x-circle me-2"></i>Deny Authorization
            </button>

            <?= $this->Html->link(
                '<i class="bi bi-arrow-left me-2"></i>Cancel',
                ['action' => 'mobileApproveAuthorizations'],
                ['class' => 'btn btn-secondary btn-lg', 'escape' => false]
            ) ?>
        </div>

        <?= $this->Form->end() ?>
    </div>
</div>