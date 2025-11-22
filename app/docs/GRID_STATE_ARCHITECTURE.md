# Grid State Architecture - Simplified

## Overview

This document describes the simplified state management architecture for the Dataverse-style grid system. The goal is a clean MVC flow where state flows in one direction: **Controller → View → User → Controller**.

## Core Principle

**Server is Source of Truth**: All state is calculated on the server and sent to the client. The client's job is to:
1. Display the state
2. Capture user actions
3. Send requested changes back to the server

## State Data Structure

All grid state is embedded in the turbo-frame as data attributes on a single `.frame-state` element:

```html
<turbo-frame id="members-grid">
    <div class="frame-state"
         data-grid-state-value='{ /* complete state JSON */ }'>
        <!-- Grid content -->
    </div>
</turbo-frame>
```

### Complete State Schema

```typescript
interface GridState {
  // View Management
  view: {
    currentId: number | null;
    currentName: string;
    isDefault: boolean;
    isUserDefault: boolean;
    available: Array<{
      id: number;
      name: string;
      isDefault: boolean;
      isUserDefault: boolean;
    }>;
  };
  
  // Search State
  search: string;
  
  // Filter State
  filters: {
    active: {
      [columnKey: string]: string[];  // Active filter values
    };
    available: {
      [columnKey: string]: {
        label: string;
        options: Array<{
          value: string;
          label: string;
        }>;
      };
    };
  };
  
  // Sort State
  sort: {
    field: string;
    direction: 'asc' | 'desc';
  } | null;
  
  // Column State
  columns: {
    visible: string[];  // Array of visible column keys in display order
    all: {
      [columnKey: string]: {
        label: string;
        sortable: boolean;
        width?: string;
        alignment?: string;
        description?: string;
      };
    };
  };
  
  // Grid Configuration
  config: {
    gridKey: string;
    primaryKey: string;
    pageSize: number;
  };
}
```

## Data Flow

### 1. Page Load / Turbo Frame Update

```
Controller (PHP)
  ↓ Prepares complete state
View (PHP Template)
  ↓ Renders state as JSON in data attribute
  ↓ Renders UI using state data
Stimulus Controller (JS)
  ↓ Reads state from data attribute
  ↓ Initializes UI components
```

### 2. User Interaction

```
User clicks/changes something
  ↓
Stimulus Controller captures event
  ↓
Builds URL with requested changes
  ↓
Navigates via Turbo Frame
  ↓
Server generates new state
  ↓
Cycle repeats from step 1
```

## Component Responsibilities

### Controller (MembersController.php)

**Single Responsibility**: Prepare complete grid state

```php
public function indexDv() {
    // 1. Load view configuration
    $currentView = $gridViewService->getEffectiveView(...);
    $availableViews = $this->fetchTable('GridViews')->find(...);
    
    // 2. Extract state from URL params and view config
    $searchTerm = $this->extractSearch();
    $filters = $this->extractFilters();
    $sort = $this->extractSort();
    $visibleColumns = $this->extractVisibleColumns();
    
    // 3. Apply state to query
    $query = $this->applySearchToQuery($query, $searchTerm);
    $query = $this->applyFiltersToQuery($query, $filters);
    $query = $this->applySortToQuery($query, $sort);
    
    // 4. Build complete state object
    $gridState = [
        'view' => [
            'currentId' => $currentView?->id,
            'currentName' => $currentView?->name ?? 'All',
            'isDefault' => $currentView?->is_default ?? false,
            'isUserDefault' => $currentView?->isUserDefault() ?? false,
            'available' => $this->formatAvailableViews($availableViews),
        ],
        'search' => $searchTerm,
        'filters' => [
            'active' => $filters,
            'available' => $this->prepareFilterOptions(),
        ],
        'sort' => $sort,
        'columns' => [
            'visible' => $visibleColumns,
            'all' => $columnsMetadata,
        ],
        'config' => [
            'gridKey' => 'Members.index.main',
            'primaryKey' => 'id',
            'pageSize' => 25,
        ],
    ];
    
    // 5. Pass to view
    $this->set('gridState', $gridState);
    $this->set('members', $this->paginate($query));
}
```

### View Template (grid_content.php)

**Single Responsibility**: Render state as data attribute and display current state

```php
<turbo-frame id="members-grid">
    <div class="frame-state"
         data-grid-state-value="<?= h(json_encode($gridState)) ?>">
        
        <!-- Dataverse Table - uses state for display -->
        <?= $this->element('dataverse_table', [
            'data' => $members,
            'columns' => $gridState['columns']['all'],
            'visibleColumns' => $gridState['columns']['visible'],
            'currentSort' => $gridState['sort'],
        ]) ?>
        
        <!-- Pagination -->
        <?= $this->element('pagination') ?>
    </div>
</turbo-frame>
```

### Toolbar Template (grid_view_toolbar.php)

**Single Responsibility**: Display state using templates (no logic)

The toolbar should be **completely driven by state data**:

