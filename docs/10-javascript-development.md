---
layout: default
---
[← Back to Table of Contents](index.md)

# 10. JavaScript Development with Stimulus

## 10.1 Introduction to Stimulus

KMP uses the Stimulus JavaScript framework to enhance the user interface with dynamic behavior. Stimulus is a modest framework designed to augment your HTML with just enough JavaScript to make it interactive.

Stimulus works by automatically connecting DOM elements to JavaScript objects. When you add `data-controller` attributes to your HTML, Stimulus automatically instantiates the corresponding controller class and keeps it connected to the element.


## 10.2 Controller Organization

In KMP, all Stimulus controllers for the main application should be stored in the `app/assets/js/controllers` directory with filenames that follow the pattern `{name}-controller.js`. For example:

- `app/assets/js/controllers/nav-bar-controller.js`
- `app/assets/js/controllers/member-card-profile-controller.js`
- `app/assets/js/controllers/auto-complete-controller.js`

For plugin-specific controllers, use:
- `plugins/PluginName/assets/js/controllers/{name}-controller.js`

Each controller follows the Stimulus naming convention, where the filename corresponds to the controller identifier used in the HTML. This ensures consistency and automatic registration in the build process.

Here's an example of a typical Stimulus controller:

```javascript
// app/assets/js/controllers/example-controller.js
import { Controller } from "@hotwired/stimulus"

class ExampleController extends Controller {
  static targets = ["output"]

  connect() {
    console.log("Controller connected to element")
  }

  greet() {
    this.outputTarget.textContent = "Hello, Stimulus!"
  }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["example"] = ExampleController;
```

In your HTML, you would connect this controller like this:

```html
<div data-controller="example">
  <button data-action="click->example#greet">Greet</button>
  <span data-example-target="output"></span>
</div>
```

## 10.3 Development Workflow

KMP uses Laravel Mix (based on webpack) to compile JavaScript and CSS assets. The workflow for developing JavaScript in KMP is as follows:

1. Make sure you have Node.js installed and run `npm install` in the `app` directory to install dependencies
2. Start the development server using `npm run watch`
3. Make changes to your JavaScript files in the `app/assets/js` directory
4. The watch process will automatically detect changes, recompile assets, and refresh your browser

The `npm run watch` command keeps running and watches for changes in your JavaScript and CSS files. It will automatically recompile and update your browser when you save changes to any asset file.

```bash
# Navigate to app directory
cd app

# Install dependencies (if not already installed)
npm install

# Start the watch process
npm run watch
```

## 10.4 Asset Management

KMP uses Laravel Mix and webpack to manage frontend assets. All source JavaScript and CSS files should be stored in the following directories:

- JavaScript: `app/assets/js/`
- CSS: `app/assets/css/`

These files are then compiled and output to the public directory for deployment:

- JavaScript: compiled to `app/webroot/js/`
- CSS: compiled to `app/webroot/css/`

Webpack automatically bundles your JavaScript modules and their dependencies into optimized packages. The build process is configured in `app/webpack.mix.js`.


### Adding a New Stimulus Controller

To add a new Stimulus controller:

1. For the main app, create a new file in `app/assets/js/controllers/` named `your-feature-controller.js`.
2. For a plugin, create it in `plugins/PluginName/assets/js/controllers/`.
3. Implement your controller using the Stimulus pattern.
4. The controller will be automatically included in the build process.

The webpack configuration automatically detects all files ending with `-controller.js` in the appropriate controllers directory and includes them in the build.

### Main JavaScript Entry Points

The main JavaScript entry points are:

- `app/assets/js/index.js`: The main application JavaScript
- `app/assets/js/controllers/*-controller.js`: Individual Stimulus controllers

These files are compiled into the final JavaScript bundles that are loaded by the application.

## 10.5 Core Stimulus Controllers

KMP includes several Stimulus controllers that provide essential functionality across the application. These controllers are organized by functionality and serve as both working components and examples of Stimulus patterns.

### 10.5.1 Form Management Controllers

#### App Setting Form Controller (`app-setting-form-controller.js`)

**Purpose:** Manages application settings forms with validation and submission handling.

**Features:**
- Form submission control with preventDefault
- Submit button state management
- Form validation integration

**Targets:**
- `submitBtn`: The submit button element
- `form`: The form element to submit

**Usage Example:**
```html
<form data-controller="app-setting-form" data-app-setting-form-target="form">
    <!-- form fields -->
    <button type="button" 
            data-app-setting-form-target="submitBtn" 
            data-action="click->app-setting-form#submit"
            disabled>
        Save Settings
    </button>
</form>
```

**Key Methods:**
- `submit(event)`: Handles form submission with proper event handling
- `enableSubmit()`: Enables and focuses the submit button

**Note:** This controller uses CommonJS require syntax rather than ES6 imports.

#### Branch Links Controller (`branch-links-controller.js`)

**Purpose:** Manages dynamic collection of branch links with URL validation and type categorization.

**Features:**
- Dynamic link addition and removal
- URL sanitization using KMP_utils
- Link type management with Bootstrap icons
- Duplicate prevention
- JSON form field integration

**Targets:**
- `new`: Input field for new URLs
- `formValue`: Hidden field containing JSON data
- `displayList`: Container for displaying current links
- `linkType`: Element showing current link type

