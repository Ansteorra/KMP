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
 * @var array<int, array<string, mixed>> $todoRequirementStatus
 * @var array<int, array<string, mixed>> $todoBlockedStatus
 * @var int $todoGatingTotal
 * @var int $todoGatingDone
 * @var int $gatingPercent
 * @var string $currentPageUrl
 */

$todoItems = $todoItems ?? [];
$todoEligibility = $todoEligibility ?? [];
$todoRequirementStatus = $todoRequirementStatus ?? [];
$todoBlockedStatus = $todoBlockedStatus ?? [];
$todoGatingTotal = (int)($todoGatingTotal ?? 0);
$todoGatingDone = (int)($todoGatingDone ?? 0);
$gatingPercent = (int)($gatingPercent ?? 0);
$currentPageUrl = $currentPageUrl ?? '';
$progressId = $progressId ?? 'bestowal-todo-progress-label';
?>
<div data-controller="awards-bestowal-todos" data-turbo="false">
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
                <?php
                $eligible = !empty($todoEligibility[$item->id]);
                $requirement = $todoRequirementStatus[(int)$item->id] ?? null;
                $blocker = $todoBlockedStatus[(int)$item->id] ?? null;
                $missingRequirement = $requirement !== null && empty($requirement['satisfied']);
                $blocked = $blocker !== null && !empty($blocker['blocked']);
                $canAssignRequirement = $eligible && $item->isOpen() && $missingRequirement && !$blocked;
                $canCompleteDirectly = $eligible && $item->isOpen() && !$missingRequirement && !$blocked;
                $requirementField = (string)($requirement['field'] ?? '');
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div class="me-auto">
                        <div class="fw-semibold">
                            <?php if ($item->isCompleted()) : ?>
                                <i class="bi bi-check-square-fill text-success me-1" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= __('Completed task:') ?></span>
                            <?php else : ?>
                                <i class="bi bi-hourglass-split text-secondary me-1" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= __('Open task:') ?></span>
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
                            <?php if ($requirement !== null) : ?>
                                <?php if (!empty($requirement['satisfied'])) : ?>
                                    <span class="badge bg-info text-dark">
                                        <?php if ($requirementField === 'court_slot') : ?>
                                            <i class="bi bi-list-check me-1" aria-hidden="true"></i>
                                            <?= __('Court assigned') ?>
                                        <?php else : ?>
                                            <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
                                            <?= __('Gathering assigned') ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else : ?>
                                    <span class="badge bg-danger">
                                        <?php if ($requirementField === 'court_slot') : ?>
                                            <i class="bi bi-list-check me-1" aria-hidden="true"></i>
                                            <?= __('Court assignment required') ?>
                                        <?php else : ?>
                                            <i class="bi bi-calendar-x me-1" aria-hidden="true"></i>
                                            <?= __('Gathering required') ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($blocked) : ?>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
                                    <?= h((string)($blocker['label'] ?? __('Waiting on prerequisite'))) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($blocked) : ?>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                <?= h((string)($blocker['message'] ?? __('Complete prerequisite checks first.'))) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canAssignRequirement && $requirementField === 'gathering_id') : ?>
                            <div class="border rounded-3 bg-light-subtle p-3 mt-3"
                                data-bestowal-gathering-requirement>
                                <?= $this->Form->create(null, [
                                    'url' => [
                                        'plugin' => null,
                                        'controller' => 'ActionItems',
                                        'action' => 'complete',
                                        $item->id,
                                    ],
                                ]) ?>
                                <?= $this->Form->hidden('id', ['value' => $item->id]) ?>
                                <?= $this->Form->hidden('current_page', ['value' => $currentPageUrl]) ?>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox"
                                        id="todo-<?= h((string)$item->id) ?>-include-past"
                                        name="include_past" value="1"
                                        data-action="change->awards-bestowal-todos#handleIncludePastChange">
                                    <label class="form-check-label"
                                        for="todo-<?= h((string)$item->id) ?>-include-past">
                                        <?= __('Include past gatherings') ?>
                                    </label>
                                </div>
                                <?=
                                $this->KMP->autoCompleteControl(
                                    $this->Form,
                                    'todo_' . (int)$item->id . '_bestowal_gathering',
                                    'bestowal_gathering_id',
                                    (string)($requirement['lookupUrl'] ?? '#'),
                                    __('Bestowal Gathering'),
                                    true,
                                    false,
                                    2,
                                    [
                                        'data-bestowal-gathering-control' => 'true',
                                        'data-base-url' => (string)($requirement['lookupUrl'] ?? '#'),
                                        'data-ac-show-on-focus-value' => 'true',
                                    ],
                                )
                                ?>
                                <div class="form-text mb-3">
                                    <?=
                                    __(
                                        'Select a gathering using the same options as the bestowal edit form. ' .
                                        'Use Include past gatherings to backdate scheduling. ' .
                                        'Completing this form assigns the gathering and closes this to-do.',
                                    )
                                    ?>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success">
                                    <?= __('Assign Gathering and Complete') ?>
                                </button>
                                <?= $this->Form->end() ?>
                            </div>
                        <?php elseif ($canAssignRequirement && $requirementField === 'court_slot') : ?>
                            <div class="border rounded-3 bg-light-subtle p-3 mt-3">
                                <?= $this->Form->create(null, [
                                    'url' => [
                                        'plugin' => null,
                                        'controller' => 'ActionItems',
                                        'action' => 'complete',
                                        $item->id,
                                    ],
                                ]) ?>
                                <?= $this->Form->hidden('id', ['value' => $item->id]) ?>
                                <?= $this->Form->hidden('current_page', ['value' => $currentPageUrl]) ?>
                                <?php
                                $courtSlotId = 'todo-' . (int)$item->id . '-court-slot';
                                $courtOptions = (array)($requirement['options'] ?? []);
                                ?>
                                <label class="form-label" for="<?= h($courtSlotId) ?>">
                                    <?= __('Court Assignment') ?>
                                </label>
                                <?= $this->Form->select(
                                    'gathering_scheduled_activity_id',
                                    $courtOptions,
                                    [
                                        'id' => $courtSlotId,
                                        'class' => 'form-select',
                                        'empty' => __('Choose a court assignment'),
                                        'required' => true,
                                        'value' => $requirement['value'] ?? null,
                                        'aria-describedby' => $courtSlotId . '-help',
                                    ],
                                ) ?>
                                <div class="form-text mb-3" id="<?= h($courtSlotId) ?>-help">
                                    <?=
                                    __(
                                        'Choose Roaming Court, or choose a scheduled court activity that can give ' .
                                        'this award. Completing this form assigns the court slot and closes this ' .
                                        'to-do.',
                                    )
                                    ?>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success">
                                    <?= __('Assign Court and Complete') ?>
                                </button>
                                <?= $this->Form->end() ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-shrink-0">
                        <?php if ($canCompleteDirectly) : ?>
                            <?= $this->Form->postLink(
                                __('Complete'),
                                ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'complete', $item->id],
                                [
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
                        <?php elseif ($blocked) : ?>
                            <span class="text-muted small">
                                <?= h((string)($blocker['label'] ?? __('Waiting on prerequisite'))) ?>
                            </span>
                        <?php else : ?>
                            <span class="text-muted small"><?= __('Not assigned to you') ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
