---
name: double-check-docs
description: Audits KMP documentation against current source for accuracy, relevance, consistency, links, and readability.
---

# Double-check KMP documentation

Read and execute `.github/prompts/doublcheckdocs.prompt.md` in full. It is the canonical documentation-audit workflow.

Verify claims against source rather than duplicated prose, with special attention to tenant/platform boundaries, PostgreSQL, Vite, Turbo Drive being disabled, current commands, and the gated release contract. Preserve each document's audience and intent, label examples clearly, validate links and code fences, and provide one concise audit summary rather than per-page change logs.