**Usage Example:**
```html
<div data-controller="branch-links">
    <div class="input-group mb-3">
        <input type="url" data-branch-links-target="new" placeholder="Enter URL" class="form-control">
        <button type="button" data-action="click->branch-links#add" class="btn btn-primary">
            Add Link
        </button>
    </div>
    <div data-branch-links-target="displayList"></div>
    <input type="hidden" name="branch_links" data-branch-links-target="formValue">
</div>
```

**Key Methods:**
- `add(event)`: Adds new link with validation and sanitization
- `remove(event)`: Removes link from collection
- `setLinkType(event)`: Changes link type with icon update
- `createListItem(item)`: Creates DOM elements for link display

### 10.5.2 Data Input Controllers

#### Auto Complete Controller (`auto-complete-controller.js`)

**Purpose:** Advanced autocomplete functionality with AJAX search, keyboard navigation, and dynamic options.

**Features:**
- Remote data loading via AJAX
- Local data filtering from datalist
- Keyboard navigation (arrow keys, enter, escape)
- Custom value support with `allowOther` option
- Hidden field integration for form submission
- Debounced search to prevent excessive requests
- Accessibility features with ARIA attributes

**Targets:**
- `input`: The visible input field
- `hidden`: Hidden field for form submission value
- `hiddenText`: Hidden field for display text
- `results`: Container for autocomplete results
- `dataList`: Optional local data source
- `clearBtn`: Button to clear selection

**Values:**
- `url`: AJAX endpoint for remote data
- `minLength`: Minimum characters before search (default: 1)
- `delay`: Debounce delay in milliseconds (default: 300)
- `allowOther`: Allow custom values not in list
- `submitOnEnter`: Submit form on Enter key
- `queryParam`: Query parameter name (default: "q")

**Usage Example:**
```html
<div data-controller="ac" 
     data-ac-url-value="/api/members/search"
     data-ac-min-length-value="2"
     data-ac-allow-other-value="false">
    <input type="text" 
           data-ac-target="input" 
           placeholder="Search members..."
           class="form-control">
    <input type="hidden" name="member_id" data-ac-target="hidden">
    <input type="hidden" data-ac-target="hiddenText">
    <ul data-ac-target="results" class="list-group" hidden></ul>
    <button type="button" data-ac-target="clearBtn" class="btn btn-sm">Clear</button>
</div>
```

**Key Methods:**
- `fetchResults(query)`: Handles AJAX or local search
- `commit(selected)`: Processes selection and updates form fields
- `clear()`: Resets the autocomplete to empty state
- Keyboard handlers: `onArrowDownKeydown`, `onArrowUpKeydown`, `onEnterKeydown`, `onEscapeKeydown`

**Events:**
- `autocomplete.change`: Fired when selection changes
- `ready`: Fired when controller is initialized
- `loadstart/load/loadend`: AJAX loading events

### 10.5.3 File Management Controllers

#### CSV Download Controller (`csv-download-controller.js`)

**Purpose:** Handles CSV file downloads with AJAX fetch and browser download management.

**Features:**
- AJAX file retrieval to avoid page navigation
- Blob processing for client-side download
- Custom filename support
- Error handling with user feedback
- Automatic cleanup of temporary URLs

**Values:**
- `url`: Download endpoint URL
- `filename`: Custom filename for download

**Targets:**
- `button`: Optional specific button target

**Usage Example:**
```html
<!-- Method 1: Using href attribute -->
<a href="/export/members.csv" 
   data-controller="csv-download"
   data-csv-download-filename-value="members_export.csv"
   class="btn btn-primary">
    Download CSV
</a>

<!-- Method 2: Using data attributes -->
<button data-controller="csv-download"
        data-csv-download-url-value="/api/export/branches"
        data-csv-download-filename-value="branches.csv"
        data-action="click->csv-download#download"
        class="btn btn-success">
    Export Branches
</button>
```

**Key Methods:**
- `download(event)`: Handles the complete download workflow
- Error handling with user-friendly alert messages
- Automatic DOM cleanup after download

#### Image Preview Controller (`image-preview-controller.js`)

**Purpose:** Provides image preview functionality for file uploads.

**Features:**
- Real-time image preview on file selection
- Object URL management for blob display
- Loading state management
- Automatic cleanup of temporary URLs

**Targets:**
- `file`: File input element
- `preview`: Image element for preview display
- `loading`: Loading indicator element

**Usage Example:**
```html
<div data-controller="image-preview">
    <input type="file" 
           accept="image/*"
           data-image-preview-target="file"
           data-action="change->image-preview#preview"
           class="form-control">
    <div data-image-preview-target="loading" class="spinner-border">
        Loading...
    </div>
    <img data-image-preview-target="preview" 
         class="preview-image" 
         alt="Preview"
         hidden>
</div>
```

**Key Methods:**
- `preview(event)`: Handles file selection and creates preview using Object URLs

#### Kanban Controller (`kanban-controller.js`)

**Purpose:** Implements drag-and-drop Kanban board functionality with server synchronization.

**Features:**
- Drag-and-drop card movement between columns
- Server-side position updates via AJAX
- Visual feedback during drag operations
- Position restoration on failed moves
- CSRF token integration
- Custom validation callbacks

**Targets:**
- `card`: Draggable card elements
- `column`: Sortable column containers

**Values:**
- `csrfToken`: CSRF token for secure AJAX requests
- `url`: API endpoint for position updates

