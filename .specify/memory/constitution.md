<!--
Sync Impact Report:
Version: 1.0.0 → 1.1.0
Rationale: MINOR version bump - Added Turbo (Hotwired) guidance to Principle III
Modified Principles: 
  - Principle III: Expanded to include Turbo for partial page renders
Added Sections: None
Removed Sections: None
Templates Status:
  ✅ plan-template.md - updated with Turbo in technical context
  ✅ spec-template.md - no changes needed (constitution-agnostic)  
  ✅ tasks-template.md - updated with Turbo-related tasks
  ✅ checklist-template.md - not modified (constitution-agnostic)
  ✅ agent-file-template.md - not modified (constitution-agnostic)
Follow-up TODOs: None
-->

# Kingdom Management Portal (KMP) Constitution

## Core Principles

### I. CakePHP Convention Over Configuration

All development MUST follow CakePHP 5.x conventions and best practices:
- **Directory Structure**: Adhere to standard CakePHP structure (`src/`, `templates/`, `plugins/`, `webroot/`)
- **Naming Conventions**: Controllers (PascalCase + Controller suffix), Models (singular PascalCase), Tables (plural PascalCase + Table suffix), templates (lowercase snake_case)
- **MVC+ Pattern**: Extend MVC with Service layer for business logic, Event system for loose coupling, Behaviors for model extensions, Components for controller extensions
- **Routing**: Use conventional routing in `config/routes.php` following RESTful patterns
- **Database**: All schema changes MUST use migrations; follow CakePHP naming conventions for tables, columns, and foreign keys

**Rationale**: Convention over configuration reduces cognitive load, ensures consistency across the codebase, and leverages CakePHP's built-in features. Deviation from conventions requires explicit justification and approval.

### II. Plugin-Based Architecture

Features MUST be organized as plugins when they represent cohesive, self-contained functionality:
- **Plugin Structure**: Each plugin MUST follow standard structure with `PluginNamePlugin.php` main class, `src/`, `templates/`, `assets/`, `config/`, and `tests/` directories
- **Registration**: Plugins MUST be registered in `config/plugins.php` with explicit migration order
- **Integration Points**: Plugins integrate via NavigationProvider (menus), CallForCellsHandler (UI components), and Event system
- **Independence**: Plugins SHOULD be independently testable and minimally coupled to core and other plugins
- **Asset Organization**: Plugin-specific assets MUST reside in `plugins/PluginName/assets/` with CSS and JS subdirectories

**Rationale**: Plugin architecture enables modular development, clear boundaries, independent testing, and potential reuse across SCA kingdoms. It supports the extensible nature required for customization per kingdom.

### III. Hotwired Stack for Frontend (NON-NEGOTIABLE)

All frontend functionality MUST use the Hotwired stack (Turbo + Stimulus):

**Turbo for Partial Page Renders**:
- **Turbo Frames**: Use `<turbo-frame>` for lazy-loading and targeted updates in multi-tab, multi-grid scenarios
- **Turbo Streams**: Use for real-time updates and server-pushed changes without full page reload
- **Navigation**: Turbo Drive handles automatic AJAX navigation with history management
- **Frame Targeting**: Use `data-turbo-frame` attribute to target specific frames for updates
- **Best Practice**: Ideal for tabbed interfaces, modal content, paginated grids, and filtered lists

**Stimulus.JS for JavaScript Interactivity**:
- **Controller Pattern**: Follow standard structure with `static targets`, `static values`, `static outlets`, `connect()`, event handlers, and `disconnect()`
- **File Organization**: Place controllers in `assets/js/controllers/` with `-controller.js` suffix (or `plugins/PluginName/assets/js/controllers/` for plugin-specific)
- **Registration**: Controllers MUST be added to `window.Controllers` registry and registered with Stimulus application in `assets/js/index.js`
- **HTML Integration**: Use data attributes (`data-controller`, `data-[controller]-target`, `data-action`) for binding
- **Turbo Integration**: Stimulus controllers work seamlessly with Turbo Frame updates and navigation

**No Inline JavaScript**: Avoid inline JavaScript in templates; use Turbo and Stimulus patterns instead

**Rationale**: The Hotwired stack (Turbo + Stimulus) provides a lightweight, server-centric approach that aligns with CakePHP's server-rendered patterns. Turbo eliminates most JavaScript for navigation and updates, while Stimulus handles interactive behaviors. This combination avoids heavy framework overhead while maintaining rich user experiences, particularly in complex multi-tab and multi-grid scenarios common in membership management.

