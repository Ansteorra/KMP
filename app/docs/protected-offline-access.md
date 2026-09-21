# Trusted devices and offline cards

KMP offers **Trust this personal device** after sign-in. Setup verifies the current
password, registers the passkey with the server, and checks a real assertion.
WebAuthn authentication and PRF decryption are separate capabilities:

| Protection | Online sign-in | Offline unlock |
| --- | --- | --- |
| Passkey with reproducible PRF | Passkey | Passkey authenticates and decrypts |
| Passkey without PRF, with offline PIN | Passkey; no PIN prompt | Passkey authentication, then PIN decryption |
| Passkey without PRF, online only | Passkey; no PIN prompt | Unavailable until an offline PIN is added |
| Passkey authentication unavailable | Device PIN | Device PIN |

The three-step wizard on the mobile card and in Security confirms the password,
checks the passkey and offers the appropriate protection, then reports completion.
Each WebAuthn prompt has its own button to preserve user activation. Capability
probes open no prompts and time out after two seconds. Browser PRF hints and old
PRF-failure hints never disable authentication. Platform availability does not
exclude password managers or external security keys; creation permits either.

A valid assertion without PRF preserves the passkey and explains the need for an
offline PIN. Users may save a 6–12 digit PIN now or choose **Continue online only**.
Online-only setup stores public credential metadata, without an encrypted private
payload, saved password, or decryption key. **Add offline PIN** in Security verifies
the password and existing passkey, then saves the PIN without creating another
passkey. Existing saved data and waiting RSVPs cannot be discarded by switching
to online-only setup. A canceled or invalid assertion does not unlock an existing
hybrid vault with PIN alone; PIN-only enrollment is offered when authentication
cannot be completed and the user chooses that fallback.

PRF protection still requires an assertion that successfully unwraps the same
key. Owner, epoch, lock and generation checks run before committing any record.
Prompts have a two-minute bound. Errors, next actions and completion receive focus;
status updates are announced. Opt-in diagnostics are described below.

The final screen confirms which method was saved and separately reports whether
offline information is ready, still saving, or needs retrying. Background status
updates cannot dismiss that screen; **Done** returns to the normal compact status.
Steps and completion receive focus, and saving/readiness updates are announced.
Security uses a full-screen dialog on small phones and hides its other tasks while
the device wizard is active, restoring them on Done or cancellation.

While online and signed in, both desktop and mobile pages hide the device panel
if its encrypted copy is locked. The server session permits normal online use;
this presentation rule does not decrypt the copy or change its protection.
Explicit device controls in Security remain available. Going offline restores
the unlock control when the local copy is locked.

The same unlock control is rendered on the normal login page and in the public
mobile fallback. Unlocking offline opens the existing mobile screens. Unlocking
online uses a server-issued challenge and a verified passkey signature for new
passkey registrations. No offline PIN is requested, and the encrypted offline
copy can remain locked. Security offers an explicit unlock to refresh that copy;
background work never prompts for the PIN. PIN-only and older local PRF records
continue using the decrypted password through the ordinary password login form
with fresh CSRF/form-protection tokens. Trusted login switches between two mutually exclusive views: device unlock and
email/password sign-in. Switching back clears password entries; losing connection
immediately returns to device unlock and hides email/password and online sign-in
links. Old quick PIN configurations are retired before the login form is selected. A browser without a
trusted copy explains that a connection is needed. Trusted login shows no intermediate card link. After local unlock, offline users
enter the mobile app directly; online users follow the normal server redirect,
including device routing and any validated return URL. Offline-data refresh runs
on the destination page, so it does not delay login. The saved password never
appears in page markup, plaintext persistent storage, or the service-worker cache.

**Log out** ends the server session and locks the local copy in every open tab;
it preserves the encrypted login, offline data, and waiting RSVPs. **Stop trusting
this device** explicitly removes them. New passkey devices must be online and
signed in to remove their server registration as well as their local copy. Password reset, credential revocation,
account changes, and impersonation still invalidate the copy when observed.

