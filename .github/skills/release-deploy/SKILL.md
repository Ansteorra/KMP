---
name: release-deploy
description: Perform official KMP POC and production releases. Use when the user says "push to dev", "do a release", "release to POC", "release to production", "promote to production", or asks to ship a KMP version. The release flow always updates the in-app changelog and uses the same user-facing notes for the GitHub Release.
---

# KMP Release and Deployment

Use the official Ansteorra workflows and immutable image digests. Never deploy
uncommitted local changes or bypass a failed quality gate.

## Intent: "push to dev"

This means release the selected official `main` commit to POC only.

1. Inspect the worktree and fetch `upstream`.
2. Resolve the requested commit, defaulting to `upstream/main`.
3. Confirm the commit is on official `main` and that updating `dev` is a
   fast-forward. Never force-push `dev`.
4. Confirm `Quality Gates` passed for the exact merged `main` commit.
5. Push the exact commit to `refs/heads/dev`; create the branch if it does not
   exist.
6. Watch `Nightly / Dev Docker Image`. It verifies that existing exact-commit
   evidence instead of rerunning the suites, then builds and smoke-tests one
   immutable release-candidate image.
7. Watch `POC / Deploy to Azure`. It imports the tested image, verifies its
   digest, runs the worker canary and migrations, cuts over web traffic, and
   aligns retained jobs. A successful deployment records the POC-validated
   digest for production promotion.
8. Verify POC readiness, tenant and platform hosts, login, queue/worker
   processing, and the changed user journeys.
9. Do not create a production release or change production.

## Intent: "do a release"

This means prepare user-facing notes, validate the exact candidate in POC, then
publish and promote a stable production release.

### 1. Select the release

- Use a user-supplied `v*` version when provided.
- Otherwise inspect the latest stable `v*` release. Default to the next patch
  version; require explicit user direction for a major or minor bump unless the
  existing changelog already identifies that release.
- Start from official `main`, never from uncommitted files or a fork-only commit.

### 2. Prepare one source of release notes

Before POC testing:

1. Read the sync markers in `app/CHANGELOG.md`.
2. Review commits from `LAST_SYNCED_COMMIT` through the candidate code commit.
3. Add a release section headed `## KMP <version without the leading v> — <date>`.
4. Include meaningful user-visible features, improvements, security changes,
   performance changes, and important fixes. Consolidate related commits and
   exclude CI, test-only, dependency-only, refactoring, and developer-tooling
   noise unless users are affected.
5. Update `LAST_SYNCED_COMMIT` to the candidate code commit and
   `LAST_SYNCED_DATE`.
6. Commit the changelog on the release branch and get it merged into `main`.
7. Treat the resulting merged `main` commit as the immutable release candidate.

The Markdown under the new KMP version heading is the canonical release body.
Use that same text in the GitHub Release; do not independently generate a second
summary that can drift from the in-app changelog.

### 3. Validate POC

Run the complete "push to dev" flow for the merged changelog-bearing commit.
Do not modify the candidate after POC succeeds. Any code or changelog change
creates a new candidate and requires another POC deployment.

### 4. Publish and promote production

1. Publish a non-prerelease GitHub Release with the selected `v*` tag, targeting
   the exact POC-tested commit and using the canonical changelog section as the
   release body.
2. Watch `Release Docker Image`. It verifies the successful `main` quality run
   and POC deployment for the tagged commit, then applies release tags to the
   exact POC-validated digest without rebuilding or rerunning test suites.
3. Stop and surface any missing evidence or digest mismatch.
4. Wait for the user-authorized GitHub `production` environment approval. Never
   bypass or self-remove the approval gate.
5. Watch the production deployment through image import, digest verification,
   worker canary, migrations, web cutover, and retained-job alignment.
6. Verify production `/livez` and `/health`, tenant and platform hosts, login,
   queue/worker processing, active image digests, the in-app changelog, and the
   GitHub Release notes.

Prereleases and published tags that do not start with `v` must never promote to
the production Azure environment.

## Failure handling

- Never force-push official branches or tags.
- Never release a commit or image digest different from the POC-tested
  candidate.
- Never continue past failed quality gates, POC checks, image smoke tests,
  migrations, worker canaries, or digest checks.
- Preserve deployment snapshots and use the existing Azure cutover rollback
  behavior when a deployment fails.
- Keep production unchanged until the approval-gated production job runs.
