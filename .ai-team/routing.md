# Work Routing

How to decide who handles what.

## Routing Table

| Work Type | Route To | Examples |
|-----------|----------|----------|
| Architecture & scope | Mal | System design, plugin structure, decisions, trade-offs |
| Code review | Mal | Review PRs, check quality, suggest improvements |
| PHP / CakePHP backend | Kaylee | Controllers, models, tables, entities, services, migrations |
| Database & queries | Kaylee | Schema changes, MariaDB, query optimization |
| Workflow engine | Kaylee | Workflow definitions, states, transitions, approval gates |
| Plugin development | Kaylee | Plugin PHP code, plugin bootstrapping, plugin services |
| Stimulus.JS controllers | Wash | JavaScript controllers, data attributes, outlet patterns |
| Templates & views | Wash | CakePHP templates, view cells, tab ordering, HTML |
| CSS & styling | Wash | Bootstrap, custom CSS, responsive layout |
| Asset pipeline | Wash | Laravel Mix, webpack, JS/CSS compilation |
| Testing | Jayne | PHPUnit tests, fixtures, integration tests, edge cases |
| Quality & edge cases | Jayne | Find bugs, verify fixes, test coverage |
| Scope & priorities | Mal | What to build next, trade-offs, decisions |
| Session logging | Scribe | Automatic — never needs routing |

## Rules

1. **Eager by default** — spawn all agents who could usefully start work, including anticipatory downstream work.
2. **Scribe always runs** after substantial work, always as `mode: "background"`. Never blocks.
3. **Quick facts → coordinator answers directly.** Don't spawn an agent for "what port does the server run on?"
4. **When two agents could handle it**, pick the one whose domain is the primary concern.
5. **"Team, ..." → fan-out.** Spawn all relevant agents in parallel as `mode: "background"`.
6. **Anticipate downstream work.** If a feature is being built, spawn the tester to write test cases from requirements simultaneously.