## Server and local authentication

`MemberPasskeys` stores public keys and credential IDs in the tenant database,
bound to the member's revocable authentication epoch. Install its core migration
before deploying this feature. `/offline/verify-login` issues a five-minute
registration challenge after password verification; `/offline/register-passkey`
consumes it. `/members/passkey-options` prepares a short-lived login challenge
before the click; `/members/passkey-login` verifies the assertion and uses the
normal tenant-bound session and redirect. POST requests retain CSRF checks.
The authentication endpoint is rate limited. Credential removal is self-service
through `/offline/remove-passkey`; impersonation cannot register, use or remove
passkeys. Password reset/revocation changes invalidate registered credentials.

The `lbuchs/webauthn` decoder validates none-attestation and COSE public keys.
The application validates exact origin, relying-party hash, ceremony type,
single-use challenge, user presence/verification, backup flags and signature.
Only ES256/P-256 and RS256/RSA keys of at least 2048 bits are accepted. Nonzero
signature counters must advance; counterless authenticators are supported.
Challenges are session- and tenant-bound. Localhost development origins are
allowed explicitly alongside HTTPS; exact origin checking remains mandatory.

Offline verification uses a random local challenge and WebCrypto signature
verification against the saved public key. A valid hybrid assertion authorizes
PIN entry for two minutes in memory, bound to the vault and lock generation.
Locking clears it. No network request or PRF output is needed for this assertion.
This requires the selected provider to make its passkey available while offline;
a browser capability hint cannot prove that provider behavior. Assertions and
registration responses sent to the server exclude PRF extension output.

## Passkey diagnostics

On a build containing `passkey-debug-service.js`, sign in and stay on the same
page throughout reproduction. Open Chrome DevTools → Console and run:

```javascript
window.KMP_passkeyDebug.start()
```

Return focus to the page and perform device setup normally. Choose the intended provider in the browser prompt. Starting diagnostics does
not change capability checks. While a prompt
is stuck, or after the error appears, copy the report from the Console:

```javascript
copy(window.KMP_passkeyDebug.report())
```

`copy()` is a Chrome DevTools helper. The report itself is a JSON string, also
available by evaluating `window.KMP_passkeyDebug.report()`. Share that report and
the provider/extension version manually; browser APIs do not identify which
password-manager extension handled a prompt. Entries also appear in the console
with the `[KMP passkey debug]` prefix. No credential prompts are triggered by
starting or exporting diagnostics.

The report records elapsed milliseconds, browser user agent, secure-context/API
availability, online state, page visibility/focus and user activation, capability
checks, cached rejection/recheck, credential prompt start/return/error, PRF
presence/enabled flag/output length, credential-match boolean, recognized
attachment/transports, and encryption/unwrap/commit stages. In particular:

| Report evidence | Meaning |
| --- | --- |
| `support-cached-failure` | A previous incompatibility hint suppressed setup. |
| `create-start` or `get-start` without a matching return/error | The credential request has not settled. |
| `create-prf-disabled` | Creation explicitly reported PRF disabled. |
| `get-prf-rejected` | Inspect `get-prf` for missing/invalid-length PRF output or a credential mismatch. |
| `verify-error` before `verify-key-opened` | Reopening the encrypted data key failed; PRF output was present but did not successfully unwrap it. |
| `verify-committed` and `setup-complete` | The key and payload reopened and device setup completed. |

Error names are allowlisted. Error messages/stacks, URLs, account identifiers,
credential IDs, passwords, PINs, challenges, PRF inputs/outputs, ciphertext and
keys are excluded. Diagnostics send nothing to the server and use no persistent
storage. Only the latest 200 entries are retained in memory; `dropped` counts
older entries removed from the report. Reload/navigation clears the recorder.
`window.KMP_passkeyDebug.stop()` stops recording and keeps the report;
`window.KMP_passkeyDebug.clear()` stops recording and discards it. Starting again
begins a fresh report. Clearing the report does not erase existing console lines.

These are encryption-capability checks performed during online enrollment.
Diagnostics do not disconnect the browser or prove that a provider works offline.