**Usage Example:**
```html
<div data-controller="kanban" 
     data-kanban-url-value="/api/kanban/update"
     data-kanban-csrf-token-value="<%= $this->request->getAttribute('csrfToken') %>">
    <div class="kanban-column sortable" data-col="todo" data-kanban-target="column">
        <div class="card" 
             data-kanban-target="card"
             data-rec-id="1"
             data-stack-rank="100"
             draggable="true"
             data-action="dragstart->kanban#grabCard dragend->kanban#dropCard">
            <div class="card-body">Task 1</div>
        </div>
    </div>
    <div class="kanban-column sortable" data-col="in-progress" data-kanban-target="column">
        <!-- More cards -->
    </div>
</div>
```

**Key Methods:**
- `grabCard(event)`: Initiates drag operation and stores original position
- `dropCard(event)`: Completes drag operation and syncs with server
- `processDrag(event, isDrop)`: Handles drag logic and position calculations
- `restoreOriginalPosition()`: Restores card to original position on failure
- `registerBeforeDrop(callback)`: Allows custom validation before drop operations

### 10.5.4 Interface Controllers

### 10.5.4 Interface Controllers

#### Filter Grid Controller (`filter-grid-controller.js`)

**Purpose:** Manages grid filtering with automatic form submission.

**Features:**
- Automatic form submission on filter changes
- Integration with CakePHP pagination
- Grid refresh without page reload

**Usage Example:**
```html
<form data-controller="filter-grid" method="get">
    <input type="text" name="search" placeholder="Filter results...">
    <select name="status" data-action="change->filter-grid#submitForm">
        <option value="">All</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
</form>
```

**Key Methods:**
- `submitForm(event)`: Submits the form to refresh grid data

#### Modal Opener Controller (`modal-opener-controller.js`)

**Purpose:** Programmatically opens Bootstrap modals based on value changes.

**Features:**
- Automatic modal triggering via value changes
- Integration with Bootstrap modal system
- Event-driven modal activation

**Values:**
- `modalBtn`: ID of modal button element to trigger

**Usage Example:**
```html
<!-- Modal trigger controller -->
<div data-controller="modal-opener" 
     data-modal-opener-modal-btn-value="">
    <!-- This controller watches for modalBtn value changes -->
</div>

<!-- Hidden modal trigger button -->
<button id="hidden-modal-trigger" 
        data-bs-toggle="modal" 
        data-bs-target="#confirmModal" 
        style="display: none;">
</button>

<!-- Modal -->
<div class="modal fade" id="confirmModal">
    <div class="modal-dialog">
        <!-- Modal content -->
    </div>
</div>

<script>
// Trigger modal programmatically
const controller = document.querySelector('[data-controller="modal-opener"]');
controller.setAttribute('data-modal-opener-modal-btn-value', 'hidden-modal-trigger');
</script>
```

**Key Methods:**
- `modalBtnValueChanged()`: Automatically triggered when modalBtn value changes, clicks the specified modal button

#### Navigation Bar Controller (`nav-bar-controller.js`)

**Purpose:** Manages navigation bar interactions with expand/collapse state tracking.

**Features:**
- Navigation item expand/collapse state tracking
- AJAX requests for state persistence
- Automatic event listener management
- Server-side state synchronization

**Targets:**
- `navHeader`: Navigation header elements with expand/collapse functionality

**Usage Example:**
```html
<nav data-controller="nav-bar">
    <button class="nav-link" 
            data-nav-bar-target="navHeader"
            data-expand-url="/nav/expand/123"
            data-collapse-url="/nav/collapse/123"
            aria-expanded="false"
            data-bs-toggle="collapse"
            data-bs-target="#navSection123">
        Section Header
    </button>
    <div id="navSection123" class="collapse">
        <!-- Navigation content -->
    </div>
</nav>
```

**Key Methods:**
- `navHeaderClicked(event)`: Handles navigation header clicks and sends state to server
- `navHeaderTargetConnected(event)`: Sets up event listeners for header elements
- `navHeaderTargetDisconnected(event)`: Cleanup event listeners

#### Detail Tabs Controller (`detail-tabs-controller.js`)

**Purpose:** Manages tabbed interfaces for detailed views with URL state management.

**Features:**
- URL-based tab state management
- Browser history integration
- Automatic first tab selection
- Turbo frame reload integration
- Scroll position management

**Targets:**
- `tabBtn`: Tab button elements
- `tabContent`: Tab content areas

**Values:**
- `updateUrl`: Whether to update URL on tab changes (default: true)

**Usage Example:**
```html
<div data-controller="detail-tabs" data-detail-tabs-update-url-value="true">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <button class="nav-link" 
                    id="nav-profile-tab"
                    data-detail-tabs-target="tabBtn">
                Profile
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" 
                    id="nav-history-tab"
                    data-detail-tabs-target="tabBtn">
                History
            </button>
        </li>
    </ul>
    <!-- Tab content areas -->
    <div class="tab-content">
        <turbo-frame id="profile-frame" data-detail-tabs-target="tabContent">
            <!-- Profile content -->
        </turbo-frame>
        <turbo-frame id="history-frame" data-detail-tabs-target="tabContent">
            <!-- History content -->
        </turbo-frame>
    </div>
</div>
```

**Key Methods:**
- `tabBtnTargetConnected(event)`: Sets up tab button and handles URL-based tab selection
- `tabBtnClicked(event)`: Handles tab clicks and updates URL state
- `tabBtnTargetDisconnected(event)`: Cleanup event listeners

#### Guifier Controller (`guifier-controller.js`)

**Purpose:** Integrates the Guifier library for dynamic form generation and JSON data editing.

