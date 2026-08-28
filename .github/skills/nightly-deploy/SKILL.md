---
name: nightly-deploy
description: Inspect or operate KMP's direct Azure nightly helper without confusing it with the official dev, POC, and production release path.
---

# KMP direct nightly helper

Use `deploy/azure/nightly-deploy.sh` from the repository root for explicitly requested direct-nightly operations. Begin with:

```bash
bash deploy/azure/nightly-deploy.sh help
```

The script and its help output are the authority for current subcommands, environment overrides, resource names, and URLs. Do not copy volatile Azure identifiers into durable guidance.

## Safety boundary

This helper is separate from the official release contract:

- “Push to dev” and “Do a release” must use `.github/skills/release-deploy/SKILL.md` and the gated GitHub workflows.
- Direct-nightly deployment does not prove that a commit or digest passed POC gates and must never be used to promote production.
- `deploy-local` builds and pushes the current checkout. `reset` wipes/reseeds data. `reset-passwords` changes credentials. Run these only when the user explicitly authorizes that exact state change.
- Confirm the Azure subscription/resource group and authenticated `az`/`gh` identities before any mutation.
- Treat seeded passwords and accounts as disposable environment fixtures, never production credentials.

## Read-only inspection

Use the helper's current read-only subcommands for status, build watching, revisions, logs, health, URL output, or tenant-host verification. Verify command names through `help` before execution.

## What deployment currently coordinates

Source-check the helper and `deploy/azure/cutover-unified-worker.sh` before describing or changing deployment behavior. The direct flow imports or builds an image, captures scheduled-job definitions, starts a unified-worker canary, runs platform and tenant migrations, updates the web revision, probes health and tenant/platform hosts, reconciles backup keys, and parks legacy scheduler jobs after successful cutover. Optional recommendation migration and reset paths add further mutations.

Report the exact command, selected image/tag or digest when available, target environment, health results, and any step not verified.
