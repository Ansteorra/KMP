<?php
/**
 * Approval triage Kanban board shell.
 *
 * @var \App\View\AppView $this
 * @var array<int,array<string,mixed>> $lanes
 * @var string $triageUrl
 * @var string $detailUrl
 */

$lanes = $lanes ?? [];
?>
<div
    class="approval-kanban"
    data-controller="approval-kanban"
    data-approval-kanban-triage-url-value="<?= h($triageUrl) ?>"
    data-approval-kanban-detail-url-value="<?= h($detailUrl) ?>"
    data-action="click->approval-kanban#handleBoardClick">
    <div class="approval-kanban-heading d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="h5 mb-1"><?= __('Private Triage Board') ?></h4>
            <p class="text-muted small mb-0">
                <?=
                __(
                    'Drag cards or use the Move control to organize your pending approvals. ' .
                    'Only you can see these triage states.',
                )
                ?>
            </p>
        </div>
        <div class="approval-kanban-legend small text-muted" aria-label="<?= h(__('Triage lane order')) ?>">
            <i class="bi bi-kanban me-1" aria-hidden="true"></i><?= __('New to Ready to Decide') ?>
        </div>
    </div>

    <div class="visually-hidden" role="status" aria-live="polite" data-approval-kanban-target="status"></div>

    <div class="approval-kanban-scroll" role="region" tabindex="0" aria-label="<?= h(__('Approval triage lanes')) ?>">
        <?php foreach ($lanes as $lane) : ?>
            <section
                class="approval-kanban-lane-shell"
                data-approval-kanban-target="laneShell"
                data-lane-state="<?= h($lane['state']) ?>">
                <turbo-frame id="<?= h($lane['frameId']) ?>" src="<?= h($lane['url']) ?>">
                    <div class="approval-kanban-lane approval-kanban-lane-loading" aria-busy="true">
                        <div class="d-flex align-items-center gap-2 p-3">
                            <span
                                class="spinner-border spinner-border-sm text-primary"
                                role="status"
                                aria-hidden="true"></span>
                            <span><?= h(__('Loading {0} approvals...', $lane['label'])) ?></span>
                        </div>
                    </div>
                </turbo-frame>
            </section>
        <?php endforeach; ?>
    </div>
</div>
