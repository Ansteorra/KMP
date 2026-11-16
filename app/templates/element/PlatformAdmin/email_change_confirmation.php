<?php
declare(strict_types=1);

use Cake\Core\Configure;

$totpDigits = (int)Configure::read('Platform.adminMfa.digits', 6);
?>
<fieldset class="card mb-4" aria-describedby="email-change-help">
    <legend class="h5 px-3 pt-3 mb-0"><?= __('Confirm email delivery changes') ?></legend>
    <div class="card-body">
        <p id="email-change-help" class="text-muted">
            <?= __(
                'Complete this section only when changing email delivery. '
                . 'Use a fresh authenticator code; each code can be used once. '
                . 'Other tenant edits do not require this confirmation.',
            ) ?>
        </p>
        <?= $this->Form->control('confirmation', [
            'label' => __('Email change confirmation'),
            'help' => __('Type EMAIL followed by a space and the tenant slug, for example EMAIL example.'),
            'autocomplete' => 'off',
        ]) ?>
        <?= $this->Form->control('reason', [
            'type' => 'textarea',
            'label' => __('Reason for email delivery change'),
            'help' => __('Enter at least 10 characters. The reason is recorded in the platform audit log.'),
            'maxlength' => 1000,
        ]) ?>
        <?= $this->Form->control('totp', [
            'type' => 'text',
            'label' => __('Fresh authenticator code for email changes'),
            'value' => '',
            'inputmode' => 'numeric',
            'autocomplete' => 'one-time-code',
            'maxlength' => $totpDigits,
            'pattern' => '[0-9]{' . $totpDigits . '}',
        ]) ?>
    </div>
</fieldset>
