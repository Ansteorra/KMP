<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
$shortSiteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
?>
<div data-controller="security-settings" data-action="kmp:device-setup->security-settings#deviceSetup">
    <div data-security-settings-target="panel" data-step="home">
        <h3 class="h5" tabindex="-1" data-security-settings-target="heading overview">
            <?= $isSelf ? 'Your account security' : h('Security for ' . $member->sca_name) ?>
        </h3>
        <p data-security-settings-target="overview">
            Choose what you would like to do. We will explain each step before making a change.
        </p>
        <div class="d-grid gap-3">
            <button type="button" class="btn btn-outline-primary text-start"
                data-action="security-settings#open" data-step="password-intro"
                data-security-settings-target="overview">
                <strong class="d-block"><?= $isSelf ? 'Change password' : 'Reset password' ?></strong>
                <span class="d-block small">
                    <?= h($isSelf
                        ? __('Choose a new {0} password.', $shortSiteTitle)
                        : __('Set a new {0} password for this member.', $shortSiteTitle)) ?>
                </span>
            </button>
            <button type="button" class="btn btn-outline-danger text-start"
                data-action="security-settings#open" data-step="revoke" data-security-settings-target="overview">
                <strong class="d-block">Sign out all devices</strong>
                <span class="d-block small">End all sign-ins and disable quick login on all devices.</span>
            </button>
            <?php if ($isSelf) : ?>
                <?= $this->element('offline_access') ?>
            <a class="btn btn-outline-secondary text-start" data-turbo="false" data-security-settings-target="overview"
                href="<?= $this->Url->build(['controller' => 'Members', 'action' => 'logout', 'plugin' => null]) ?>">
                Sign out this device
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                data-security-settings-target="overview">Done</button>
        </div>
    </div>
    <div data-security-settings-target="panel" data-step="password-intro" hidden>
        <p class="text-body-secondary">Step 1 of 2</p>
        <h3 class="h5" tabindex="-1">
            <?= $isSelf ? 'Before changing your password' : 'Before resetting this password' ?>
        </h3>
        <p><?= $isSelf
            ? 'Changing your password signs you out on every device and invalidates your quick login PINs.'
            : 'Changing this password signs the member out everywhere and invalidates their quick login PINs.' ?></p>
        <p>Use the new password to sign in again. You can set up quick login again after signing in.</p>
        <button type="button" class="btn btn-primary"
            data-action="security-settings#open" data-step="password-form">Continue</button>
        <button type="button" class="btn btn-outline-secondary"
            data-action="security-settings#home">Back to Security</button>
    </div>
    <div data-security-settings-target="panel" data-step="password-form" hidden>
        <p class="text-body-secondary">Step 2 of 2</p>
        <h3 class="h5" tabindex="-1">Choose a new password</h3>
        <?= $this->Form->create($passwordReset, [
            'url' => ['controller' => 'Members', 'action' => 'changePassword', $member->id, 'plugin' => null],
            'data-turbo' => 'false',
            'data-action' => 'submit->security-settings#validatePassword',
        ]) ?>
        <?= $this->Form->control('new_password', [
            'type' => 'password', 'label' => __('New {0} password', $shortSiteTitle),
            'autocomplete' => 'new-password',
            'required' => true, 'minlength' => 12, 'maxlength' => 125,
            'data-security-settings-target' => 'password', 'aria-describedby' => 'security-password-help',
        ]) ?>
        <p id="security-password-help" class="form-text">
            Use 12–125 characters. A few unrelated words can be easier to remember.
        </p>
        <?= $this->Form->control('confirm_password', [
            'type' => 'password', 'label' => 'Repeat new password', 'autocomplete' => 'new-password',
            'required' => true, 'minlength' => 12, 'maxlength' => 125,
            'data-security-settings-target' => 'confirmation', 'aria-describedby' => 'security-password-error',
        ]) ?>
        <p id="security-password-error" role="alert" data-security-settings-target="error"></p>
        <div class="d-flex flex-wrap gap-2">
            <?= $this->Form->button('Save password', ['class' => 'btn btn-primary']) ?>
            <button type="button" class="btn btn-outline-secondary"
                data-action="security-settings#open" data-step="password-intro">Back</button>
            <button type="button" class="btn btn-outline-secondary" data-action="security-settings#home">Cancel</button>
        </div>
        <?= $this->Form->end() ?>
    </div>
    <div data-security-settings-target="panel" data-step="revoke" hidden>
        <?= $this->element('members/revokeSessions', ['member' => $member]) ?>
    </div>
</div>
