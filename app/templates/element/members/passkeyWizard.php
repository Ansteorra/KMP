<?php
/** @var \App\View\AppView $this */
$hasActivePasskey = $passkeys->some(fn($key) => hash_equals((string)$member->auth_version, (string)$key->auth_version));
?>
<div data-controller="passkey" data-passkey-initial-value="<?= $hasActivePasskey ? 'manage' : 'intro' ?>">
    <p class="text-body-secondary mb-2" data-passkey-target="progress"></p>
    <h3 class="h5" tabindex="-1" data-passkey-target="heading">Your passkeys</h3>
    <p role="status" aria-live="polite" aria-atomic="true" id="passkey-feedback" data-passkey-target="status"></p>
    <div data-passkey-target="panel" data-step="manage" hidden>
        <p>A passkey lets you sign in using your face, fingerprint or device screen lock.</p>
        <ul class="list-group mb-3">
            <?php foreach ($passkeys as $passkey) : ?>
            <li class="list-group-item">
                <span class="d-block text-break"><?= h($passkey->label) ?></span>
                <span class="d-block small mb-2"><?= hash_equals((string)$member->auth_version, (string)$passkey->auth_version) ? 'Ready to use' : 'No longer works — you can remove it' ?></span>
                <button type="button" class="btn btn-outline-danger btn-sm" data-action="passkey#confirmRemove"
                    data-id="<?= h($passkey->id) ?>" data-label="<?= h($passkey->label) ?>"
                    aria-label="<?= h('Remove ' . $passkey->label) ?>">Remove</button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" data-action="passkey#start">Add a passkey</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Done</button>
        </div>
    </div>
    <div data-passkey-target="panel" data-step="intro" hidden>
        <p>A passkey is an easier way to sign in. Your phone or computer checks your face, fingerprint or screen lock, so you do not have to type your KMP password each time.</p>
        <p>KMP never receives your fingerprint, face scan or device PIN. You can still use your KMP password.</p>
        <p>We will check your password, then help you save a passkey. You can cancel at any time.</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" data-action="passkey#passwordStep">Get started</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Not now</button>
            <?php if (!$passkeys->isEmpty()) : ?>
            <button type="button" class="btn btn-outline-secondary" data-action="passkey#manage">View saved passkeys</button>
            <?php endif; ?>
        </div>
    </div>
    <div data-passkey-target="panel" data-step="password" hidden>
        <p>Enter the password you use to sign in to KMP. This helps us make sure it is you adding a new way to sign in.</p>
        <?= $this->Form->create(null, ['data-action' => 'submit->passkey#prepareRegistration']) ?>
        <?= $this->Form->control('label', ['label' => 'Name this passkey', 'maxlength' => 80, 'required' => true,
            'value' => 'My device', 'data-passkey-target' => 'label', 'aria-describedby' => 'passkey-name-help']) ?>
        <p class="form-text" id="passkey-name-help">Use a name you will recognize, such as “My iPhone”.</p>
        <?= $this->Form->control('password', ['label' => 'Your KMP password', 'type' => 'password',
            'autocomplete' => 'current-password', 'required' => true, 'data-passkey-target' => 'password',
            'aria-describedby' => 'passkey-password-help passkey-feedback']) ?>
        <p id="passkey-password-help" class="form-text">This is your KMP password, not the PIN used to unlock your phone.</p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?= $this->Form->button('Continue', ['class' => 'btn btn-primary', 'data-passkey-target' => 'work']) ?>
            <button type="button" class="btn btn-outline-secondary" data-action="passkey#start">Back</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
        <?= $this->Form->end() ?>
    </div>
    <div data-passkey-target="panel" data-step="device" hidden>
        <p>Your password is confirmed. Next, your device will ask where to save the passkey and may check your face, fingerprint or screen lock.</p>
        <p>Choose <strong>Create passkey</strong> below, then follow your device’s instructions.</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" data-action="passkey#register" data-passkey-target="continue work">Create passkey</button>
            <button type="button" class="btn btn-outline-secondary" data-action="passkey#passwordStep">Back</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
    </div>
    <div data-passkey-target="panel" data-step="success" hidden>
        <p>Your passkey is ready. Next time you sign in, choose <strong>Sign in with a passkey</strong> and follow your device’s instructions.</p>
        <p>Want to use cards without an internet connection? You can set that up separately under <strong>Protected offline cards and RSVPs</strong>.</p>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
        <button type="button" class="btn btn-outline-secondary" data-action="passkey#refresh">View my passkeys</button>
    </div>
    <div data-passkey-target="panel" data-step="remove" hidden>
        <p>Remove <strong class="text-break" data-passkey-target="removeName"></strong> from KMP?</p>
        <p>You will no longer be able to use this passkey to sign in. You can still use your password or another passkey. Devices already signed in will stay signed in.</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-danger" data-action="passkey#remove" data-passkey-target="work">Remove passkey</button>
            <button type="button" class="btn btn-outline-secondary" data-action="passkey#manage">Cancel</button>
        </div>
    </div>
    <div data-passkey-target="panel" data-step="removed" hidden>
        <p>This passkey can no longer sign in to KMP. You may still see it in your device’s password manager, where you can delete the saved copy.</p>
        <button type="button" class="btn btn-primary" data-action="passkey#refresh">View my passkeys</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Done</button>
    </div>
</div>
