---
name: code-verification
description: Comprehensive code verification toolkit for the KMP application. Run all quality checks (PHPUnit, Jest, Webpack, PHPCS, PHPStan) and get guidance on writing tests and verifying production readiness.
---

# Code Verification for KMP

This skill provides instructions for verifying code changes in the KMP application to ensure production readiness.

## Quick Verification

Run the full verification suite from `/workspaces/KMP/app`:

```bash
bash bin/verify.sh
```

This runs PHPUnit, Jest, Webpack, PHPCS, and PHPStan in order and provides a summary.

## Individual Checks

| Check | Command | Expected Result |
|-------|---------|----------------|
| PHPUnit | `composer test` | 1018+ tests, 0 failures |
| Jest | `npm run test:js` | 27+ tests, 0 failures |
| Webpack | `npm run dev` | "compiled successfully" |
| PHPCS | `composer cs-check` | 0 violations in changed files |
| PHPStan | `composer stan` | 1 known baseline error only |

## E2E Testing

E2E tests require a running server and database reset:

```bash
# Ensure services are running
bash /workspaces/KMP/dev-up.sh

# Run E2E tests (resets DB automatically)
cd /workspaces/KMP/app
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8080 npm run test:ui
```

## When to Run What

| Change Type | Minimum Verification |
|------------|---------------------|
| PHP code (controller/model/service/policy) | PHPUnit + PHPCS + PHPStan |
| JavaScript (Stimulus controller) | Jest + Webpack |
| Template (.php view) | PHPUnit (feature tests) + Webpack |
| Database migration | PHPUnit (full suite) + E2E |
| Configuration change | Full verify.sh |
| CSS/asset change | Webpack only |

## Writing Tests for New Code

### PHPUnit Tests

**For a new controller action:**
1. Create test in `tests/TestCase/Controller/` or `tests/TestCase/Core/Feature/`
2. Extend `HttpIntegrationTestCase`
3. Call `$this->enableCsrfToken()`, `$this->enableSecurityToken()`, `$this->authenticateAsSuperUser()` in setUp
4. Test both success path and error/authorization paths

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class NewFeatureTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testIndexReturnsOk(): void
    {
        $this->get('/new-feature');
        $this->assertResponseOk();
    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->session([]);
        $this->get('/new-feature');
        $this->assertRedirectContains('/members/login');
    }
}
```

**For a new model/table:**
1. Create test in `tests/TestCase/Model/Table/`
2. Extend `BaseTestCase`
3. Use seed data via `self::ADMIN_MEMBER_ID` etc.
4. Test validation rules, finder methods, and associations

**For a new service:**
1. Create test in `tests/TestCase/Services/`
2. Extend `BaseTestCase`
3. Test business logic with various input scenarios

**For plugin code:**
1. Create test in `tests/TestCase/Plugins/{Plugin}/` or `plugins/{Plugin}/tests/TestCase/`
2. Extend `PluginIntegrationTestCase` with `protected const PLUGIN_NAME = 'PluginName'`

### Jest Tests

**For a new Stimulus controller:**
1. Create test in `tests/js/controllers/{name}-controller.test.js`
2. Use jsdom environment (configured in jest.config.js)
3. Mock Stimulus via `tests/js/__mocks__/stimulus.js`

```javascript
describe('MyNewController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="my-new"
                 data-my-new-url-value="/api/test">
                <input data-my-new-target="input" />
                <button data-action="click->my-new#submit">Submit</button>
            </div>
        `;
    });

    it('should initialize with default state', () => {
        // Test initial DOM state
    });

    it('should handle user interaction', () => {
        // Test click/input handling
    });
});
```

### Playwright BDD Tests

**For a new user flow:**
1. Create feature directory: `tests/ui/bdd/@feature-name/`
2. Create `FeatureName.feature` with Gherkin scenarios
3. Create `steps.js` with step definitions
4. Reuse SharedSteps.js for common actions (login, navigation, forms, grids)

```gherkin
Feature: New Feature
    As a user
    I want to do something
    So that I get value

    Scenario: Basic flow
        Given I am logged in as "admin@test.com"
        When I navigate to "/new-feature"
        Then I should be on a page containing "Expected Content"
        And I should see a success message
```

## Edge Case Testing Checklist

When writing tests for new features, always cover:

### Authorization Edge Cases
- [ ] Unauthenticated user is redirected to login
- [ ] User without permission gets 403 or redirect
- [ ] Super user can access everything
- [ ] Branch-scoped access works correctly

### Data Validation Edge Cases
- [ ] Empty/null required fields rejected
- [ ] String length limits enforced
- [ ] Invalid email formats rejected
- [ ] Duplicate unique fields rejected (e.g., email)
- [ ] Foreign key references validated

### CRUD Edge Cases
- [ ] Create with valid data succeeds
- [ ] Create with invalid data shows errors
- [ ] Edit preserves unchanged fields
- [ ] Delete handles cascade/restrict correctly
- [ ] View non-existent record returns 404

### Date/Timezone Edge Cases
- [ ] Dates display in correct kingdom timezone
- [ ] Date comparisons use consistent timezone
- [ ] Mailer dates are pre-formatted with TimezoneHelper

### Security Edge Cases
- [ ] CSRF token required on all POST/PUT/DELETE
- [ ] XSS: all output uses `h()` helper
- [ ] IDOR: users can't access other users' data
- [ ] File uploads validate type and size

## Database Reset

If tests fail due to corrupted database state:

```bash
cd /workspaces/KMP
sudo bash reset_dev_database.sh
```

This drops and recreates the database, loads seed data, runs migrations, and resets all passwords to "TestPassword".

## Mutation Testing

Mutation testing verifies test quality by introducing small changes (mutants) to source code and checking if tests catch them.

### JavaScript (StrykerJS)

```bash
npm run test:mutate          # Security-critical controllers only
npm run test:mutate:all      # All JS source files
```

Target controllers: mobile-pin-gate, face-photo-validator, login-device-auth, member-mobile-card-pwa, member-mobile-card-profile.

HTML report: `tests/mutation-reports/stryker-report.html`

### PHP (Infection)

```bash
composer mutate              # Policies + Awards state machine
composer mutate:policy       # Just authorization policies
```

Scoped to: `src/Policy/`, `plugins/Awards/src/Model/Entity/`, `plugins/Awards/src/Model/Table/`

HTML report: `tests/mutation-reports/infection.html`

### When to Run Mutation Testing

- After writing new tests for security-critical code
- When hardening authorization policies
- Before releasing changes to the awards recommendation workflow
- When a survived mutant is reported — write a test that kills it

### Interpreting Results

- **Killed**: Test caught the mutation ✅
- **Survived**: Mutation wasn't detected — write a better test ⚠️
- **No Coverage**: No test reaches this code — add test coverage first
- **Timed Out**: Test hung on the mutation — usually counts as "detected"

## Troubleshooting

| Problem | Solution |
|---------|----------|
| PHPUnit "table not found" | Run `sudo bash reset_dev_database.sh` |
| Jest "cannot find module" | Run `cd app && npm install` |
| Webpack fails | Run `cd app && npm install && npm run dev` |
| PHPCS violations | Run `composer cs-fix` to auto-fix |
| PHPStan > 9 errors | Check if you introduced type errors; baseline handles pre-existing issues |
| Playwright fails | Ensure server is running: `bash dev-up.sh` |
| MariaDB not running | Run `sudo service mariadb start` |