```php
<?php
// Extract state from Stimulus value
// (Stimulus controller will have already parsed gridState)
?>
<div class="grid-view-toolbar">
    <!-- Tabs: Generated from state.view.available -->
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <button data-action="click->grid-view#showAll"
                    class="nav-link <?= !$gridState['view']['currentId'] ? 'active' : '' ?>">
                All
            </button>
        </li>
        <?php foreach ($gridState['view']['available'] as $view): ?>
        <li class="nav-item">
            <button data-action="click->grid-view#switchView"
                    data-view-id="<?= $view['id'] ?>"
                    class="nav-link <?= $view['id'] === $gridState['view']['currentId'] ? 'active' : '' ?>">
                <?= h($view['name']) ?>
                <?php if ($view['isUserDefault']): ?>
                <i class="bi bi-star-fill"></i>
                <?php endif; ?>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Filters and Search: Generated from state.filters and state.search -->
    <!-- ... similar pattern ... -->
</div>
```

### Stimulus Controller (grid-view-controller.js)

**Single Responsibility**: Reflect UI state and capture user actions

```javascript
class GridViewController extends Controller {
    static values = {
        gridState: Object  // Single source of truth
    }
    
    connect() {
        // Parse state from data attribute
        this.state = this.gridStateValue;
        
        // Initialize UI based on state
        this.updateUI();
    }
    
    // User Actions - just build URL and navigate
    switchView(event) {
        const viewId = event.currentTarget.dataset.viewId;
        const url = this.buildUrlWithView(viewId);
        this.navigateToUrl(url);
    }
    
    toggleFilter(event) {
        const column = event.currentTarget.dataset.filterColumn;
        const value = event.currentTarget.value;
        const url = this.buildUrlWithFilter(column, value);
        this.navigateToUrl(url);
    }
    
    applySort(event) {
        const field = event.currentTarget.dataset.columnKey;
        const url = this.buildUrlWithSort(field);
        this.navigateToUrl(url);
    }
    
    // Helper: Build URL from current state + changes
    buildUrlWithView(viewId) {
        const params = new URLSearchParams();
        params.set('view_id', viewId);
        return `${window.location.pathname}?${params}`;
    }
    
    // Helper: Navigate via Turbo
    navigateToUrl(url) {
        window.history.pushState({}, '', url);
        window.Turbo.visit(url, { frame: 'members-grid' });
    }
    
    // UI Update: Reflect state changes (called after frame load)
    updateUI() {
        // No state management - just reflect what server sent
        // The template already rendered the correct state
        // This is just for dynamic UI updates if needed
    }
}
```

## Benefits of This Architecture

### 1. Single Source of Truth
- State lives in one place (server-generated JSON)
- No synchronization issues
- No "which value is correct" questions

### 2. Simplified Client Code
- No state management in JS
- No "preserve this, update that" logic
- Just URL building and navigation

### 3. Server Control
- All business logic on server
- Authorization checked once
- Consistent state calculations

### 4. Easier Testing
- Test controller state generation
- Test that templates render state correctly
- Test that Stimulus navigates correctly
- No complex state synchronization to test

### 5. Easier Debugging
- State is visible in one data attribute
- Can copy/paste state JSON for debugging
- Clear boundaries between components

## Migration Strategy

### Phase 1: Add Complete State to Frame
- [ ] Update controller to build complete `gridState` object
- [ ] Add `data-grid-state-value` to frame-state element
- [ ] Keep existing attributes for backward compatibility

### Phase 2: Update Templates to Use State
- [ ] Update toolbar to read from `gridState`
- [ ] Remove server-side conditional logic from templates
- [ ] Make templates pure display of state

### Phase 3: Simplify Stimulus Controller
- [ ] Remove state management code
- [ ] Remove sync logic between URL/values/frame
- [ ] Keep only URL building and navigation

### Phase 4: Remove Legacy Code
- [ ] Remove individual data attributes
- [ ] Remove unused Stimulus values
- [ ] Clean up comments and documentation

## Example: Complete Flow

### User clicks a filter checkbox

```
1. User clicks "Active" in Status filter
   ↓
2. Stimulus captures event
   toggleFilter(event) {
       const url = buildUrlWithFilter('status', 'active')
       navigateToUrl(url)
   }
   ↓
3. URL becomes: /members?filter[status][]=active
   ↓
4. Server receives request
   - Parses filter[status]=active
   - Applies to query
   - Rebuilds gridState with filter included
   ↓
5. Server returns HTML with updated state
   <div data-grid-state-value='{
       "filters": {
           "active": {"status": ["active"]},
           ...
       }
   }'>
   ↓
6. Turbo replaces frame content
   ↓
7. Stimulus reads new state
   - Sees status filter is active
   - UI already shows checkbox checked (from template)
   ↓
8. User sees updated grid with filter applied
```

## Key Decisions

### Why Single State Object?
- Easier to understand complete system state
- No ambiguity about which attribute has "truth"
- Can serialize entire state for debugging

### Why Not Generate Tabs in JS?
- Tabs rarely change (only when views are saved/deleted)
- Server already knows available views
- Simpler to render once on server than sync in JS

### Why Keep URL Parameters?
- Bookmarkable state
- Shareable links
- Browser back/forward works correctly

### Why Not Use Stimulus Values for State?
- Values are meant for configuration, not state
- State object is large and complex
- Single JSON attribute is clearer than many small attributes

## Conclusion

This architecture creates a clean separation of concerns:
- **PHP**: State calculation and business logic
- **Templates**: State display
- **JavaScript**: User interaction capture and navigation

The result is a simpler, more maintainable system with clearer responsibilities and fewer opportunities for bugs.
