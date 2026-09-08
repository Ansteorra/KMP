# Security and PII remediation register

Remediation register for the authorized 2026-09-05 review and security PR. Code verification
and production rollout are separate states; this review is not certification or evidence of a breach.

## Baseline and handling

- Reviewed commit: `dcdd85a8a3e43ab2d7dbecc8d283bdc6bf9cfc2f`.
- Branch: `codex/security-pii-audit`, based on `codex/bestowal-cancellation-workflow`.
- Context: nonprofit supporting approximately 2,000 SCA users; protecting member PII is a
  primary objective. The owner confirmed production uses Azure Container Apps.
- Azure control-plane inspection identified `release-v1.5.9`; local `v1.5.9` is commit
  `6ef29da504c7f0514d43f7d873278cd459ce7ead`. The core authentication, PII/browser, upload,
  logging and public-association findings below are also present in that release source.
  This comparison does not attest the bytes in a running replica or establish prior abuse.
- Local tests used repository-defined synthetic accounts at `kmp.localhost:8080`,
  `kmp2.localhost:8080`, and `platform.kmp.localhost:8080`. Requests were sequential and
  bounded. No database resets, destructive writes/restores, brute force or load tests ran.
- Production inspection was read-only Azure metadata: no production application probes,
  database queries, log contents, blob contents, secret values, restarts or configuration changes.
- Detailed sanitized evidence and synthetic proofs are in the ignored root directory
  `security-reports/2026-09-05/`. Do not force-add this evidence, cloud inventories or raw
  scanner output to a public repository. Temporary synthetic session state and browser cache
  were removed after testing. This file is the durable remediation register, not a raw report.

Source line numbers below refer to the reviewed commit unless explicitly labeled otherwise.
Use the existing [security regression checklist](../../docs/deployment/security-regression-checklist.md)
and [assessment scope checklist](../../docs/deployment/penetration-test-scope-checklist.md).

## Triage

Application fixes, deployment controls and the targeted runtime upgrades are implemented. The full
repository verification bundle passes; residual native advisories and production acceptance remain
open as described below. Production remains unchanged. Assign rollout owners and dates before promotion; responsible areas below
are not commitments by particular volunteers. P0/Critical requires immediate containment assessment
and blocks reliance on the affected boundary. P1/High belongs in the next security patch; any
deferral needs a named approver, mitigation and expiry. Schedule P2/Medium work explicitly after
containment. P3/Low is planned hardening. Scanner severity is distinct from application priority.

A local confirmation proves the code defect, not production exploitation. Conditions such as
multiple tenants, a compromised session, a shared browser, enabled telemetry or legacy hosting
are part of each finding. Determine active tenant count before concluding that cross-tenant
exposure exists today; Azure confirms tenancy is enabled, not how many tenant databases are active.

