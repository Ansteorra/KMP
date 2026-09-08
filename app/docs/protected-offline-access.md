# Protected offline cards and RSVPs

Members enable offline access from the mobile screen's **Protected offline cards
and RSVPs** link. The public `/offline` screen also opens when an auth card,
My RSVPs or mobile calendar navigation fails without a connection.

Enable and refresh while signed in, then test unlocking in airplane mode before
travelling. The browser saves a card, the member's RSVPs, and the current and next
calendar month. Offline RSVP creation queues a private attendance only; notes,
sharing changes and cancellations of confirmed attendance use the online UI.
Pending requests can be removed before synchronization.

## Unlocking and storage

Device unlock uses a separate WebAuthn credential with required user verification
and the PRF extension. The device may request its PIN or biometrics. Enrollment
must obtain the PRF result twice and successfully unwrap/decrypt data; a browser's
passkey-support indicator alone is insufficient. Credential providers and devices
vary, and some passkeys synchronize. This feature does not enroll a website login
credential or change password authentication.

Browsers without usable PRF can use a separate offline passphrase of 15–128
characters. A short application PIN cannot protect the offline vault. Each vault
has one selected unlock method. Losing that method requires signing in online and
replacing local data, including any unsynchronized requests.

The vault stores AES-GCM ciphertext in `kmp-offline-vault` IndexedDB. A random data
key is encrypted with either a PRF/HKDF-derived key or a PBKDF2-HMAC-SHA256 key
(600,000 iterations and a random salt). Keys and PRF results stay in memory; they
must never enter IndexedDB, localStorage, telemetry or credential JSON sent to a
server. Associated data binds encrypted keys and records to origin, opaque actor,
security epoch, format version and validity times.

The core card DTO contains only displayed names, branch, membership number and
expiry, background-check expiry, and an optional bounded thumbnail. Plugins may
opt into offline display through `offline_sections` on their registered card JSON
cell. Each section has `title` and `items`; an item has `label` and optional ISO
`expires_on`. Unknown plugin fields and member `additional_info` are excluded.
Activities supplies authorization summaries with individual expiry dates.

## Lifetime and synchronization

Online refresh extends validity to seven days from server time. Individual dates
remain visible and expired authorizations are marked expired. Offline status is a
historical snapshot: revocation cannot reach a disconnected browser immediately.
Client expiry checks also cannot provide trusted time on a deliberately modified
device. An unlocked/compromised device or active same-origin XSS remains outside
at-rest encryption's protection.

The page locks and clears private DOM when hidden, on navigation, after five
minutes without interaction, or on a cross-tab lock signal. Closing the browser
retains encrypted data for later unlocking. Explicit logout, account changes,
impersonation and a newly observed security epoch invalidate the local vault.
Offline enrollment and synchronization are unavailable during impersonation.

Synchronization is foreground-only while unlocked. A fresh `/offline/context`
response supplies the current opaque actor/epoch and CSRF token. Every queued POST
to `GatheringAttendances::mobileRsvp` compares the originating actor and epoch to
the current server identity. It never treats client fields as authorization.
A unique `offline_request_id` makes retried submissions idempotent; existing RSVPs
are preserved. Failed/authentication responses leave pending requests unconsumed.
Each snapshot response, including the photo, carries its producing actor/epoch;
the client rejects mismatches even when the account changes back during refresh.

## Service worker and upgrade

`sw.js` caches only the nonpersonalized shell and build-controlled Vite assets
listed by `/offline/assets`. These public responses contain no identity, CSRF,
flash messages or private navigation. There is no arbitrary URL caching, private
response cache, or `ignoreVary` fallback. Enrollment verifies that public assets
were saved before claiming offline readiness.

Version 3 activates its security cleanup even if an asset download fails. It
removes known legacy KMP caches without copying their entries. Browser startup
upgrades `kmp-rsvp-cache` and `kmp-offline-queue` to empty version-2 tombstones;
ownerless plaintext rows and unsynced actions cannot safely be migrated to the
next account. Old tabs with open database connections must close to complete the
upgrade. The UI explains that old saved data was removed. Devices that never
reconnect cannot receive cleanup remotely.

## Verification

Run the targeted vault/data/RSVP/worker/controller Jest tests and the pure
`OfflineIdentityTest` and `RevokeSessionsTest` PHPUnit tests. The standalone browser
check avoids application databases and uses synthetic data:

```sh
node tests/ui/support/offline-vault-browser-check.mjs
```

It exercises real IndexedDB, PRF with a virtual authenticator, reload/unlock with
the browser offline, passphrase fallback, account clearing, and legacy database
cleanup.

With the normal local migrations applied to both seeded tenants and the platform,
run the application check while holding the shared browser/database test lane:

```sh
node tests/ui/support/offline-app-browser-check.cjs
```

This check uses `kmp.localhost:8080` and `kmp2.localhost:8080`, creates one synthetic
gathering and removes its attendance and gathering afterward; it never resets the
database. It verifies signed-in snapshot saving, public cache contents, actual
offline navigation and unlock, queue survival across reload, cross-tab locking,
private RSVP sync, duplicate retries, wrong-owner rejection, logout clearing,
actual session-cookie replay denial in the other tenant, and mobile reflow.

Release acceptance still needs physical target-device PIN/biometric and credential
provider behavior in airplane mode. Virtual authenticators cannot attest to these
hardware and provider combinations.
