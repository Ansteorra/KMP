# Awards Plugin Controller Technical Requirements

This document details the business logic and technical requirements for each controller method in the Awards plugin. The goal is to provide enough detail to port the logic to other languages and frameworks.

---

## AwardsController

### initialize()
- **Purpose:** Set up authorization and authentication for the controller.
- **Logic:**
  - Calls parent initialize.
  - Applies model-level authorization for `index` and `add` actions.
  - Allows unauthenticated access to the `awardsByDomain` action.

### index()
- **Purpose:** List all awards with related domain, level, and branch info.
- **Logic:**
  - Builds a query joining Awards with Domains, Levels, and Branches (selecting only id and name for each).
  - Applies authorization scope to the query.
  - Paginates the results.
  - Passes the awards list to the view.

### view($id)
- **Purpose:** Show details for a single award.
- **Logic:**
  - Finds the award by id, including related Domains, Levels, and Branches.
  - Throws 404 if not found.
  - Authorizes the user for this award.
  - Loads lists of all domains, levels (ordered by progression), and branches (tree list, ordered by name).
  - Passes all data to the view.

### add()
- **Purpose:** Add a new award.
- **Logic:**
  - Creates a new empty Award entity.
  - If the request is POST, patches the entity with request data and attempts to save.
  - On success, flashes a success message and redirects to index.
  - On failure, flashes an error and stays on the form.
  - Loads lists of domains, levels, and branches for the form.

### edit($id)
- **Purpose:** Edit an existing award.
- **Logic:**
  - Loads the award by id.
  - Throws 404 if not found.
  - Authorizes the user for this award.
  - If the request is PATCH/POST/PUT, patches the entity with request data, decodes specialties from JSON, and attempts to save.
  - On success, flashes a success message and redirects to the award view.
  - On failure, flashes an error and redirects to the award view.

### delete($id)
- **Purpose:** Soft-delete an award if it has no recommendations.
- **Logic:**
  - Allows only POST/DELETE methods.
  - Loads the award by id.
  - Throws 404 if not found.
  - Authorizes the user for this award.
  - Counts recommendations for this award.
  - If any exist, flashes an error and redirects to the award view.
  - Otherwise, prefixes the award name with "Deleted: " and deletes the record.
  - Flashes success or error and redirects appropriately.

### awardsByDomain($domainId)
- **Purpose:** List all awards for a given domain (public endpoint).
- **Logic:**
  - Skips authorization.
  - Finds all awards with the given domain_id, including related Domains, Levels, and Branches.
  - Orders by level progression and award name.
  - Returns the result as JSON.

---

## RecommendationsController

### beforeFilter($event)
- **Purpose:** Allow unauthenticated access to recommendation submission.
- **Logic:**
  - Calls parent beforeFilter.
  - Allows unauthenticated access to `submitRecommendation`.

### index()
- **Purpose:** Show the main recommendations landing page with view configuration.
- **Logic:**
  - Gets `view` and `status` from query params (defaults: 'Index', 'All').
  - Creates an empty Recommendation entity.
  - Gets the current user and authorizes with context (view, status, query args).
  - Loads the page configuration from app settings (with fallback to default).
  - Checks if board view is enabled and if the user can use it.
  - Passes view, status, and config to the view.
  - Handles and logs exceptions, redirecting to home on error.

### table($csvExportService, $view, $status)
- **Purpose:** Show recommendations in a table, with optional CSV export.
- **Logic:**
  - Gets view and status (defaults: 'Default', 'All').
  - Loads page config and filter from app settings.
  - Determines required permission (default: 'index').
  - Gets current user and authorizes with context.
  - If view is 'SubmittedByMember', sets requester_id to current user.
  - Processes filter and checks if export is enabled and requested.
  - If so, runs export and returns CSV.
  - Otherwise, sets config and runs the table view.
  - Handles and logs exceptions, redirecting to index on error.