| ID | Priority | Remediation | Responsible area | Evidence | Status |
| --- | --- | --- | --- | --- | --- |
| SEC-001 | P0 / Critical | Bind member sessions to their tenant | Authentication / tenancy | Local HTTP confirmed | Awaiting rollout |
| SEC-002 | P1 / High | Revoke sessions and devices on security changes | Authentication | Source + isolated PHP | Awaiting rollout |
| SEC-003 | P1 / High | Apply PII permissions to API serialization | API / policies | Source + isolated PHP | Awaiting rollout |
| SEC-004 | P1 / High | Purge private service-worker cache entries | Frontend / authentication | Browser persistence + isolated JS | Browser verified; physical-device test and rollout pending |
| SEC-005 | P1 / High | Respect attendance consent in award helpers | Awards / gatherings | Complete source trace | Awaiting rollout |
| SEC-006 | P1 / High | Confine platform administration to reserved origins | Platform / middleware | Local HTTP + source | Awaiting rollout |
| SEC-007 | P1 / High | Remove PII and document bytes from telemetry | Telemetry / Waivers | Source + isolated PHP + Azure flags | Awaiting rollout |
| SEC-008 | P1 / High | Exclude runtime secrets and PII from image builds | Docker / release | Synthetic Docker build | Awaiting rollout |
| SEC-009 | P2 / Medium | Narrow PostgreSQL access and runtime privileges | Azure / database | Live network metadata; grants unverified | Verified in code; rollout required |
| SEC-010 | P2 / Medium | Prevent recovery enumeration and reset abuse | Authentication | Complete source trace | Awaiting rollout |
| SEC-011 | P2 / Medium | Remove URL credentials and scrub sensitive routes | API / logging | Source + Azure telemetry flags | Awaiting rollout |
| SEC-012 | P2 / Medium | Bind offline caches and queued actions to their owner | Frontend / gatherings | Source + isolated JS | Browser verified; physical-device test and rollout pending |
| SEC-013 | P2 / Medium | Reject invalid single-PDF uploads | Waivers / documents | Isolated PHP confirmed | Awaiting rollout |
| SEC-014 | P2 / Medium | Resolve npm advisories and embedded parser copies | Frontend / dependencies | Registry scans + reachability review | Verified in code; CI activation pending |
| SEC-015 | P2 / Medium | Make security verification fail closed | CI / dependencies | Source + failing-tool simulation | Verified in code; CI activation pending |
| SEC-016 | P2 / Medium | Harden platform privilege transitions | Platform authentication | Source; conditional prerequisites | Awaiting rollout |
| SEC-017 | P3 / Low | Pin privileged build and installation inputs | CI / release | Supply-chain hardening | Verified in code; CI activation pending |
| SEC-018 | P1 / High | Authenticate or retire the legacy updater | Legacy VPC / installer | Source; outside stated production | Retired; operator action required |
| SEC-019 | P2 / Medium | Encrypt and restrict legacy SQL backups | Legacy VPC | Synthetic shell proof; outside Azure path | Retired; operator action required |
| SEC-020 | P2 / Medium | Update or retire legacy Go binaries | Legacy installer | Advisory matches; call graph incomplete | Retired; operator action required |
| SEC-021 | P1 / High | Constrain public recommendation association writes | Awards / workflows | Source + real marshaller synthetic proof | Awaiting rollout |
| SEC-022 | P2 / Medium | Deploy immutable image references | Azure / release | Live Azure/registry metadata | Awaiting rollout |
| SEC-023 | P2 / Medium | Align database recovery horizon with approved policy | Azure / operations | Live configuration drift | Awaiting rollout |
| SEC-024 | P2 / Medium | Verify effective storage identity and blast radius | Azure / documents | Live authority; auth-mode drift | Verified in code; rollout required |
| SEC-025 | P1 / High | Authorize contact lookup against the target member | Gatherings / policies | Complete source trace | Awaiting rollout |
| SEC-026 | P1 / High | Rebuild patched PHP/GD and native runtime | Docker / runtime | Final-image scan + native smoke tests | PHP/GD verified; residual native triage and rollout pending |

## Recommended remediation sequence

1. Assess active tenant exposure and fix SEC-001; close public associated writes (SEC-021).
2. Rebuild the affected runtime (SEC-026) and patch authorization/consent/revocation leaks
   (SEC-002/003/005/025), browser persistence (SEC-004) and telemetry (SEC-007/011).
3. Enforce platform-origin isolation and immutable, clean builds (SEC-006/008/022), then address
   cloud scope/drift and remaining medium items with named owners and dates.
4. Keep legacy-only findings separate from this Azure rollout; communicate supported fixes or
   retirement requirements to other operators through a coordinated disclosure process.

This sequence is a proposal for review, not authorization to bypass release gates or change
production. Every fix needs the acceptance checks below and an exact deployed-candidate retest.

## Implemented controls and rollout evidence

- **SEC-001/002/006/010/016:** tenant-bound identity envelopes, per-request identity and
  permission reload, authentication epochs, session/PIN revocation, platform-host rejection,
  session renewal, atomic TOTP consumption and database-backed recovery limits. See
  [authentication security](authentication-security.md). HTTP lifecycle and retained-session
  role-revocation tests pass; a 16-process counter race admits exactly the configured limit.
- **SEC-003/005/021/025:** per-member PII serialization and search scopes, redacted mixed-scope
  exports, target contact authorization, consent-aware attendance policy, and allowlisted
  public nomination fields and existing gathering links. Four real HTTP privacy cases pass,
  including header-authenticated API requests and contact/attendance denials. See
  [privacy boundaries](privacy-boundaries.md).
- **SEC-004/012:** public-only worker caching and a separate encrypted, owner-bound offline
  vault with PRF unlock or an explicitly selected strong passphrase. Legacy plaintext stores
  are purged, not imported. Synthetic Chromium covers ciphertext, reopen/lock, unlock failure,
  passphrase and virtual PRF, offline access, and owner changes. Two real local-app browser runs
  also prove cross-tab locking, encrypted queue survival across offline reload, private RSVP
  synchronization, duplicate protection, logout cleanup and copied-cookie cross-tenant denial. See
  [protected offline access](protected-offline-access.md). Physical phone/OS support in
  airplane mode still requires manual acceptance; disconnected devices cannot learn revocation.
- **SEC-007/011/013:** header-only API authentication, sanitized file/telemetry/audit targets,
  structured diagnostic context, and bounded PDF parsing in a separate PHP process. Every PDF
  must have pages before storage activation. Tests cover both telemetry transports, local
  logs, invalid single/mixed PDFs and preserved valid page counts. Historical logs and backups
  require separate access review and cleanup; the code change does not erase old data.