## Mobile continuity

The shared device panel owns connection, saving, retry, and modern unlock
messages. The public mobile recovery controller does not repeat these in a
second live region; it only reports recovery states such as expired data,
legacy unlock preparation, and operation errors. Missing trust uses the sign-in
card. Background sync progress never replaces a recovery error or expiry notice.

The card, My RSVPs, and calendar keep their normal URLs when a navigation falls
back to saved information. Online and offline use the same `mobile_app` layout, mobile page templates,
Menu controller, and card/RSVP/calendar renderers. The public variants contain
the normal empty mobile UI, without identity, private forms, or CSRF tokens.
The browser fetch proxy feeds approved saved JSON into those existing controllers
when disconnected, when the request fails or times out, or in a public variant.
`offline-response-service` reconstructs the existing API shapes from approved
encrypted fields; `offline-vault-controller` only handles recovery and device trust.
There is no separate offline card, event list, or RSVP renderer. Connection status
is announced without blocking supported offline pages.

After a fresh authenticated snapshot succeeds, the shell returns automatically
to the requested server page, restoring its online controls. A short retry guard
prevents loops if that page alone is failing; a successful normal page clears the
guard. If sign-in has expired, saved information remains open until the user signs
in. Device removal is a secondary control under **This device**.
Shared mobile CSS must remain free of session data because the shell is public.
Mobile fonts and Bootstrap icons are bundled through Vite and cached with the
shell; they must not depend on external stylesheet or font services.

## Storage and trust boundary

The vault stores AES-GCM ciphertext in `kmp-offline-vault` IndexedDB. A random data
key encrypts the approved data and saved login. A PIN-derived wrapping key
(PBKDF2-SHA256, 600,000 iterations, random salt) or a passkey PRF-derived wrapping
key (HKDF-SHA256) encrypts the data key. Protected records use
`wrapper.method = trusted` with `unlockMethod = pin|device|passkey-pin`; unlike older trusted
records, they contain no persistently stored unprotected wrapping CryptoKey.

An unlocked tab keeps its random wrapping key in sessionStorage for navigation,
with a twelve-hour limit and a cross-tab lock marker. The PIN and password are not
stored there. Logout removes this tab session and invalidates other tabs' saved
unlock sessions. A new tab without an unlock session requires the PIN or passkey.
Client PIN retry delays limit ordinary UI guessing; a copied database remains
subject to offline PIN guessing. Same-origin script execution can read an unlocked
session, so these protections do not defend against same-origin XSS or a
compromised device. Browser session restoration may preserve an unlocked tab
within the twelve-hour limit; explicit logout always locks it.

Associated data binds the encrypted key and payload to origin, opaque actor,
security epoch, format version, and validity times. New passkey records also bind
the authentication public key, credential ID and unlock method to both encrypted
objects; changing or removing the passkey requirement invalidates decryption. Account or observed epoch
changes clear the entire record. Neither the stored key nor the trust choice
provides server authorization. The verified password is stored only inside the encrypted payload; server session
cookies are never copied into the vault. Encryption or storage failures are
reported without a plaintext fallback.

The core card DTO includes displayed names, branch, membership number and expiry,
background-check expiry, and a bounded thumbnail. Plugin card JSON cells may opt
in with `offline_sections`: each section has `title` and `items`, with `label` and
optional ISO `expires_on`. Unknown plugin fields and member `additional_info` are
excluded. Activities provides authorization summaries and individual expiry dates.

## Automatic operation and lifetime

The shared application observer restores an already unlocked tab on ordinary
pages and in the public offline shell. It never prompts for a PIN or passkey in
the background, and never opens a locked protected record without user action. It updates while KMP is open, after reconnecting, on
returning to the app, and during foreground retry intervals. Successful updates
are throttled for five minutes within a page lifetime. Forced updates for reconnects
and newly queued RSVPs bypass that throttle, including when they wait for an
in-progress update to finish. A fresh signed-in context
is checked before synchronizing. An unlocked protected copy can use its saved
password to restore an expired server session before syncing; failed login does
not grant server access and waiting work remains queued. Pending RSVPs send before a complete card, RSVP,
and current/next-month calendar snapshot replaces the previous snapshot.

