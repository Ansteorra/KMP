<?php
declare(strict_types=1);

/**
 * Cancellation and reconsideration confirmation for an open bestowal.
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Bestowal $bestowal
 */

$memberName = $bestowal->member->sca_name ?? $bestowal->member_sca_name ?? __('Unknown Member');
?>
<div class="modal fade" id="cancelBestowalModal" tabindex="-1"
    aria-labelledby="cancelBestowalModalLabel" aria-describedby="cancelBestowalImpact" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h2 class="modal-title fs-5" id="cancelBestowalModalLabel">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <?= __('Cancel Bestowal') ?>
                </h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'cancel',
                    $bestowal->id,
                ],
                'data-turbo-frame' => '_top',
            ]) ?>
            <div class="modal-body">
                <div class="alert alert-danger" id="cancelBestowalImpact">
                    <p class="fw-semibold mb-2">
                        <?= __('This starts a complete reconsideration for {0}.', h($memberName)) ?>
                    </p>
                    <ul class="mb-0">
                        <li><?= __('The bestowal will be marked Cancelled.') ?></li>
                        <li><?= __('All open bestowal to-dos will be cancelled.') ?></li>
                        <li>
                            <?= __('Linked recommendations will return to Submitted and begin a new approval cycle.') ?>
                        </li>
                    </ul>
                </div>

                <?= $this->Form->control('close_reason', [
                    'type' => 'textarea',
                    'id' => 'cancel-bestowal-reason',
                    'label' => __('Cancellation reason'),
                    'required' => true,
                    'rows' => 4,
                    'placeholder' => __('Explain why this bestowal requires complete reconsideration.'),
                    'aria-describedby' => 'cancelBestowalReasonHelp',
                ]) ?>
                <div id="cancelBestowalReasonHelp" class="form-text">
                    <?= __('This reason is retained with the cancelled bestowal for its audit history.') ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('Keep Bestowal Open') ?>
                </button>
                <?= $this->Form->button(
                    __('Cancel Bestowal and Reconsider'),
                    ['type' => 'submit', 'class' => 'btn btn-danger'],
                ) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