**Features:**
- Dynamic form generation from JSON schema
- Real-time data updates
- Fullscreen editing interface
- Automatic container collapse
- Change event propagation

**Targets:**
- `hidden`: Hidden input field containing JSON data
- `container`: Container element for Guifier interface

**Values:**
- `type`: Data type for Guifier (e.g., "json", "yaml")

**Usage Example:**
```html
<div data-controller="guifier-control" 
     data-guifier-control-type-value="json">
    <div data-guifier-control-target="container" id="guifier-container"></div>
    <input type="hidden" 
           name="settings_data" 
           data-guifier-control-target="hidden"
           value='{"key": "value"}'>
</div>
```

**Key Methods:**
- `connect()`: Initializes Guifier instance with configuration and change handlers

### 10.5.5 Member Management Controllers

### 10.5.5 Member Management Controllers

#### Member Card Profile Controller (`member-card-profile-controller.js`)

**Purpose:** Manages dynamic member profile card generation with multi-card layout support.

**Features:**
- AJAX data loading for member profile information
- Multi-card layout with automatic overflow handling
- Dynamic content organization by plugin sections
- Card height management and space calculation
- Real-time membership and background check status

**Targets:**
- `cardSet`: Container for all profile cards
- `firstCard`: Primary card template
- `name`, `scaName`, `branchName`: Member identification fields
- `membershipInfo`, `backgroundCheck`: Status information fields
- `lastUpdate`: Timestamp field
- `loading`: Loading indicator
- `memberDetails`: Main details container

**Values:**
- `url`: API endpoint for member data

**Usage Example:**
```html
<div data-controller="member-card-profile" 
     data-member-card-profile-url-value="/api/members/123/card-data">
    <div data-member-card-profile-target="loading" class="spinner-border">
        Loading...
    </div>
    <div data-member-card-profile-target="memberDetails" class="member-profile" hidden>
        <div data-member-card-profile-target="cardSet" class="card-set">
            <div data-member-card-profile-target="firstCard" class="auth_card">
                <div class="cardbox">
                    <h2 data-member-card-profile-target="name"></h2>
                    <h3 data-member-card-profile-target="scaName"></h3>
                    <p data-member-card-profile-target="branchName"></p>
                    <div data-member-card-profile-target="membershipInfo"></div>
                    <div data-member-card-profile-target="backgroundCheck"></div>
                    <div data-member-card-profile-target="lastUpdate"></div>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Key Methods:**
- `loadCard()`: Initiates AJAX request and populates member data
- `startCard()`: Creates new card when current card is full
- `appendToCard(element, minSpace)`: Adds content with overflow management
- `usedSpaceInCard()`: Calculates current card space usage

#### Member Mobile Card Profile Controller (`member-mobile-card-profile-controller.js`)

**Purpose:** Specialized mobile interface for member profile cards with PWA integration.

**Features:**
- Mobile-optimized card layout with Bootstrap styling
- PWA readiness integration
- Responsive table layout for member data
- Dynamic card generation for plugin sections
- Automatic data formatting and organization

**Targets:**
- `cardSet`: Container for mobile cards
- `name`, `scaName`, `branchName`: Member identification
- `membershipInfo`, `backgroundCheck`: Status fields
- `lastUpdate`: Update timestamp
- `loading`: Loading state indicator
- `memberDetails`: Main content container

**Values:**
- `url`: API endpoint for member data
- `pwaReady`: PWA readiness flag

**Usage Example:**
```html
<div data-controller="member-mobile-card-profile" 
     data-member-mobile-card-profile-url-value="/api/members/123/mobile"
     data-member-mobile-card-profile-pwa-ready-value="false">
    <div data-member-mobile-card-profile-target="loading" class="text-center">
        <div class="spinner-border"></div>
    </div>
    <div data-member-mobile-card-profile-target="memberDetails" hidden>
        <div class="text-center mb-3">
            <h1 data-member-mobile-card-profile-target="name"></h1>
            <h2 data-member-mobile-card-profile-target="scaName"></h2>
            <h3 data-member-mobile-card-profile-target="branchName"></h3>
            <p data-member-mobile-card-profile-target="membershipInfo"></p>
            <p data-member-mobile-card-profile-target="backgroundCheck"></p>
            <small data-member-mobile-card-profile-target="lastUpdate"></small>
        </div>
        <div data-member-mobile-card-profile-target="cardSet"></div>
    </div>
</div>
```

**Key Methods:**
- `loadCard()`: Loads and formats member data for mobile display
- `startCard(title)`: Creates new mobile-optimized card sections
- `pwaReadyValueChanged()`: Responds to PWA state changes

#### Member Mobile Card PWA Controller (`member-mobile-card-pwa-controller.js`)

**Purpose:** Manages Progressive Web App functionality for offline member card access.

**Features:**
- Service Worker registration and management
- Offline/online status monitoring
- URL caching for offline access
- Automatic refresh when online
- Status indicator management

**Targets:**
- `urlCache`: Cache configuration data
- `status`: Online/offline status indicator
- `refreshBtn`: Manual refresh button

**Values:**
- `swUrl`: Service Worker script URL

**Usage Example:**
```html
<div data-controller="member-mobile-card-pwa" 
     data-member-mobile-card-pwa-sw-url-value="/service-worker.js">
    <script type="application/json" data-member-mobile-card-pwa-target="urlCache">
        ["/api/members/profile", "/offline-assets/"]
    </script>
    <div class="d-flex align-items-center mb-3">
        <span class="badge me-2" data-member-mobile-card-pwa-target="status">Checking...</span>
        <button class="btn btn-sm btn-outline-primary" 
                data-member-mobile-card-pwa-target="refreshBtn" 
                data-action="click->member-mobile-card-pwa#refreshPageIfOnline">
            Refresh
        </button>
    </div>
