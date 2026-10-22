# PostgreSQL-only platform verification

Verified October 22, 2026. KMP now supports PostgreSQL only across application
configuration, container startup, development tools and CI. This implements the
decision to focus on managed SaaS hosting for kingdoms.

## Behavior and compatibility

- MySQL/MariaDB datasource selectors and URLs are unsupported. Startup validates
  merged tenant/platform/test settings, including administrative and DSN driver
  overrides, before registering application connections. Errors omit credentials.
- Native runtime builds omit MySQL/MariaDB clients and PHP extensions. Production
  final stages reject prebuilt bases with enabled MySQL extensions or without
  PostgreSQL PDO. The old MySQL-enabled development image was rejected by this
  check; the new PostgreSQL-only base passed.
- Backup restore, schema reset, JSON filtering and rate-limit writes use the
  PostgreSQL paths. Existing logical archives retain PostgreSQL import support.
  Applied migrations were not edited; fresh core and plugin migrations passed
  in a disposable PostgreSQL database.
- The nightly image health smoke test now uses PostgreSQL 16. The devcontainer
  provisions PostgreSQL only. Legacy dump/reset paths and the unsupported VPC
  Compose/database templates have been retired.
- No live database conversion, production deployment or version bump occurred.
  Existing MySQL installations require a separately planned migration before
  upgrading. Historical evidence, migration names and bundled upstream library
  portability code are not promises of supported database backends.

## Verification

All three PHP suites passed: 1,901 core-unit tests, 627 core-feature tests and
1,028 plugin tests, with six existing skips. All 2,277 JavaScript tests and
28 security regression tests passed. Seed contracts, the skipped-test budget,
PHPStan, Vite, Azure runtime contracts, workflow lint, Compose validation and
changed-script syntax checks passed.

The combined verification run could not perform its documentation check inside
the app container because Git metadata is not mounted there. Markdown and JSDoc
checks passed separately on the host. PHPCS encountered a PHP 8.5 deprecation
inside the installed Slevomat sniff; after correcting actual style findings,
the complete changed-PHP manifest passed with `E_DEPRECATED` disabled for that
tool invocation only. No repository lint rule or application error setting was
weakened.

The native runtime and complete production Dockerfile were built locally.
Required PostgreSQL tools, PHP 8.5.12, Apache configuration, and five GD codec
round trips passed. The finished image's production and development entrypoints
and `/health` passed against isolated PostgreSQL 16 instances with temporary credentials,
no published ports and no existing data. Both mount helpers had their elevated
mode bits removed. Disposable containers, network and migration database were
removed afterward. Pre-existing user font changes were preserved.

## Final-image security evidence

The final local ARM64 image is
`sha256:0470f9e0a8c5afe5bb69a1bc01016107d5ecc653331fd2b67bece6a83873e79f`.
The [machine-readable record](postgresql-only-runtime.json) binds its image ID,
Trivy 0.74.0 report, inventory SBOM and checksums. Raw reports and build logs are
retained privately under `security-reports/2026-10-22/postgresql-only/` and are
not committed.

| Native findings | 1.5.13 release evidence | Local PostgreSQL-only candidate |
| --- | --- | --- |
| High/Critical package rows | 129 | 63 |
| Distinct High/Critical advisory IDs | 32 | 19 |
| Fixable High/Critical findings | 0 | 0 |

No installed MySQL/MariaDB packages, clients or PHP extensions remain in the new
image. All 13 MariaDB High/Critical advisory IDs from the release evidence are
absent; no new native High/Critical IDs appeared. The full native scan still has
552 findings at all severities. Remaining findings, particularly libxml2, are
not fixed or accepted by this change; retain the [1.5.13 reachability review](1.5.13-native.md).

The release baseline was AMD64 and this candidate is ARM64, so the row-count
comparison is not an architecture-controlled regression result. This local
image has not been published or deployed. The next release still requires its
AMD64 quality gates, exact-image audit, POC validation and normal production
approval. No scanner exception or residual-risk acceptance was added.
