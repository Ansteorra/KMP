<?php

/**
 * Mobile Approvals View Template
 *
 * Mobile-optimized card-based interface for reviewing and responding
 * to pending workflow approvals. Online-only — requires network
 * to fetch approvals and submit responses.
 *
 * @var \App\View\AppView $this
 */
$mobileApprovalsDataUrl = $this->Url->build(['controller' => 'Approvals', 'action' => 'mobileApprovalsData']);
$recordApprovalUrl = $this->Url->build(['controller' => 'Approvals', 'action' => 'recordApproval']);
$triageUrl = $this->Url->build(['controller' => 'Approvals', 'action' => 'updateTriage']);
$eligibleApproversUrl = $this->Url->build(['controller' => 'Approvals', 'action' => 'eligibleApprovers']);
$approvalDetailUrl = $this->Url->build(['controller' => 'Approvals', 'action' => 'approvalDetail']);
?>

<div class="mobile-approvals-container mx-3 mt-3"
     data-controller="mobile-approvals"
     data-section="approvals"
     data-mobile-approvals-data-url-value="<?= h($mobileApprovalsDataUrl) ?>"
     data-mobile-approvals-record-url-value="<?= h($recordApprovalUrl) ?>"
     data-mobile-approvals-triage-url-value="<?= h($triageUrl) ?>"
     data-mobile-approvals-eligible-url-value="<?= h($eligibleApproversUrl) ?>"
     data-mobile-approvals-detail-url-value="<?= h($approvalDetailUrl) ?>">

    <!-- Header with refresh -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge rounded-pill" style="background: var(--section-approvals); color: var(--medieval-parchment);"
              data-mobile-approvals-target="countBadge" hidden>
            0 pending
        </span>
        <button class="btn btn-sm btn-outline-secondary"
                data-action="click->mobile-approvals#refresh"
                data-mobile-approvals-target="refreshBtn">
            <i class="bi bi-arrow-clockwise me-1"></i><?= __('Refresh') ?>
        </button>
    </div>

    <!-- Loading State -->
    <div data-mobile-approvals-target="loading" class="text-center py-5">
        <div class="spinner-border" style="color: var(--section-approvals);" role="status">
            <span class="visually-hidden"><?= __('Loading...') ?></span>
        </div>
        <p class="mt-3 text-muted"><?= __('Loading approvals...') ?></p>
    </div>

    <!-- Empty State -->
    <div data-mobile-approvals-target="empty" hidden>
        <div class="card cardbox" data-section="approvals">
            <div class="card-body text-center py-5">
                <i class="bi bi-check2-all d-block fs-1 mb-3" style="color: var(--section-approvals);"></i>
                <h3 class="h5 mb-2"><?= __('All Caught Up!') ?></h3>
                <p class="text-muted mb-0">
                    <?= __('You have no pending approvals to review.') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <div data-mobile-approvals-target="error" hidden>
        <div class="card cardbox border-danger" data-section="approvals">
            <div class="card-body text-center py-4">
                <i class="bi bi-exclamation-triangle d-block fs-1 mb-3 text-danger"></i>
                <h3 class="h5 mb-2"><?= __('Unable to Load') ?></h3>
                <p class="text-muted mb-3" data-mobile-approvals-target="errorMessage">
                    <?= __('Failed to load approvals.') ?>
                </p>
                <button class="btn btn-outline-danger" data-action="click->mobile-approvals#refresh">
                    <i class="bi bi-arrow-clockwise me-1"></i><?= __('Try Again') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Approvals List (populated by JS) -->
    <div data-mobile-approvals-target="list" class="approval-cards-list"></div>

    <!-- Toast for success/error feedback -->
    <div class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index: 1080;">
        <div class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true"
             data-mobile-approvals-target="toast" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body" data-mobile-approvals-target="toastBody"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
</div>

<style>
/* Mobile Approvals Styles */
.approval-cards-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-bottom: 2rem;
}