### IV. Test-Driven Development

Testing is mandatory for all new features and changes:
- **PHPUnit Tests**: All controllers, models, services, and commands MUST have corresponding PHPUnit tests
- **Test Organization**: Tests MUST mirror source structure in `tests/TestCase/` with appropriate subdirectories
- **Fixtures**: Use fixtures for database-dependent tests; fixtures MUST be maintained with schema changes
- **Integration Tests**: Use `IntegrationTestTrait` for controller tests to test full request/response cycle
- **Coverage**: Aim for meaningful coverage; critical business logic MUST have comprehensive test coverage
- **Test Execution**: Tests MUST pass before code review; run via `composer test` or `vendor/bin/phpunit`

**Rationale**: Tests ensure reliability, enable confident refactoring, serve as documentation, and prevent regressions. Testing discipline is essential for a membership management system handling sensitive data.

### V. Security and Authorization

Security MUST be enforced at multiple layers:
- **Authentication**: Use CakePHP Authentication plugin; configure in `Application.php`
- **Authorization**: Use CakePHP Authorization plugin with Policy classes for resource-level access control
- **Policy Pattern**: Create Policy classes in `src/Policy/` for each protected entity; use `canAccess`, `canEdit`, `canDelete` methods
- **Controller Authorization**: Call `$this->Authorization->authorizeModel()` in controller `initialize()` for model-level checks
- **RBAC**: Leverage role-based access control; roles stored in database with warrant-based assignments
- **Input Validation**: All user input MUST be validated; use Entity validation rules and Form objects
- **SQL Injection**: Use ORM query builder; NEVER concatenate user input into raw SQL

**Rationale**: KMP handles sensitive member information for SCA kingdoms. Multi-layer security ensures data protection, privacy, and compliance with organizational policies.

### VI. Service Layer for Business Logic

Complex business logic MUST reside in Service classes, not Controllers or Models:
- **Service Location**: Place services in `src/Services/` (core) or `plugins/PluginName/src/Services/` (plugin-specific)
- **Service Pattern**: Services encapsulate multi-step operations, coordinate between models, interact with external APIs, and dispatch events
- **ServiceResult Pattern**: Services SHOULD return `ServiceResult` objects with success/failure status and data/error messages
- **Examples**: `WarrantManager`, `ActiveWindowManager`, `CsvExportService`, `NavigationRegistry`, `ViewCellRegistry`
- **Controller Responsibility**: Controllers orchestrate requests by calling services and rendering views; they MUST NOT contain business logic
- **Testability**: Services MUST be independently testable with mocked dependencies

**Rationale**: Service layer separates concerns, enables reuse, simplifies testing, and keeps controllers thin. Business logic in services is easier to maintain and evolve than logic scattered across controllers and models.

### VII. Code Quality and Standards

All code MUST meet quality standards enforced by automated tools:
- **PHP Standards**: Follow PSR-12 via PHP_CodeSniffer with CakePHP ruleset; run `composer cs-check` and `composer cs-fix`
- **Type Declarations**: Use strict types (`declare(strict_types=1);`), type hints for parameters, and return type declarations
- **Static Analysis**: Code MUST pass PHPStan checks; configuration in `phpstan.neon`
- **Documentation**: PHPDoc blocks REQUIRED for all classes, methods, and properties; explain "why" not just "what"
- **JavaScript Standards**: Follow ESLint configuration extending Standard JS style; run `npm run lint`
- **Line Length**: PHP and JS lines SHOULD NOT exceed 120 characters
- **Indentation**: Use 4 spaces for PHP, follow ESLint config for JS; NO TABS

**Rationale**: Consistent code quality and standards reduce friction, improve readability, ease maintenance, and prevent common errors. Automated enforcement ensures compliance without manual oversight.

## Technology Stack Requirements

### Mandatory Technologies

- **Backend Framework**: CakePHP 5.x
- **PHP Version**: 8.1 or higher with required extensions (intl, mbstring, xml, openssl, sodium, json, pdo_mysql, mysqli, gd, zip, yaml, posix, opcache)
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Frontend Framework**: Hotwired Stack (Turbo + Stimulus.JS)
  - **Turbo**: Partial page updates, frame-based rendering, stream updates
  - **Stimulus.JS**: JavaScript interactivity and behavior