Successful ordinary JSON GETs to the current-member card, My RSVPs, and unfiltered
mobile calendar endpoints also update trusted storage as the responses arrive.
The fetch wrapper preserves the caller's response and copies only approved display
fields, after checking the response's actor/epoch against the trust present when
the request began. Untrusted browsers, other endpoints, POSTs, scoped queries,
redirects, unbound responses, and errors never enter this capture path. Proactive
snapshot requests bypass capture and commit as a complete verified snapshot.
Capture makes no extra network requests; bounded photo downloads remain part of
proactive preparation, while ordinary JSON updates retain the prepared thumbnail.

Per-resource request times prevent a slow earlier request or background snapshot
from replacing newer foreground data. Captures preserve pending requests and do
not extend the seven-day validity of unrelated saved information. Visited calendar
months are bounded to six; complete refreshes discard older extra months rather
than silently renewing them. Storage failures leave online responses usable,
report that the latest copy was not saved, and permit the next automatic retry.
Readiness means the last successful complete preparation, not guaranteed knowledge
of server changes while KMP is closed or disconnected.

A failed download leaves the prior snapshot intact. A waiting RSVP remains queued
until the server accepts it. Offline RSVP creation asks who may see the member’s name: Kingdom, Hosting
Group, and Nobility/Crown. All choices start off. The three explicit booleans
travel with the encrypted queue entry and are replayed unchanged after sign-in.
Old entries without choices remain private. Kingdom sharing requires a saved
eligibility flag and the server rechecks its existing minor restriction when saving.
Notes, later sharing changes, and cancellation of confirmed attendance use the online UI. Waiting requests can be removed before synchronization. Automatic sending
requires KMP to be open, connected, and signed in; closed-app background execution
is not promised.

Snapshots are usable for seven days after server verification. Individual expiry
dates remain visible and expired authorizations are marked. When the snapshot
expires, KMP withholds the card and new offline RSVP choices, but retains device
trust and unsent requests. Signing in and completing an update restores display
without asking the user to trust the device again. Offline revocation cannot
arrive instantly, and client clock checks do not defend against a modified device.

An ordinary sign-in timeout preserves trusted data and requests. Logout actions
synchronously lock the copy before navigation. Server logout also sends a
host-only `kmp_offline_lock=1` cookie so direct navigation locks the copy across a
redirect. Explicit revocation and password resets use the separate
`kmp_offline_clear=1` signal and remove the copy. The browser consumes these
nonsecret signals, and a durable local lock marker prevents another tab from
restoring an earlier unlock session. Tabs remove private DOM on lock or removal.

## Existing protected copies

Older passphrase/PRF vaults retain their original unlock method. Older automatically
opening trusted copies can be upgraded while signed in by adding a PIN or passkey.
Upgrade preserves the snapshot age and queued RSVP choices, and never silently
replaces the old wrapper before the new protection succeeds. An old trusted copy
without a PIN/passkey cannot reopen after explicit logout until the user signs in
online and finishes protection setup. New setup always requires PIN or passkey.

## Synchronization and public shell

Fresh `/offline/context` responses provide opaque actor/epoch and CSRF. Each
queued POST to `GatheringAttendances::mobileRsvp` checks the originating actor and
epoch against the authenticated server identity and applies server authorization.
A unique `offline_request_id` makes retries idempotent. Existing RSVPs are preserved.
Snapshot responses carry their producing actor/epoch; mismatches invalidate local
storage. Authentication failures preserve trusted queues unless the server sends
an explicit invalidation signal. Transient errors never become successful syncs.

