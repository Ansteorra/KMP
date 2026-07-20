---
name: double-check-docs
description: Reviews KMP documentation for clarity, consistency, completeness, accuracy, and grammar. Use when auditing docs or user asks to review documentation quality.
---

# Double-Check Documentation

Technical writing review of project documentation.

## Workflow

**Read and execute** `.github/prompts/doublcheckdocs.prompt.md` in full.

## Review criteria

1. **Clarity** — understandable for target audience
2. **Consistency** — terminology, formatting, style
3. **Completeness** — no content gaps
4. **Accuracy** — verify against code when unsure (highest priority)
5. **Grammar/spelling**

## Scope

- Review each page in `docs/` (skip API-only folders if instructed)
- Maintain original tone while improving
- Provide summary of changes per page

When uncertain about technical details, read the relevant source code before editing.