- **SEC-008/014/015/017/022/026:** clean production source copies, patched dependency locks,
  removal of guifier, pinned build inputs, PHP 8.4.25 on Debian Trixie with refreshed native
  packages and rebuilt extensions, immutable web/job image payloads, fail-closed scans and
  static analysis, and CycloneDX SBOM generation. Composer, both npm locks and the Ruby
  documentation lock pass current advisory gates. The finished arm64 image passes 40 native
  assertions covering required extensions, GD formats, PostgreSQL clients and PDF processing.
  All 33 inspected layers (24,405 files) exclude local configuration, private runtime files
  and application development dependencies. The final scan has zero fixable High/Critical
  findings but retains 30 native advisory IDs (121 package rows), including a libxml2 issue,
  for explicit release triage in restricted reports. This is not a vulnerability-free image.
  The shared base also builds successfully; CI must independently build and scan amd64.
  Published candidates and scheduled checks refresh native layers instead of reusing stale APT caches.
- **SEC-009/023/024:** see the [Azure security rollout](../../deploy/azure/security-rollout.md)
  for separated runtime/administrative credentials and jobs, idempotent PostgreSQL grants,
  exact firewall planning and drift checks, managed storage identity, and 35-day retention.
  Disposable PostgreSQL tests pass 56 assertions using the finished image's packaged source,
  proving allowed DML, denied DDL and denied cross-database connections; storage contracts cover
  container-scoped grants, restricted archives and administrative provisioning.
  Effective Azure permissions/firewall/backend/retention remain unverified until controlled rollout.
- **SEC-018/019/020:** executable legacy installer/updater/backup distribution and publishing
  workflows are removed. Existing installations are unaffected until operators follow the
  [retirement instructions](../../installer/README.md); historical plaintext backups remain
  an explicit operator responsibility.

This PR is initially based on `codex/bestowal-cancellation-workflow`; retarget it to `main`
after PR #722 merges. Deployment requires application and platform migrations, one-time
login and device re-enrollment, and a fresh offline enrollment. Do not roll back to the
unbound-session or plaintext-cache code after cutover; preserve additive migrations and use
an audited forward fix. Production promotion must use the exact POC-verified image digest
and the existing approval gates. Never deploy a locally built review tag directly.

The full local `bash bin/verify.sh` run passes all nine checks: 3,398 PHP tests with
14,635 assertions (seven pre-existing skips), 1,999 Jest tests, seed/skip-budget contracts,
Markdown/JSDoc integrity, Vite, changed-file PHPCS, Azure runtime contracts and configured
PHPStan. The host run uses the existing local container for PHP and the build, retaining
host Git metadata for documentation and changed-file enumeration. Existing framework
deprecation warnings are not new security failures. The dedicated browser checks are
serial and use synthetic fixtures, which are cleaned up afterward. The curated Playwright
journey lane also passes all 35 scenarios with one worker and no database reset, covering
public nominations, registration, gatherings, approvals, officer/warrant workflows and tenancy.

Private networking, historical-data cleanup, real-device acceptance, residual native advisory
triage, effective production role checks and deployed-candidate validation remain outstanding. They are not silently
closed by passing unit tests or by merging the PR.

## Findings and acceptance criteria

### SEC-001 — Bind member sessions to their tenant

**P0 / Critical; Awaiting rollout; Authentication / tenancy.** Evidence: Local HTTP confirmed.

`app/src/Application.php:1023` loads stock session authentication with shared `Auth` state and
no tenant binding. `TenantResolutionMiddleware.php:67–109` selects the destination database by
host. Local A session on A returned 200/A marker; B without a session returned 302/login;
replaying A's own session on B returned 200/B marker without a B login. A multi-tenant deployment
sharing session storage is required. Numeric-ID self-management and superuser grants increase
impact. Host-only cookies do not prevent deliberate Cookie-header replay.

**Remediation and acceptance:** Bind authentication, impersonation and pending-login state to immutable resolved tenant ID;
reject absent/mismatched bindings before using it, and reload the identity in that tenant.
Invalidate legacy unbound sessions at deployment. Test ordinary/admin replay with overlapping
IDs across GET, POST, Turbo, profiles, uploads and impersonation; deny all cross-tenant reuse
while preserving intended same-tenant aliases. Separate logins in two tenants are insufficient.

### SEC-002 — Revoke sessions and devices on security changes

**P1 / High; Awaiting rollout; Authentication.** Evidence: Source + isolated PHP.

`MemberAuthenticationService.php:169–176` and `MembersController.php:1941–1964` update passwords
without a checked session version or device revocation. Stock session authentication accepts
serialized identities without current status checks. Isolated PHP confirmed a deactivated
identity is accepted and serialized grants survive; HTTP role-removal behavior was not tested.
Existing quick-login PIN/device credentials
can issue new sessions after password recovery. This requires a previously held session/device;
30-minute idle timeout and new-login lockouts do not provide immediate revocation.

