---
layout: default
title: Authentication security
---

# Authentication security

Member sessions store a versioned credential envelope containing the immutable tenant identifier, member identifier, credential epoch and issuance time. They do not store profile data or permission snapshots. Each authenticated request loads the current member from the resolved tenant database, checks account eligibility and compares the credential epoch. Missing tenant context, copied cookies from another tenant, legacy serialized sessions and revoked epochs fail closed. Database failures never fall back to cached identities.

Password changes, completed password resets, email changes, account status changes and deletion rotate an opaque `auth_version` value. Existing reset links are invalidated on these security changes. All sessions and login passkeys from the previous epoch stop working. Explicit `POST /members/revoke-sessions/{id}` performs the same revocation under the existing password-management authorization. Revoking yourself ends the current session. Revocation is effective on the next request; a request already executing can complete.

Native passkey enrollment at `/passkeys` requires password reauthentication and a two-minute, tenant/session/origin-bound challenge. Challenges are consumed atomically in the tenant database. Both enrollment and login require device user verification; server verification checks the signature, exact origin, RP ID, credential owner and current authentication epoch. Synced zero-counter credentials use an atomic revision update. Password reset/change requires re-enrollment. Old quick-login PIN enrollment and authentication are retired; migration deletes the server PIN hashes and browser startup removes the fast local verifier. The optional 6–8 digit PIN protects only the encrypted offline vault; see [protected offline access](protected-offline-access.md). Impersonation sessions validate both the effective member and the original administrator's current epoch, active state and super-user authority. Tenant or administrator revocation terminates impersonation.

Password recovery returns the same success message and redirect for known, unknown and throttled accounts. Recovery mail always enters the asynchronous queue, including when general mail queuing is disabled; queue failures retain that public response and emit only a fixed failure event without account or token details. Issuance has a five-minute atomic account cooldown and a one-hour reset-link lifetime. Token consumption and password replacement are a single conditional database write, so concurrent redemption succeeds at most once. Registration links issued by the existing registration workflow remain supported with their existing expiry.

## Member security dialogs

Desktop account actions and the mobile menu open passkey management in a Bootstrap modal, without navigating away. The `/passkeys` link remains a small entry page for bookmarks. Private settings load in a Turbo Frame only when the modal opens and are discarded on close.

Members without an active passkey start with a three-step wizard: a plain-language explanation, KMP password confirmation and a separate device-prompt tap. Only one step is visible at a time, with progress, Back/Cancel controls and an explicit success screen. Password values and pending options are cleared on Back, close and disconnect. Closing cancels pending native requests. Focus moves to each step heading and returns to the opening control (the Menu button on mobile).

Removing a passkey uses an in-dialog consequence/confirmation step and completion screen. Signing out all devices uses its own confirmation modal, explicitly explaining password sign-in, passkey revocation and the disconnected-card expiry limit. Existing server permissions, reauthentication requirements and revocation behavior remain authoritative.

## Shared throttles

`security_rate_limits` stores only keyed digests, counters and expiry timestamps. Multitenant requests use the platform database with immutable tenant namespaces; single-tenant requests use the default database. Platform actions use a separate namespace. No replica-local cache or Redis service is required. A counter-store outage fails closed before protected work. Expired rows are removed after a day.

| Action | Limit |
| --- | --- |
| Password recovery per IP | 10 attempts per 15 minutes |
| Password recovery per account | 3 attempts per hour, plus a five-minute issuance cooldown |
| Passkey ceremonies per IP | 20 attempts per 5 minutes |
| Passkey enrollment password checks per member | 5 attempts per 5 minutes |
| Platform login per IP | 20 attempts per 15 minutes |
| Platform login per account | 5 attempts per 15 minutes |
| Platform MFA | 5 failed or pending challenges per 15 minutes per administrator |

Counters use atomic database updates. Successful MFA releases only its own reservation; prior failures remain counted. Successful TOTP moving factors are consumed atomically in `platform_users`, across login, step-up actions and emergency recovery. A previously used code or older code cannot authorize another action. Wait for a fresh authenticator code for each sensitive action; there is no reuse grace period. Recovery codes are also consumed conditionally once.

## Platform and API boundaries

Every platform route, including login and logout, requires a host listed in `Platform.adminPortal.hosts`. The middleware rejects tenant and unlisted origins before session processing, and the controller repeats the check. Platform sessions carry their own format version, host and credential epoch and rotate the session identifier on login. Password recovery rotates the platform epoch and revokes emergency sessions. MFA reset continues to fail closed until the existing multi-administrator approval workflow is available.

API credentials are accepted only in `Authorization: Bearer …` or `X-API-Key` headers. The `api_key` query parameter is ignored. Update integrations before rollout; never put credentials into URLs.

## Rollout and verification

Apply `20260905090000_AddPlatformAuthenticationState` to the platform database and `20260905090000_AddMemberAuthenticationState` to every tenant/default database before serving the new application revision. The deployment's tenant schema gate keeps unmigrated tenants in maintenance. Also apply `20260908120000_AddMemberPasskeys` to each tenant; it adds native credential/challenge storage and irreversibly deletes legacy PIN hashes. Existing browser and platform sessions require fresh login; members enroll new passkeys after password login. The passkey retirement migration refuses rollback. Existing passwords are preserved. Runtime database roles need counter-table DML and the existing platform-user update access used by MFA.

Do not roll back to the vulnerable session implementation after issuing new sessions. Coordinate any rollback with a session purge and forward security fix. No production migration or credential changes are performed by the repository tests.

Targeted PHPUnit coverage is in `MemberSessionAuthenticatorTest`, `MemberSecurityLifecycleTest`, `PasswordRecoveryDispatchHttpTest`, `MembersQuickLoginSetupTest` (retired endpoint denial), `MemberPasskeyServiceTest`, `MemberPasskeysTest`, `ServicePrincipalHeaderTest`, `RequestRateLimiterTest`, `PlatformTotpChallengeServiceTest`, `PlatformAdminRecoveryServiceTest`, `PlatformAdminPortalTest` and `TenantResolutionMiddlewareTest`. Run these with the repository test database, never against production. Explicit logout and self-revocation send `X-KMP-Offline-Clear: 1` for the offline session observer.
