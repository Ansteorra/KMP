<?php
declare(strict_types=1);
$deviceFormId = 'trusted-device-' . uniqid();
$shortSiteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
?>
<section class="card my-3 text-start trusted-device" data-controller="offline-access" aria-label="This device" hidden>
    <div class="card-body">
        <h2 class="h5"><?= h($shortSiteTitle) ?> on this device</h2>
        <p class="small mb-2" role="status" tabindex="-1" id="<?= h($deviceFormId) ?>-status"
            data-offline-access-target="status"></p>
        <div class="alert alert-info" role="status" data-offline-access-target="migration" hidden>
            <h3 class="h6">A fresh start for PIN login</h3>
            <p class="mb-0" data-offline-access-target="migrationMessage"></p>
        </div>
        <div data-offline-access-target="choice" class="mb-2" hidden>
            <button type="button" class="btn btn-primary py-2" data-action="offline-access#trust"
                data-offline-access-target="trustButton">
                Trust this personal device
            </button>
            <button type="button" class="btn btn-outline-secondary py-2" data-action="offline-access#decline">
                Not now
            </button>
        </div>
        <div data-offline-access-target="wizard" hidden>
            <p class="small fw-semibold mb-2" data-offline-access-target="stepLabel"></p>
            <h3 class="h5" tabindex="-1" data-offline-access-target="stepHeading"></h3>
            <form data-offline-access-target="setup" data-action="submit->offline-access#setupDevice">
                <fieldset data-offline-access-target="passwordFields">
                    <legend class="visually-hidden">Confirm this is your personal device</legend>
                    <p>Keep your card, RSVPs, and events with you, even without internet.</p>
                    <ul class="small ps-3">
                        <li>Use your own phone, tablet, or computer. Avoid shared or public devices.</li>
                        <li>Your PIN or passkey will unlock <?= h($shortSiteTitle) ?> online and offline.</li>
                        <li>Logging out locks your saved information. It stays here for your next visit.</li>
                    </ul>
                    <label class="form-label" for="<?= h($deviceFormId) ?>-password">
                        Current <?= h($shortSiteTitle) ?> password</label>
                    <p class="form-text mt-0" id="<?= h($deviceFormId) ?>-password-help">
                        Enter it once to confirm it’s you. <?= h($shortSiteTitle) ?> will save an encrypted copy
                        on this device
                        so you can sign in with your PIN or passkey next time.
                    </p>
                    <input class="form-control mb-3" id="<?= h($deviceFormId) ?>-password" type="password"
                        autocomplete="current-password" required maxlength="125"
                        aria-describedby="<?= h($deviceFormId) ?>-password-help"
                        data-offline-access-target="password">
                </fieldset>
                <fieldset data-offline-access-target="protectionFields" hidden disabled>
                    <legend class="visually-hidden">Choose how to unlock <?= h($shortSiteTitle) ?></legend>
                    <p>You’ll use this whenever <?= h($shortSiteTitle) ?> is locked, including when you’re offline.</p>
                    <div data-offline-access-target="methodChoice">
                        <label class="form-label" for="<?= h($deviceFormId) ?>-method">Unlock this device with</label>
                        <select class="form-select mb-2" id="<?= h($deviceFormId) ?>-method"
                            data-offline-access-target="method" aria-describedby="<?= h($deviceFormId) ?>-method-help"
                            data-action="change->offline-access#chooseMethod">
                            <option value="device">Device unlock (passkey)</option>
                            <option value="pin"><?= h($shortSiteTitle) ?> PIN</option>
                        </select>
                    </div>
                    <p role="status" data-offline-access-target="availability" hidden></p>
                    <button type="button" class="btn btn-link mb-2" data-offline-access-target="recheck"
                        data-action="offline-access#recheckPasskeys" data-trust-control hidden>
                        Check passkey support again
                    </button>
                    <p class="form-text mt-0 mb-3" id="<?= h($deviceFormId) ?>-method-help"
                        data-offline-access-target="methodHelp"></p>
                    <div data-offline-access-target="pinFields" hidden>
                        <label class="form-label" for="<?= h($deviceFormId) ?>-pin">Choose a PIN (6–12 digits)</label>
                        <input class="form-control mb-3" id="<?= h($deviceFormId) ?>-pin" type="password"
                            inputmode="numeric" autocomplete="new-password" minlength="6" maxlength="12"
                            pattern="[0-9]{6,12}" data-offline-access-target="pin" disabled>
                        <label class="form-label" for="<?= h($deviceFormId) ?>-confirm">Repeat your PIN</label>
                        <input class="form-control mb-3" id="<?= h($deviceFormId) ?>-confirm" type="password"
                            inputmode="numeric" autocomplete="new-password" minlength="6" maxlength="12"
                            pattern="[0-9]{6,12}" aria-describedby="<?= h($deviceFormId) ?>-status"
                            data-offline-access-target="confirm" disabled>
                    </div>
                </fieldset>
                <p role="status" data-offline-access-target="setupProgress" hidden></p>
                <div class="alert alert-warning" data-offline-access-target="setupError" hidden>
                    <p class="mb-2" role="alert" tabindex="-1" data-offline-access-target="setupErrorMessage"></p>
                    <button type="button" class="btn btn-outline-primary" data-offline-access-target="pinFallback"
                        data-action="offline-access#usePin" data-trust-control hidden>
                        Use <?= h($shortSiteTitle) ?> PIN instead</button>
                </div>
                <div class="trusted-device-actions">
                    <button class="btn btn-primary" type="submit" data-offline-access-target="next"
                        data-trust-control>Continue</button>
                    <button class="btn btn-primary" type="button" data-offline-access-target="continue"
                        data-action="offline-access#continueSetup" data-trust-control hidden>Set up passkey</button>
                    <button class="btn btn-outline-secondary" type="button" data-offline-access-target="back"
                        data-action="offline-access#back" data-trust-control hidden>Back</button>
                    <button class="btn btn-link" type="button" data-action="offline-access#cancelSetup"
                        data-trust-control>Cancel setup</button>
                </div>
            </form>
        </div>
        <div data-offline-access-target="success" hidden>
            <p class="small fw-semibold mb-2">Step 3 of 3 · Complete</p>
            <h3 class="h4" tabindex="-1" data-offline-access-target="successHeading">
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i> You’re all set!
            </h3>
            <p class="fw-semibold" data-offline-access-target="successMethod"></p>
            <p>This device is now trusted. Next time, unlock <?= h($shortSiteTitle) ?> the same way,
                online or offline.</p>
            <p class="rounded border p-3" role="status" data-offline-access-target="readiness"></p>
            <p class="small">Logging out locks <?= h($shortSiteTitle) ?>. Your saved information stays on this device.
                You can remove it any time in Security.</p>
            <div class="trusted-device-actions">
                <button class="btn btn-primary" type="button" data-action="offline-access#done">Done</button>
                <button class="btn btn-outline-secondary" type="button"
                    data-offline-access-target="retry"
                    data-action="offline-access#retrySave" hidden>Try saving again</button>
            </div>
        </div>
        <form data-offline-access-target="unlock" data-action="submit->offline-access#unlockDevice" hidden>
            <label class="form-label" for="<?= h($deviceFormId) ?>-unlock">Device PIN</label>
            <input class="form-control mb-3" id="<?= h($deviceFormId) ?>-unlock" type="password" inputmode="numeric"
                autocomplete="current-password" maxlength="12" data-offline-access-target="unlockPin">
            <button class="btn btn-primary" type="submit" data-offline-access-target="unlockButton">
                Unlock <?= h($shortSiteTitle) ?></button>
            <button class="btn btn-link" type="button" data-action="offline-access#usePassword"
                data-offline-access-target="passwordSwitch" hidden>
                Use email and password
            </button>
        </form>
        <a class="btn btn-outline-primary py-2" href="/offline" data-turbo="false"
            data-offline-access-target="link" hidden>View saved information</a>
        <button type="button" class="btn btn-outline-secondary py-2" data-offline-access-target="forget"
            data-action="offline-access#forget" hidden>Stop trusting this device</button>
    </div>
</section>
