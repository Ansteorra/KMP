<?php
declare(strict_types=1);
$shortSiteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
?>
<?= $this->element('offline_access') ?>
<p class="small my-2" role="status" aria-live="polite" data-offline-vault-target="status">
    Opening <?= h($shortSiteTitle) ?>…</p>
<div class="card my-3" data-offline-vault-target="enroll" hidden>
    <div class="card-body">
        <h2 class="h5">Keep <?= h($shortSiteTitle) ?> with you</h2>
        <p>Sign in online and trust your personal device once to keep your card, RSVPs, and events available here.</p>
        <a href="/members/login" class="btn btn-primary" data-turbo="false"
            data-offline-vault-target="signIn" hidden>Sign in</a>
    </div>
</div>
<div class="card my-3" data-offline-vault-target="locked" hidden>
    <div class="card-body">
        <h2 class="h5">Open your previously saved information</h2>
        <button type="button" class="btn btn-primary" data-offline-vault-target="deviceUnlock"
            data-action="offline-vault#unlockDevice">Unlock with this device</button>
        <form data-offline-vault-target="passphraseForm" data-action="submit->offline-vault#unlockPassphrase" hidden>
            <label for="offline-unlock-passphrase" class="form-label">Offline password</label>
            <input id="offline-unlock-passphrase" type="password" class="form-control mb-3"
                autocomplete="current-password"
                required maxlength="128" data-offline-vault-target="passphrase">
            <button type="submit" class="btn btn-primary">Open saved information</button>
        </form>
    </div>
</div>
<a href="/members/login" class="btn btn-primary my-2" data-offline-vault-target="trustLegacy"
    data-turbo="false" hidden>Sign in to add a PIN or passkey</a>