.approval-card {
    border-left: 4px solid var(--section-approvals);
    border-radius: 6px;
    overflow: hidden;
    transition: box-shadow 0.15s ease;
    background: var(--medieval-parchment, #f4efe4);
}

.approval-card-summary {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.875rem;
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.approval-card-summary:active {
    background: rgba(0, 0, 0, 0.03);
}

.approval-card-icon {
    flex-shrink: 0;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    background: color-mix(in srgb, var(--section-approvals) 15%, transparent);
    color: var(--section-approvals);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.approval-card-info {
    flex: 1;
    min-width: 0;
}

.approval-card-title {
    font-family: var(--font-display, 'Cinzel', serif);
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.3;
    margin-bottom: 0.25rem;
    color: var(--medieval-ink, #2c1810);
}

.approval-card-meta {
    font-size: 0.8rem;
    color: var(--medieval-stone, #6c757d);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.approval-card-chevron {
    flex-shrink: 0;
    color: var(--medieval-stone, #6c757d);
    transition: transform 0.2s ease;
    align-self: center;
}

.approval-card.expanded .approval-card-chevron {
    transform: rotate(90deg);
}

/* Progress indicator */
.approval-progress {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.approval-progress-bar {
    width: 3rem;
    height: 4px;
    background: #e0d8cc;
    border-radius: 2px;
    overflow: hidden;
}

.approval-progress-fill {
    height: 100%;
    background: var(--section-approvals);
    border-radius: 2px;
    transition: width 0.3s ease;
}

/* Expanded detail section */
.approval-card-detail {
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding: 0.875rem;
    background: rgba(255, 255, 255, 0.4);
}

.approval-detail-fields {
    margin-bottom: 0.75rem;
}

.approval-detail-field {
    display: flex;
    justify-content: space-between;
    padding: 0.375rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    font-size: 0.85rem;
}

.approval-detail-field:last-child {
    border-bottom: none;
}

.approval-detail-label {
    font-weight: 600;
    color: var(--medieval-ink, #2c1810);
    flex-shrink: 0;
    margin-right: 0.5rem;
}

.approval-detail-value {
    text-align: right;
    color: var(--medieval-stone-dark, #555);
    word-break: break-word;
}

/* Response timeline */
.approval-responses {
    margin-bottom: 0.75rem;
}

.approval-response-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.375rem 0;
    font-size: 0.8rem;
}

.approval-response-icon {
    flex-shrink: 0;
    margin-top: 0.125rem;
}

/* Response form */
.approval-response-form {
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding-top: 0.75rem;
}

.approval-decision-btns {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.approval-decision-btns .btn {
    flex: 1;
    padding: 0.625rem;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 6px;
}

.btn-approve {
    background: linear-gradient(180deg, #28a745, #1e7e34);
    color: #fff;
    border: none;
}
.btn-approve:hover, .btn-approve:focus { background: #1e7e34; color: #fff; }
.btn-approve.active { box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.4); }

.btn-reject {
    background: linear-gradient(180deg, #dc3545, #bd2130);
    color: #fff;
    border: none;
}
.btn-reject:hover, .btn-reject:focus { background: #bd2130; color: #fff; }
.btn-reject.active { box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.4); }

.approval-comment-box {
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 0.5rem;
    font-size: 0.85rem;
    resize: vertical;
    min-height: 3rem;
    font-family: var(--font-body, 'Crimson Pro', serif);
}

.approval-comment-box:focus {
    border-color: var(--section-approvals);
    outline: none;
    box-shadow: 0 0 0 2px rgba(139, 105, 20, 0.2);
}

.approval-next-approver {
    margin-top: 0.75rem;
}

.approval-next-approver select {
    width: 100%;
    padding: 0.5rem;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 0.85rem;
}

.approval-submit-btn {
    width: 100%;
    padding: 0.625rem;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 6px;
    margin-top: 0.75rem;
    background: linear-gradient(180deg, var(--section-approvals), color-mix(in srgb, var(--section-approvals) 70%, black));
    color: var(--medieval-parchment, #f4efe4);
    border: none;
}
.approval-submit-btn:hover { opacity: 0.9; color: #fff; }
.approval-submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Comment warning */
.approval-comment-warning {
    font-size: 0.78rem;
    color: var(--medieval-stone, #6c757d);
    margin-top: 0.25rem;
}

/* Fade out animation for completed cards */
.approval-card-done {
    animation: fadeSlideOut 0.4s ease forwards;
}

@keyframes fadeSlideOut {
    0% { opacity: 1; transform: translateX(0); max-height: 300px; margin-bottom: 0.75rem; }
    50% { opacity: 0; transform: translateX(30px); max-height: 300px; margin-bottom: 0.75rem; }
    100% { opacity: 0; transform: translateX(30px); max-height: 0; margin-bottom: 0; padding: 0; overflow: hidden; }
}

/* Submitting state */
.approval-card-submitting {
    pointer-events: none;
    opacity: 0.6;
}
</style>
