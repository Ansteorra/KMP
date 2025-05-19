# KMP Modernization Action Plan

## Phase 1: Assessment and Foundation

1.  **Detailed Codebase Audit:**
    *   **Goal:** Understand the current state, identify technical debt, and pinpoint areas that deviate from the established best practices.
    *   **Actions:**
        *   Run static analysis tools (PHPStan, Psalm, ESLint) to identify code quality issues.
        *   Review existing controllers, models, and services for complexity and adherence to separation of concerns.
        *   Analyze JavaScript and Stimulus controller implementations.
        *   Assess current test coverage for both backend and frontend.
2.  **Strengthen Coding Standards & Automation:**
    *   **Goal:** Ensure consistent code quality and automate checks.
    *   **Actions:**
        *   Verify `phpcs.xml`, `phpstan.neon`, and `psalm.xml` are up-to-date and enforced.
        *   Integrate linters and formatters into a pre-commit hook (e.g., using Husky for JavaScript and a PHP equivalent).
3.  **Dependency Review and Update:**
    *   **Goal:** Ensure all backend and frontend dependencies are current and secure.
    *   **Actions:**
        *   Review `composer.json` and `package.json` for outdated or vulnerable packages.
        *   Plan a strategy for updating major dependencies (e.g., CakePHP, Stimulus, Bootstrap) if significant updates are available and beneficial.

## Phase 2: Transitioning to an API-Centric Architecture

1.  **Define API Strategy:**
    *   **Goal:** Establish a clear approach for API development.
    *   **Actions:**
        *   Choose an API standard (e.g., RESTful with JSON:API or a simpler JSON structure, or consider GraphQL for complex data needs).
        *   Define API versioning strategy (e.g., URL-based `/api/v1/...`).
2.  **Develop Core API Endpoints:**
    *   **Goal:** Expose core functionalities through APIs.
    *   **Actions:**
        *   Identify key resources (e.g., Members, Branches, Awards, Activities) that need API exposure.
        *   Create dedicated API controllers (e.g., in `src/Controller/Api/V1/MembersController.php`). These controllers should be lean and delegate business logic to service classes.
        *   Ensure API responses are consistent and well-documented (consider using tools like Swagger/OpenAPI).
3.  **API Authentication and Authorization:**
    *   **Goal:** Secure API endpoints.
    *   **Actions:**
        *   Implement token-based authentication (e.g., JWT) for APIs, leveraging CakePHP's Authentication plugin.
        *   Apply CakePHP's Authorization plugin and policies to API endpoints to control access to resources.
4.  **Refactor Existing Functionality to Consume APIs (Gradual):**
    *   **Goal:** Decouple frontend from direct backend rendering where appropriate.
    *   **Actions:**
        *   Identify parts of the application where Stimulus.js controllers can fetch data from the new API endpoints instead of relying on server-rendered data embedded in templates.
        *   Update Stimulus controllers to use `fetch` for API communication, incorporating robust error handling as per the guidelines.

## Phase 3: Backend Modernization (CakePHP)

1.  **Service Layer Refinement:**
    *   **Goal:** Enhance separation of concerns and reusability.
    *   **Actions:**
        *   Review existing service classes in `src/Services/`.
        *   Move business logic from controllers (both web and API) into these service classes. Controllers should primarily handle request/response and delegate to services.
        *   Ensure services are easily testable.
2.  **Database and ORM Optimization:**
    *   **Goal:** Improve database performance and maintainability.
    *   **Actions:**
        *   Review database schema for adherence to conventions (plural, lowercase, underscores).
        *   Ensure all schema changes are managed through migrations.
        *   Optimize queries and ensure proper use of CakePHP's ORM features (associations, behaviors, finders).
3.  **Enhanced Error Handling and Logging:**
    *   **Goal:** Improve debugging and system stability.
    *   **Actions:**
        *   Standardize exception handling across the application.
        *   Ensure meaningful logging with sufficient context for both PHP and API errors.

## Phase 4: Frontend Modernization (Stimulus.js & Assets)

1.  **Stimulus.js Controller Best Practices:**
    *   **Goal:** Ensure all Stimulus controllers are clean, maintainable, and follow the project's conventions.
    *   **Actions:**
        *   Review all Stimulus controllers (`assets/js/controllers/` and `plugins/*/assets/js/controllers/`) for adherence to the defined structure (targets, values, outlets, lifecycle methods).
        *   Refactor complex controllers into smaller, more focused ones if necessary.
        *   Ensure consistent use of HTML data attributes.
2.  **JavaScript Modularity and Utilities:**
    *   **Goal:** Improve JavaScript organization and code reuse.
    *   **Actions:**
        *   Review `assets/js/KMP_utils.js` and other utility modules. Consolidate and organize shared JavaScript functions.
        *   Ensure `assets/js/index.js` correctly initializes Stimulus and registers all controllers.
3.  **Asset Compilation and Management:**
    *   **Goal:** Optimize asset delivery.
    *   **Actions:**
        *   Review `webpack.mix.js` configuration for efficiency.
        *   Ensure CSS and JavaScript assets are properly minified and versioned for production.
        *   Verify the AssetMix helper is used for all assets in templates.

## Phase 5: Testing and Quality Assurance

1.  **Expand Test Coverage:**
    *   **Goal:** Increase confidence in code changes and reduce regressions.
    *   **Actions:**
        *   Write PHPUnit tests for new API endpoints and service classes.
        *   Improve existing PHPUnit tests, focusing on integration and controller tests.
        *   Implement JavaScript tests for critical Stimulus controllers, focusing on DOM interactions and event handling (using Jest or a similar framework).
2.  **Integration Testing for API-Frontend Interaction:**
    *   **Goal:** Ensure seamless communication between the API and Stimulus controllers.
    *   **Actions:**
        *   Develop tests that simulate frontend API calls and verify the responses and UI updates.

## Phase 6: Documentation and Workflow

1.  **Update Documentation:**
    *   **Goal:** Keep project documentation current with the modernized codebase.
    *   **Actions:**
        *   Update `docs/` to reflect API-centric architecture, new API endpoints, and any changes to frontend/backend development practices.
        *   Document API usage with examples.
2.  **Reinforce Git Workflow:**
    *   **Goal:** Maintain a clean and understandable version history.
    *   **Actions:**
        *   Strictly enforce branch naming conventions (`feature/`, `fix/`, `release/`).
        *   Ensure all commits follow semantic commit message guidelines.

## General Maintainability Improvements

*   **Modularity:** Continue to design components (both backend and frontend) to be as modular and independent as possible.
*   **Readability:** Emphasize clear, well-commented code, especially for complex logic.
*   **Configuration over Code:** Utilize CakePHP's configuration system where possible to avoid hardcoding values.