</div>
```

**Key Methods:**
- `manageOnlineStatus()`: Sets up online/offline monitoring and Service Worker
- `updateOnlineStatus()`: Updates UI based on connection status
- `refreshPageIfOnline()`: Refreshes page when connection is available

#### Member Unique Email Controller (`member-unique-email-controller.js`)

**Purpose:** Validates email uniqueness during member registration/editing with real-time feedback.

**Features:**
- Real-time email uniqueness validation
- AJAX validation against server endpoint
- Visual feedback with Bootstrap validation classes
- Original email comparison for edits
- Custom validity messaging

**Values:**
- `url`: Email validation endpoint

**Usage Example:**
```html
<input type="email" 
       name="email"
       class="form-control"
       data-controller="member-unique-email"
       data-member-unique-email-url-value="/api/members/check-email"
       data-original-value="current@email.com"
       required>
<div class="invalid-feedback">
    This email address is already taken.
</div>
<div class="valid-feedback">
    Email address is available.
</div>
```

**Key Methods:**
- `checkEmail(event)`: Validates email uniqueness via AJAX
- Automatic Bootstrap validation class management
- Custom validity message setting

#### Member Verify Form Controller (`member-verify-form-controller.js`)

**Purpose:** Handles member verification form workflows with conditional field management.

**Features:**
- Conditional field enabling/disabling
- Parent member verification toggle
- Membership information toggle
- Form field state management

**Targets:**
- `scaMember`: SCA member checkbox field
- `membershipNumber`: Membership number field
- `membershipExpDate`: Membership expiration field

**Usage Example:**
```html
<form data-controller="member-verify-form">
    <div class="form-check">
        <input type="checkbox" 
               data-action="change->member-verify-form#toggleParent"
               class="form-check-input">
        <label class="form-check-label">Has Parent/Guardian</label>
    </div>
    <input type="text" 
           data-member-verify-form-target="scaMember"
           placeholder="Parent/Guardian Name"
           class="form-control">
    
    <div class="form-check">
        <input type="checkbox" 
               data-action="change->member-verify-form#toggleMembership"
               class="form-check-input">
        <label class="form-check-label">Has Membership</label>
    </div>
    <input type="text" 
           data-member-verify-form-target="membershipNumber"
           placeholder="Membership Number"
           class="form-control">
    <input type="date" 
           data-member-verify-form-target="membershipExpDate"
           class="form-control">
</form>
```

**Key Methods:**
- `toggleParent(event)`: Enables/disables parent/guardian field
- `toggleMembership(event)`: Enables/disables membership fields

### 10.5.6 Administrative Controllers

#### Permission Add Role Controller (`permission-add-role-controller.js`)

**Purpose:** Manages adding roles to permissions with form validation.

**Features:**
- Role selection validation
- Submit button state management
- Automatic focus management

**Targets:**
- `role`: Role selection element
- `form`: Form element
- `submitBtn`: Submit button

**Usage Example:**
```html
<form data-controller="permission-add-role" data-permission-add-role-target="form">
    <select data-permission-add-role-target="role" 
            data-action="change->permission-add-role#checkSubmitEnable"
            name="role_id">
        <option value="">Select a role...</option>
        <option value="role_1">Administrator</option>
        <option value="role_2">Member</option>
    </select>
    <button type="submit" 
            data-permission-add-role-target="submitBtn" 
            disabled>
        Add Role
    </button>
</form>
```

**Key Methods:**
- `checkSubmitEnable()`: Validates role selection and enables/disables submit button

#### Permission Manage Policies Controller (`permission-manage-policies-controller.js`)

**Purpose:** Manages comprehensive permission policy matrix with class/method granular control.

**Features:**
- Batch checkbox processing for performance
- Class-level and method-level permission management
- Indeterminate state handling for partial selections
- Loading overlay for large policy matrices
- AJAX persistence for permission changes

**Targets:**
- `policyClass`: Class-level permission checkboxes
- `policyMethod`: Method-level permission checkboxes

**Values:**
- `url`: API endpoint for permission updates

**Usage Example:**
```html
<div data-controller="permission-manage-policies" 
     data-permission-manage-policies-url-value="/api/permissions/update"
     class="permissions-matrix">
    <!-- Class-level checkbox -->
    <input type="checkbox" 
           data-permission-manage-policies-target="policyClass"
           data-class-name="MemberPolicy"
           data-permission-id="123"
           class="form-check-input">
    
    <!-- Method-level checkboxes -->
    <input type="checkbox" 
           data-permission-manage-policies-target="policyMethod"
           data-class-name="MemberPolicy"
           data-permission-id="123"
           data-method-name="view"
           class="form-check-input">
    <input type="checkbox" 
           data-permission-manage-policies-target="policyMethod"
           data-class-name="MemberPolicy"
           data-permission-id="123"
           data-method-name="edit"
           class="form-check-input">
