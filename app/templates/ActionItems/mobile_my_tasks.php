<?php

/**
 * Mobile My To-Dos View Template
 *
 * Mobile-optimized card interface for open action items. Online-only because
 * completion mutates server state.
 *
 * @var \App\View\AppView $this
 * @var int $mobileQueuePerPage
 */
$mobileMyTasksDataUrl = $this->Url->build(['controller' => 'ActionItems', 'action' => 'mobileMyTasksData']);
$completeBaseUrl = $this->Url->build(['controller' => 'ActionItems', 'action' => 'complete']);
?>

<div class="mobile-action-items-container mx-3 mt-3"
     data-controller="mobile-action-items"
     data-section="todos"
     data-mobile-action-items-data-url-value="<?= h($mobileMyTasksDataUrl) ?>"
     data-mobile-action-items-per-page-value="<?= h((string)$mobileQueuePerPage) ?>"
     data-mobile-action-items-complete-url-value="<?= h($completeBaseUrl) ?>">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge rounded-pill text-bg-warning"
              data-mobile-action-items-target="countBadge" hidden>
            <?= __('0 open') ?>
        </span>
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                data-action="click->mobile-action-items#refresh"
                data-mobile-action-items-target="refreshBtn">
            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><?= __('Refresh') ?>
        </button>
    </div>

    <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
         data-mobile-action-items-target="status"></div>

    <div data-mobile-action-items-target="loading" class="text-center py-5" role="status">
        <div class="spinner-border text-warning" role="status">
            <span class="visually-hidden"><?= __('Loading...') ?></span>
        </div>
        <p class="mt-3 text-muted"><?= __('Loading to-dos...') ?></p>
    </div>

    <div data-mobile-action-items-target="empty" hidden>
        <div class="card cardbox" data-section="todos">
            <div class="card-body text-center py-5">
                <i class="bi bi-check2-all d-block fs-1 mb-3 text-success" aria-hidden="true"></i>
                <h2 class="h5 mb-2"><?= __('All Caught Up!') ?></h2>
                <p class="text-muted mb-0">
                    <?= __('You have no open to-dos right now.') ?>
                </p>
            </div>
        </div>
    </div>

    <div data-mobile-action-items-target="error" hidden>
        <div class="card cardbox border-danger" data-section="todos">
            <div class="card-body text-center py-4">
                <i class="bi bi-exclamation-triangle d-block fs-1 mb-3 text-danger" aria-hidden="true"></i>
                <h2 class="h5 mb-2"><?= __('Unable to Load') ?></h2>
                <p class="text-muted mb-3" data-mobile-action-items-target="errorMessage">
                    <?= __('Failed to load to-dos.') ?>
                </p>
                <button type="button" class="btn btn-outline-danger" data-action="click->mobile-action-items#refresh">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><?= __('Try Again') ?>
                </button>
            </div>
        </div>
    </div>

    <div data-mobile-action-items-target="list" class="todo-cards-list"></div>

    <div class="text-center my-3"
         data-mobile-action-items-target="loadMore"
         hidden>
        <button type="button"
                class="btn btn-outline-warning"
                data-mobile-action-items-target="loadMoreBtn"
                data-action="click->mobile-action-items#loadMore"
                aria-busy="false">
            <?= __('Load more') ?>
        </button>
    </div>

    <div class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index: 1080;">
        <div class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true"
             data-mobile-action-items-target="toast" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body" data-mobile-action-items-target="toastBody"></div>
                <button type="button"
                        class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast"
                        aria-label="<?= __('Close') ?>"></button>
            </div>
        </div>
    </div>
</div>

<style>
.todo-cards-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-bottom: 2rem;
}

.todo-owner-card {
    border-left: 4px solid var(--section-todos, #946200);
}

.todo-owner-summary {
    align-items: flex-start;
    background: transparent;
    border: 0;
    color: inherit;
    display: flex;
    gap: 0.75rem;
    padding: 0.875rem;
    text-align: left;
    width: 100%;
}

.todo-owner-summary:focus-visible {
    outline: 3px solid color-mix(in srgb, var(--section-todos, #946200) 45%, white);
    outline-offset: 2px;
}

.todo-owner-icon {
    align-items: center;
    background: color-mix(in srgb, var(--section-todos, #946200) 16%, transparent);
    border-radius: 50%;
    color: var(--section-todos, #946200);
    display: flex;
    flex-shrink: 0;
    font-size: 1rem;
    height: 2.25rem;
    justify-content: center;
    width: 2.25rem;
}

.todo-owner-info {
    flex: 1;
    min-width: 0;
}

.todo-owner-title {
    color: var(--medieval-ink, #2c1810);
    font-family: var(--font-display, 'Cinzel', serif);
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.3;
}

.todo-owner-meta,
.todo-item-description {
    color: var(--medieval-stone-dark, #4b5563);
    font-size: 0.82rem;
}

.todo-owner-chevron {
    align-self: center;
    color: var(--medieval-stone, #6b7280);
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.todo-owner-card.expanded .todo-owner-chevron {
    transform: rotate(90deg);
}

.todo-owner-detail {
    background: rgba(255, 255, 255, 0.45);
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    padding: 0.875rem;
}

.todo-item {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 0.5rem;
    padding: 0.75rem;
}

.todo-item + .todo-item {
    margin-top: 0.75rem;
}

.todo-item-completing {
    opacity: 0.65;
    pointer-events: none;
}
</style>