**Remediation and acceptance:** Check active account/authentication version on every request; invalidate old sessions/devices
on recovery, deactivation and security reset. Define routine password-change policy and revoke-all
behavior. Refresh serialized permission state. Test two sessions plus enrolled device through
password reset/change, deactivation, reactivation, and role removal; old credentials/grants must fail.

### SEC-003 — Apply PII permissions to API serialization

**P1 / High; Awaiting rollout; API / policies.** Evidence: Source + isolated PHP.

`app/src/Controller/Api/V1/MembersController.php:82–98` checks only `view`; `120–129` emits legal
names, email and membership fields. `MemberPolicy.php:33–58` defines `viewPii` separately.
An isolated principal had view=true/PII=false while the formatter included PII. Valid API token
and record-view permission are prerequisites; this is field over-disclosure, not anonymous access.

**Remediation and acceptance:** Use explicit per-entity field allowlists governed by `viewPii` consistently across API/list/
export channels. Test view-only, PII-authorized, restricted branch, unrelated branch, minors,
and no-token cases. Keep record authorization and avoid inference through hidden-field filters.

### SEC-004 — Purge private service-worker cache entries

**P1 / High; Browser verified; physical-device test and rollout pending; Frontend / authentication.** Evidence: Browser persistence + isolated JS.

`app/webroot/sw.js:190–220` caches successful GETs without privacy/identity checks and uses
`ignoreVary` on fallback; mobile registration gives it root scope. In a real local browser,
a synthetic member page remained readable in Cache Storage after logout despite no-store headers.
A VM proof also served a private marker offline under another synthetic Cookie. Full browser
offline-navigation replay was not established: the automation attempt returned
`ERR_INTERNET_DISCONNECTED`. Shared/lost browser profiles are the exposure, not another origin.

