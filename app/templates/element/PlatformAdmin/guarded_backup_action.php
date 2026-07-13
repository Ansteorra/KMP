<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var string $modalId
 * @var string $templateId
 * @var array|string $url
 * @var string $nonce
 * @var string $buttonLabel
 * @var string $modalTitle
 * @var string $description
 * @var string $confirmation
 * @var string $submitLabel
 * @var string|null $warning
 * @var string|null $tone
 * @var string|null $buttonClass
 * @var string|null $fieldsHtml Pre-rendered extra form controls shown before the confirmation input
 * @var bool|null $multipart Render the form with file-upload encoding
 */

$tone = in_array($tone ?? '', ['primary', 'warning', 'danger'], true) ? $tone : 'primary';
$buttonClass = $buttonClass ?? sprintf('btn btn-outline-%s btn-sm', $tone);
$submitClass = sprintf('btn btn-%s', $tone);
$formId = $templateId . '-form';
$confirmationId = $templateId . '-confirmation';
$confirmationHelpId = $templateId . '-confirmation-help';
$reasonId = $templateId . '-reason';
$reasonHelpId = $templateId . '-reason-help';
$totpId = $templateId . '-totp';
?>
<button
    type="button"
    class="<?= h($buttonClass) ?>"
    data-action="guarded-action-modal#open"
    data-guarded-template-id="<?= h($templateId) ?>"
    data-guarded-modal-title="<?= h($modalTitle) ?>"
    aria-haspopup="dialog"
    aria-controls="<?= h($modalId) ?>"
>
    <?= h($buttonLabel) ?>
</button>
<template id="<?= h($templateId) ?>">
    <?= $this->Form->create(null, array_filter([
        'url' => $url,
        'id' => $formId,
        'type' => !empty($multipart) ? 'file' : null,
        'data-expected-confirmation' => $confirmation,
    ], static fn($value) => $value !== null)) ?>
    <?= $this->Form->hidden('nonce', ['id' => $templateId . '-nonce', 'value' => $nonce]) ?>
    <div class="modal-body">
        <p><?= h($description) ?></p>
        <?php if (!empty($warning)) : ?>
            <div class="alert alert-<?= $tone === 'danger' ? 'danger' : 'warning' ?> py-2" role="note">
                <?= h($warning) ?>
            </div>
        <?php endif; ?>
        <?= $fieldsHtml ?? '' ?>
        <?= $this->Form->control('confirmation', [
            'id' => $confirmationId,
            'label' => __('Type "{0}" to confirm', $confirmation),
            'required' => true,
            'autocomplete' => 'off',
            'aria-describedby' => $confirmationHelpId,
            'data-guarded-action-initial-focus' => true,
        ]) ?>
        <div id="<?= h($confirmationHelpId) ?>" class="form-text mb-3">
            <?= __('The confirmation must match exactly.') ?>
        </div>
        <?= $this->Form->control('reason', [
            'id' => $reasonId,
            'type' => 'textarea',
            'label' => __('Operator reason'),
            'rows' => 3,
            'minlength' => 10,
            'required' => true,
            'aria-describedby' => $reasonHelpId,
        ]) ?>
        <div id="<?= h($reasonHelpId) ?>" class="form-text mb-3">
            <?= __('Enter at least 10 characters. This reason is written to the platform audit log.') ?>
        </div>
        <?= $this->Form->control('totp', [
            'id' => $totpId,
            'label' => __('MFA code'),
            'required' => true,
            'autocomplete' => 'one-time-code',
            'inputmode' => 'numeric',
        ]) ?>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <?= __('Cancel') ?>
        </button>
        <?= $this->Form->button($submitLabel, ['class' => $submitClass]) ?>
    </div>
    <?= $this->Form->end() ?>
</template>
