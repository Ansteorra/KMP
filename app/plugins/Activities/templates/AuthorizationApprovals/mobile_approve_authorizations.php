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
?>

<div class="card cardbox mx-3">
    <div class="card-body">
        <h3 class="card-title text-center display-6">
            <i class="bi bi-check-circle me-2"></i>Approve Authorizations
        </h3>

        <!-- Queue Owner Info -->
        <div class="alert alert-info mb-3">
            <strong>Approval Queue for:</strong> <?= h($queueFor) ?>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane"
                    type="button" role="tab">
                    Pending
                    <?php if (count($pending) > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= count($pending) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved-pane"
                    type="button" role="tab">
                    Approved
                    <?php if (count($approved) > 0): ?>
                        <span class="badge bg-success ms-1"><?= count($approved) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="denied-tab" data-bs-toggle="tab" data-bs-target="#denied-pane"
                    type="button" role="tab">
                    Denied
                    <?php if (count($denied) > 0): ?>
                        <span class="badge bg-danger ms-1"><?= count($denied) ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content">
            <!-- Pending Approvals -->
            <div class="tab-pane fade show active" id="pending-pane" role="tabpanel" tabindex="0">
                <?php if (empty($pending)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>No pending approvals!
                    </div>
                <?php else: ?>
                    <?php foreach ($pending as $request): ?>
                        <div class="card mb-3 border-warning">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?= h($request->authorization->member->sca_name) ?>
                                </h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <?= h($request->authorization->activity->name) ?>
                                </h6>

                                <dl class="row mb-2">
                                    <dt class="col-5">Requested:</dt>
                                    <dd class="col-7"><?= $request->requested_on->format('n/j/Y g:i A') ?></dd>

                                    <dt class="col-5">Member #:</dt>
                                    <dd class="col-7"><?= h($request->authorization->member->membership_number ?: 'N/A') ?></dd>

                                    <dt class="col-5">Member Exp:</dt>
                                    <dd class="col-7">
                                        <?= $request->authorization->member->membership_expires_on ? $request->authorization->member->membership_expires_on->format('n/j/Y') : 'N/A' ?>
                                    </dd>

                                    <dt class="col-5">Bg Check Exp:</dt>
                                    <dd class="col-7">
                                        <?= $request->authorization->member->background_check_expires_on ? $request->authorization->member->background_check_expires_on->format('n/j/Y') : 'N/A' ?>
                                    </dd>
                                </dl>

                                <div class="d-grid gap-2">
                                    <?= $this->Html->link(
                                        '<i class="bi bi-check-circle me-2"></i>Approve',
                                        ['controller' => 'AuthorizationApprovals', 'action' => 'mobileApprove', $request->id, 'plugin' => 'Activities'],
                                        ['class' => 'btn btn-success', 'escape' => false]
                                    ) ?>
                                    <?= $this->Html->link(
                                        '<i class="bi bi-x-circle me-2"></i>Deny',
                                        ['controller' => 'AuthorizationApprovals', 'action' => 'mobileDeny', $request->id, 'plugin' => 'Activities'],
                                        ['class' => 'btn btn-danger', 'escape' => false]
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Approved Authorizations -->
            <div class="tab-pane fade" id="approved-pane" role="tabpanel" tabindex="0">
                <?php if (empty($approved)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No approved authorizations yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($approved as $request): ?>
                        <div class="card mb-3 border-success">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?= h($request->authorization->member->sca_name) ?>
                                </h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <?= h($request->authorization->activity->name) ?>
                                </h6>

                                <dl class="row mb-0">
                                    <dt class="col-5">Approved:</dt>
                                    <dd class="col-7"><?= $request->responded_on->format('n/j/Y g:i A') ?></dd>

                                    <dt class="col-5">Requested:</dt>
                                    <dd class="col-7"><?= $request->requested_on->format('n/j/Y') ?></dd>
                                </dl>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Denied Authorizations -->
            <div class="tab-pane fade" id="denied-pane" role="tabpanel" tabindex="0">
                <?php if (empty($denied)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No denied authorizations.
                    </div>
                <?php else: ?>
                    <?php foreach ($denied as $request): ?>
                        <div class="card mb-3 border-danger">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?= h($request->authorization->member->sca_name) ?>
                                </h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <?= h($request->authorization->activity->name) ?>
                                </h6>

                                <dl class="row mb-0">
                                    <dt class="col-5">Denied:</dt>
                                    <dd class="col-7"><?= $request->responded_on->format('n/j/Y g:i A') ?></dd>

                                    <dt class="col-5">Requested:</dt>
                                    <dd class="col-7"><?= $request->requested_on->format('n/j/Y') ?></dd>

                                    <?php if ($request->responded_comment): ?>
                                        <dt class="col-5">Reason:</dt>
                                        <dd class="col-7"><?= h($request->responded_comment) ?></dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>