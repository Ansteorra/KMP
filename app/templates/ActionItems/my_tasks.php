<?php

/**
 * My To-Dos - Dataverse grid of the member's to-do action items.
 *
 * Mirrors the My Approvals grid: a lazy-loaded grid plus a shared completion
 * modal that marks a to-do complete (or reopens a completed one) via a Turbo
 * Stream that refreshes the grid in place.
 *
 * @var \App\View\AppView $this
 */

$completeUrl = $this->Url->build(['controller' => 'ActionItems', 'action' => 'complete']);
$reopenUrl = $this->Url->build(['controller' => 'ActionItems', 'action' => 'reopen']);
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': My To-Dos';
$this->KMP->endBlock(); ?>

<h3><kmp-icon name="checks" class="me-2" aria-hidden="true"></kmp-icon><?= __('My To-Dos') ?></h3>
<p class="text-muted">
    <?= __(
        'Checks assigned to you across the kingdom. Review the effects shown before completing or reopening a check.',
    ) ?>
</p>

<?= $this->element('dv_grid', [
    'frameId' => 'action-items-grid',
    'dataUrl' => $this->Url->build(['controller' => 'ActionItems', 'action' => 'myTasksGridData']),
]) ?>

<!-- To-Do Completion Modal -->

<?php
$dialogBindings = [
    'completeToDo' => __('Complete To-Do'),
    'markThisToDoComplete' => __('Mark this to-do complete?'),
    'markComplete' => __('Mark Complete'),
    'reopenToDo' => __('Reopen To-Do'),
    'reopenThisToDo' => __('Reopen this to-do?'),
    'reopen' => __('Reopen'),
    'chooseACompletionOutcome' => __('Choose a completion outcome'),
    'additionalInformation' => __('Additional Information'),
    'chooseTheGatheringWhereThisBestowalWill' => __(
        'Choose the gathering where this bestowal will be presented. '
        . 'Use Include past gatherings to backdate scheduling.',
    ),
    'chooseACourtAssignment' => __('Choose a court assignment'),
    'chooseRoamingCourtOrAnEligibleScheduled' => __('Choose Roaming Court or an eligible scheduled court activity.'),
];
?>
<div class="modal fade" id="todoCompleteModal"
    data-controller="todo-completion-modal"
    data-todo-completion-modal-bindings-value="<?= h(json_encode($dialogBindings)) ?>"
    tabindex="-1" aria-labelledby="todoCompleteModalLabel"
    aria-hidden="true" data-complete-url="<?= h($completeUrl) ?>" data-reopen-url="<?= h($reopenUrl) ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="todoCompleteModalLabel" data-todo-target="modalTitle">
                    <kmp-icon name="checkbox" class="me-2" aria-hidden="true"></kmp-icon><?= __('Complete To-Do') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'ActionItems', 'action' => 'complete'],
                'id' => 'todoCompleteForm',
                'data-turbo' => 'true',
                'data-controller' => 'turbo-modal',
                'data-action' => implode(' ', [
                    'submit->turbo-modal#submitAsTurboStream',
                    'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
                ]),
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="id" value="" data-todo-target="id">
                <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>

                <p class="mb-3" data-todo-target="prompt">
                    <?= __('Mark this to-do complete?') ?>
                    <strong data-todo-target="title"></strong>
                </p>

                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3" data-todo-target="specialForm" hidden>
                    <h6 class="fw-semibold mb-2" data-todo-target="specialTitle">
                        <?= __('Additional Information') ?>
                    </h6>
                    <p class="text-muted small mb-3" data-todo-target="specialDescription" role="status"></p>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="todoIncludePastGatherings"
                            name="include_past" value="1" data-todo-target="includePastGatherings">
                        <label class="form-check-label" for="todoIncludePastGatherings">
                            <?= __('Include past gatherings') ?>
                        </label>
                    </div>
                    <?= $this->KMP->autoCompleteControl(
                        $this->Form,
                        'bestowal_gathering_name',
                        'bestowal_gathering_id',
                        '#',
                        __('Bestowal Gathering'),
                        false,
                        false,
                        2,
                        [
                            'data-todo-target' => 'bestowalGatheringControl',
                            'data-ac-show-on-focus-value' => 'true',
                        ],
                    ) ?>
                    <div class="form-text" data-todo-target="bestowalGatheringHelp">
                        <?= __('Choose the gathering where this bestowal will be presented.') ?>
                    </div>
                    <div class="text-danger small" data-todo-target="bestowalGatheringError" hidden>
                        <kmp-icon name="alert-circle" class="me-1" aria-hidden="true"></kmp-icon>
                        <?= __('Select the gathering where the bestowal will be presented.') ?>
                    </div>
                    <div class="mt-3" data-todo-target="courtSlotGroup" hidden>
                        <label class="form-label" for="todoCourtSlot">
                            <?= __('Court Assignment') ?>
                        </label>
                        <select class="form-select" id="todoCourtSlot" name="gathering_scheduled_activity_id"
                            data-todo-target="courtSlotSelect" aria-describedby="todoCourtSlotHelp">
                            <option value=""><?= __('Choose a court assignment') ?></option>
                        </select>
                        <div class="form-text" id="todoCourtSlotHelp" data-todo-target="courtSlotHelp">
                            <?= __('Choose Roaming Court or an eligible scheduled court activity.') ?>
                        </div>
                        <div class="text-danger small" data-todo-target="courtSlotError" hidden>
                            <kmp-icon name="alert-circle" class="me-1" aria-hidden="true"></kmp-icon>
                            <?= __('Select Roaming Court or a scheduled court activity.') ?>
                        </div>
                    </div>
                </div>

                <div class="mb-3" data-todo-target="completionOutcomeGroup" hidden>
                    <label class="form-label" for="todoCompletionOutcome" data-todo-target="completionOutcomeLabel">
                        <?= __('Completion outcome') ?>
                    </label>
                    <select class="form-select" id="todoCompletionOutcome"
                        data-todo-target="completionOutcomeSelect"
                        aria-describedby="todoCompletionOutcomeHelp" disabled>
                    </select>
                    <div class="form-text" id="todoCompletionOutcomeHelp"
                        data-todo-target="completionOutcomeHelp"></div>
                </div>
                <div>
                    <label class="form-label" for="todoCompleteNote"><?= __('Note (optional)') ?></label>
                    <textarea class="form-control" id="todoCompleteNote" name="note" rows="3"
                        placeholder="<?= __('Add an optional note for the activity log...') ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-success" data-todo-target="submit">
                    <?= __('Mark Complete') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