### board($view, $status)
- **Purpose:** Show recommendations in a kanban/board view.
- **Logic:**
  - Gets view and status (defaults: 'Default', 'All').
  - Gets current user and authorizes with context.
  - Loads page config from app settings.
  - If board view is not enabled, flashes info and redirects to index.
  - Otherwise, sets config and runs the board view.
  - Handles and logs exceptions, redirecting to index on error.

### updateStates()
- **Purpose:** Bulk update the state and status of recommendations.
- **Logic:**
  - Gets view and status from POST data (defaults: 'Index', 'All').
  - Allows only POST/GET methods.
  - Gets current user and authorizes a new empty recommendation.
  - Gets list of recommendation IDs from POST data.
  - (Further logic not shown in snippet, but would update states/statuses for the given IDs.)

---

## EventsController

### initialize()
- **Purpose:** Set up authorization for the controller.
- **Logic:**
  - Calls parent initialize.
  - Applies model-level authorization for `index` and `add` actions.

### index()
- **Purpose:** List all events (implementation not shown).

### allEvents($state)
- **Purpose:** List all events by state (active/closed).
- **Logic:**
  - Validates state parameter.
  - Authorizes a new empty event entity.
  - Builds a query for events, joining branches.
  - Filters by closed status based on state.
  - Applies authorization scope.
  - Orders and paginates results.
  - Passes events and state to the view.

### view($id)
- **Purpose:** Show details for a single event.
- **Logic:**
  - Finds event by id, including branch info.
  - Gets current user and checks if they can view awards recommendations.
  - Throws 404 if not found.
  - Authorizes the user for this event.
  - Loads all branches (tree list, ordered by name).
  - Passes event, branches, and showAwards flag to the view.

### add()
- **Purpose:** Add a new event.
- **Logic:**
  - Creates a new empty Event entity.
  - If POST, patches entity with request data, sets start/end dates, and closed=false.
  - (Further logic not shown in snippet.)

---

## DomainsController

### initialize()
- **Purpose:** Set up authorization for the controller.
- **Logic:**
  - Calls parent initialize.
  - Applies model-level authorization for `index` and `add` actions.

### index()
- **Purpose:** List all award domains.
- **Logic:**
  - Finds all domains, orders by name, paginates, and passes to the view.

### view($id)
- **Purpose:** Show details for a single domain.
- **Logic:**
  - Gets domain by id, including related awards, levels, and branches.
  - Throws 404 if not found.
  - Authorizes the user for this domain.
  - Passes domain to the view.

### add()
- **Purpose:** Add a new domain.
- **Logic:**
  - Creates a new empty Domain entity.
  - If POST, patches entity with request data and attempts to save.
  - On success, flashes success and redirects to view.
  - On failure, flashes error and stays on the form.
  - Passes domain to the view.

---

## LevelsController

### initialize()
- **Purpose:** Set up authorization for the controller.
- **Logic:**
  - Calls parent initialize.
  - Applies model-level authorization for `index` and `add` actions.

### index()
- **Purpose:** List all award levels.
- **Logic:**
  - Finds all levels, orders by progression_order, paginates, and passes to the view.

### view($id)
- **Purpose:** Show details for a single level.
- **Logic:**
  - Gets level by id, including related awards, domains, and branches.
  - Throws 404 if not found.
  - Authorizes the user for this level.
  - Passes level to the view.

### add()
- **Purpose:** Add a new level.
- **Logic:**
  - Creates a new empty Level entity.
  - If POST, patches entity with request data and attempts to save.
  - On success, flashes success and redirects to view.
  - On failure, flashes error and stays on the form.
  - Passes level to the view.

---

## ReportsController

### initialize()
- **Purpose:** Set up the controller (authorization commented out).
- **Logic:**
  - Calls parent initialize.
  - (Authorization logic is commented out.)

---

*This document is auto-generated from controller code as of May 19, 2025.*
