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
 * @var array<string, string> $checkOptions
 */

use Cake\Routing\Router;

$modalId = $modalId ?? 'bestowalBulkTodoModal';
$checkOptions = $checkOptions ?? [];
$formUrl = $this->URL->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'bulkCompleteTodo',
]);
$currentPage = Router::url(null, true);
?>
<div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1"
    aria-labelledby="<?= h($modalId) ?>Label" aria-hidden="true"
    data-controller="awards-bestowal-bulk-todo">
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
                    <select id="bulkCheckKey" name="check_key" class="form-select" required>
                        <option value=""><?= __('— Select a check —') ?></option>
                        <?php foreach ($checkOptions as $key => $label) : ?>
                            <option value="<?= h($key) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        <?= __(
                            'Only completes bestowals where you are the assigned doer and the check is still open.',
                        ) ?>
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