The service worker caches three public variants: `/offline` (card),
`/offline?page=rsvps`, and `/offline?page=calendar`, plus build-controlled public
assets from `/offline/assets`. Each variant renders its normal mobile template
through the shared layout. Navigation chooses the variant for the requested route. It never caches personalized HTML, JSON, photos, identity, or
CSRF tokens. Card, RSVP, calendar, offline, and login navigations use the public
shell on network failure, a four-second timeout, or server error, retaining the
requested URL. An already unlocked tab opens its saved data there automatically; a locked device
shows the same PIN/passkey unlock control as the online login page. All required shell assets
must be present before readiness is shown. The previous public shell remains
available if a replacement download fails. Browser persistence is requested after
trusting; browser storage cleanup can still remove local data.

Startup purges known legacy plaintext caches and upgrades `kmp-rsvp-cache` and
`kmp-offline-queue` to empty version-2 tombstones. Ownerless legacy rows cannot
safely be assigned to the next signed-in member. Encrypted owner-bound vaults are
preserved for recovery or trust conversion. Devices that never reconnect cannot
receive cleanup remotely.

## Verification

Run JavaScript vault, data, RSVP, worker, controller, and runtime tests, plus the
authentication/security PHPUnit tests. Real browser checks are:

```sh
node tests/ui/support/offline-vault-browser-check.mjs
node tests/ui/support/offline-app-browser-check.cjs
node tests/ui/support/trusted-passkey-browser-check.cjs
```

The first uses synthetic data and real IndexedDB/WebCrypto, including legacy
recovery and persisted trusted keys. The second uses seeded local tenants and
creates/removes a synthetic member and gathering with a generated test password without resetting the database. It checks
explicit trust on an ordinary page, automatic offline reopening, encrypted queues
across reload and session timeout, reconnect synchronization, duplicate retries,
owner mismatch denial, logout locking and offline PIN unlock, explicit removal,
tenant isolation, shared-device opt-out,
identical normal/offline card markup, saved JSON through the existing controllers,
mobile routes and Menu keyboard behavior, automatic return to online controls,
mobile reflow, and Security dialog keyboard behavior. The third checks WebAuthn
PRF with Chromium’s built-in and external virtual authenticators, offline passkey
unlock after logout, server passkey login without PIN, the hybrid passkey-then-PIN
flow, online-only setup followed by adding a PIN, and PIN-only setup when WebAuthn
is unavailable. Physical Safari/iOS and
Android restart, airplane-mode, and storage-pressure behavior remain part of
device acceptance.

## Existing quick PIN migration

The login and shared device-setup controls detect `kmp.quickLogin.config` on this
origin. Before removing that legacy PIN configuration, they write a versioned
`kmp.quickLogin.migration.v1` reminder and preserve the remembered email. The
normal login page offers password sign-in and explains that the old PIN no longer
works. Offline users see instructions to reconnect; no password form is offered
offline. An app that has not yet downloaded this update cannot show the new notice.

After sign-in, the shared mobile and desktop device panel offers **Set up new PIN
or passkey**, using the existing personal-device/password confirmation wizard.
Setup remains deliberate. **Not now** dismisses the panel for the current session;
Security still offers setup. Failed password attempts, reloads, canceled setup,
and ordinary logout do not remove the reminder. Only successful protected-device
setup (or detection of an already protected device) clears it. The reminder is
browser-local, contains no credentials, grants no access, and does not alter vault
data, encryption, queued RSVPs, or server authentication rules. Browsers with only
a remembered email or device ID are not classified as legacy PIN users.

## Configured branding

User-facing device, security, login, recovery, and sync messages use
`KMP.ShortSiteTitle`. The desktop/login and mobile layouts expose this public
setting through the escaped `kmp-short-site-title` meta tag, including cached
public mobile shells. JavaScript reads it through `shortSiteTitle()` and writes
messages as text. Passkey display names use the same setting. Older shells
without this metadata use the neutral phrase “this app”. A saved offline shell
retains the title from its last online refresh. Legacy branding image paths must
resolve to files inside `webroot/img`; traversal and symlinks outside that directory
are rejected before reading a file.

Internal names, storage keys, protocol headers, and cryptographic domain strings
remain stable; changing a display title does not invalidate saved device keys.
