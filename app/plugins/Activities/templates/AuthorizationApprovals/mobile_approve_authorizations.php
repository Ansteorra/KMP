<?php

/**
 * Mobile Authorization Approval Template
 * 
 * Mobile-optimized interface for approving pending activity authorization requests.
 * Uses the mobile_app layout for consistent PWA experience.
 * All PWA infrastructure, menu, and styling is provided by the mobile_app layout.
 * This view only contains the approval queue specific content.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 * @var string $queueFor The name of the person whose queue this is
 * @var bool $isMyQueue Whether this is the current user's queue
 */

// Separate approvals by status
$pending = [];
$approved = [];
$denied = [];

foreach ($authorizationApprovals as $authorizationApproval) {
    if ($authorizationApproval->responded_on == null) {
        $pending[] = $authorizationApproval;
    } elseif ($authorizationApproval->approved == 1) {
        $approved[] = $authorizationApproval;
    } else {
        $denied[] = $authorizationApproval;
    }
}

// Sort pending by requested_on (oldest first)
usort($pending, function ($a, $b) {
    return $a->requested_on <=> $b->requested_on;
});

// Sort approved by responded_on (most recent first)
usort($approved, function ($a, $b) {
    return $b->responded_on <=> $a->responded_on;
});

// Sort denied by responded_on (most recent first)
usort($denied, function ($a, $b) {
    return $b->responded_on <=> $a->responded_on;
});

$pendingCount = count($pending);
$approvedCount = count($approved);
$deniedCount = count($denied);
?>

<div class="auth-approvals-container mx-3 mt-3" data-section="approvals">
    <!-- Queue Owner Info -->
    <div class="queue-info-card mb-3">
        <i class="bi bi-person-badge me-2"></i>
        <strong>Queue:</strong> <?= h($queueFor) ?>
    </div>

    <!-- Tabs Navigation - RSVPs style -->
    <ul class="nav approval-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane"
                type="button" role="tab">
                <i class="bi bi-hourglass-split me-1"></i>Pending
                <?php if ($pendingCount > 0): ?>
                    <span class="badge ms-1"><?= $pendingCount ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved-pane"
                type="button" role="tab">
                <i class="bi bi-check-circle me-1"></i>OK
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="denied-tab" data-bs-toggle="tab" data-bs-target="#denied-pane"
                type="button" role="tab">
                <i class="bi bi-x-circle me-1"></i>Denied
            </button>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content">
        <!-- Pending Approvals -->
        <div class="tab-pane fade show active" id="pending-pane" role="tabpanel" tabindex="0">
            <?php if (empty($pending)): ?>
                <div class="empty-state-card">
                    <i class="bi bi-check-circle-fill d-block fs-1 mb-2" style="color: var(--mobile-success);"></i>
                    <h3 class="h6 mb-1">All Clear!</h3>
                    <p class="text-muted mb-0 small">No pending approvals</p>
                </div>
            <?php else: ?>
                <div class="approval-list">
                <?php foreach ($pending as $request): ?>
                    <div class="approval-card approval-pending">
                        <div class="approval-header">
                            <div>
                                <h4 class="approval-name">
                                    <?= h($request->authorization->member->sca_name) ?>
                                </h4>
                                <span class="approval-activity">
                                    <i class="bi bi-shield-check me-1"></i><?= h($request->authorization->activity->name) ?>
                                </span>
                            </div>
                        </div>

                        <div class="approval-details">
                            <p class="mb-1">
                                <i class="bi bi-clock me-2"></i>
                                <?= $this->Timezone->format($request->requested_on) ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-card-text me-2"></i>
                                #<?= h($request->authorization->member->membership_number ?: 'N/A') ?>
                                <?php if ($request->authorization->member->membership_expires_on): ?>
                                    (exp <?= $this->Timezone->format($request->authorization->member->membership_expires_on, null, 'n/j/y') ?>)
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="approval-actions">
                            <?= $this->Html->link(
                                '<i class="bi bi-check-circle"></i> Approve',
                                ['controller' => 'AuthorizationApprovals', 'action' => 'mobileApprove', $request->id, 'plugin' => 'Activities'],
                                ['class' => 'btn btn-success btn-sm flex-grow-1', 'escape' => false]
                            ) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-x-circle"></i> Deny',
                                ['controller' => 'AuthorizationApprovals', 'action' => 'mobileDeny', $request->id, 'plugin' => 'Activities'],
                                ['class' => 'btn btn-danger btn-sm flex-grow-1', 'escape' => false]
                            ) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Approved Authorizations -->
        <div class="tab-pane fade" id="approved-pane" role="tabpanel" tabindex="0">
            <?php if (empty($approved)): ?>
                <div class="empty-state-card">
                    <i class="bi bi-check-circle d-block fs-1 mb-2 text-muted"></i>
                    <h3 class="h6 mb-1">No Approved Yet</h3>
                    <p class="text-muted mb-0 small">Approved authorizations will appear here</p>
                </div>
            <?php else: ?>
                <div class="approval-list">
                <?php foreach ($approved as $request): ?>
                    <div class="approval-card approval-approved">
                        <div class="approval-header">
                            <div>
                                <h4 class="approval-name">
                                    <?= h($request->authorization->member->sca_name) ?>
                                </h4>
                                <span class="approval-activity">
                                    <i class="bi bi-shield-check me-1"></i><?= h($request->authorization->activity->name) ?>
                                </span>
                            </div>
                            <span class="badge bg-success"><i class="bi bi-check"></i></span>
                        </div>

                        <div class="approval-details">
                            <p class="mb-0">
                                <i class="bi bi-calendar-check me-2"></i>
                                Approved <?= $this->Timezone->format($request->responded_on, null, 'n/j/y') ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Denied Authorizations -->
        <div class="tab-pane fade" id="denied-pane" role="tabpanel" tabindex="0">
            <?php if (empty($denied)): ?>
                <div class="empty-state-card">
                    <i class="bi bi-x-circle d-block fs-1 mb-2 text-muted"></i>
                    <h3 class="h6 mb-1">No Denials</h3>
                    <p class="text-muted mb-0 small">Denied authorizations will appear here</p>
                </div>
            <?php else: ?>
                <div class="approval-list">
                <?php foreach ($denied as $request): ?>
                    <div class="approval-card approval-denied">
                        <div class="approval-header">
                            <div>
                                <h4 class="approval-name">
                                    <?= h($request->authorization->member->sca_name) ?>
                                </h4>
                                <span class="approval-activity">
                                    <i class="bi bi-shield-x me-1"></i><?= h($request->authorization->activity->name) ?>
                                </span>
                            </div>
                            <span class="badge bg-danger"><i class="bi bi-x"></i></span>
                        </div>

                        <div class="approval-details">
                            <p class="mb-1">
                                <i class="bi bi-calendar-x me-2"></i>
                                Denied <?= $this->Timezone->format($request->responded_on, null, 'n/j/y') ?>
                            </p>
                            <?php if ($request->responded_comment): ?>
                            <p class="mb-0 fst-italic">
                                "<?= h($request->responded_comment) ?>"
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Auth Approvals Container */
.auth-approvals-container {
    padding-bottom: 80px;
}

