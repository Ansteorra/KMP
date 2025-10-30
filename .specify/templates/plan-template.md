# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]
**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

**Language/Version**: PHP 8.1+  
**Primary Dependencies**: CakePHP 5.x, Hotwired (Turbo + Stimulus.JS), Bootstrap, Laravel Mix  
**Storage**: MySQL 5.7+ or MariaDB 10.2+  
**Testing**: PHPUnit (PHP), Playwright (UI tests - optional)  
**Target Platform**: Web application (responsive, multi-browser)
**Project Type**: Web application (CakePHP MVC+ with plugins)  
**Architecture**: Plugin-based modular architecture, Service layer for business logic, Turbo Frames for partial updates  
**Performance Goals**: Standard web application performance; <500ms page load, efficient database queries, fast partial updates via Turbo  
**Constraints**: CakePHP conventions, PSR-12 coding standards, multi-tenant SCA kingdom support, Hotwired patterns for frontend  
**Scale/Scope**: Multi-kingdom deployment, thousands of members per kingdom, complex role-based permissions, multi-tab/multi-grid interfaces

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Verify compliance with KMP Constitution (`.specify/memory/constitution.md`):

- [ ] **CakePHP Conventions**: Follows directory structure, naming conventions, MVC+ pattern, and routing standards
- [ ] **Plugin Architecture**: Implemented as plugin if self-contained feature; follows plugin structure and integration points
- [ ] **Hotwired Stack**: Uses Turbo Frames/Streams for partial updates (especially in multi-tab/multi-grid scenarios); Stimulus controllers for interactivity
- [ ] **Test Coverage**: PHPUnit tests planned for controllers, models, services; fixtures prepared for database tests
- [ ] **Security & Authorization**: Authentication/authorization considered; Policy classes planned if needed
- [ ] **Service Layer**: Complex business logic placed in Service classes, not Controllers or Models
- [ ] **Code Quality**: Code will follow PSR-12, type declarations, PHPDoc comments, and pass CS checks

**Justification for any deviations**: [Explain any necessary deviations from constitution principles]

## Project Structure

### Documentation (this feature)

```
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (KMP CakePHP Structure)

**For Core Features**:
```
app/
├── src/
│   ├── Controller/         # Controllers for this feature
│   ├── Model/
│   │   ├── Entity/        # Entity classes
│   │   └── Table/         # Table classes
│   ├── Services/          # Service layer for business logic
│   ├── Policy/            # Authorization policies
│   └── View/              # View cells and helpers
├── templates/             # View templates
├── assets/
│   ├── css/              # Feature-specific CSS
│   └── js/
│       └── controllers/  # Stimulus.JS controllers
├── tests/
│   └── TestCase/
│       ├── Controller/   # Controller tests
│       ├── Model/        # Model tests
│       └── Service/      # Service tests
└── config/
    └── Migrations/       # Database migrations
```

**For Plugin-Based Features**:
```
app/plugins/[FeatureName]/
├── src/
│   ├── [FeatureName]Plugin.php  # Main plugin class
│   ├── Controller/              # Plugin controllers
│   ├── Model/                   # Plugin models
│   ├── Services/                # Plugin services (including NavigationProvider)
│   ├── Event/                   # Event listeners (CallForCellsHandler, etc.)
│   └── Policy/                  # Plugin authorization policies
├── templates/                   # Plugin templates
├── assets/
│   ├── css/                     # Plugin CSS
│   └── js/
│       └── controllers/         # Plugin Stimulus controllers
├── tests/                       # Plugin tests
├── config/
│   ├── Migrations/              # Plugin migrations
│   └── bootstrap.php            # Plugin bootstrap
└── webroot/                     # Plugin public assets
```

**Structure Decision**: [Choose "Core Feature" or "Plugin-Based Feature" and explain the decision based on feature scope and reusability]

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