</div>
```

**Key Methods:**
- `connect()`: Initializes batch processing with loading overlay
- `classClicked(event)`: Handles class-level permission toggle
- `methodClicked(event)`: Handles method-level permission changes
- `checkClass(className, permissionId)`: Updates class checkbox based on method states

#### Role Add Member Controller (`role-add-member-controller.js`)

**Purpose:** Manages adding members to roles with member validation and branch requirements.

**Features:**
- Member selection validation with ID parsing
- Optional branch requirement validation
- Submit button state management
- Automatic focus handling

**Targets:**
- `scaMember`: Member selection element
- `form`: Form element
- `submitBtn`: Submit button
- `branch`: Optional branch selection

**Usage Example:**
```html
<form data-controller="role-add-member" data-role-add-member-target="form">
    <select data-role-add-member-target="scaMember" 
            data-action="change->role-add-member#checkSubmitEnable"
            name="member_id">
        <option value="">Select a member...</option>
        <option value="member_123">John Doe</option>
        <option value="member_456">Jane Smith</option>
    </select>
    
    <!-- Optional branch selection -->
    <select data-role-add-member-target="branch" 
            data-action="change->role-add-member#checkSubmitEnable"
            name="branch_id">
        <option value="">Select branch...</option>
        <option value="1">Local Branch</option>
    </select>
    
    <button type="submit" 
            data-role-add-member-target="submitBtn" 
            disabled>
        Add Member
    </button>
</form>
```

**Key Methods:**
- `checkSubmitEnable()`: Validates member selection and optional branch requirement

#### Role Add Permission Controller (`role-add-permission-controller.js`)

**Purpose:** Manages adding permissions to roles with permission validation.

**Features:**
- Permission selection validation with ID parsing
- Submit button state management
- Automatic focus handling
- Form validation integration

**Targets:**
- `permission`: Permission selection element
- `form`: Form element
- `submitBtn`: Submit button

**Usage Example:**
```html
<form data-controller="role-add-permission" data-role-add-permission-target="form">
    <select data-role-add-permission-target="permission" 
            data-action="change->role-add-permission#checkSubmitEnable"
            name="permission_id">
        <option value="">Select a permission...</option>
        <option value="permission_1">View Members</option>
        <option value="permission_2">Edit Members</option>
    </select>
    <button type="submit" 
            data-role-add-permission-target="submitBtn" 
            disabled>
        Add Permission
    </button>
</form>
```

**Key Methods:**
- `checkSubmitEnable()`: Validates permission selection and enables/disables submit button

#### Revoke Form Controller (`revoke-form-controller.js`)

**Purpose:** Manages revocation forms with reason validation and outlet communication.

**Features:**
- Outlet-based communication with other controllers
- Reason validation for revocation actions
- Form state management
- ID value management through outlets

**Targets:**
- `submitBtn`: Submit button
- `reason`: Reason text field
- `id`: Hidden ID field

**Values:**
- `url`: Revocation endpoint

**Outlets:**
- `outlet-btn`: Communication with outlet button controllers

**Usage Example:**
```html
<form data-controller="revoke-form" 
      data-revoke-form-url-value="/api/revoke"
      data-revoke-form-outlet-btn-outlet="#revoke-buttons">
    <input type="hidden" data-revoke-form-target="id" name="record_id">
    
    <textarea data-revoke-form-target="reason" 
              data-action="input->revoke-form#checkReadyToSubmit"
              placeholder="Enter reason for revocation..."
              class="form-control" 
              required></textarea>
    
    <button type="submit" 
            data-revoke-form-target="submitBtn" 
            class="btn btn-danger" 
            disabled>
        Revoke
    </button>
</form>

<!-- Outlet buttons container -->
<div id="revoke-buttons">
    <button data-controller="outlet-btn" 
            data-outlet-btn-btn-data-value='{"id": "123"}'
            data-action="click->outlet-btn#fireNotice">
        Revoke Item 123
    </button>
</div>
```

**Key Methods:**
- `setId(event)`: Receives ID from outlet button communications
- `checkReadyToSubmit()`: Validates reason field and enables/disables submit
- `outletBtnOutletConnected/Disconnected()`: Manages outlet communication lifecycle

**Note:** This controller demonstrates the outlet pattern for inter-controller communication.

#### Outlet Button Controller (`outlet-button-controller.js`)

**Purpose:** Provides inter-controller communication through Stimulus outlets with data passing.

**Features:**
- Data validation and requirement checking
- Event dispatching for controller communication
- Dynamic button state management
- Custom event listener management

**Values:**
- `btnData`: Object containing button-specific data
- `requireData`: Whether data is required for button functionality

**Usage Example:**
```html
<button data-controller="outlet-btn"
        data-outlet-btn-btn-data-value='{"id": "123", "type": "member"}'
        data-outlet-btn-require-data-value="true"
        data-action="click->outlet-btn#fireNotice"
        class="btn btn-primary">
    Process Member 123
</button>

<script>
// Listening controller setup
const form = document.querySelector('[data-controller="revoke-form"]');
form.addEventListener('outlet-btn:outlet-button-clicked', (event) => {
    console.log('Received data:', event.detail);
});
</script>
```

**Key Methods:**
- `btnDataValueChanged()`: Updates button state based on data availability
- `addBtnData(data)`: Programmatically sets button data
- `fireNotice(event)`: Dispatches custom event with button data
- `addListener(callback)` / `removeListener(callback)`: Manages event listeners

### 10.5.7 Session Management Controllers

#### Session Extender Controller (`session-extender-controller.js`)

**Purpose:** Extends user sessions to prevent timeout during active use.

**Features:**
- Automatic session extension alerts
- Configurable timeout intervals (25 minutes default)
- AJAX session refresh
- Timer management and cleanup

**Values:**
- `url`: Session extension endpoint

**Usage Example:**
```html
<div data-controller="session-extender" 
     data-session-extender-url-value="/auth/extend-session">
    <!-- This controller runs in background -->
