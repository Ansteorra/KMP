<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Bestowal $bestowal
 * @var array<string, string> $terminalStateOptions
 */
?>
<div class="modal fade" id="changeTerminalStateModal" tabindex="-1"
    aria-labelledby="changeTerminalStateLabel" aria-describedby="changeTerminalStateHelp" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="changeTerminalStateLabel"><?= __('Change terminal state') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => ['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'changeTerminalState', $bestowal->id],
                'data-turbo-frame' => '_top',
            ]) ?>
            <?= $this->Form->hidden('expected_status', ['value' => $bestowal->lifecycle_status]) ?>
            <div class="modal-body">
                <p><?= h(__('Current terminal state: {0}', ucfirst($bestowal->lifecycle_status))) ?></p>
                <p id="changeTerminalStateHelp">
                    <?php if (isset($terminalStateOptions['given'])) : ?>
                        <?= __('When the recipient receives the award, choose Given.') ?>
                    <?php endif; ?>
                    <?= __('The original close date and closed To-Dos are preserved. This change is recorded in the To-Do history.') ?>
                </p>
                <?= $this->Form->control('terminal_status', [
                    'type' => 'select', 'id' => 'change-terminal-state-outcome',
                    'label' => __('New terminal state'), 'options' => $terminalStateOptions,
                    'empty' => __('Choose a terminal state'), 'required' => true,
                    'aria-describedby' => 'changeTerminalStateHelp',
                ]) ?>
                <?= $this->Form->control('note', [
                    'type' => 'textarea', 'id' => 'change-terminal-state-note',
                    'label' => __('Note (optional)'), 'rows' => 3,
                ]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <?= $this->Form->button(__('Save terminal state'), ['type' => 'submit', 'class' => 'btn btn-primary']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