/* Queue Info Card */
.queue-info-card {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    padding: 10px 14px;
    font-size: 15px;
    color: var(--mobile-text-secondary);
    border-left: 5px solid var(--section-approvals, #8b6914);
    box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
}

/* Tabs - Pill Style (matches RSVPs) */
.approval-tabs {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    padding: 4px;
    box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
    border: 1px solid rgba(139, 105, 20, 0.1);
    display: flex;
    gap: 2px;
}

.approval-tabs .nav-item {
    flex: 1;
}

.approval-tabs .nav-link {
    border: none;
    border-radius: 4px;
    padding: 8px 6px;
    font-weight: 500;
    font-size: 13px;
    color: var(--mobile-text-secondary, #4a3728);
    background: transparent;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-family: var(--font-display, 'Cinzel', serif);
}

.approval-tabs .nav-link i {
    font-size: 14px;
}

.approval-tabs .nav-link:hover {
    color: var(--section-approvals, #8b6914);
    background: rgba(139, 105, 20, 0.08);
}

.approval-tabs .nav-link.active {
    background: linear-gradient(180deg, var(--section-approvals, #8b6914), color-mix(in srgb, var(--section-approvals, #8b6914) 70%, black));
    color: var(--medieval-parchment, #f4efe4);
}

.approval-tabs .nav-link.active .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: white;
}

.approval-tabs .nav-link .badge {
    font-size: 10px;
    padding: 2px 6px;
}

/* Approval Cards - Match RSVPs */
.approval-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.approval-card {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
    border: 1px solid rgba(139, 105, 20, 0.1);
    border-left: 5px solid var(--section-approvals, #8b6914);
    padding: 12px 14px;
}

.approval-pending {
    border-left-color: var(--section-approvals, #8b6914);
}

.approval-approved {
    border-left-color: var(--mobile-success, #1e6f50);
    opacity: 0.9;
}

.approval-denied {
    border-left-color: var(--mobile-danger, #8b2252);
    opacity: 0.85;
}

.approval-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.approval-name {
    font-size: 17px;
    font-weight: 600;
    margin: 0 0 2px 0;
    color: var(--mobile-text-primary, #2c1810);
    font-family: var(--font-display, 'Cinzel', serif);
}

.approval-activity {
    font-size: 14px;
    color: var(--section-approvals, #8b6914);
}

.approval-details {
    font-size: 14px;
    color: var(--mobile-text-secondary, #4a3728);
    margin-bottom: 10px;
}

.approval-details i {
    color: var(--medieval-bronze, #8b6914);
    width: 18px;
}

.approval-details p:last-child {
    margin-bottom: 0;
}

.approval-actions {
    display: flex;
    gap: 8px;
}

.approval-actions .btn {
    padding: 8px 12px;
    font-size: 14px;
}

/* Empty State Card */
.empty-state-card {
    background: var(--mobile-card-bg, #fffef9);
    border-radius: 4px;
    padding: 24px 16px;
    text-align: center;
    border: 1px solid rgba(139, 105, 20, 0.1);
    box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
}

.empty-state-card h3 {
    font-family: var(--font-display, 'Cinzel', serif);
    color: var(--mobile-text-primary, #2c1810);
}
</style>