</div>
```

**Key Methods:**
- `urlValueChanged()`: Sets up timer and handles session extension workflow
- Timer automatically shows alert at 25-minute intervals
- Fetches session extension endpoint after user confirmation

### 10.5.8 Utility Controllers

#### Select All Switch List Controller (`select-all-switch-list-controller.js`)

**Purpose:** Provides "select all" functionality for checkbox lists with Bootstrap switches.

**Features:**
- Automatic "Select All" checkbox generation
- Bidirectional checkbox synchronization
- Bootstrap form-switch styling integration
- Dynamic state management

**Usage Example:**
```html
<div data-controller="select-all-switch">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="items[]" value="1">
        <label class="form-check-label">Item 1</label>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="items[]" value="2">
        <label class="form-check-label">Item 2</label>
    </div>
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="items[]" value="3">
        <label class="form-check-label">Item 3</label>
    </div>
    <!-- Select All checkbox will be automatically inserted here -->
</div>
```

**Key Methods:**
- `connect()`: Creates "Select All" checkbox and sets up event listeners
- `updateSelectAll(event)`: Synchronizes individual checkboxes with "Select All" state

## 10.6 Controller Development Patterns

### Standard Controller Structure

All KMP Stimulus controllers follow this standard pattern:

```javascript
import { Controller } from "@hotwired/stimulus"

class YourController extends Controller {
    // Define targets - elements your controller interacts with
    static targets = ["input", "output"]
    
    // Define values - properties that can be set from HTML
    static values = {
        url: String,
        delay: { type: Number, default: 300 }
    }
    
    // Define outlets - connections to other controllers
    static outlets = ["other-controller"]
    
    // Initialize function (optional)
    initialize() {
        // Setup code here
    }
    
    // Connect function - runs when controller connects to DOM
    connect() {
        // Connection code here
    }
    
    // Event handler methods
    handleEvent(event) {
        // Handle events
    }
    
    // Disconnect function - cleanup when controller disconnects
    disconnect() {
        // Cleanup code here
    }
}

// Register with global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["your-controller"] = YourController;
```

### Import/Require Inconsistencies

**Note:** Some controllers in the codebase use CommonJS `require` syntax instead of ES6 `import` statements. This inconsistency should be addressed:

- **ES6 Import (Recommended):** `import { Controller } from "@hotwired/stimulus"`
- **CommonJS Require:** `const { Controller } = require("@hotwired/stimulus")`

Controllers currently using `require` include:
- `app-setting-form-controller.js`
- `session-extender-controller.js`

These should be updated to use ES6 imports for consistency.

### Security Considerations

When developing controllers that handle user input:

1. **Always sanitize URLs** using `KMP_utils.sanitizeUrl()` 
2. **Validate input** before processing using native HTML5 validation or custom logic
3. **Use CSRF tokens** for state-changing AJAX requests
4. **Sanitize strings** using `KMP_utils.sanitizeString()` for display
5. **Handle errors gracefully** with user-friendly feedback

### Performance Best Practices

1. **Debounce expensive operations** like AJAX calls (see auto-complete example)
2. **Clean up event listeners** in the `disconnect()` method
3. **Use efficient DOM operations** - batch updates when possible
4. **Implement lazy loading** for data-heavy components

### Code Quality Notes

**Issues Found in Current Codebase:**
- Some controllers have incomplete `disconnect()` methods (e.g., `csv-download-controller.js`)
- The `auto-complete-controller.js` has duplicate getter methods that may cause issues
- Import statement inconsistency across controllers (mix of ES6 and CommonJS)

These issues should be addressed to improve code reliability and maintainability.

## 10.7 Examples from the Codebase

KMP includes several Stimulus controllers that serve as good examples:

- `app-setting-form-controller.js`: Simple form management
- `auto-complete-controller.js`: Complex AJAX interactions with accessibility
- `branch-links-controller.js`: Dynamic list management with validation
- `csv-download-controller.js`: File handling and download management
- `member-card-profile-controller.js`: Component state management


Examining these controllers can provide insight into how to implement common patterns in Stimulus.

For more best practices and coding standards, see the [KMP CakePHP & Stimulus.JS Best Practices](../.github/copilot-instructions.md).

---

## 10.8 Asset Management

KMP uses Laravel Mix (based on webpack) to manage frontend assets with sophisticated build optimization and automated controller discovery.

### 10.8.1 Build System Architecture

The asset compilation system is configured in `webpack.mix.js` and provides:

- **Automatic Controller Discovery**: Recursively finds all `-controller.js` files
- **Code Splitting**: Separates vendor libraries from application code  
- **Asset Versioning**: Cache-busting for production deployments
- **Source Maps**: Development debugging support
- **CSS Processing**: SASS compilation and optimization

### 10.8.2 Webpack Configuration

The build system automatically discovers Stimulus controllers across the application:

```javascript
// webpack.mix.js - Controller Discovery
function getJsFilesFromDir(startPath, skiplist, filter, callback) {
    // Recursively scans directories for controller files
    // Skips node_modules and webroot directories
    // Finds all files matching '-controller.js' pattern
}

