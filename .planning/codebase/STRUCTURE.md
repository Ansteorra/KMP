# Directory Structure

## Root (`/workspaces/KMP/`)

```
KMP/
├── app/                    # Main CakePHP application (PHP/JS/CSS source + vendor)
├── docker/                 # Docker container configs (PHP, Apache, DB)
├── docker-compose.yml      # Local dev environment definition
├── docs/                   # Architecture and feature documentation
├── cronScripts/            # Cron wrapper scripts for production (runCakeCommand.sh)
├── deploy/                 # Deployment scripts and configs
├── installer/              # Installation/setup scripts
├── .github/workflows/      # CI/CD pipelines (tests.yml, nightly.yml, release.yml)
├── dev-up.sh               # Start Docker dev environment
├── dev-down.sh             # Stop Docker dev environment
├── reset_dev_database.sh   # Reset DB to seed state
├── fix_permissions.sh      # Fix Apache file ownership
├── security-checker.sh     # Security vulnerability scan
├── create_erd.sh           # Generate ERD from schema
├── CLAUDE.md               # Claude Code project instructions
├── AGENTS.md               # Comprehensive agent guide
└── README.md               # Project overview
```

## `app/` — Application Root

```
app/
├── src/                    # PHP application source
├── plugins/                # Self-contained feature plugins
├── templates/              # Twig/PHP view templates
├── assets/                 # Frontend source (JS/CSS — NOT served directly)
├── webroot/                # Publicly served static files (compiled JS/CSS, images)
├── config/                 # Application configuration
├── tests/                  # All test code
├── vendor/                 # Composer PHP dependencies (gitignored)
├── node_modules/           # NPM JS dependencies (gitignored)
├── bin/                    # CLI scripts (verify.sh, cake console)
├── composer.json           # PHP dependencies
├── package.json            # JS dependencies
├── webpack.mix.js          # Laravel Mix asset pipeline config
├── phpunit.xml.dist        # PHPUnit configuration
├── phpstan.neon            # PHPStan static analysis config
├── phpstan-baseline.neon   # PHPStan baseline (1947 suppressed errors)
├── phpcs.xml               # PHP CodeSniffer ruleset
├── jest.config.js          # Jest JS test config
├── stryker.config.js       # StrykerJS mutation test config
├── infection.json5         # PHP Infection mutation test config
└── playwright.config.js    # Playwright E2E test config
```

## `app/src/` — PHP Source

```
src/
├── Application.php         # App bootstrap, middleware stack, DI container bindings
├── Controller/             # HTTP controllers (plural PascalCase, e.g. MembersController)
├── Model/
│   ├── Entity/             # ORM entities (singular PascalCase, e.g. Member)
│   └── Table/              # ORM table classes (plural + Table suffix, e.g. MembersTable)
├── Policy/                 # Authorization policy classes (per-entity, e.g. MemberPolicy)
├── Services/               # Business logic service classes with interfaces
├── View/                   # View helpers and cells
├── Mailer/                 # Email mailer classes
├── Command/                # CakePHP CLI commands
├── Console/                # Console shell helpers
├── Middleware/             # HTTP middleware (impersonation, etc.)
├── Form/                   # Form objects for non-entity forms
├── Authenticator/          # Custom authentication adapters
├── Authorization/          # Custom authorization adapters
├── Identifier/             # Auth identifier classes
├── Event/                  # Event listener/handler classes
├── Error/                  # Error handler customization
├── Queue/                  # Background job queue handlers
└── KMP/                    # Framework-level utilities
    ├── GridColumns/        # Dataverse grid column definitions
    ├── StaticHelpers.php   # Global utility (app settings, DB queries — no DI)
    ├── TimezoneHelper.php  # Date/time formatting for mailers and views
    ├── PermissionsLoader.php # Loads authorization permissions
    ├── KMPPluginInterface.php # Interface all plugins must implement
    └── KmpIdentityInterface.php # Auth identity contract
```

## `app/templates/` — View Templates

```
templates/
├── layout/                 # Base page layouts (default, login, etc.)
├── element/                # Reusable template partials
├── cell/                   # ViewCell templates
├── email/                  # Email-specific templates (html/ + text/)
├── EmailTemplates/         # User-editable email template management UI
├── Error/                  # Error page templates (400, 404, 500)
├── Members/                # Member management views
├── Branches/               # Branch hierarchy views
├── Roles/                  # Role management views
├── MemberRoles/            # Member-role assignment views
├── Permissions/            # Permission management views
├── Gatherings/             # Event/gathering views
├── GatheringActivities/    # Gathering activity views
├── GatheringAttendances/   # Attendance tracking views
├── GatheringStaff/         # Event staff views
├── GatheringTypes/         # Gathering type config views
├── AppSettings/            # Application settings views
├── Backups/                # Database backup views
├── Reports/                # Reporting views
└── Pages/                  # Static pages (home, etc.)
```

