<?php
declare(strict_types=1);

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
 * @var array<int, string> $terminalStateByTodoId Current owner outcome for completed terminal tasks.
 * @var array<int, bool> $todoEligibility
 * @var array<int, array<string, mixed>> $todoRequirementStatus
 * @var array<int, array<string, mixed>> $todoBlockedStatus
 * @var int $todoGatingTotal
 * @var int $todoGatingDone
 * @var int $gatingPercent
 * @var string $currentPageUrl
 */
use App\Model\Entity\ActionItem;
use Awards\Model\Entity\BestowalTodoTemplateItem;

$todoItems = $todoItems ?? [];
$terminalStateByTodoId = $terminalStateByTodoId ?? [];
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
    <?php if (isset($bestowal) && !$bestowal->allowsActionItemMutations()) : ?>
        <p class="alert alert-info"><?= h($bestowal->actionItemReadOnlyReason()) ?></p>
    <?php endif; ?>
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
                $hasOutcomeChoices = $item->is_terminal && BestowalTodoTemplateItem::terminalOutcomesFor($item) !== [];
                $requirementField = (string)($requirement['field'] ?? '');
                ?>
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div class="me-auto">
                        <div class="fw-semibold">
                            <?php if ($item->isCompleted()) : ?>
                                <kmp-icon name="checkbox" class="text-success me-1" aria-hidden="true"></kmp-icon>
                                <span class="visually-hidden"><?= __('Completed task:') ?></span>
                            <?php elseif ($item->status === ActionItem::STATUS_CANCELLED) : ?>
                                <kmp-icon name="square-minus" class="text-secondary me-1" aria-hidden="true"></kmp-icon>
                                <span class="visually-hidden"><?= __('Closed task:') ?></span>
                            <?php else : ?>
                                <kmp-icon name="hourglass" class="text-secondary me-1" aria-hidden="true"></kmp-icon>
                                <span class="visually-hidden"><?= __('Open task:') ?></span>
                            <?php endif; ?>
                            <?= h($item->title) ?>
                            <?php if ($item->is_terminal) :
                                ?><span class="badge bg-primary"><?= h(__(
                                    isset($terminalStateByTodoId[(int)$item->id])
                                        ? 'Terminal state: {0}' : 'Terminal — marks {0}',
                                    ucfirst($terminalStateByTodoId[(int)$item->id]
                                        ?? BestowalTodoTemplateItem::terminalStatusFor($item)),
                                )) ?></span><?php
                            endif; ?>
                        </div>
                        <?php if (!empty($item->description)) : ?>
                            <div class="small text-muted"><?= h($item->description) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item->history_notes)) : ?>
                            <div class="mt-3" data-bestowal-todo-notes>
                                <p class="fw-semibold mb-1"><?= __('To-Do notes') ?></p>
                                <ul class="list-unstyled mb-0" role="list"
                                    aria-label="<?= h(__('Notes for {0}', $item->title)) ?>">
                                    <?php foreach ($item->history_notes as $entry) : ?>
                                        <li class="border-start ps-3 mb-2">
                                            <div class="small text-muted">
                                                <?= h($entry->transition_label) ?> · <?= h($entry->author_name) ?>
                                                <?php if ($entry->created !== null) : ?>
                                                    · <time datetime="<?= h($entry->created->format(DATE_ATOM)) ?>">
                                                        <?= $this->Timezone->format($entry->created) ?>
                                                    </time>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-break"><?= nl2br(h($entry->note)) ?></div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <div class="mt-1">
                            <?php if ($item->isCompleted()) : ?>
                                <span class="badge bg-success"><?= __('Completed') ?></span>
                            <?php elseif ($item->status === ActionItem::STATUS_CANCELLED) : ?>
                                <span class="badge bg-secondary"><?= __('Closed — not applicable') ?></span>
                            <?php else : ?>
                                <span class="badge bg-light text-dark border"><?= __('Open') ?></span>
                            <?php endif; ?>
                            <?php if ($item->is_gating) : ?>
                                <span class="badge bg-warning text-dark">
                                    <kmp-icon name="flag-3" class="me-1" aria-hidden="true"></kmp-icon><?= __('Required') ?>
                                </span>
                            <?php else : ?>
                                <span class="badge bg-light text-dark border">
                                    <kmp-icon name="flag" class="me-1" aria-hidden="true"></kmp-icon><?= __('Optional') ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($requirement !== null) : ?>
                                <?php if (!empty($requirement['satisfied'])) : ?>
                                    <span class="badge bg-info text-dark">
                                        <?php if ($requirementField === 'court_slot') : ?>
                                            <kmp-icon name="list-check" class="me-1" aria-hidden="true"></kmp-icon>
                                            <?= __('Court assigned') ?>
                                        <?php else : ?>
                                            <kmp-icon name="calendar-check" class="me-1" aria-hidden="true"></kmp-icon>
                                            <?= __('Gathering assigned') ?>
                                        <?php endif; ?>
                                    </span>
                                <?php else : ?>
                                    <span class="badge bg-danger">
                                        <?php if ($requirementField === 'court_slot') : ?>
                                            <kmp-icon name="list-check" class="me-1" aria-hidden="true"></kmp-icon>
                                            <?= __('Court assignment required') ?>
                                        <?php else : ?>
                                            <kmp-icon name="calendar-x" class="me-1" aria-hidden="true"></kmp-icon>
                                            <?= __('Gathering required') ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($blocked) : ?>
                                <span class="badge bg-secondary">
                                    <kmp-icon name="lock" class="me-1" aria-hidden="true"></kmp-icon>
                                    <?= h((string)($blocker['label'] ?? __('Waiting on prerequisite'))) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($blocked) : ?>
                            <div class="small text-muted mt-1">
                                <kmp-icon name="info-circle" class="me-1" aria-hidden="true"></kmp-icon>
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
                                <?= $this->element('bestowal_terminal_outcome_field', ['item' => $item]) ?>
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
                                <button type="submit" class="btn btn-sm btn-success"
                                    <?php if ($item->is_terminal) :
                                        ?>data-confirm-message="<?= h($item->complete_confirmation) ?>"<?php
                                    endif; ?>>
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
                                <?= $this->element('bestowal_terminal_outcome_field', ['item' => $item]) ?>
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
                                <button type="submit" class="btn btn-sm btn-success"
                                    <?php if ($item->is_terminal) :
                                        ?>data-confirm-message="<?= h($item->complete_confirmation) ?>"<?php
                                    endif; ?>>
                                    <?= __('Assign Court and Complete') ?>
                                </button>
                                <?= $this->Form->end() ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-shrink-0">
                        <?php if ($canCompleteDirectly && $hasOutcomeChoices) : ?>
                            <?= $this->Form->create(null, [
                                'url' => ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'complete', $item->id],
                            ]) ?>
                            <?= $this->Form->hidden('current_page', ['value' => $currentPageUrl]) ?>
                            <?= $this->element('bestowal_terminal_outcome_field', ['item' => $item]) ?>
                            <?= $this->Form->button(__('Complete {0}', $item->title), [
                                'class' => 'btn btn-sm btn-success',
                                'data-confirm-message' => $item->complete_confirmation,
                            ]) ?>
                            <?= $this->Form->end() ?>
                        <?php elseif ($canCompleteDirectly) : ?>
                            <?= $this->Form->postLink(
                                __('Complete'),
                                ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'complete', $item->id],
                                [
                                    'class' => 'btn btn-sm btn-success',
                                    'data' => ['current_page' => $currentPageUrl],
                                    'confirm' => $item->complete_confirmation
                                        ?? __('Mark "{0}" complete?', $item->title),
                                    'aria-label' => __('Mark complete: {0}', $item->title),
                                ],
                            ) ?>
                        <?php elseif ($eligible && $item->isCompleted()) : ?>
                            <?= $this->Form->postLink(
                                '<kmp-icon name="rotate" class="me-1" aria-hidden="true"></kmp-icon>' . __('Reopen'),
                                ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'reopen', $item->id],
                                [
                                    'escapeTitle' => false,
                                    'class' => 'btn btn-sm btn-outline-secondary',
                                    'data' => ['current_page' => $currentPageUrl],
                                    'confirm' => $item->reopen_confirmation ?? __('Reopen "{0}"?', $item->title),
                                    'aria-label' => __('Reopen: {0}', $item->title),
                                ],
                            ) ?>
                        <?php elseif ($canAssignRequirement) : ?>
                            <span class="text-muted small">
                                <?= $requirementField === 'court_slot'
                                ? __('Choose a court to complete this task.')
                                : __('Choose a gathering to complete this task.') ?>
                            </span>
                        <?php elseif ($blocked) : ?>
                            <span class="text-muted small">
                                <?= h((string)($blocker['label'] ?? __('Waiting on prerequisite'))) ?>
                            </span>
                        <?php else : ?>
                            <span class="text-muted small">
                                <?= isset($bestowal) && !$bestowal->allowsActionItemMutations()
                                ? __('Read-only') : __('Not assigned to you') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
