---
description: Audit KMP documentation against the repository and update inaccurate, stale, or unhelpful guidance
---

# Double-check KMP documentation

Review the requested documentation as maintained product documentation, not as prose in isolation.

## Workflow

1. Read the root `AGENTS.md` and every nearer `AGENTS.md` that owns the documentation or source being described.
2. Inventory the requested Markdown files and identify their intended audience and owner.
3. Verify technical claims against current source, configuration, scripts, tests, workflows, and neighboring documentation. Do not preserve a claim merely because another document repeats it.
4. Pay particular attention to multi-tenant boundaries, host-based tenant resolution, platform-versus-tenant connections, PostgreSQL support, Vite, Turbo Drive being disabled, current command paths, and the gated release workflow.
5. Replace volatile snapshots such as test counts, timings, baseline totals, deployment IDs, and version claims with commands or authoritative source paths.
6. Remove obsolete instructions and duplicated narratives. Link to the canonical owning document when a short pointer is more durable.
7. Preserve meaningful examples and each tool-specific document's intent. Clearly label placeholders and examples so they are not mistaken for factual paths or credentials.
8. Check headings, lists, code fences, local links, path casing, shell snippets, grammar, and accessibility of images or tables.
9. Review the final diff for unrelated changes and run documentation-appropriate validation.

## Safety

- Never print secrets or real tenant/member data while validating documentation.
- Treat startup, reset, seed, deploy, release, migration, and scanner commands as state-changing until their source proves otherwise.
- Do not execute destructive or external operations merely to validate a snippet.
- Do not add per-page change logs; use version control and provide one concise audit summary after the work is complete.
