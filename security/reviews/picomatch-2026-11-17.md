# Picomatch reachability review — November 17, 2026

## Decision and scope

**No application exploit path was found in the reviewed KMP candidate. The
dependency itself is vulnerable. The user explicitly accepted the temporary
build/test-only exception as Platform Owner and Security Lead on November 17,
2026, through December 1, 2026.**

Baseline: `063b702eaad609ea6a8c3be174bbd94a47c552dd`, PR #865, with the local
mitigation changes described below. Scope is source, locked dependencies,
emitted browser assets, and a bounded local dependency reproduction. No attack
requests were sent to local, POC, or production servers; no accounts, tenant
data, secrets, or database changes were used. Child processes had a 2-second
timeout and a 64 MiB Node heap limit. Ordinary builds/tests use repository data.

The [GitHub advisory](https://github.com/advisories/GHSA-fc5q-39xc-vjpf)
reports single-branch repeated-extglob ReDoS through picomatch 4.0.7. Its patched
version field is empty; npm's latest is 4.0.7 as checked on November 17. The
advisory's linked upstream PR #194 concerns a different extglob parsing case and
is not evidence of an available fix. Do not downgrade Jest/Vite based on npm's
automated suggestion or treat that suggestion as proof of remediation.

## Evidence and trust boundary

| Surface | Finding |
| --- | --- |
| PHP web/API, workers, calendar search and uploads | No picomatch calls/imports found in application source. These paths execute PHP; they do not feed customer strings to a Node glob matcher. |
| Browser code | No picomatch, anymatch, micromatch, or tinyglobby sources in the 28 existing emitted JS source maps (1,056 source entries). A fresh production build also passes the new module-graph guard. |
| Application npm dependency | The unused direct `dependencies.picomatch` declaration was removed. Both remaining locked copies are marked `dev: true`. No package versions or integrity hashes changed. |
| Vite | Uses picomatch for build filters and repository literal `import.meta.glob` patterns. KMP runs `vite build`, not an exposed Vite development server. Entry paths and controller/service patterns are repository-controlled. |
| Jest / Playwright BDD | Uses picomatch through Jest helpers, anymatch, watcher, tinyglobby and fdir for repository test/file discovery. The reviewed configuration supplies fixed patterns; tenant input is not a pattern source. |
| PHP deployment image | `docker/Dockerfile.prod` installs npm dependencies only in the builder. The final image copies PHP source/vendor and compiled webroot, not `node_modules`. Docker exclusions also omit host-installed dependencies and old generated bundles. |
| Live-update Node service | Its separate lock contains jose/Redis dependencies and no picomatch. The application npm tree is not copied into that service. |

Search used repository source excluding installed dependencies and lockfiles,
then inspected the installed dependency callers and all relevant locks. `npm ls
picomatch --all` identifies 4.0.7 at the app root and 2.3.2 under anymatch.
The absence of a reviewed web-input path is an application reachability
assessment, not a claim that the library is fixed or that arbitrary future
build configurations are safe.

## Controlled reproduction

A separate Node process called `picomatch('+(*a)b')('a'.repeat(n) + '!')` for
each installed copy. The compiled regular expression contains nested unbounded
repetition. Observed times on this host:

| Version | 16 repeated characters | 24 repeated characters | 32 repeated characters |
| --- | --- | --- | --- |
| 4.0.7 | 3.0 ms | 340.6 ms | killed after the 2-second limit |
| 2.3.2 | 3.1 ms | 335.2 ms | killed after the 2-second limit |

This confirms the dependency-level denial-of-service behavior; it does not
demonstrate exploitation of a KMP route. A contributor able to change build/test
patterns could stall a CI process; that contributor already controls code run
by those jobs. Repository trust, review, and CI job timeouts remain necessary.
Do not enable a public Vite server or accept customer-supplied glob patterns
under this assessment.

## Accepted temporary exception and safeguards

`security/npm-picomatch-review.json` records the exact reviewed file hashes and
an expiry of **December 1, 2026 (inclusive, UTC)**. Both acceptance fields record
the user’s explicit approval in this chat, with the approval statement retained
verbatim. The exception is enabled subject to all of these constraints:

1. Apply only to the app npm lock and advisory **GHSA-fc5q-39xc-vjpf** on
   picomatch, including npm's dependent-package reports of that same advisory.
   Keep raw npm findings. Do not suppress packages wholesale or change severity
   thresholds, image scanning, Composer scanning, or production approval gates.
2. Require both installed versions to remain reviewed, dev-only copies. Verify
   hashes of the lock, package declaration, Vite build/guard, entry globs,
   Jest/Playwright configuration, final-image Dockerfile/exclusions, and gateway
   lock. Any change requires a fresh review; expiration fails closed.
3. Reject any picomatch module from the browser build graph. The actual Vite
   boundary test rejects direct/nested page imports and dedicated/shared module
   worker imports, while permitting benign page and worker entries. The worker
   regression first failed on the original top-level-only guard; separate fresh
   guard instances now protect every Vite worker build. This also prevents later accidental frontend use while the exception
   is active.
4. Keep new advisories, Critical escalation, missing graph edges and cycles
   blocking. Only the reviewed advisory can be excluded from the decision.
5. Rerun exact-commit quality, final-image scans, build/smoke checks and POC
   validation before deployment. This acceptance does not establish image
   validation or authorize production. Upgrade and remove the exception when
   a patched upstream version becomes available; reassess by the expiry date.

On the fresh audit, all 39 High package reports trace to this one advisory.
Testing the accepted-advisory classifier against that report leaves zero
High/Critical blockers. Without the accepted exception, all 39 still block. Synthetic regression tests prove that a second High advisory on picomatch
or a dependent package remains blocking.

## Verification

- Full security contract suite: 52 tests passed, including 15 scoped scanner/review tests.
- Actual Vite build-boundary test: passed for benign, direct and nested inputs.
- Production Vite build: passed.
- JavaScript suite: 2,514 tests across 156 suites passed.
- PHP-dependent security contracts ran using the existing PHP 8.5.12 image in
  disposable network-disabled containers with a read-only repository mount.
- The full dependency security checker passed Composer, root/app npm and Ruby
  audits with the accepted policy. Final-image checks remain part of CI.
- Raw npm audit still returns a nonzero status and retains the vulnerability.
  The policy classifier applies only the accepted advisory; all remaining
  quality, final-image and deployment gates remain required.

The acceptance requirement comes from
[the security scope checklist](../../docs/deployment/penetration-test-scope-checklist.md#evidence-and-disposition):
“High findings must be fixed and retested or explicitly accepted by the Platform
Owner and Security Lead.” Acceptance is recorded from the user’s explicit
November 17 approval, not inferred from this technical assessment.

## Renewal — November 28, 2026

The Dependabot updates applied on `dev` (babel group, playwright 1.65.1,
undici 8.12.4, `@hugeicons/core-free-icons` 4.4.0) changed `app/package.json`
and `app/package-lock.json`, so the pinned digests were renewed. Verified
before renewal: picomatch still resolves to exactly 2.3.2 (under `anymatch`)
and 4.0.7, both `dev: true`; `security/test-picomatch-boundary.mjs` still
passes against the real Vite guard. The scope, mitigation, and December 1
expiry of the original acceptance are unchanged. The Platform Owner and
Security Lead explicitly approved this renewal in chat on November 28, 2026.
