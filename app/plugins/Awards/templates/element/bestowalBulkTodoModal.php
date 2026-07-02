<?php

/**
 * Bestowal bulk "Complete Check" modal.
 *
 * Opened from the bestowals grid bulk-action bar. Lets the user pick one named
 * preparation check and complete it across the selected bestowals. Only checks
 * the user is the assigned doer for (and that are still open) are flipped — the
 * server enforces eligibility and reports the outcome.
 *
 * @var \App\View\AppView $this
 * @var string $modalId
 */

use Cake\Routing\Router;

$modalId = $modalId ?? 'bestowalBulkTodoModal';
$formUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'bulkCompleteTodo',
]);
$lookupUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'gatheringsForBestowalAutoComplete',
]);
$currentPage = Router::url(null, true);
?>
<div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1"
    aria-labelledby="<?= h($modalId) ?>Label" aria-hidden="true"
    data-controller="awards-bestowal-bulk-todo"
    data-awards-bestowal-bulk-todo-lookup-url-value="<?= h($lookupUrl) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => $formUrl, 'id' => 'bestowal_bulk_todo_form']) ?>
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="<?= h($modalId) ?>Label">
                    <i class="bi bi-check2-square me-2" aria-hidden="true"></i><?= __('Complete a Check') ?>
                </h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="alert alert-info" role="status" aria-live="polite"
                    data-awards-bestowal-bulk-todo-target="summary">
                    <?= __('Select bestowals in the grid to complete a check across them.') ?>
                </p>
                <input type="hidden" name="bestowal_ids" data-awards-bestowal-bulk-todo-target="ids">
                <input type="hidden" name="current_page" value="<?= h($currentPage) ?>">
                <div class="mb-2">
                    <label for="bulkCheckKey" class="form-label"><?= __('Check to complete') ?></label>
                    <select id="bulkCheckKey" name="check_key" class="form-select" required
                        data-awards-bestowal-bulk-todo-target="checkSelect"
                        aria-describedby="bulkCheckKeyHelp">
                        <option value=""><?= __('Select bestowals first') ?></option>
                    </select>
                    <div class="form-text" id="bulkCheckKeyHelp">
                        <?= __(
                            'The list only includes open checks assigned to you on the selected bestowals.',
                        ) ?>
                    </div>
                </div>
                <div class="mt-3" data-awards-bestowal-bulk-todo-target="gatheringSection" hidden>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="<?= h($modalId) ?>IncludePast"
                            name="include_past" value="1"
                            data-awards-bestowal-bulk-todo-target="includePast"
                            data-action="change->awards-bestowal-bulk-todo#handleIncludePastChange">
                        <label class="form-check-label" for="<?= h($modalId) ?>IncludePast">
                            <?= __('Include past gatherings') ?>
                        </label>
                    </div>
                    <?= $this->KMP->autoCompleteControl(
                        $this->Form,
                        'bulk_bestowal_gathering_name',
                        'bestowal_gathering_id',
                        $lookupUrl,
                        __('Bestowal Gathering'),
                        true,
                        false,
                        2,
                        [
                            'data-awards-bestowal-bulk-todo-target' => 'gatheringControl',
                            'data-ac-show-on-focus-value' => 'true',
                        ],
                    ) ?>
                    <div class="form-text" data-awards-bestowal-bulk-todo-target="gatheringHelp">
                        <?=
                        __(
                            'Gatherings are filtered with the same rules used by the bestowal edit form. ' .
                            'Only gatherings selectable for the matching selected bestowals are offered.',
                        )
                        ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary"
                    data-awards-bestowal-bulk-todo-target="submit" disabled>
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= __('Complete Check') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
