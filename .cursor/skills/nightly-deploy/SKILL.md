---
name: nightly-deploy
description: Deploys KMP to Azure nightly environment. Use for "deploy to nightly", nightly status/logs/health, reset nightly DB, or rebuild nightly image.
---

# KMP Nightly Azure Deploy

All operations via `deploy/azure/nightly-deploy.sh` from repo root.

**URL**: https://kmpnightly-web.lemonstone-62ccb06f.centralus.azurecontainerapps.io/  
**Login**: seeded email + `TestPassword` (e.g. `admin@amp.ansteorra.org`)

## Prerequisites

- `az login --tenant 77070ec3-247c-40ce-9a4f-df875ffe914f`
- `gh auth login` (for build subcommand)

## Commands

| Intent | Command |
|--------|---------|
| Deploy current `:nightly` image | `bash deploy/azure/nightly-deploy.sh deploy` |
| Rebuild from HEAD + deploy | `bash deploy/azure/nightly-deploy.sh build` |
| Deploy + wipe/reseed DB | `bash deploy/azure/nightly-deploy.sh reset` |
| GHCR build status | `bash deploy/azure/nightly-deploy.sh status` |
| Tail build | `bash deploy/azure/nightly-deploy.sh watch` |
| Container revisions | `bash deploy/azure/nightly-deploy.sh revisions` |
| Web logs | `bash deploy/azure/nightly-deploy.sh logs [--tail N]` |
| Health check | `bash deploy/azure/nightly-deploy.sh health` |

Full details: `.github/skills/nightly-deploy/SKILL.md`
