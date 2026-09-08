# Protected offline cards and RSVPs

Members enable offline access from the mobile screen's **Protected offline cards
and RSVPs** link. The public `/offline` screen also opens when an auth card,
login, My RSVPs or mobile calendar navigation fails without a connection.

Enable and refresh while signed in, then test unlocking in airplane mode before
travelling. The browser saves a card, the member's RSVPs, and the current and next
calendar month. Offline RSVP creation queues a private attendance only; notes,
sharing changes and cancellations of confirmed attendance use the online UI.
Pending requests can be removed before synchronization.

## Unlocking and storage

Website login uses native passkeys (managed at `/passkeys`) or the existing
password/recovery flow. App quick-login PINs and the old browser PIN overlay are
retired. Enrollment requires password reauthentication; login assertions require
user verification, an exact tenant origin, and a single-use, session-bound server
challenge. Password changes, recovery, deactivation and revoke-all invalidate
passkeys through the member authentication version. Members can remove individual
passkeys from their account. A login passkey does not automatically save offline data.

Device unlock uses a separate WebAuthn credential with required user verification
and the PRF extension. Enrollment prepares the public shell first, then each OS
prompt starts from a separate button press. Creation can supply the initial PRF
result; otherwise another explicit step obtains it. A final device assertion must
reproduce the wrapping key and successfully decrypt before the vault is saved.
Every OS prompt has a hard timeout and setup can be cancelled. Incomplete setup
stays in memory. A browser's passkey-support indicator alone is insufficient.
Credential providers and devices vary, and some passkeys synchronize.

The explicitly selected offline fallback accepts **6–8 ASCII numeric digits**,
including leading zeros. This is an offline encryption PIN, never a website login
credential or a browser-stored fast PIN verifier. The project accepts its lower
brute-force resistance compared with a long passphrase; PBKDF2 slows guesses but
cannot provide server-enforced attempt limits to an attacker holding the database.
The OS device-unlock option remains preferable when supported. Existing long
passphrase vaults still unlock; new fallback enrollment uses a confirmed numeric PIN.
Each vault has one selected unlock method. Losing that method requires signing in
online and replacing local data, including unsynchronized requests.

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
the browser offline, numeric PIN fallback, account clearing, and legacy database
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

## Passkey rollout and acceptance

Apply `20260908120000_AddMemberPasskeys` to each tenant before routing this revision
to it. It creates credential/challenge tables and deletes legacy server PIN hashes;
login and offline startup remove the old local PIN hash/device configuration.
Old PINs cannot be converted to passkeys. Sign in with a password, open **Manage
passkeys**, confirm the password, and create a new login passkey. The migration
refuses rollback: restoring old PIN authentication is not an acceptable rollback.
The application schema gate keeps unmigrated tenants in maintenance mode.

WebAuthn uses the resolved tenant's exact host as RP ID and exact HTTPS origin;
TLS-terminating proxies must preserve the configured trusted-proxy scheme handling.
Passkeys for one host do not sign in to another tenant or tenant alias. Challenge
rows expire after two minutes and are consumed atomically before verification.
Credential counter updates use compare-and-swap, including zero-counter synced keys.
The server stores public keys and opaque user handles, never biometric data,
device PINs, private keys or offline PRF output. The platform-admin MFA flow is unchanged.

`tests/ui/support/passkey-browser-check.cjs` exercises native registration, login,
and credential removal against the local synthetic tenant through a local HTTPS
proxy (`PASSKEY_TEST_ORIGIN`, default `https://kmp.localhost:9443`). It uses a virtual
authenticator and removes its test credential afterward. It does not reset databases.

Physical iOS 26 acceptance remains required in both Safari and the Home Screen app:
create a login passkey, sign out and sign in with it; then separately enable offline
access, complete every device check, and wait for **Offline data saved**. Reopen the
same browser/app in airplane mode and unlock. If the provider cannot reproduce PRF
offline, reconnect, remove the offline copy and explicitly enroll an offline PIN.
Also verify cancellation, device prompt timeout, incorrect PIN, seven-day expiry,
and switching accounts with pending RSVPs. Safari and a Home Screen app may have
separate browser storage; enroll and test the exact context that will be used.
