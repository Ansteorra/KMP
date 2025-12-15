<?php

/**
 * Grid View Toolbar Element - Dumb Container Version
 * 
 * This toolbar is just a container with data attributes.
 * The Stimulus controller will read the JSON state and populate the UI.
 * 
 * Server-side only handles:
 * - Feature availability (can we filter? can we add views?)
 * - Controller identifier
 * - Grid key for modal IDs
 * 
 * Everything else is driven by JavaScript reading the state JSON.
 * 
 * @var \App\View\AppView $this
 * @var array $gridState Complete grid state (only used for feature flags)
 * @var string $controllerName The Stimulus controller identifier
 */

$controllerName = $controllerName ?? 'grid-view';
$gridKey = $gridState['config']['gridKey'] ?? 'grid';
$gridKeyEscaped = h($gridKey);

// Feature flags from server
$canFilter = $gridState['config']['canFilter'] ?? true;
$canAddViews = $gridState['config']['canAddViews'] ?? true;
$showAllTab = $gridState['config']['showAllTab'] ?? true;
$hasSearch = $gridState['config']['hasSearch'] ?? false;
$hasDropdownFilters = $gridState['config']['hasDropdownFilters'] ?? false;
$hasDateRangeFilters = $gridState['config']['hasDateRangeFilters'] ?? false;
$canExportCsv = $gridState['config']['canExportCsv'] ?? true;
$showFilterPills = $gridState['config']['showFilterPills'] ?? true;
$showViewTabs = $gridState['config']['showViewTabs'] ?? true;
$enableColumnPicker = $gridState['config']['enableColumnPicker'] ?? true;
$dateRangeFilterColumns = $gridState['dateRangeFilterColumns'] ?? [];

// Build searchable columns description from column metadata
$allColumns = $gridState['columns']['all'] ?? [];
$searchableLabels = [];
foreach ($allColumns as $columnKey => $columnMeta) {
    if (!empty($columnMeta['searchable'])) {
        $searchableLabels[] = $columnMeta['label'] ?? $columnKey;
    }
}
$searchDescription = !empty($searchableLabels)
    ? __('Search across {0}', implode(', ', $searchableLabels))
    : __('Search');
?>

<div class="grid-view-toolbar mb-3" data-toolbar-container>

    <?php if ($showViewTabs): ?>
        <!-- Row 1: View Tabs (populated by JS) -->
        <div class="mb-3">
            <ul class="nav nav-tabs" role="tablist" data-view-tabs-container>
                <!-- JS will populate view tabs here -->
                <?php if (!$showAllTab): ?>
                    <!-- Hidden marker for JS to know not to show "All" tab -->
                    <li style="display: none;" data-no-all-tab></li>
                <?php endif; ?>

                <?php if ($canAddViews): ?>
                    <!-- Create View Button -->
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" data-action="click-><?= h($controllerName) ?>#saveView"
                            title="Create new view" role="tab">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Row 2: Filters and Actions -->
    <div class="d-flex justify-content-between align-items-start gap-3">
        <?php if ($showFilterPills): ?>
            <!-- Left: Active Filter Badges (populated by JS) -->
            <div class="d-flex flex-wrap gap-2 align-items-center grid-view-badges" data-filter-pills-container>
                <!-- JS will populate filter pills and search badge here -->
            </div>
        <?php else: ?>
            <!-- Empty spacer when pills are hidden -->
            <div></div>
        <?php endif; ?>

        <!-- Right: Filter Dropdown Button and Export -->
        <?php if ($canFilter || $canExportCsv): ?>
            <div class="d-flex gap-2 align-items-center flex-shrink-0">
                <!-- CSV Export Button -->
                <?php if ($canExportCsv): ?>
                    <button type="button" class="btn btn-outline-primary d-flex align-items-center gap-2"
                        data-action="click-><?= h($controllerName) ?>#exportCsv"
                        title="Export to CSV">
                        <i class="bi bi-download"></i>
                        <span>Export CSV</span>
                    </button>
                <?php endif; ?>

                <?php if ($canFilter): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button"
                            id="filterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                            data-filter-button>
                            <i class="bi bi-funnel"></i>
                            <span>Filter</span>
                            <!-- JS will add badge here if needed -->
                        </button>

                        <div class="dropdown-menu dropdown-menu-end p-0"
                            style="min-width: 420px; max-height: 520px; overflow: hidden;" aria-labelledby="filterDropdown">

                            <?php if ($hasSearch): ?>
                                <!-- Search Section -->
                                <div class="border-bottom">
                                    <div class="px-3 py-2 bg-light">
                                        <strong class="text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                            Search
                                        </strong>
                                    </div>
                                    <div class="px-3 py-2">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" placeholder="Search..."
                                                data-<?= h($controllerName) ?>-target="searchInput"
                                                data-action="keyup-><?= h($controllerName) ?>#handleSearchKeyup keydown.enter-><?= h($controllerName) ?>#performSearch">
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <?= h($searchDescription) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($hasDropdownFilters): ?>
                                <!-- Filter Tree -->
                                <div class="border-bottom">
                                    <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                                        <strong class="text-uppercase small" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                            Filters
                                        </strong>
                                        <small class="text-muted">Select a filter to see options</small>
                                    </div>
                                    <div class="d-flex" style="max-height: 360px; overflow-y: auto;">
                                        <!-- Filter Navigation (populated by JS) -->
                                        <div class="list-group list-group-flush flex-shrink-0" style="min-width: 190px;"
                                            role="tablist" data-filter-nav-container>
                                            <!-- JS will populate filter tabs here -->
                                        </div>

                                        <!-- Filter Panels (populated by JS) -->
                                        <div class="flex-grow-1 border-start" style="min-width: 230px;" data-filter-panels-container>
                                            <!-- JS will populate filter panels here -->
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Clear All Footer -->
                            <div class="px-3 py-2 bg-light border-top" data-clear-filters-container style="display: none;">
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0"
                                    data-action="click-><?= h($controllerName) ?>#clearAllFilters">
                                    Clear all filters
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($enableColumnPicker): ?>
    <!-- Column Picker Modal (populated by JS) -->
    <div class="modal fade" id="columnPickerModal-<?= $gridKeyEscaped ?>" tabindex="-1"
        aria-labelledby="columnPickerLabel-<?= $gridKeyEscaped ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="columnPickerLabel-<?= $gridKeyEscaped ?>">
                        Select Columns
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Check columns to show/hide. Drag to reorder.
                    </p>

                    <!-- Sortable column list (populated by JS) -->
                    <div data-controller="sortable-list"
                        data-action="sortable-list:reordered-><?= h($controllerName) ?>#handleColumnReorder"
                        class="list-group" data-column-list-container>
                        <!-- JS will populate column list here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                        data-action="click-><?= h($controllerName) ?>#applyColumnChanges">
                        <i class="bi bi-check-lg"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>