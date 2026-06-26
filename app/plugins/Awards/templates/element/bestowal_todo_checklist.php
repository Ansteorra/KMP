<?php

/**
 * Shared bestowal to-do checklist.
 *
 * Renders the parallel preparation checks for a single bestowal with an optional
 * required-progress bar and eligibility-gated Complete / Reopen actions. Reused by
 * the bestowal view "To-Dos" tab and the quick To-Dos modal.
 *
 * Wrapped in data-turbo="false" so the Complete / Reopen posts perform a full
 * navigation (server redirect back to current_page) even when rendered inside a
 * Turbo Frame modal, rather than being captured by the frame.
 *
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\ActionItem> $todoItems
 * @var array<int, bool> $todoEligibility
 * @var int $todoGatingTotal
 * @var int $todoGatingDone
 * @var int $gatingPercent
 * @var string $currentPageUrl
 */

$todoItems = $todoItems ?? [];
$todoEligibility = $todoEligibility ?? [];
$todoGatingTotal = (int)($todoGatingTotal ?? 0);
$todoGatingDone = (int)($todoGatingDone ?? 0);
$gatingPercent = (int)($gatingPercent ?? 0);
$currentPageUrl = $currentPageUrl ?? '';
$progressId = $progressId ?? 'bestowal-todo-progress-label';
?>
<div data-turbo="false">
    <?php if (empty($todoItems)) : ?>
        <p class="text-muted"><?= __('No to-do checks are configured for this award yet.') ?></p>
    <?php else : ?>
        <?php if ($todoGatingTotal > 0) : ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <span id="<?= h($progressId) ?>"><?= __('Required checks complete') ?></span>
                    <span aria-hidden="true"><?= h($todoGatingDone . ' / ' . $todoGatingTotal) ?></span>
                </div>
                <div class="progress" role="progressbar" aria-labelledby="<?= h($progressId) ?>"
                    aria-valuenow="<?= h((string)$todoGatingDone) ?>" aria-valuemin="0"
                    aria-valuemax="<?= h((string)$todoGatingTotal) ?>">
                    <div class="progress-bar" style="width: <?= h((string)$gatingPercent) ?>%;"></div>
                </div>
            </div>
        <?php endif; ?>
        <ul class="list-group mb-3" role="list">
            <?php foreach ($todoItems as $item) : ?>
                <?php $eligible = !empty($todoEligibility[$item->id]); ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div class="me-auto">
                        <div class="fw-semibold">
                            <?php if ($item->isCompleted()) : ?>
                                <i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>
                            <?php else : ?>
                                <i class="bi bi-circle me-1" aria-hidden="true"></i>
                            <?php endif; ?>
                            <?= h($item->title) ?>
                        </div>
                        <?php if (!empty($item->description)) : ?>
                            <div class="small text-muted"><?= h($item->description) ?></div>
                        <?php endif; ?>
                        <div class="mt-1">
                            <?php if ($item->isCompleted()) : ?>
                                <span class="badge bg-success"><?= __('Completed') ?></span>
                            <?php else : ?>
                                <span class="badge bg-light text-dark border"><?= __('Open') ?></span>
                            <?php endif; ?>
                            <?php if ($item->is_gating) : ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-flag-fill me-1" aria-hidden="true"></i><?= __('Required') ?>
                                </span>
                            <?php else : ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-flag me-1" aria-hidden="true"></i><?= __('Optional') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <?php if ($eligible && $item->isOpen()) : ?>
                            <?= $this->Form->postLink(
                                '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>' . __('Complete'),
                                ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'complete', $item->id],
                                [
                                    'escapeTitle' => false,
                                    'class' => 'btn btn-sm btn-success',
                                    'data' => ['current_page' => $currentPageUrl],
                                    'confirm' => __('Mark "{0}" complete?', $item->title),
                                    'aria-label' => __('Mark complete: {0}', $item->title),
                                ],
                            ) ?>
                        <?php elseif ($eligible && $item->isCompleted()) : ?>
                            <?= $this->Form->postLink(
                                '<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . __('Reopen'),
                                ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'reopen', $item->id],
                                [
                                    'escapeTitle' => false,
                                    'class' => 'btn btn-sm btn-outline-secondary',
                                    'data' => ['current_page' => $currentPageUrl],
                                    'confirm' => __('Reopen "{0}"?', $item->title),
                                    'aria-label' => __('Reopen: {0}', $item->title),
                                ],
                            ) ?>
                        <?php else : ?>
                            <span class="text-muted small"><?= __('Not assigned to you') ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
