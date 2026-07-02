<?php

/**
 * Bestowal bulk gathering assignment modal.
 *
 * @var \App\View\AppView $this
 * @var string $modalId
 */

use Cake\Routing\Router;

$modalId = $modalId ?? 'bestowalBulkGatheringModal';
$formUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'bulkAssignGathering',
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
    data-controller="awards-bestowal-bulk-gathering"
    data-awards-bestowal-bulk-gathering-lookup-url-value="<?= h($lookupUrl) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => $formUrl, 'id' => 'bestowal_bulk_gathering_form']) ?>
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="<?= h($modalId) ?>Label">
                    <i class="bi bi-calendar-event me-2" aria-hidden="true"></i><?= __('Mass Assign Gathering') ?>
                </h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="alert alert-info" role="status" aria-live="polite"
                    data-awards-bestowal-bulk-gathering-target="summary">
                    <?= __('Select bestowals in the grid to mass assign a gathering.') ?>
                </p>
                <input type="hidden" name="bestowal_ids" data-awards-bestowal-bulk-gathering-target="ids">
                <input type="hidden" name="current_page" value="<?= h($currentPage) ?>">

                <?= $this->KMP->autoCompleteControl(
                    $this->Form,
                    'bulk_bestowal_gathering_name',
                    'bestowal_gathering_id',
                    $lookupUrl,
                    __('Gathering'),
                    true,
                    false,
                    2,
                    [
                        'data-awards-bestowal-bulk-gathering-target' => 'gatheringControl',
                        'data-ac-show-on-focus-value' => 'true',
                    ],
                ) ?>
                <div class="form-text mb-3" id="<?= h($modalId) ?>GatheringHelp">
                    <?=
                    __(
                        'Gatherings are filtered with the same rules used by the bestowal edit form. ' .
                        'Only gatherings selectable for all selected bestowals are offered.',
                    )
                    ?>
                </div>

                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="<?= h($modalId) ?>CompleteTodo"
                        name="complete_required_todo" value="1" checked>
                    <label class="form-check-label" for="<?= h($modalId) ?>CompleteTodo">
                        <?= __('Complete matching Event Scheduled to-dos when eligible') ?>
                    </label>
                </div>
                <div class="form-text">
                    <?=
                    __('Only to-dos assigned to you and satisfied by the gathering assignment will be completed.')
                    ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary"
                    data-awards-bestowal-bulk-gathering-target="submit" disabled>
                    <i class="bi bi-calendar-check me-1" aria-hidden="true"></i><?= __('Assign Gathering') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