- **CSS Framework**: Bootstrap (via Bootstrap plugin)
- **Asset Compilation**: Laravel Mix (Webpack wrapper) configured in `webpack.mix.js`
- **Package Management**: Composer (PHP dependencies), NPM (JavaScript dependencies)
- **Testing**: PHPUnit for PHP, Playwright for UI tests (optional)

### Development Environment

- **Dev Container**: Use provided `.devcontainer` configuration for consistent development environment
- **Version Control**: Git with semantic commit messages (feat, fix, docs, refactor, test)
- **Branching**: Feature branches (`feature/feature-name`), bugfix branches (`fix/bug-description`)
- **Database Seeding**: Use provided seed scripts (`dev_seed_clean.sql`, `make_amp_seed_db.sh`)
- **Migrations**: All database changes via CakePHP migrations; use `bin/cake migrations` commands

### Browser Support

- Chrome, Firefox, Safari, Edge (latest 2 versions)
- Mobile browser support for iOS and Android devices
- Responsive design for various screen sizes

## Development Workflow

### Code Development Process

1. **Branch Creation**: Create feature/fix branch from main with descriptive name
2. **Implementation**: Follow TDD where applicable; write tests before implementation
3. **Code Standards**: Run `composer cs-check` and `npm run lint` before committing
4. **Testing**: Run `composer test` to ensure all tests pass
5. **Documentation**: Update relevant documentation files in `docs/` if architecture or workflow changes
6. **Commit**: Use semantic commit messages with clear descriptions
7. **Pull Request**: Submit PR with description of changes, reference to issues/specs
8. **Code Review**: Address review feedback; ensure tests and standards checks pass
9. **Merge**: Merge to main after approval

### Migration Management

- Create migrations via `bin/cake bake migration DescriptiveName`
- Test migrations with `bin/cake migrations migrate` and `bin/cake migrations rollback`
- Migrations MUST be reversible; implement `down()` method
- Plugin migrations follow plugin order in `config/plugins.php`
- Never modify committed migrations; create new migration for changes

### Asset Management

- Source assets in `assets/css/` and `assets/js/` (core) or `plugins/PluginName/assets/` (plugin-specific)
- Compile via `npm run dev` (development) or `npm run production` (production)
- Compiled assets output to `webroot/css/` and `webroot/js/`
- Use AssetMix helper in templates for versioned asset URLs
- Extract vendor bundles via Laravel Mix `extract()` method

### Documentation Requirements

- Update relevant files in `docs/` when adding features or changing architecture
- Update `.github/copilot-instructions.md` when establishing new patterns or conventions
- Include inline PHPDoc and JSDoc comments for complex logic
- Update README.md if setup or usage instructions change

## Governance

### Constitution Authority

This constitution supersedes all other practices and guidelines. In case of conflict between this document and other documentation, this constitution takes precedence.

### Amendment Process

1. **Proposal**: Amendments must be proposed with clear rationale and impact analysis
2. **Discussion**: Discuss impact on existing code, templates, and documentation
3. **Approval**: Amendments require approval from project maintainers
4. **Version Bump**: Follow semantic versioning:
   - **MAJOR**: Backward incompatible governance/principle removals or redefinitions
   - **MINOR**: New principle/section added or materially expanded guidance
   - **PATCH**: Clarifications, wording, typo fixes, non-semantic refinements
5. **Synchronization**: Update all dependent templates, documentation, and guidance files
6. **Migration Plan**: Provide migration plan if existing code must change to comply
7. **Documentation**: Update Sync Impact Report at top of this file

### Compliance Verification

- All pull requests MUST verify compliance with this constitution
- Code reviewers MUST check adherence to principles and standards
- Automated checks (CS, tests, linting) MUST pass before merge
- Complexity or deviations MUST be explicitly justified in PR description
- Use `.github/copilot-instructions.md` for detailed runtime development guidance

### Guidance Hierarchy

1. **Constitution** (this file): Core principles and non-negotiable rules
2. **Copilot Instructions** (`.github/copilot-instructions.md`): Detailed patterns, examples, and conventions
3. **Documentation** (`docs/`): Architecture, features, and usage guides
4. **Code Comments**: Implementation-specific guidance and rationale

**Version**: 1.1.0 | **Ratified**: 2025-10-07 | **Last Amended**: 2025-10-07