**Remediation and acceptance:** Allowlist public static assets and separately approve minimal intentional offline PII. Honor
private/no-store across fetch, CACHE_URLS and migration paths; purge existing sensitive caches
on upgrade/logout/account change. Give deliberate offline data owner isolation and expiry.
Test A/B/logout/revocation/offline and direct Cache Storage reads, including legacy-cache upgrades.
A screen PIN is not encryption of cached bytes. The [Cache API](https://developer.mozilla.org/en-US/docs/Web/API/Cache)
does not honor HTTP cache directives automatically.

### SEC-005 — Respect attendance consent in award helpers

**P1 / High; Awaiting rollout; Awards / gatherings.** Evidence: Complete source trace.

`Awards/RecommendationsController.php:2170–2183` skips authorization and enables attendance
for any identity plus client-supplied member public ID. `2243–2258` loads unshared attendance;
`2270–2288` distinguishes its presence even when crown sharing is false. `status=Given` includes
history. Ordinary members can infer another member's whereabouts for award-linked events;
notes are not returned. Public IDs are discoverable. Guests currently receive no attendance.

**Remediation and acceptance:** Separate public gathering options from authorized attendance enrichment; enforce member/
attendance policy, branch scope and audience consent. Test self/guardian, unrelated member,
authorized crown/staff and guest against private/crown/host/kingdom rows, minors and history.
Unauthorized users must not distinguish unshared attendance from absence.

### SEC-006 — Confine platform administration to reserved origins

**P1 / High; Awaiting rollout; Platform / middleware.** Evidence: Local HTTP + source.

`app/config/routes.php:102–105` has a host-unconstrained platform prefix. Tenant middleware
does not deny that prefix on known tenant hosts; platform login binds its session to the host
that issued it. Locally the platform password/TOTP form returned HTTP 200 on both a tenant
origin and the reserved platform origin. Platform credentials and MFA are still required.
An admin using a tenant origin exposes privileged pages to that origin's scripts and worker cache.

**Remediation and acceptance:** Reject every platform route on non-allowlisted hosts before login/session issuance or tenant
binding. Test known tenant/unknown/reserved hosts, all platform routes and forwarding-header
spoofing under the trusted-proxy policy. Invalidate platform sessions previously issued on tenant
origins and prove credential submission there cannot create privileged session state. Preserve
host-bound sessions, live status checks and MFA.

### SEC-007 — Remove PII and document bytes from telemetry

**P1 / High; Awaiting rollout; Telemetry / Waivers.** Evidence: Source + isolated PHP + Azure flags.

`WaiverFileService.php:338` logs failed entity data including notes;
`ImageToPdfConversionService.php:264–270` logs uploaded bytes. `ApplicationInsightsLog.php:182–187`
sanitizes only the message; `303–337` serializes original context. In-memory transport retained
invented nested notes/bytes despite message sanitization. Azure confirms error/query/request
telemetry enabled and application telemetry retained 90 days. No real log records were read;
actual past disclosure is unverified. Debug-only notes are not assumed exported by error-only sinks.

**Remediation and acceptance:** Remove content/entity dumps; allowlist safe event context and recursively scrub every transport.
Retain event codes/counts/correlation IDs. Verify synthetic notes, document bytes and credentials
are absent from direct/OTLP/file sinks while security events remain. Review telemetry readers,
retention and raw local SQL logging; production enables PERF_DB_QUERY_LOG_ENABLED.

### SEC-008 — Exclude runtime secrets and PII from image builds

**P1 / High; Awaiting rollout; Docker / release.** Evidence: Synthetic Docker build.

`.dockerignore` does not exclude local app configuration, vendor, temporary sessions or uploaded
images. `docker/Dockerfile.prod:51–64` installs production vendor then copies all app source over
it. A scratch build with four synthetic canaries included all four paths. Exposure requires a
build from a populated checkout; clean CI may lack these files. No published-image PII leak was
established. Startup replacement does not remove data from earlier image layers.

**Remediation and acceptance:** Use a clean allowlisted context or explicit sensitive/generated exclusions, and prevent vendor
overlays. Assert canary secrets/uploads/sessions/backups/reports are absent from all layers;
verify final vendor equals the locked no-dev inventory. Investigate prior populated-context
builds before deciding whether keys/sessions need revocation.

### SEC-009 — Narrow PostgreSQL access and runtime privileges

**P2 / Medium; Verified in code; rollout required; Azure / database.** Evidence: Live network metadata; grants unverified.

Production PostgreSQL has public networking, AllowAzureServices and additional client/POC-named
firewall rules; TLS is required. The broad Azure rule includes other customers' subscriptions,
with credentials still required ([Microsoft](https://learn.microsoft.com/en-us/azure/postgresql/security/security-firewall-rules)).
`deploy/azure/main.bicep` also uses admin credentials for default/platform URLs. Actual production
role grants were not read, so excessive DB privileges remain a verification item.

**Remediation and acceptance:** Use private access or a narrow verified egress allowlist; remove obsolete rules. Separate
runtime/registry from migration/provisioning identities. Record sanitized grants and demonstrate
ordinary runtime roles cannot read/write other tenants’ data or administer schemas. Validate
app/job connectivity and denial
from unrelated networks in an approved deployment plan.

### SEC-010 — Prevent recovery enumeration and reset abuse

**P2 / Medium; Awaiting rollout; Authentication.** Evidence: Complete source trace.

`MembersController.php:1975–2003` distinguishes known/unknown email by message and redirect;
repeated requests replace tokens and queue email without an endpoint limiter. Public callers
can obtain CSRF tokens. Login errors disclose some account states too. Impact is membership
inference, targeted phishing, email/queue abuse and disruption of legitimate recovery.

**Remediation and acceptance:** Use equivalent public responses, shared account/IP limits and issuance cooldown/deduplication.
Test known/unknown synthetic users, bounded mail/queue work and recovery during cooldown;
the limiter must not reveal account existence. No bulk live testing is needed.

### SEC-011 — Remove URL credentials and scrub sensitive routes

**P2 / Medium; Awaiting rollout; API / logging.** Evidence: Source + Azure telemetry flags.

`ServicePrincipalAuthenticator.php:155–159` accepts api_key in query strings. Reset URLs contain
the token in the path. Apache combined access logging records request targets; optional request
timing in `Application.php:469–504` records raw paths. Production enables request/all-request and
SQL logs. URL/history/log readers may recover reusable credentials; no real token was inspected.

**Remediation and acceptance:** Migrate clients to Bearer/X-API-Key headers and remove query authentication. Scrub token paths/
queries at proxy, app and telemetry boundaries, not only SQL messages. Synthetic markers must
never appear in any sink; preserve reset functionality and meaningful security events.

### SEC-012 — Bind offline caches and queued actions to their owner

**P2 / Medium; Browser verified; physical-device test and rollout pending; Frontend / gatherings.** Evidence: Source + isolated JS.

`rsvp-cache-service.js:357–378` replays ownerless pending records using the current page CSRF;
cache keys use gathering IDs and logout does not call clearAll. The generic offline queue has
a similar gap. A VM proof replayed invented A intent with B CSRF without HTTP. Shared browser/
tenant usage is required. Server authorization still applies to B; it cannot identify stale A intent.

**Remediation and acceptance:** Namespace by stable tenant/user; check queued owner against current authenticated actor, and
purge/quarantine on logout/account/impersonation change. Clear stale rows when a fresh dataset
is empty. Test offline A → logout → B → reconnect and authorized A recovery without cross-user
notes, visibility or unintended mutations.

### SEC-013 — Reject invalid single-PDF uploads

**P2 / Medium; Awaiting rollout; Waivers / documents.** Evidence: Isolated PHP confirmed.

`PdfProcessingService.php:84–91` turns validation failure into zero pages, while `120–128`
returns success after copying a single input. Mixed conversion/waiver persistence accept it.
A tiny synthetic non-PDF produced validation=false, merge=true and unchanged output. Authorized
upload is required; attachment authorization/storage/size controls remain. No RCE or viewer
exploit was demonstrated.

**Remediation and acceptance:** Validate every input and positive page count; use server-verified type, propagate failures,
bound parser resources and clean temporary outputs. Benign invalid/truncated/zero-page/mixed
fixtures must create no active waiver or stored document; valid single/multi-page files must work.

### SEC-014 — Resolve npm advisories and embedded parser copies

**P2 / Medium; Verified in code; CI activation pending; Frontend / dependencies.** Evidence: Registry scans + reachability review.

HEAD app lock audit: 9 affected packages (5 high/4 moderate vendor ratings); release v1.5.9 lock:
11 (7 high/4 moderate). Root npm and current Composer lock scans: zero advisories. Build tooling
and apparently unused guifier account for much of the npm graph; no member-facing exploit from
these counts was proven. Guifier's distributed JS embeds an older js-yaml copy, beyond lock
fixes. Release-only additions include Browserslist and fast-uri tooling.

**Remediation and acceptance:** Triage symbols/emitted assets; remove unused guifier or replace/rebuild embedded parsers.
Update compatible affected XML/YAML/tooling packages and qs override, then clean locked install,
audit, Jest, Vite and relevant feature tests. Preserve face-api.js until focused compatibility
validation. References: [XML](https://github.com/NaturalIntelligence/fast-xml-parser/security/advisories/GHSA-gh4j-gqv2-49f6),
[YAML](https://github.com/nodeca/js-yaml/security/advisories/GHSA-5p4m-2wfm-xmqj).
Package counts are not independently exploitable application findings.

### SEC-015 — Make security verification fail closed

**P2 / Medium; Verified in code; CI activation pending; CI / dependencies.** Evidence: Source + failing-tool simulation.

`security-checker.sh` uses a fixed workspace path and exits successfully after scanner errors;
a synthetic run with every scanner failing still returned 0. Dependabot is a placeholder.
Inspected workflows lack enforced lock/image CVE audits and periodic base refresh. Copied plugins,
bundled parser code and native libraries are outside Composer/npm registry coverage.

**Remediation and acceptance:** Use portable fail-closed tooling, valid update ecosystems, SBOM/final-image scans and scheduled
base refresh. Verify missing tools, scan failures and controlled vulnerable fixtures fail CI.
Track explicit exceptions with owner/reason/expiry. Inventory both npm locks, Composer, Go,
Ruby docs, copied plugins, face assets and native document-processing dependencies.

### SEC-016 — Harden platform privilege transitions

**P2 / Medium; Awaiting rollout; Platform authentication.** Evidence: Source; conditional prerequisites.

`PlatformAdmin/AuthController.php:46–54` does not renew session ID on MFA login. Sensitive-action
TOTP has no separate attempt limiter or accepted-counter consumption. Initial login is throttled.
Fixation requires a known valid pre-login session; step-up abuse requires a compromised platform
session/code. No platform MFA bypass or brute-force feasibility was demonstrated.

**Remediation and acceptance:** Renew session ID on privilege gain and reject the old ID; throttle step-up and define atomic
TOTP replay policy. Test missing/old/wrong/reused codes without sensitive side effects, while
preserving legitimate repeated administrative workflows.

### SEC-017 — Pin privileged build and installation inputs

**P3 / Low; Verified in code; CI activation pending; CI / release.** Evidence: Supply-chain hardening.

Mutable workflow action tags, PHP extension installer latest and builder/base tags may change
outside source review. Legacy installers accept missing checksum metadata in some paths.
No upstream compromise was observed. Existing POC digest validation/promotion should remain.

**Remediation and acceptance:** Pin reviewed action SHAs/build digests, automate reviewable updates and require verified
provenance/checksums before executable replacement. Missing/mismatched evidence must fail.

### SEC-018 — Authenticate or retire the legacy updater

**P1 / High; Retired; operator action required; Legacy VPC / installer.** Evidence: Source; outside stated production.

`installer/internal/updater/server.go:59–130` accepts update/rollback without operator auth;
compose shares the app network and mounts Docker socket/deploy directory. Port is not Internet
published by default; socket permissions and network/SSRF foothold are prerequisites. Fixed
image repository limits input. No remote-code-execution claim or updater request was made.

**Remediation and acceptance:** Retire unused deployments or require authorized authenticated requests, approved digests,
restricted admin channel and minimal Docker rights. Mock Docker to prove unauthorized/invalid
requests cause no pull/write/restart. This legacy finding is not a deployed Azure sidecar finding.

### SEC-019 — Encrypt and restrict legacy SQL backups

**P2 / Medium; Retired; operator action required; Legacy VPC.** Evidence: Synthetic shell proof; outside Azure path.

`deploy/vpc/backup.sh` produces gzip SQL without encryption/restrictive umask. A synthetic run
under umask 022 produced readable gzip mode 0644/directory 0755. Parent/storage access governs
who can read it. This is separate from encrypted managed backups; no real backup was generated.

**Remediation and acceptance:** Use authenticated encryption, tested key recovery and private file/directory modes plus remote
retention/access controls. Prove synthetic ciphertext cannot be decompressed, another UID cannot
read it, and wrong-key/tamper recovery fails safely.

### SEC-020 — Update or retire legacy Go binaries

**P2 / Medium; Retired; operator action required; Legacy installer.** Evidence: Advisory matches; call graph incomplete.

Archived installer/updater declares Go 1.24 and old modules. OSV matched three modules to four
advisories, plus declared-toolchain issues; some affected symbols are unused/Windows-only.
No compiled binary call-graph scan ran. Floating tags do not establish exact compiler versions,
and these findings do not establish a Go vulnerability in the PHP Container App.

**Remediation and acceptance:** Retire the surface or rebuild with supported patched Go/modules; run source and binary
govulncheck and record exact compiler/module data. Apply the [Go release policy](https://go.dev/doc/devel/release)
and platform-aware [vulnerability database](https://pkg.go.dev/vuln/) results.

### SEC-021 — Constrain public recommendation association writes

**P1 / High; Awaiting rollout; Awards / workflows.** Evidence: Source + real marshaller synthetic proof.

Public award submission forwards raw form data through workflow extraction into
`RecommendationSubmissionService.php:112–138`, which patches/saves associated Gatherings
without onlyIds/field allowlisting or gathering authorization. An isolated real Cake marshaller
changed an existing synthetic gathering name/public-page flag with zero validation errors;
the DB driver was configured to reject connections. Source traces the subsequent associated
save. Enabled public Awards submission, a shipped-equivalent workflow, a normal CSRF token
and a successful valid nomination save are prerequisites. Deployed workflow customizations were
not inspected; no HTTP database write was performed.

**Remediation and acceptance:** Allowlist public submission fields and accept gathering links only as validated IDs; reject
nested entity updates and client-controlled attribution/state. Authorize any intentional related
write separately. Regression: public/authenticated submissions with nested existing/new gathering
objects must not change gathering fields, while permitted linking succeeds in the proper tenant.

### SEC-022 — Deploy immutable image references

**P2 / Medium; Awaiting rollout; Azure / release.** Evidence: Live Azure/registry metadata.

Production web and retained jobs reference release-v1.5.9 by tag; registry metadata permits
tag overwrite/deletion. Current registry resolution is recorded privately but does not prove
running-replica bytes. A registry writer could change bytes resolved by future replicas without
changing revision text. No overwrite or image compromise was observed.

**Remediation and acceptance:** Use the verified POC-tested @sha256 image in web/jobs; protect release tags and retain provenance/
SBOM. Assert promotion rejects mismatch and restart/scale preserves the attested digest. This
closes the gap between the repository release contract and actual deployed reference.

### SEC-023 — Align database recovery horizon with approved policy

**P2 / Medium; Awaiting rollout; Azure / operations.** Evidence: Live configuration drift.

Azure PostgreSQL retention is 15 days; production.bicepparam declares 35. Geo redundancy is
enabled. This is recovery-policy drift, not PII disclosure or proven data loss. Verify supported
provider limits and intended policy rather than automatically applying the stale parameter.

**Remediation and acceptance:** Approve a supported recovery horizon, align IaC/runbooks and independent encrypted backups,
and demonstrate isolated restores beyond the required incident-detection window. Add drift
monitoring. No backup/restore or production retention change was performed in this review.

### SEC-024 — Verify effective storage identity and blast radius

**P2 / Medium; Verified in code; rollout required; Azure / documents.** Evidence: Live authority; auth-mode drift.

Production identity has Blob Data Contributor over the whole docs account; tenancy relies on
application isolation. Public blobs/shared keys are disabled and TLS/encryption are enabled.
Live AZURE_STORAGE_AUTH_MODE is connectionString while template expects managedIdentity.
The secret was not read: it may reference another account or SAS, so runtime behavior is unverified.

**Remediation and acceptance:** Verify effective storage backend and intended managed identity; retire stale credential paths
only after validation. Narrow network/RBAC as practical and document residual account-wide
compromise impact. Test synthetic tenant upload/download and cross-tenant denial, no unintended
credential fallback, and recovery before deploying changes.

### SEC-025 — Authorize contact lookup against the target member

**P1 / High; Awaiting rollout; Gatherings / policies.** Evidence: Complete source trace.

`GatheringStaffController.php:252–266` authorizes edit of the supplied gathering, then returns
any separately supplied member public ID's email/phone. Gathering edit can be granted by steward
assignment and does not imply target-member viewPii or a staff relationship. Public IDs are
lookup identifiers, not secrets. A legitimate gathering editor is the prerequisite; no arbitrary
member contact data was requested during the audit.

**Remediation and acceptance:** Require appropriate per-member/branch PII authority or an explicit consented staff-selection
contract, in addition to gathering edit. Test a steward with no global PII privilege against
unrelated/member/minor records, branch boundaries and legitimate authorized staff. Broad member
contact harvesting must fail without breaking intended staff assignment.

### SEC-026 — Rebuild patched PHP/GD and native runtime

**P1 / High; PHP/GD verified; residual native triage and rollout pending; Docker / runtime.**
Baseline evidence: immutable registry image scan, digest-verified native layer extraction and
official security advisories; no production image or exploit execution. The replacement local
image build and runtime tests are recorded under implemented controls above.

The registry image currently referenced by the production release contains PHP **8.4.23** and
bundled GD. Extracted php-config and gd.so independently confirm version and bundled linkage;
no external libgd dependency was present. The v1.5.9 waiver-upload path authorizes upload,
passes image conversion to `ImageToPdfConversionService.php:348`, then calls `imagecreatefromgif`.
[PHP 8.4.24 security fixes](https://www.php.net/ChangeLog-8.php#8.4.24) include **CVE-2026-9672**;
the [upstream fix](https://github.com/php/php-src/commit/b590f0380fda26ed180874a0255f0be078434315)
patches the bundled GIF parser. Crafted GIF input can affect native memory safety; an authorized
upload is required for the traced path. Application RCE or PII extraction was not demonstrated.

Trivy's installed Composer inventory has 72 packages and zero advisory matches, which does
not cover this custom PHP runtime. Its OS results include duplicate source-package and kernel-header
matches; do not count each row as a runtime exploit. Retain the detailed native-library triage
and exact scanned digest privately. A current registry digest is still distinct from attestation
of all running replicas while Azure uses a mutable tag (SEC-022).

**Remediation and acceptance:** rebuild the shared base and app with a current supported patched
PHP 8.4 release containing the GD fix, updated required native libraries and recreated extensions;
remove unnecessary build/header tools from the runtime. Rebuilding app code against the old base
or updating Composer alone is insufficient. Verify resulting PHP/GD metadata and image SBOM/CVE
triage, test bounded GIF/JPEG/PNG/PDF flows and image limits, then validate and promote the same
immutable candidate digest through the normal POC/production approval workflow. Add scheduled
base refresh and final-image gates (SEC-015). No production rollout is part of this audit.

## Verified controls and remaining work

Positive checks: unknown local tenant returns 404; web session alone fails API auth with 401;
missing-CSRF POST returns 403. Waiver downloads/previews authorize the entity, CSV export
neutralizes common formula prefixes, and sampled markdown/member-name rendering escapes input.
Azure has HTTPS enforcement, DEBUG=false, database TLS, private blob authorization, key-vault
purge protection, geo-redundant storage/backups and no observed subscription-wide workload role.
Preserve these controls while fixing the gaps.

Unverified controls need explicit evidence, not an assumed pass:

| Work item | Required evidence | Responsible area |
| --- | --- | --- |
| Running artifact attestation | Registry image scan completed; verify running-replica digest/source provenance, residual native advisories and custom extension coverage | Release / Azure |
| Active tenant inventory | Count sharing the session backend; post-fix replay denial for ordinary/admin users | Platform |
| Data lifecycle | Inventory legal/contact/DOB/minor/guardian data, cards, waivers/signatures, notes, attendance, exports and email; define purpose/retention/deletion ownership | Data owner |
| Erasure and restore | Approved retention/holds, bounded purge, prevention of restored backups undoing required erasure or revocation | Data owner / backups |
| Key custody and recovery | Actual roles, rotation/recovery tests and separation of backup keys from readable archives | Operations |
| Access/export monitoring | Privacy-safe bulk-read/export events, meaningful alerts and incident-response contacts | Operations / security |
| Audit resilience | Hash-chain validation and separately provisioned immutable sink when required; DB hashes alone are not external WORM | Platform |
| Copied/native code | Maintained upstream revisions for bundled plugins, PDF/image libraries, face assets and embedded JS | Dependencies |

No legal retention period is established by this review. Confirm policy from actual obligations
and purpose. UI-affecting fixes must invoke the repository WCAG skill and targeted regressions.

## Closing an entry

Record named owner, target date, code/config commit, deployment digest when relevant, retest
results and limitations. Close only when acceptance checks pass on the deployed candidate.
Time-limited acceptance requires accountable approver, compensating control and expiry.
Keep detailed reproduction data out of public tickets until disclosure/remediation is coordinated.
