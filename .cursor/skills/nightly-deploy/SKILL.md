---
name: nightly-deploy
description: Inspects or operates KMP's direct Azure nightly helper while preserving the official dev, POC, and production release boundary.
---

# KMP direct nightly helper

Read and follow `.github/skills/nightly-deploy/SKILL.md`. Confirm current commands, URLs, resource names, and overrides with:

```bash
bash deploy/azure/nightly-deploy.sh help
```

Do not substitute the direct-nightly helper for “Push to dev” or “Do a release”; those phrases are governed by `.github/skills/release-deploy/SKILL.md`. Commands that build/push a local checkout, deploy, migrate, reset data, or change passwords require explicit authorization and a confirmed Azure target.
