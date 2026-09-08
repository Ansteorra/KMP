<?php /** @var \App\View\AppView $this */ ?>
<div data-controller="security-settings">
    <div data-security-settings-target="panel" data-step="home">
        <h3 class="h5" tabindex="-1" data-security-settings-target="heading"><?= $isSelf ? 'Your account security' : h('Security for ' . $member->sca_name) ?></h3>
        <p>Choose what you would like to do. We will explain each step before making a change.</p>
        <div class="d-grid gap-3">
            <?php if ($canManagePasskeys) : ?>
            <button type="button" class="btn btn-outline-primary text-start" data-action="security-settings#open" data-step="passkeys">
                <strong class="d-block">Manage passkeys</strong>
                <span class="d-block small">Add an easier way to sign in, or remove a passkey.</span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-primary text-start" data-action="security-settings#open" data-step="password-intro">
                <strong class="d-block"><?= $isSelf ? 'Change password' : 'Reset password' ?></strong>
                <span class="d-block small"><?= $isSelf ? 'Choose a new KMP password.' : 'Set a new KMP password for this member.' ?></span>
            </button>
            <button type="button" class="btn btn-outline-danger text-start" data-action="security-settings#open" data-step="revoke">
                <strong class="d-block">Sign out all devices</strong>
                <span class="d-block small">End all sign-ins and invalidate every passkey.</span>
            </button>
            <?php if ($isSelf) : ?>
            <a class="btn btn-outline-secondary text-start" data-turbo="false" href="<?= $this->Url->build(['controller' => 'Members', 'action' => 'logout', 'plugin' => null]) ?>">Sign out this device</a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Done</button>
        </div>
    </div>
    <?php if ($canManagePasskeys) : ?>
    <div data-security-settings-target="panel" data-step="passkeys" hidden>
        <?= $this->element('members/passkeyWizard', compact('member', 'passkeys')) ?>
        <button type="button" class="btn btn-link mt-3" data-action="security-settings#home">Back to Security</button>
    </div>
    <?php endif; ?>
    <div data-security-settings-target="panel" data-step="password-intro" hidden>
        <p class="text-body-secondary">Step 1 of 2</p>
        <h3 class="h5" tabindex="-1"><?= $isSelf ? 'Before changing your password' : 'Before resetting this password' ?></h3>
        <p><?= $isSelf ? 'Changing your password signs you out on every device and invalidates your current passkeys.' : 'Changing this password signs the member out on every device and invalidates their current passkeys.' ?></p>
        <p>Use the new password to sign in again. Passkeys can then be added again from Security.</p>
        <button type="button" class="btn btn-primary" data-action="security-settings#open" data-step="password-form">Continue</button>
        <button type="button" class="btn btn-outline-secondary" data-action="security-settings#home">Back to Security</button>
    </div>
    <div data-security-settings-target="panel" data-step="password-form" hidden>
        <p class="text-body-secondary">Step 2 of 2</p>
        <h3 class="h5" tabindex="-1">Choose a new password</h3>
        <?= $this->Form->create($passwordReset, ['url' => ['controller' => 'Members', 'action' => 'changePassword', $member->id, 'plugin' => null], 'data-turbo' => 'false', 'data-action' => 'submit->security-settings#validatePassword']) ?>
        <?= $this->Form->control('new_password', ['type' => 'password', 'label' => 'New KMP password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => 12, 'maxlength' => 125, 'data-security-settings-target' => 'password', 'aria-describedby' => 'security-password-help']) ?>
        <p id="security-password-help" class="form-text">Use 12–125 characters. A few unrelated words can be easier to remember.</p>
        <?= $this->Form->control('confirm_password', ['type' => 'password', 'label' => 'Repeat new password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => 12, 'maxlength' => 125, 'data-security-settings-target' => 'confirmation', 'aria-describedby' => 'security-password-error']) ?>
        <p id="security-password-error" role="alert" data-security-settings-target="error"></p>
        <div class="d-flex flex-wrap gap-2">
            <?= $this->Form->button('Save password', ['class' => 'btn btn-primary']) ?>
            <button type="button" class="btn btn-outline-secondary" data-action="security-settings#open" data-step="password-intro">Back</button>
            <button type="button" class="btn btn-outline-secondary" data-action="security-settings#home">Cancel</button>
        </div>
        <?= $this->Form->end() ?>
    </div>
    <div data-security-settings-target="panel" data-step="revoke" hidden>
        <?= $this->element('members/revokeSessions', ['member' => $member]) ?>
    </div>
</div>
