<?php

/**
 * Approval triage Kanban lane.
 *
 * @var \App\View\AppView $this
 * @var array<string,mixed> $lane
 * @var iterable<\App\Model\Entity\WorkflowApproval> $data
 * @var array<string,array<string,mixed>> $cardActions
 * @var int $page
 * @var int $pageCount
 * @var int $totalCount
 * @var bool $hasNextPage
 * @var int $nextPage
 */

use App\Model\Entity\WorkflowApprovalTriageState;

$data = $data ?? [];
$cardActions = $cardActions ?? [];
$states = WorkflowApprovalTriageState::labels();
$laneState = (string)$lane['state'];
$laneLabel = (string)$lane['label'];
$laneId = (string)$lane['frameId'];
$cardsId = $laneId . '-cards';
$headingId = $laneId . '-heading';
$query = $this->getRequest()->getQueryParams();
foreach (array_keys($query) as $key) {
    if (str_starts_with(html_entity_decode(rawurldecode((string)$key), ENT_QUOTES | ENT_HTML5), 'amp;')) {
        unset($query[$key]);
    }
}
$query['triage_state'] = $laneState;
$query['page'] = $nextPage ?? 2;
$nextUrl = $this->Url->build([
    'controller' => 'Approvals',
    'action' => 'approvalsKanbanLaneData',
    '?' => $query,
], ['escape' => false]);
$visibleCount = count($data);
?>
<turbo-frame id="<?= h($laneId) ?>">
    <section class="approval-kanban-lane" data-approval-kanban-target="lane" data-lane-state="<?= h($laneState) ?>"
        data-total-count="<?= h((string)$totalCount) ?>"
        data-action="dragover->approval-kanban#dragOver dragleave->approval-kanban#dragLeave drop->approval-kanban#drop"
        aria-labelledby="<?= h($headingId) ?>">
        <header class="approval-kanban-lane-header">
            <div>
                <h5 class="approval-kanban-lane-title" id="<?= h($headingId) ?>">
                    <?= h($laneLabel) ?>
                </h5>
                <p class="approval-kanban-lane-subtitle mb-0">
                    <?= h(__n('{0} approval', '{0} approvals', $totalCount, $totalCount)) ?>
                </p>
            </div>
            <span class="badge rounded-pill text-bg-light border">
                <?= h((string)$totalCount) ?>
            </span>
        </header>

        <div class="approval-kanban-cards" id="<?= h($cardsId) ?>" role="list"
            aria-label="<?= h(__('{0} approvals', $laneLabel)) ?>" data-approval-kanban-target="cardList"
            data-lane-state="<?= h($laneState) ?>">
            <?php if ($visibleCount === 0 && !$hasNextPage) : ?>
            <div class="approval-kanban-empty" role="note">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <span><?= h(__('No approvals here.')) ?></span>
            </div>
            <?php endif; ?>

            <?php foreach ($data as $approval) : ?>
            <?php
                $approvalId = (int)$approval->id;
                $detailId = 'approval-kanban-card-detail-' . $approvalId;
                $cardTitle = (string)($approval->request ?? __('Approval {0}', $approvalId));
                ?>
            <article class="approval-kanban-card" role="listitem" draggable="true" tabindex="-1"
                data-approval-kanban-target="card" data-approval-id="<?= h((string)$approvalId) ?>"
                data-current-state="<?= h((string)$approval->triage_state) ?>"
                data-triage-note="<?= h((string)$approval->triage_note) ?>" data-card-title="<?= h($cardTitle) ?>"
                data-action="dragstart->approval-kanban#dragStart dragend->approval-kanban#dragEnd">
                <div class="approval-kanban-card-topline">
                    <span class="approval-kanban-card-type">
                        <i class="bi <?= h((string)($approval->icon ?? 'bi-question-circle')) ?>"
                            aria-hidden="true"></i>
                        <?= h((string)$approval->workflow_name) ?>
                    </span>
                    <span class="approval-kanban-card-status"><?= h((string)$approval->status_label) ?></span>
                </div>

                <h6 class="approval-kanban-card-title"><?= h($cardTitle) ?></h6>

                <dl class="approval-kanban-card-meta">
                    <div>
                        <dt><?= h(__('Requester')) ?></dt>
                        <dd><?= h((string)$approval->requester) ?></dd>
                    </div>
                    <div>
                        <dt><?= h(__('Last action')) ?></dt>
                        <dd>
                            <?php if (!empty($approval->modified_iso)) : ?>
                            <time datetime="<?= h((string)$approval->modified_iso) ?>">
                                <?= h((string)$approval->modified_label) ?>
                            </time>
                            <?php else : ?>
                            <?= h((string)$approval->modified_label) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>

                <?php if (!empty($approval->triage_note)) : ?>
                <p class="approval-kanban-note">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <?= h((string)$approval->triage_note) ?>
                </p>
                <?php endif; ?>

                <div class="approval-kanban-move">
                    <label class="form-label small" for="approval-kanban-move-<?= h((string)$approvalId) ?>">
                        <?= h(__('Move')) ?>
                    </label>
                    <select class="form-select form-select-sm" id="approval-kanban-move-<?= h((string)$approvalId) ?>"
                        data-action="change->approval-kanban#moveFromSelect"
                        aria-label="<?= h(__('Move {0} to another triage lane', $cardTitle)) ?>">
                        <?php foreach ($states as $stateValue => $stateLabel) : ?>
                        <option value="<?= h($stateValue) ?>"
                            <?= $stateValue === $approval->triage_state ? 'selected' : '' ?>>
                            <?= h($stateLabel) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="approval-kanban-card-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-action="click->approval-kanban#toggleDetails"
                        data-approval-id="<?= h((string)$approvalId) ?>" aria-expanded="false"
                        aria-controls="<?= h($detailId) ?>">
                        <i class="bi bi-chevron-down me-1" aria-hidden="true"></i><?= h(__('Details')) ?>
                    </button>
                    <?php if (!empty($approval->source_url)) : ?>
                    <a href="<?= h((string)$approval->source_url) ?>" class="btn btn-sm btn-outline-primary"
                        data-turbo-frame="_top">
                        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                        <?= h(__('View Source')) ?>
                    </a>
                    <?php endif; ?>
                    <?= $this->element('dataverse_table_row_actions', [
                            'actions' => $cardActions,
                            'row' => $approval,
                            'user' => $this->request->getAttribute('identity'),
                        ]) ?>
                </div>

                <div class="approval-kanban-card-detail" id="<?= h($detailId) ?>" data-approval-kanban-target="detail"
                    hidden></div>
            </article>
            <?php endforeach; ?>
        </div>

        <footer class="approval-kanban-lane-footer">
            <?php if ($hasNextPage) : ?>
            <button type="button" class="btn btn-sm btn-outline-secondary w-100" data-approval-kanban-target="loadMore"
                data-approval-kanban-load-more="true" data-next-url="<?= h($nextUrl) ?>"
                aria-controls="<?= h($cardsId) ?>">
                <span><?= h(__('Load more')) ?></span>
                <span class="text-muted">
                    <?= h(__('Page {0} of {1}', $page, $pageCount)) ?>
                </span>
            </button>
            <?php else : ?>
            <span class="approval-kanban-lane-complete">All approvals loaded
            </span>
            <?php endif; ?>
        </footer>
    </section>
</turbo-frame>