// Scans main app and plugins for controllers
getJsFilesFromDir('./assets/js', skipList, '-controller.js', callback);
getJsFilesFromDir('./plugins', skipList, '-controller.js', callback);
```

**Build Outputs:**
- `webroot/js/controllers.js` - All Stimulus controllers bundled together
- `webroot/js/index.js` - Main application JavaScript entry point
- `webroot/js/core.js` - Vendor libraries (Bootstrap, Turbo, Stimulus)
- `webroot/css/*.css` - Compiled CSS files

### 10.8.3 NPM Scripts

The `package.json` defines comprehensive build and test workflows:

**Development Scripts:**
```bash
npm run dev          # Development build
npm run watch        # Watch files and rebuild on changes
npm run watch-poll   # Watch with polling (for Docker/VM)
npm run hot          # Hot module reloading
```

**Production Scripts:**
```bash
npm run prod         # Production build with optimization
npm run production   # Alias for prod
```

**Testing Scripts:**
```bash
npm run test         # Run all tests (JS + UI)
npm run test:js      # Jest JavaScript tests
npm run test:ui      # Playwright end-to-end tests
npm run test:security # Security vulnerability checks
```

### 10.8.4 Dependencies Management

**Core Framework Dependencies:**
- `@hotwired/stimulus` - Stimulus JavaScript framework
- `@hotwired/turbo` - Turbo navigation and caching
- `bootstrap` - UI framework and components
- `guifier` - Dynamic form generation library

**Development Dependencies:**
- `laravel-mix` - Asset compilation wrapper for webpack
- `jest` - JavaScript testing framework
- `@playwright/test` - End-to-end testing framework
- `playwright-bdd` - Behavior-driven development for Playwright

### 10.8.5 Asset Organization

**JavaScript Structure:**
```
app/assets/js/
├── index.js                    # Main entry point
├── KMP_utils.js               # Global utilities
└── controllers/               # Stimulus controllers
    ├── app-setting-form-controller.js
    ├── auto-complete-controller.js
    └── ...                    # All other controllers

plugins/*/assets/js/controllers/  # Plugin-specific controllers
```

**CSS Structure:**
```
app/assets/css/
├── app.css          # Main application styles
├── signin.css       # Authentication page styles
├── cover.css        # Landing page styles
└── dashboard.css    # Dashboard-specific styles
```

### 10.8.6 Build Process Optimization

**Code Splitting Strategy:**
1. **Vendor Bundle** (`core.js`): Bootstrap, Turbo, Stimulus, and other vendor libraries
2. **Controllers Bundle** (`controllers.js`): All Stimulus controllers from app and plugins
3. **Application Bundle** (`index.js`): Main application logic and utilities

**Benefits:**
- Vendor libraries cached separately from application code
- Controllers can be updated without affecting vendor cache
- Smaller incremental updates for better performance

### 10.8.7 Development Workflow

**Setting Up Development Environment:**
```bash
cd app
npm install                    # Install dependencies
npm run watch                  # Start development with file watching
```

**Adding New Assets:**
1. **Stimulus Controllers**: Place in `assets/js/controllers/` with `-controller.js` suffix
2. **CSS Files**: Add to `assets/css/` and reference in `webpack.mix.js`
3. **JavaScript Modules**: Import into `index.js` or controller files as needed

**Automatic Discovery:**
- Controllers are automatically discovered and bundled
- No manual webpack entry configuration required
- Plugin controllers automatically included in build

### 10.8.8 Production Deployment

**Production Build Process:**
```bash
npm run production
```

**Production Optimizations:**
- **Minification**: JavaScript and CSS are minified
- **Tree Shaking**: Unused code is removed
- **Asset Versioning**: Files include content hashes for cache busting
- **Source Maps**: Available for production debugging if needed

**Output Files:**
```
webroot/js/
├── controllers.js              # All Stimulus controllers
├── index.js                   # Main application code
├── core.js                    # Vendor libraries
├── manifest.json              # Laravel Mix manifest for versioning
└── runtime.js                 # Webpack runtime

webroot/css/
├── app.css                    # Main styles
├── signin.css                 # Authentication styles
└── ...                       # Other CSS files
```

### 10.8.9 Browser Support

The build system targets modern browsers with the following browserlist configuration:

```json
"browserslist": ["defaults"]
```

This provides support for:
- **Chrome**: Last 2 versions
- **Firefox**: Last 2 versions  
- **Safari**: Last 2 versions
- **Edge**: Last 2 versions
- Covers >95% of global browser usage

### 10.8.10 Performance Considerations

**Bundle Size Optimization:**
- Vendor libraries extracted to separate bundle for better caching
- Controllers bundled together for optimal loading
- Source maps available for development debugging

**Loading Strategy:**
- Core vendor libraries loaded first
- Controllers loaded separately to allow for independent updates
- CSS loaded asynchronously to prevent render blocking

### 10.8.11 Troubleshooting

**Common Build Issues:**

1. **Controller Not Loading**: Ensure filename ends with `-controller.js`
2. **Build Errors**: Check for syntax errors in controller files
3. **Asset Not Found**: Verify file paths in `webpack.mix.js`
4. **Memory Issues**: Use `--max-old-space-size=4096` for large projects

**Debug Commands:**
```bash
npm run dev                    # Development build with detailed output
npm run watch                  # Watch mode shows file changes
npm run test:js                # Run JavaScript tests
```

**File Watching Issues:**
- Use `npm run watch-poll` for Docker/VM environments
- Ensure file permissions allow webpack to watch files
- Check that `skipList` in webpack.mix.js doesn't exclude your files