# App-local documentation guide

## Purpose

Own app-local implementation notes, migration notes, cleanup notes, and feature design documents that support development but are not part of the published `docs/` site.

## Ownership

- Use this directory for durable app implementation notes tied closely to code under `app/`.
- Published user/developer documentation belongs in root `docs/`.
- Inline docblocks should stay maintenance-focused; broader narratives belong here or in root docs.

## Local Contracts

- Keep documents current, concise, and tied to active behavior.
- Remove stale task lists or migration notes when they no longer describe current work.
- Do not store secrets, environment values, customer data, generated reports, or temporary planning notes here.

## Work Guidance

1. Update app-local docs when implementation contracts, commands, workflows, or architecture under `app/` change.
2. Prefer linking to existing root docs rather than duplicating long architecture references.
3. Do not create planning scratch files here; use the session artifact folder for temporary notes.

## Verification

- Documentation-only edits require diff review for accuracy and formatting.
- Run app verification only when documentation changes include executable examples or commands that need validation.

## Child AGENTS index

No child `AGENTS.md` files are currently present.
