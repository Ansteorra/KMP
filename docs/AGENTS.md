---
layout: default
title: Documentation contribution contract
description: Maintenance and verification conventions for the KMP developer documentation site.
---

# Published documentation guide

## Purpose

Own the published documentation site and long-lived project documentation for architecture, setup, deployment, security, plugins, services, JavaScript, UI components, and operational runbooks.

## Ownership

- Root `docs/` is the durable documentation site.
- `docs/deployment` owns deployment and operational runbooks.
- `docs/api` owns API-facing documentation.
- App-local implementation notes belong in `app/docs` unless they are intended for the published docs site.

## Local Contracts

- Keep docs concise, current, and operational; delete stale text instead of preserving history in place.
- Update docs when public commands, installation/deployment behavior, plugin contracts, user-facing workflows, or developer workflows change.
- Do not document secrets, private environment values, or temporary task notes.
- Preserve Jekyll site structure, front matter, and existing navigation conventions.

## Work Guidance

1. Search for existing docs before adding new pages.
2. Prefer updating the nearest existing topic page over creating a disconnected document.
3. Keep examples aligned with current Vite, CakePHP, Docker, and deployment commands.
4. Cross-link related plugin, service, and deployment docs when behavior spans domains.

## Verification

- Documentation-only edits require diff review plus `cd app && npm run docs:check`.
- If Jekyll structure or site config changes, run `BUNDLE_FROZEN=true bundle exec jekyll build` from `docs/`.
- Generated source references are ignored build artifacts; validate them with `./generate_api_docs.sh` from the repository root.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
