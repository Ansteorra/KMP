<section data-controller="offline-vault" aria-labelledby="offline-title">
    <h1 id="offline-title">Offline cards and RSVPs</h1>
    <p>Your saved information is encrypted on this browser for up to seven days. Refresh online before travelling.
        Offline cards show the last verified status and cannot reflect new revocations.</p>
    <p><a href="/members/view-mobile-card">Online auth card</a> · <a href="/gathering-attendances/my-rsvps">Online RSVPs</a> · <a href="/members/login">Sign in</a></p>
    <div class="alert alert-info" role="status" aria-live="polite" aria-atomic="true" data-offline-vault-target="status">Checking offline storage…</div>
    <div data-offline-vault-target="enroll" hidden>
        <h2 class="h4">Enable offline access</h2>
        <p>Use this only on a device you control. Device unlock uses Face ID, Touch ID or your device screen lock where supported.
            You can also choose a 6–8 digit offline PIN.</p>
        <button type="button" class="btn btn-outline-primary mb-3" data-action="offline-vault#enrollDevice">Use device unlock</button>
        <div data-offline-vault-target="deviceSetup" class="border rounded p-3 mb-3" hidden>
            <p>Follow each step here to test device encryption. Creating a passkey alone does not finish offline setup.</p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" data-offline-vault-target="deviceNext" data-action="offline-vault#continueDevice">Create offline passkey</button>
                <button type="button" class="btn btn-outline-secondary" data-action="offline-vault#cancelDevice">Cancel device setup</button>
            </div>
        </div>
        <form data-action="submit->offline-vault#enrollPassphrase">
            <label for="offline-new-passphrase" class="form-label">Offline PIN</label>
            <input id="offline-new-passphrase" type="password" inputmode="numeric" pattern="[0-9]{6,8}" class="form-control" autocomplete="new-password" minlength="6" maxlength="8"
                required aria-describedby="offline-passphrase-help" data-offline-vault-target="newPassphrase">
            <p id="offline-passphrase-help" class="form-text">Use 6–8 digits. This PIN unlocks saved cards and RSVPs in this browser.
                Without your unlock method, reconnect and replace the saved data.</p>
            <label for="offline-confirm-pin" class="form-label">Confirm offline PIN</label>
            <input id="offline-confirm-pin" type="password" inputmode="numeric" pattern="[0-9]{6,8}" class="form-control mb-3" autocomplete="new-password" minlength="6" maxlength="8"
                required aria-describedby="offline-passphrase-help" data-offline-vault-target="confirmPin">
            <button type="submit" class="btn btn-primary">Save with offline PIN</button>
        </form>
    </div>
    <div data-offline-vault-target="locked" hidden>
        <h2 class="h4">Unlock saved information</h2>
        <button type="button" class="btn btn-primary" data-offline-vault-target="deviceUnlock" data-action="offline-vault#unlockDevice">Unlock with device PIN or biometrics</button>
        <form data-offline-vault-target="passphraseForm" data-action="submit->offline-vault#unlockPassphrase" hidden>
            <label for="offline-unlock-passphrase" class="form-label">Offline passphrase</label>
            <input id="offline-unlock-passphrase" type="password" class="form-control mb-2" autocomplete="current-password" required maxlength="128" data-offline-vault-target="passphrase">
            <button type="submit" class="btn btn-primary">Unlock offline data</button>
        </form>
    </div>
    <div data-offline-vault-target="unlocked" hidden>
        <div class="d-flex flex-wrap gap-2 my-3">
            <button type="button" class="btn btn-primary" data-action="offline-vault#refresh">Refresh saved data online</button>
            <button type="button" class="btn btn-outline-primary" data-action="offline-vault#sync">Sync pending RSVPs</button>
            <button type="button" class="btn btn-outline-secondary" data-action="offline-vault#lock">Lock now</button>
        </div>
        <p class="text-muted" data-offline-vault-target="verified"></p>
        <p>Information locks when this page is hidden or after five minutes without interaction. Only pending private RSVPs sync; changes to sharing or notes require the online page.</p>
        <section class="card my-3" aria-labelledby="offline-card-title">
            <div class="card-body"><h2 id="offline-card-title" class="h4">Auth card</h2><div data-offline-vault-target="card"></div></div>
        </section>
        <section class="card my-3" aria-labelledby="offline-rsvps-title">
            <div class="card-body"><h2 id="offline-rsvps-title" class="h4">My RSVPs</h2><div data-offline-vault-target="rsvps"></div></div>
        </section>
        <section class="card my-3" aria-labelledby="offline-events-title">
            <div class="card-body"><h2 id="offline-events-title" class="h4">Saved events</h2>
                <p>The current and next month are saved when refreshed. Queued RSVPs remain private.</p>
                <div data-offline-vault-target="events"></div>
            </div>
        </section>
    </div>
    <button type="button" class="btn btn-outline-danger mt-3" data-offline-vault-target="forget" data-action="offline-vault#forget" hidden>Remove offline data from this browser</button>
    <noscript><p>Offline access requires JavaScript and browser encryption support.</p></noscript>
</section>
