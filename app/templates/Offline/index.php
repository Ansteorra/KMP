<section data-controller="offline-vault" aria-labelledby="offline-title">
    <h1 id="offline-title">Offline cards and RSVPs</h1>
    <p>Your saved information is encrypted on this browser for up to seven days. Refresh online before travelling.
        Offline cards show the last verified status and cannot reflect new revocations.</p>
    <p><a href="/members/view-mobile-card">Online auth card</a> · <a href="/gathering-attendances/my-rsvps">Online RSVPs</a> · <a href="/members/login">Sign in</a></p>
    <div class="alert alert-info" role="status" aria-live="polite" aria-atomic="true" data-offline-vault-target="status">Checking offline storage…</div>
    <div data-offline-vault-target="enroll" hidden>
        <h2 class="h4">Enable offline access</h2>
        <p>Use this only on a device you control. Device unlock uses your device PIN or biometrics where supported.
            If unavailable, choose a separate offline passphrase. This does not change how you sign in to KMP.</p>
        <button type="button" class="btn btn-primary mb-3" data-action="offline-vault#enrollDevice">Use device unlock</button>
        <form data-action="submit->offline-vault#enrollPassphrase">
            <label for="offline-new-passphrase" class="form-label">Offline passphrase</label>
            <input id="offline-new-passphrase" type="password" class="form-control" autocomplete="new-password" minlength="15" maxlength="128"
                required aria-describedby="offline-passphrase-help" data-offline-vault-target="newPassphrase">
            <p id="offline-passphrase-help" class="form-text">Use 15–128 characters, such as several unrelated words. Do not reuse your KMP password or enter a short PIN.
                Without your unlock method, you must reconnect and replace the saved data.</p>
            <button type="submit" class="btn btn-outline-primary">Use offline passphrase</button>
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
