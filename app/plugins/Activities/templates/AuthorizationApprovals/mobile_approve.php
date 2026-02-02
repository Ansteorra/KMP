<?php

/**
 * Mobile Authorization Approval Form
 * 
 * Mobile-optimized interface for approving activity authorization requests.
 * Uses the mobile_app layout for consistent PWA experience.
 * 
 * @var \App\View\AppView $this
 * @var \Activities\Model\Entity\AuthorizationApproval $authorizationApproval
 * @var bool $hasMoreApprovalsToGo Whether additional approvals are needed
 */
?>

<div class="card cardbox mx-3 mt-3" data-section="approvals"
    data-controller="activities-approve-and-assign-auth"
    data-activities-approve-and-assign-auth-url-value="<?= $this->Url->build(['plugin' => 'activities', 'controller' => 'AuthorizationApprovals', 'action' => 'AvailableApproversList']) ?>"
    data-activities-approve-and-assign-auth-approval-id-value="<?= $authorizationApproval->id ?>">
    <div class="card-body">
        <!-- Authorization Request Details -->
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading mb-3"><i class="bi bi-file-earmark-text me-2"></i>Authorization Request</h5>
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

        <!-- Approval Form -->
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'AuthorizationApprovals', 'action' => 'mobileApprove', $authorizationApproval->id, 'plugin' => 'Activities']
        ]); ?>

        <?= $this->Form->hidden('id', ['value' => $authorizationApproval->id, 'data-activities-approve-and-assign-auth-target' => 'id']) ?>

        <?php if ($hasMoreApprovalsToGo): ?>
            <!-- Next Approver Selection -->
            <div class="mb-4">
                <label for="next-approver" class="form-label">Forward To (Next Approver)</label>
                <?= $this->Form->select(
                    'next_approver_id',
                    [],
                    [
                        'empty' => '-- Select next approver --',
                        'class' => 'form-select form-select-lg',
                        'id' => 'next-approver',
                        'data-activities-approve-and-assign-auth-target' => 'approvers',
                        'data-action' => 'change->activities-approve-and-assign-auth#checkReadyToSubmit'
                    ]
                ) ?>
                <div class="form-text">
                    This authorization requires additional approval. Select who should approve next.
                </div>
            </div>
        <?php else: ?>
            <!-- Final Approval -->
            <?= $this->Form->hidden('next_approver_id', ['value' => '']) ?>
            <div class="alert alert-success mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Final Approval:</strong> Your approval will complete this authorization request.
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-grid gap-3 mt-4">
            <button type="submit"
                class="btn btn-success btn-lg"
                <?= $hasMoreApprovalsToGo ? 'data-activities-approve-and-assign-auth-target="submitBtn"' : '' ?>>
                <i class="bi bi-check-circle me-2"></i>
                <?= $hasMoreApprovalsToGo ? 'Approve & Forward' : 'Approve Authorization' ?>
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