## `app/assets/` — Frontend Source

```
assets/
├── js/
│   ├── index.js            # Entry point — starts Stimulus, registers all controllers
│   ├── KMP_utils.js        # Shared JS utilities
│   ├── timezone-utils.js   # Client-side timezone helpers
│   ├── controllers/        # 30+ Stimulus controllers (*-controller.js)
│   └── services/           # JS service modules (offline-queue, quick-login, rsvp-cache)
└── css/
    ├── app.css             # Main app stylesheet
    ├── bootstrap.css       # Bootstrap customizations
    ├── bootstrap-icons.css # Icon font
    ├── dashboard.css       # Dashboard layout
    ├── gatherings_public.css # Public-facing gathering pages
    └── signin.css          # Login page styles
```

## `app/plugins/` — Feature Plugins

Each plugin is a self-contained module with its own controllers, models, templates, migrations, and tests.

```
plugins/
├── Awards/                 # Awards management — recommendations, gatherings, award types
│   ├── src/
│   │   ├── Controller/     # AwardsController, RecommendationsController (2,379 lines), etc.
│   │   ├── Model/          # Award, Recommendation, AwardsDomain entities + tables
│   │   ├── Policy/         # Authorization policies
│   │   ├── Services/       # RecommendationService, etc.
│   │   ├── View/           # ViewCells, Helpers
│   │   └── KMP/            # GridColumns definitions
│   ├── templates/          # Awards-specific views
│   ├── assets/             # Plugin-specific JS controllers
│   ├── config/Migrations/  # Database migrations
│   └── tests/              # Plugin test cases
│
├── Officers/               # Officer assignment and management
│   ├── src/
│   │   ├── Controller/     # OfficersController, etc.
│   │   ├── Model/          # Officer, Office entities
│   │   ├── Policy/         # Authorization policies
│   │   ├── Services/       # Officer business logic
│   │   ├── Mailer/         # Officer email notifications
│   │   └── View/           # ViewCells
│   ├── templates/
│   └── config/Migrations/
│
├── Activities/             # Activities (e.g., martial), waivers, gathering activities
│   ├── src/                # Controllers, models, services, policies
│   ├── templates/
│   └── config/Migrations/
│
├── Waivers/                # Waiver management (partial — Documents table is a stub)
│   ├── src/
│   └── config/Migrations/
│
├── Queue/                  # Background job queue (vendor package integration)
│
├── Template/               # Email template management plugin
│
├── Bootstrap/              # App bootstrap/initialization plugin (no tests)
│
└── GitHubIssueSubmitter/   # GitHub issue submission from within the app
```

## `app/config/` — Configuration

```
config/
├── app.php                 # Core app config (database, cache, email, security)
├── app_local.php           # Local overrides (gitignored, generated from .env)
├── app_queue.php           # Queue plugin config
├── bootstrap.php           # App bootstrap (plugin loading, etc.)
├── bootstrap_cli.php       # CLI-specific bootstrap
├── bootstrap_tests.php     # Test environment bootstrap
├── paths.php               # Path constants
├── plugins.php             # Plugin registration with migrationOrder
├── routes.php              # URL routing definitions
├── requirements.php        # PHP requirement checks
├── Migrations/             # Core database migrations (numbered, sequential)
├── Seeds/                  # Database seed files for dev/test
├── schema/                 # Raw SQL schema dumps
├── schema_dump.sql         # Current schema snapshot
└── version.txt             # App version string
```

## `app/tests/` — Test Code

```
tests/
├── bootstrap.php           # PHPUnit bootstrap (loads seed data via SeedManager)
├── TestCase/
│   ├── BaseTestCase.php    # Base class — wraps each test in a DB transaction
│   ├── TestAuthenticationHelperTrait.php  # Auth helpers for controller tests
│   ├── TestDatabaseTrait.php  # DB utilities
│   ├── Support/
│   │   └── HttpIntegrationTestCase.php  # Base for HTTP controller tests
│   ├── Controller/         # Controller integration tests
│   ├── Model/              # Table/entity unit tests
│   ├── Services/           # Service layer tests
│   ├── Policy/             # Authorization policy tests
│   ├── Command/            # CLI command tests
│   ├── Middleware/         # Middleware tests
│   ├── KMP/                # KMP utility tests
│   ├── Core/               # Core feature tests
│   ├── Plugins/            # Tests for plugin-level code loaded in core
│   └── View/               # View helper tests
├── js/
│   ├── __mocks__/
│   │   └── stimulus.js     # Stimulus mock for unit tests
│   └── controllers/        # ~94 *.test.js files for Stimulus controllers
└── ui/
    ├── bdd/                # Gherkin BDD feature files (@auth/, @members/, @awards/, @activities/)
    └── gen/                # Generated Playwright specs (from npx bddgen)
```
