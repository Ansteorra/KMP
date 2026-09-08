<?php
/** @var \App\View\AppView $this */
if (!$mobile) {
    $this->extend('/layout/TwitterBootstrap/dashboard');
}
?>
<section aria-labelledby="passkeys-title" data-controller="passkey">
    <h1 id="passkeys-title">Your passkeys</h1>
    <p>Sign in using Face ID, Touch ID, a security key or your device screen lock. KMP does not receive your biometric data or device PIN.</p>
    <p>Password changes, recovery and signing out all devices revoke your login passkeys. Add them again after signing in with your password.</p>
    <p><a href="/members/profile">My account</a> · <a href="/offline">Set up or unlock offline cards and RSVPs</a></p>
    <p>Offline access is saved separately in each browser. Creating a login passkey does not download offline data.</p>
    <div role="status" aria-live="polite" aria-atomic="true" class="my-3" data-passkey-target="status"></div>
    <h2 class="h4">Add a passkey</h2>
    <?= $this->Form->create(null, ['data-action' => 'submit->passkey#prepareRegistration']) ?>
    <?= $this->Form->control('label', [
        'label' => 'Passkey name', 'maxlength' => 80, 'required' => true,
        'data-passkey-target' => 'label', 'placeholder' => 'For example, iCloud Keychain',
    ]) ?>
    <?= $this->Form->control('password', [
        'label' => 'Confirm your KMP password', 'type' => 'password', 'autocomplete' => 'current-password',
        'required' => true, 'data-passkey-target' => 'password',
    ]) ?>
    <?= $this->Form->button('Continue', ['class' => 'btn btn-primary my-2']) ?>
    <?= $this->Form->end() ?>
    <div class="d-flex flex-wrap gap-2 my-3">
        <button type="button" class="btn btn-primary" data-action="passkey#register" data-passkey-target="continue" hidden>Create passkey</button>
        <button type="button" class="btn btn-outline-secondary" data-action="passkey#cancel" data-passkey-target="cancel" hidden>Cancel setup</button>
    </div>
    <h2 class="h4">Registered passkeys</h2>
    <?php if ($passkeys->isEmpty()) : ?>
        <p>No passkeys yet. Your previous quick-login PIN can no longer sign in.</p>
    <?php endif; ?>
    <ul class="list-group">
        <?php foreach ($passkeys as $passkey) : ?>
            <li class="list-group-item d-flex flex-wrap align-items-center gap-3">
                <span><?= h($passkey->label) ?> — <?= hash_equals((string)$member->auth_version, (string)$passkey->auth_version) ? 'Active' : 'Revoked; remove and add again' ?></span>
                <?= $this->Form->postLink('Remove ' . $passkey->label, ['action' => 'delete', $passkey->id], [
                    'class' => 'btn btn-outline-danger', 'confirm' => 'Remove this passkey? You can still sign in with your password.',
                ]) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
