<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridViewConfig;
use App\Model\Entity\Member;
use App\Services\GridViewService;
use Cake\Log\Log;
use Cake\ORM\Query;

/**
 * Dataverse Grid Trait
 *
 * Provides unified grid processing logic for dataverse-style grids with saved views,
 * filters, sorting, and column pickers. Supports both saved user views and system views.
 *
 * ## Features
 * - **Saved Views**: Create, save, update, and delete custom views (when enabled)
 * - **System Views**: Pre-defined views with fixed configurations (when enabled)
 * - **User Defaults**: Set personal default views per grid
 * - **System Defaults**: Administrators can set organization-wide defaults
 * - **Column Picker**: Show/hide columns dynamically
 * - **Advanced Filters**: Multi-condition filtering with operators
 * - **Multi-column Sorting**: Sort by multiple fields with direction control
 * - **Search**: Full-text search across searchable columns
 * - **Pagination**: Configurable page sizes
 *
 * ## Usage in Controllers
 * ```php
 * use App\Controller\DataverseGridTrait;
 *
 * class MembersController extends AppController
 * {
 *     use DataverseGridTrait;
 *
 *     public function gridData()
 *     {
 *         $result = $this->processDataverseGrid([
 *             'gridKey' => 'Members.index.main',
 *             'gridColumnsClass' => \App\KMP\GridColumns\MembersGridColumns::class,
 *             'baseQuery' => $this->Members->find()->contain(['Branches', 'Parents']),
 *             'tableName' => 'Members',
 *             'defaultSort' => ['Members.sca_name' => 'ASC'],
 *             'defaultPageSize' => 25,
 *         ]);
 *
 *         $this->set([
 *             'members' => $result['data'],
 *             'gridState' => $result['gridState'],
 *         ]);
 *     }
 * }
 * ```
 */
trait DataverseGridTrait
{
    /**
     * Process dataverse grid request with unified logic
     *
     * This method handles all aspects of grid processing including view management,
     * filtering, searching, sorting, and pagination. It supports both saved user views
     * and system-defined views.
     *
     * @param array $config Grid configuration with the following keys:
     *   - gridKey (string): Unique identifier for the grid
     *   - gridColumnsClass (string): Fully qualified class name for grid columns metadata
     *   - baseQuery (Query): Base query object to start with
     *   - tableName (string): Primary table name for field qualification
     *   - defaultSort (array): Default sort configuration ['field' => 'direction']
     *   - defaultPageSize (int): Default number of records per page (default: 25)
     *   - systemViews (array|null): Optional array of system views (for Warrants-style grids)
     *   - defaultSystemView (string|null): Default system view key (required if systemViews provided)
     *   - queryCallback (callable|null): Optional callback to modify query per system view
     *   - showAllTab (bool): Whether to show "All" tab (default: true for saved views, false for system views)
     *   - canAddViews (bool): Whether users can create custom views (default: true)
     *   - canFilter (bool): Whether filtering is enabled (default: true)
     *   - canExportCsv (bool): Whether CSV export button is shown (default: true)
     *   - showFilterPills (bool): Whether active filter pills/badges are displayed (default: true)
     *   - showViewTabs (bool): Whether view tabs are displayed (default: true)
     *   - enableColumnPicker (bool): Whether column picker is available (default: true)
     * @return array Result array with keys: data, gridState, columnsMetadata, etc.
     */
    protected function processDataverseGrid(array $config): array
    {
        // Extract configuration
        $gridKey = $config['gridKey'];
        $gridColumnsClass = $config['gridColumnsClass'];
        $baseQuery = $config['baseQuery'];
        $tableName = $config['tableName'];
        $defaultSort = $config['defaultSort'];
        $defaultPageSize = $config['defaultPageSize'] ?? 25;
        $systemViews = $config['systemViews'] ?? null;
        $defaultSystemView = $config['defaultSystemView'] ?? null;
        $queryCallback = $config['queryCallback'] ?? null;
        $showAllTab = $config['showAllTab'] ?? ($systemViews === null);
        $canAddViews = $config['canAddViews'] ?? ($systemViews === null);
        $canFilter = $config['canFilter'] ?? true;
        $canExportCsv = $config['canExportCsv'] ?? true;
        $showFilterPills = $config['showFilterPills'] ?? true;
        $showViewTabs = $config['showViewTabs'] ?? true;
        $enableColumnPicker = $config['enableColumnPicker'] ?? true;

        // Load column metadata
        $columnsMetadata = $gridColumnsClass::getColumns();

        // Get date-range filter columns (needed for filter application)
        $dateRangeFilterColumns = [];
        if ($canFilter) {
            $dateRangeFilterColumns = array_filter(
                $columnsMetadata,
                fn($col) => !empty($col['filterable']) && ($col['filterType'] ?? null) === 'date-range'
            );
        }

        // Get grid view service
        $gridViewService = new GridViewService(
            $this->fetchTable('GridViews'),
            new GridViewConfig()
        );

        // Get current member
        $currentMember = $this->request->getAttribute('identity');

        // Get dirty flags (user modified view configuration)
        $dirtyFlags = $this->request->getQuery('dirty', []);
        $dirtyFilters = isset($dirtyFlags['filters']);
        $dirtySearch = isset($dirtyFlags['search']);
        $dirtySort = isset($dirtyFlags['sort']);
        $dirtyColumns = isset($dirtyFlags['columns']);

        // Check if user explicitly wants to ignore default
        $ignoreDefault = $this->request->getQuery('ignore_default');

        // Get requested view ID
        $viewId = $this->request->getQuery('view_id');
        if ($viewId !== null && $viewId !== '') {
            if ($systemViews !== null) {
                // For system views, keep as string
                $viewId = (string)$viewId;
            } else {
                // For saved views, cast to integer
                $viewId = (int)$viewId;
            }
        }

        // Determine current view based on whether we're using system views or saved views
        $currentView = null;
        $selectedSystemView = null;
        $preferredViewId = null;
        $systemViewDefaults = [
            'filters' => [],
            'dateRange' => [],
            'search' => null,
            'skipFilterColumns' => [],
        ];

        if ($systemViews !== null) {
            // System views mode (Warrants-style) - now supports user views too
            // Get user preference
            if ($currentMember instanceof Member) {
                $preferredViewId = $gridViewService->getUserPreferenceViewId($gridKey, $currentMember);
            }

            // Determine which view to use
            if ($viewId !== null) {
                $requestedViewId = $viewId;
            } elseif ($preferredViewId !== null) {
                $requestedViewId = is_int($preferredViewId) ? (string)$preferredViewId : $preferredViewId;
            } else {
                $requestedViewId = $defaultSystemView;
            }

            // Check if this is a user view (numeric ID) or system view (string ID)
            if (is_numeric($requestedViewId)) {
                // Try to load as user view
                $currentView = $gridViewService->getEffectiveView($gridKey, $currentMember, (int)$requestedViewId);
                if ($currentView) {
                    // User view found - don't apply system view callback
                    $selectedSystemView = null;
                } else {
                    // User view not found, fall back to default system view
                    $requestedViewId = $defaultSystemView;
                    $selectedSystemView = $systemViews[$requestedViewId];
                }
            } else {
                // System view - validate it exists
                if (!isset($systemViews[$requestedViewId])) {
                    $requestedViewId = $defaultSystemView;
                }
                $selectedSystemView = $systemViews[$requestedViewId];
            }

            // Apply query callback (for both system views and user views)
            // When user view is active, pass null to indicate no system view filtering needed
            if ($queryCallback !== null && is_callable($queryCallback)) {
                $baseQuery = $queryCallback($baseQuery, $selectedSystemView);
            }
        } else {
            // Saved views mode (Members-style)
            if ($ignoreDefault) {
                $currentView = null;
            } else {
                $currentView = $gridViewService->getEffectiveView($gridKey, $currentMember, $viewId);
            }

            // Track preferred view (user-selected default)
            if ($currentMember instanceof Member) {
                $preference = $gridViewService->getUserPreferenceViewId($gridKey, $currentMember);
                if (is_int($preference)) {
                    $preferredViewId = $preference;
                }
            }
        }

        if ($selectedSystemView && isset($selectedSystemView['config']) && is_array($selectedSystemView['config'])) {
            $systemViewDefaults = $this->extractSystemViewDefaults($selectedSystemView['config']);
        }

        // Get available user views (load even in system views mode to show alongside)
        $availableViews = [];
        $availableViews = $this->fetchTable('GridViews')
            ->find('byGrid', ['gridKey' => $gridKey, 'memberId' => $currentMember->id])
            ->all();

        // Extract search term from URL or view config
        $searchTerm = $this->request->getQuery('search', '');
        if (!$searchTerm && !$dirtySearch && !empty($systemViewDefaults['search'])) {
            $searchTerm = $systemViewDefaults['search'];
        }
        if (!$searchTerm && $currentView && !$dirtySearch) {
            $viewConfig = $currentView->getConfigArray();
            if (!empty($viewConfig['filters'])) {
                foreach ($viewConfig['filters'] as $filter) {
                    if ($filter['field'] === '_search') {
                        $searchTerm = $filter['value'];
                        break;
                    }
                }
            }
        }

        // Apply search if provided
        if ($searchTerm && method_exists($gridColumnsClass, 'getSearchableColumns')) {
            $searchableColumns = $gridColumnsClass::getSearchableColumns();
            $searchConditions = ['OR' => []];

            foreach ($searchableColumns as $columnKey) {
                $columnMeta = $columnsMetadata[$columnKey] ?? null;

                if ($columnMeta) {
                    if ($columnMeta['type'] === 'relation' && !empty($columnMeta['renderField'])) {
                        $relationParts = explode('.', $columnMeta['renderField']);
                        if (count($relationParts) === 2) {
                            $associationName = ucfirst($relationParts[0]) . 's';
                            $fieldName = $relationParts[1];
                            $searchConditions['OR'][$associationName . '.' . $fieldName . ' LIKE'] = '%' . $searchTerm . '%';
                        }
                    } else {
                        $searchConditions['OR'][$tableName . '.' . $columnKey . ' LIKE'] = '%' . $searchTerm . '%';
                    }
                }
            }

            if (!empty($searchConditions['OR'])) {
                $baseQuery->where($searchConditions);
            }
        }

        // Apply dropdown filters (if not dirty and enabled)
        $incomingFilters = $this->request->getQuery('filter', []);
        $currentFilters = is_array($incomingFilters) ? $incomingFilters : [];
        $skipFilterColumns = [];

        if ($selectedSystemView) {
            $hasIncomingFilters = !empty($incomingFilters);

            if (!$dirtyFilters && !$hasIncomingFilters) {
                $currentFilters = $systemViewDefaults['filters'];
            }

            if (!$dirtyFilters && !$hasIncomingFilters && !empty($systemViewDefaults['dateRange'])) {
                foreach ($systemViewDefaults['dateRange'] as $filterKey => $value) {
                    if ($value !== null && $value !== '' && !array_key_exists($filterKey, $currentFilters)) {
                        $currentFilters[$filterKey] = $value;
                    }
                }
            }

            $skipFilterColumns = ($dirtyFilters || $hasIncomingFilters)
                ? []
                : ($systemViewDefaults['skipFilterColumns'] ?? []);
        }

        if (!empty($currentFilters) && is_array($currentFilters) && $canFilter) {
            foreach ($currentFilters as $columnKey => $filterValue) {
                if ($filterValue === '' || $filterValue === null || (is_array($filterValue) && empty($filterValue))) {
                    continue;
                }

                $columnMeta = $columnsMetadata[$columnKey] ?? null;

                if ($columnMeta && !empty($columnMeta['filterable'])) {
                    if (in_array($columnKey, $skipFilterColumns, true)) {
                        continue;
                    }

                    $qualifiedField = strpos($columnKey, '.') === false ? $tableName . '.' . $columnKey : $columnKey;

                    if (is_array($filterValue)) {
                        if ($columnMeta['type'] === 'boolean') {
                            $boolValues = array_map(fn($v) => (bool)(int)$v, $filterValue);
                            $baseQuery->where([$qualifiedField . ' IN' => $boolValues]);
                        } else {
                            $baseQuery->where([$qualifiedField . ' IN' => $filterValue]);
                        }
                    } else {
                        if ($columnMeta['type'] === 'boolean') {
                            $baseQuery->where([$qualifiedField => (bool)(int)$filterValue]);
                        } else {
                            $baseQuery->where([$qualifiedField => $filterValue]);
                        }
                    }
                }
            }
        }

        // Apply date range filters (if enabled)
        $dateRangeDefaults = [];
        if ($selectedSystemView && !$dirtyFilters && $canFilter && empty($incomingFilters)) {
            $dateRangeDefaults = $systemViewDefaults['dateRange'];
        }
        if ($canFilter && !empty($dateRangeFilterColumns)) {
            foreach ($dateRangeFilterColumns as $columnKey => $columnMeta) {
                $startParam = $columnKey . '_start';
                $endParam = $columnKey . '_end';
                $startDate = $this->request->getQuery($startParam);
                $endDate = $this->request->getQuery($endParam);

                if (($startDate === null || $startDate === '') && isset($dateRangeDefaults[$startParam])) {
                    $startDate = $dateRangeDefaults[$startParam];
                }
                if (($endDate === null || $endDate === '') && isset($dateRangeDefaults[$endParam])) {
                    $endDate = $dateRangeDefaults[$endParam];
                }

                if (($startDate !== null && $startDate !== '') || ($endDate !== null && $endDate !== '')) {
                    $qualifiedField = strpos($columnKey, '.') === false ? $tableName . '.' . $columnKey : $columnKey;

                    if ($startDate !== null && $startDate !== '') {
                        if (!in_array($columnKey, $skipFilterColumns, true)) {
                            $baseQuery->where([$qualifiedField . ' >=' => $startDate]);
                        }
                        // Add to current filters for display as pill
                        $currentFilters[$startParam] = $startDate;
                    }
                    if ($endDate !== null && $endDate !== '') {
                        if (!in_array($columnKey, $skipFilterColumns, true)) {
                            $baseQuery->where([$qualifiedField . ' <=' => $endDate]);
                        }
                        // Add to current filters for display as pill
                        $currentFilters[$endParam] = $endDate;
                    }
                }
            }
        }

        // Get visible columns from URL or view config or defaults
        $columnsParam = $this->request->getQuery('columns');
        if ($columnsParam) {
            $visibleColumns = explode(',', $columnsParam);
            if (method_exists($gridColumnsClass, 'getRequiredColumns')) {
                $requiredColumns = $gridColumnsClass::getRequiredColumns();
                foreach ($requiredColumns as $requiredCol) {
                    if (!in_array($requiredCol, $visibleColumns)) {
                        $visibleColumns[] = $requiredCol;
                    }
                }
            }
        } elseif ($currentView) {
            $config = new GridViewConfig();
            $viewConfig = $currentView->getConfigArray();
            $visibleColumns = $config->extractVisibleColumns($viewConfig, $columnsMetadata);
        } else {
            $visibleColumns = array_filter(array_keys($columnsMetadata), function ($key) use ($columnsMetadata) {
                return $columnsMetadata[$key]['defaultVisible'] ?? false;
            });
        }

        // Apply expression tree from system view (if present and not dirty)
        if ($selectedSystemView && !$dirtyFilters && !empty($selectedSystemView['config']['expression'])) {
            $config = new GridViewConfig();
            $expression = $config->extractExpression(
                $selectedSystemView['config'],
                $baseQuery->clause('where') ?? $baseQuery->newExpr(),
                $tableName
            );

            if ($expression !== null) {
                $baseQuery->where($expression);
            }
        }

        // Apply view configuration filters (only if not dirty)
        // Note: This handles legacy flat filters. New views should use expressions instead.
        if ($currentView && !$dirtyFilters) {
            $config = new GridViewConfig();
            $viewConfig = $currentView->getConfigArray();

            // Check if view has expression tree (preferred)
            $expression = $config->extractExpression(
                $viewConfig,
                $baseQuery->clause('where') ?? $baseQuery->newExpr(),
                $tableName
            );

            if ($expression !== null) {
                $baseQuery->where($expression);
            } else {
                // Fallback to legacy flat filters
                $filterConditions = $config->extractFilters($viewConfig);

                if (!empty($filterConditions)) {
                    $qualifiedFilters = [];
                    foreach ($filterConditions as $fieldCondition => $value) {
                        $parts = explode(' ', $fieldCondition, 2);
                        $field = $parts[0];
                        $operator = $parts[1] ?? '';

                        // Qualify field name with table name if not already qualified
                        $qualifiedField = strpos($field, '.') === false ? $tableName . '.' . $field : $field;
                        $qualifiedKey = $operator ? $qualifiedField . ' ' . $operator : $qualifiedField;
                        $qualifiedFilters[$qualifiedKey] = $value;
                    }
                    $baseQuery->where($qualifiedFilters);
                }
            }
        }

        // Get page size from view config or default
        if ($currentView) {
            $config = new GridViewConfig();
            $viewConfig = $currentView->getConfigArray();
            $pageSize = $config->extractPageSize($viewConfig);
        } else {
            $pageSize = $defaultPageSize;
        }

        // Apply sort from URL parameters or view config
        $sortField = $this->request->getQuery('sort');
        $sortDirection = $this->request->getQuery('direction');
        $currentSort = [];

        if ($sortField && $sortDirection) {
            $columnMeta = $columnsMetadata[$sortField] ?? null;
            if ($columnMeta && isset($columnMeta['queryField'])) {
                $actualSortField = $columnMeta['queryField'];
            } elseif (strpos($sortField, '.') === false) {
                $actualSortField = $tableName . '.' . $sortField;
            } else {
                $actualSortField = $sortField;
            }
            $baseQuery->orderBy([$actualSortField => strtoupper($sortDirection)]);
            $currentSort = ['field' => $sortField, 'direction' => $sortDirection];
        } elseif ($currentView && !$dirtySort) {
            $viewConfig = $currentView->getConfigArray();
            if (!empty($viewConfig['sort'])) {
                $sortConfig = $viewConfig['sort'][0] ?? null;
                if ($sortConfig) {
                    $sortField = $sortConfig['field'];
                    $sortDirection = $sortConfig['direction'];
                    $columnMeta = $columnsMetadata[$sortField] ?? null;
                    if ($columnMeta && isset($columnMeta['queryField'])) {
                        $actualSortField = $columnMeta['queryField'];
                    } elseif (strpos($sortField, '.') === false) {
                        $actualSortField = $tableName . '.' . $sortField;
                    } else {
                        $actualSortField = $sortField;
                    }
                    $baseQuery->orderBy([$actualSortField => strtoupper($sortDirection)]);
                    $currentSort = ['field' => $sortField, 'direction' => $sortDirection];
                } else {
                    $baseQuery->orderBy($defaultSort);
                }
            } else {
                $baseQuery->orderBy($defaultSort);
            }
        } else {
            $baseQuery->orderBy($defaultSort);
        }

        // Apply authorization scope if available
        if (method_exists($this, 'Authorization') && $this->Authorization) {
            $baseQuery = $this->Authorization->applyScope($baseQuery);
        }

        // Check for CSV export request
        if ($this->isCsvExportRequest()) {
            // Return query for CSV export (controller will handle the export)
            return [
                'isCsvExport' => true,
                'query' => $baseQuery,
                'visibleColumns' => $visibleColumns,
                'columnsMetadata' => $columnsMetadata,
                'currentView' => $currentView,
                'gridState' => [
                    'view' => [
                        'currentName' => $currentView ? $currentView->name : ($selectedSystemView['name'] ?? 'All'),
                    ],
                ],
            ];
        }

        // Set up pagination
        $this->paginate = ['limit' => $pageSize];
        $data = $this->paginate($baseQuery);

        // Check if there are any searchable columns (for search functionality)
        $hasSearchableColumns = !empty(array_filter(
            $columnsMetadata,
            fn($col) => !empty($col['searchable'])
        ));

        // Get dropdown filter columns and prepare filter options
        $dropdownFilterColumns = [];
        $filterOptions = [];
        if ($canFilter && method_exists($gridColumnsClass, 'getDropdownFilterColumns')) {
            $dropdownFilterColumns = $gridColumnsClass::getDropdownFilterColumns();

            foreach ($dropdownFilterColumns as $columnKey => $columnMeta) {
                if (!empty($columnMeta['filterOptions'])) {
                    $filterOptions[$columnKey] = $columnMeta['filterOptions'];
                } elseif (!empty($columnMeta['filterOptionsSource'])) {
                    $filterOptions[$columnKey] = $this->loadFilterOptions($columnMeta['filterOptionsSource']);
                }
            }
        }

        // Show filter button if search OR dropdown filters OR date-range filters are available
        $showFilterButton = $canFilter && (
            $hasSearchableColumns ||
            !empty($dropdownFilterColumns) ||
            !empty($dateRangeFilterColumns)
        );

        // Get current filter values from query string OR view config
        $currentSearch = $this->request->getQuery('search', '');
        if (empty($currentFilters) && empty($currentSearch) && $currentView && !$dirtyFilters && !$dirtySearch) {
            $viewConfig = $currentView->getConfigArray();
            if (!empty($viewConfig['filters'])) {
                foreach ($viewConfig['filters'] as $filter) {
                    $field = $filter['field'];
                    $value = $filter['value'];
                    $operator = $filter['operator'] ?? 'eq';

                    if ($field === '_search') {
                        $currentSearch = $value;
                        continue;
                    }

                    if ($operator === 'in' && is_array($value)) {
                        $currentFilters[$field] = $value;
                    } else {
                        if (isset($currentFilters[$field])) {
                            if (!is_array($currentFilters[$field])) {
                                $currentFilters[$field] = [$currentFilters[$field]];
                            }
                            $currentFilters[$field][] = $value;
                        } else {
                            $currentFilters[$field] = $value;
                        }
                    }
                }
            }
        }

        if ($selectedSystemView && !$dirtySearch && !$currentSearch && !empty($systemViewDefaults['search'])) {
            $currentSearch = $systemViewDefaults['search'];
        }

        // Build complete grid state object
        $gridState = $this->buildDataverseGridState(
            currentView: $currentView,
            selectedSystemView: $selectedSystemView,
            systemViews: $systemViews,
            availableViews: $availableViews,
            currentMember: $currentMember instanceof Member ? $currentMember : null,
            preferredViewId: $preferredViewId,
            search: $currentSearch ?: $searchTerm,
            filters: $currentFilters,
            filterOptions: $filterOptions,
            dropdownFilterColumns: $dropdownFilterColumns,
            dateRangeFilterColumns: $dateRangeFilterColumns,
            sort: $currentSort,
            visibleColumns: $visibleColumns,
            allColumns: $columnsMetadata,
            gridKey: $gridKey,
            pageSize: $pageSize,
            showAllTab: $showAllTab,
            canAddViews: $canAddViews,
            canFilter: $showFilterButton,
            hasSearch: $hasSearchableColumns,
            hasDropdownFilters: !empty($dropdownFilterColumns),
            hasDateRangeFilters: !empty($dateRangeFilterColumns),
            skipFilterColumns: $skipFilterColumns,
            canExportCsv: $canExportCsv,
            showFilterPills: $showFilterPills,
            showViewTabs: $showViewTabs,
            enableColumnPicker: $enableColumnPicker
        );

        // Return all results
        return [
            'data' => $data,
            'gridState' => $gridState,
            'columnsMetadata' => $columnsMetadata,
            'visibleColumns' => $visibleColumns,
            'dropdownFilterColumns' => $dropdownFilterColumns,
            'filterOptions' => $filterOptions,
            'currentFilters' => $currentFilters,
            'currentSearch' => $currentSearch,
            'currentView' => $currentView,
            'availableViews' => $availableViews,
            'gridKey' => $gridKey,
            'currentSort' => $currentSort,
            'currentMember' => $currentMember,
        ];
    }

    /**
     * Build complete grid state object (single source of truth)
     *
     * @param mixed $currentView Current saved view entity (null for system views or "All")
     * @param array|null $selectedSystemView Currently selected system view
     * @param array|null $systemViews All available system views
     * @param iterable $availableViews Collection of saved views
     * @param Member|null $currentMember Authenticated member
     * @param int|string|null $preferredViewId Preferred view ID from user preferences
     * @param string $search Current search term
     * @param array $filters Active filters by column key
     * @param array $filterOptions Available filter options by column key
     * @param array $dropdownFilterColumns Metadata for filterable columns
     * @param array $sort Current sort configuration
     * @param array $visibleColumns Array of visible column keys
     * @param array $allColumns Complete column metadata
     * @param string $gridKey Unique grid identifier
     * @param int $pageSize Number of rows per page
     * @param bool $showAllTab Whether to show "All" tab
     * @param bool $canAddViews Whether users can create custom views
     * @param bool $canFilter Whether filtering is enabled
     * @param bool $canExportCsv Whether CSV export button is shown
     * @param bool $showFilterPills Whether active filter pills/badges are displayed
     * @param bool $showViewTabs Whether view tabs are displayed
     * @param bool $enableColumnPicker Whether column picker is available
     * @param array $skipFilterColumns Columns with filter UI but not query application
     * @return array Complete grid state
     */
    protected function buildDataverseGridState(
        $currentView,
        ?array $selectedSystemView,
        ?array $systemViews,
        iterable $availableViews,
        ?Member $currentMember,
        $preferredViewId,
        string $search,
        array $filters,
        array $filterOptions,
        array $dropdownFilterColumns,
        array $dateRangeFilterColumns,
        array $sort,
        array $visibleColumns,
        array $allColumns,
        string $gridKey,
        int $pageSize,
        bool $showAllTab,
        bool $canAddViews,
        bool $canFilter,
        bool $hasSearch,
        bool $hasDropdownFilters,
        bool $hasDateRangeFilters,
        array $skipFilterColumns,
        bool $canExportCsv,
        bool $showFilterPills,
        bool $showViewTabs,
        bool $enableColumnPicker
    ): array {
        // Format views based on whether we're using system or saved views
        $formattedViews = [];
        $systemDefaultId = null;
        $currentId = null;
        $currentName = 'All';

        if ($systemViews !== null) {
            // System views mode - add system views first
            $effectiveDefaultId = $preferredViewId ?? array_key_first($systemViews);

            // Set current view info (could be system view OR user view)
            if ($currentView) {
                // User view is active
                $currentId = $currentView->id;
                $currentName = $currentView->name;
            } elseif ($selectedSystemView) {
                // System view is active
                $currentId = $selectedSystemView['id'];
                $currentName = $selectedSystemView['name'];
            }

            foreach ($systemViews as $view) {
                $isPreferred = $preferredViewId !== null && $view['id'] === $preferredViewId;
                $formattedViews[] = [
                    'id' => $view['id'],
                    'name' => $view['name'],
                    'description' => $view['description'] ?? '',
                    'isPreferred' => $isPreferred,
                    'isDefault' => $view['id'] === $effectiveDefaultId,
                    'isUserDefault' => $isPreferred,
                    'isSystemDefault' => false,
                    'canManage' => $view['canManage'] ?? false,
                ];
            }

            // Add user views after system views (if any)
            foreach ($availableViews as $view) {
                $isPreferred = $preferredViewId !== null && (int)$view->id === $preferredViewId;
                $formattedViews[] = [
                    'id' => $view->id,
                    'name' => $view->name,
                    'description' => $view->description ?? '',
                    'isPreferred' => $isPreferred,
                    'isDefault' => $isPreferred,
                    'isUserDefault' => $view->isUserDefault(),
                    'isSystemDefault' => false,
                    'canManage' => $currentMember instanceof Member && $view->member_id === $currentMember->id,
                ];
            }
        } else {
            // Saved views mode
            foreach ($availableViews as $view) {
                $isPreferred = $preferredViewId !== null && (int)$view->id === $preferredViewId;
                if ($view->isSystemDefault()) {
                    $systemDefaultId = (int)$view->id;
                }

                $formattedViews[] = [
                    'id' => $view->id,
                    'name' => $view->name,
                    'description' => $view->description ?? '',
                    'isPreferred' => $isPreferred,
                    'isDefault' => $isPreferred || ($view->is_default ?? false),
                    'isUserDefault' => $view->isUserDefault(),
                    'isSystemDefault' => $view->isSystemDefault(),
                    'canManage' => $currentMember instanceof Member && $view->member_id === $currentMember->id,
                ];
            }

            if ($currentView) {
                $currentId = $currentView->id;
                $currentName = $currentView->name;
            }
        }

        // Extract filter grouping information from expression tree (for OR visual indicators)
        $filterGrouping = $this->extractFilterGrouping($selectedSystemView, $skipFilterColumns);

        // Build filter state
        $filterState = [
            'active' => $filters,
            'available' => [],
            'grouping' => $filterGrouping,
        ];

        foreach ($dropdownFilterColumns as $columnKey => $columnMeta) {
            if (isset($filterOptions[$columnKey])) {
                $filterState['available'][$columnKey] = [
                    'label' => $columnMeta['label'],
                    'options' => $filterOptions[$columnKey],
                ];
            }
        }

        // Add date range filters to available filters for pill display only
        // Mark them so JavaScript knows to skip them in filter navigation tabs
        foreach ($dateRangeFilterColumns as $columnKey => $columnMeta) {
            $startParam = $columnKey . '_start';
            $endParam = $columnKey . '_end';

            $filterState['available'][$startParam] = [
                'label' => $columnMeta['label'] . ' (after)',
                'type' => 'date-range-start',
                'options' => [],
                'baseField' => $columnKey,
            ];

            $filterState['available'][$endParam] = [
                'label' => $columnMeta['label'] . ' (before)',
                'type' => 'date-range-end',
                'options' => [],
                'baseField' => $columnKey,
            ];
        }

        return [
            'view' => [
                'currentId' => $currentId,
                'currentName' => $currentName,
                'preferredId' => $preferredViewId,
                'systemDefaultId' => $systemDefaultId,
                'isPreferred' => $currentView && $preferredViewId !== null ? ((int)$currentView->id === $preferredViewId) : ($selectedSystemView && $preferredViewId !== null && $selectedSystemView['id'] === $preferredViewId),
                'isDefault' => $currentView ? ($preferredViewId !== null && (int)$currentView->id === $preferredViewId) : ($selectedSystemView && $preferredViewId !== null && $selectedSystemView['id'] === $preferredViewId),
                'isUserDefault' => $currentView ? $currentView->isUserDefault() : ($preferredViewId !== null),
                'available' => $formattedViews,
            ],
            'search' => $search,
            'filters' => $filterState,
            'sort' => $sort,
            'columns' => [
                'visible' => $visibleColumns,
                'all' => $allColumns,
            ],
            'config' => [
                'gridKey' => $gridKey,
                'primaryKey' => 'id',
                'pageSize' => $pageSize,
                'showAllTab' => $showAllTab,
                'canAddViews' => $canAddViews,
                'canFilter' => $canFilter,
                'hasSearch' => $hasSearch,
                'hasDropdownFilters' => $hasDropdownFilters,
                'hasDateRangeFilters' => $hasDateRangeFilters,
                'canExportCsv' => $canExportCsv,
                'showFilterPills' => $showFilterPills,
                'showViewTabs' => $showViewTabs,
                'enableColumnPicker' => $enableColumnPicker,
            ],
            'dateRangeFilterColumns' => $dateRangeFilterColumns,
        ];
    }

    /**
     * Load filter options from a data source
     *
     * @param string $source Source identifier (e.g., 'branches')
     * @return array Filter options
     */
    protected function loadFilterOptions(string $source): array
    {
        switch ($source) {
            case 'branches':
                $branches = $this->fetchTable('Branches')
                    ->find('list', [
                        'keyField' => 'id',
                        'valueField' => 'name',
                    ])
                    ->orderBy(['name' => 'ASC'])
                    ->toArray();

                return array_map(
                    fn($id, $name) => ['value' => (string)$id, 'label' => $name],
                    array_keys($branches),
                    $branches
                );

            default:
                return [];
        }
    }

    /**
     * Extract default filters/search metadata for a system view configuration
     *
     * @param array<string, mixed> $systemViewConfig Raw system view config
     * @return array{filters: array, dateRange: array, search: ?string, skipFilterColumns: array}
     */
    protected function extractSystemViewDefaults(array $systemViewConfig): array
    {
        $defaults = [
            'filters' => [],
            'dateRange' => [],
            'search' => null,
            'skipFilterColumns' => [],
        ];

        if (!is_array($systemViewConfig)) {
            return $defaults;
        }

        if (!empty($systemViewConfig['skipFilterColumns']) && is_array($systemViewConfig['skipFilterColumns'])) {
            $defaults['skipFilterColumns'] = array_values(array_filter(
                $systemViewConfig['skipFilterColumns'],
                fn($value) => is_string($value) && $value !== ''
            ));
        }

        if (empty($systemViewConfig['filters']) || !is_array($systemViewConfig['filters'])) {
            return $defaults;
        }

        foreach ($systemViewConfig['filters'] as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? null;
            $value = $filter['value'] ?? null;

            if (!$field) {
                continue;
            }

            if ($field === '_search') {
                if ($value !== null && $value !== '') {
                    $defaults['search'] = (string)$value;
                }
                continue;
            }

            switch ($operator) {
                case 'eq':
                    if ($value !== null && $value !== '') {
                        $defaults['filters'][$field] = (string)$value;
                    }
                    break;
                case 'in':
                    $values = is_array($value) ? $value : [$value];
                    $sanitized = array_values(array_filter($values, fn($v) => $v !== null && $v !== ''));
                    if (!empty($sanitized)) {
                        $defaults['filters'][$field] = array_map(fn($v) => (string)$v, $sanitized);
                    }
                    break;
                case 'dateRange':
                    if (is_array($value)) {
                        $start = $value[0] ?? null;
                        $end = $value[1] ?? null;
                        if ($start !== null && $start !== '') {
                            $defaults['dateRange'][$field . '_start'] = (string)$start;
                        }
                        if ($end !== null && $end !== '') {
                            $defaults['dateRange'][$field . '_end'] = (string)$end;
                        }
                    }
                    break;
                case 'gte':
                case 'gt':
                    if ($value !== null && $value !== '') {
                        $defaults['dateRange'][$field . '_start'] = (string)$value;
                    }
                    break;
                case 'lte':
                case 'lt':
                    if ($value !== null && $value !== '') {
                        $defaults['dateRange'][$field . '_end'] = (string)$value;
                    }
                    break;
                default:
                    break;
            }
        }

        return $defaults;
    }

    /**
     * Check if the current request is for CSV export
     *
     * @return bool True if CSV export is requested
     */
    protected function isCsvExportRequest(): bool
    {
        return $this->request->getQuery('export') === 'csv';
    }

    /**
     * Handle CSV export from grid result
     *
     * Generates a CSV export response from the grid processing result.
     * Respects visible columns, applies proper headers, and generates view-aware filenames.
     *
     * @param array $result Result from processDataverseGrid() with isCsvExport flag
     * @param \App\Services\CsvExportService $csvExportService CSV export service instance
     * @param string $entityName Base name for the export file (e.g., 'members', 'warrants')
     * @return \Cake\Http\Response CSV download response
     * @throws \Authorization\Exception\ForbiddenException If user lacks export permission
     */
    protected function handleCsvExport(array $result, $csvExportService, string $entityName): \Cake\Http\Response
    {
        // Check authorization for export
        $tableName = ucfirst($entityName); // Capitalize for table name (e.g., 'members' -> 'Members')
        $table = $this->fetchTable($tableName);
        $this->Authorization->authorize($table, 'export');

        $query = $result['query'];
        $visibleColumns = $result['visibleColumns'];
        $columnsMetadata = $result['columnsMetadata'];

        // Select only visible columns for export
        $selectFields = [];
        $headers = [];
        $fieldMapping = []; // Maps result field names to header labels

        foreach ($visibleColumns as $columnKey) {
            $columnMeta = $columnsMetadata[$columnKey] ?? null;
            if (!$columnMeta) {
                continue;
            }

            // Skip non-exportable columns (exportable defaults to true if not specified)
            if (isset($columnMeta['exportable']) && $columnMeta['exportable'] === false) {
                continue;
            }

            // Build field name, alias, and header
            $headerLabel = $columnMeta['label'] ?? $columnKey;

            if ($columnMeta['type'] === 'relation' && !empty($columnMeta['queryField'])) {
                // For relation fields, use queryField for SQL SELECT (has correct table alias)
                $alias = $columnKey . '_display';
                $selectFields[$alias] = $columnMeta['queryField'];
                $fieldMapping[$alias] = $headerLabel;
            } else {
                // For regular fields, use column key as alias
                $selectFields[$columnKey] = $tableName . '.' . $columnKey;
                $fieldMapping[$columnKey] = $headerLabel;
            }
        }

        // Apply select fields to query
        $query->select($selectFields);

        // Execute query and transform results to use header labels as keys
        $results = $query->all();
        $transformedData = [];
        foreach ($results as $row) {
            $rowArray = $row->toArray();
            $transformedRow = [];
            foreach ($fieldMapping as $fieldName => $headerLabel) {
                $transformedRow[$headerLabel] = $rowArray[$fieldName] ?? '';
            }
            $transformedData[] = $transformedRow;
        }

        // Generate filename with current view name
        $filename = $entityName;
        $currentViewName = $result['gridState']['view']['currentName'] ?? null;
        if ($currentViewName && $currentViewName !== 'All') {
            $filename .= '_' . preg_replace('/[^a-z0-9_-]/i', '_', strtolower($currentViewName));
        }
        $filename .= '_' . date('Y-m-d') . '.csv';

        // Export to CSV with transformed data
        return $csvExportService->outputCsv($transformedData, $filename);
    }

    /**
     * Extract filter grouping information from expression tree
     *
     * Analyzes the expression tree to determine OR relationships between filters.
     * Returns metadata that the frontend can use to display visual OR indicators.
     *
     * @param array|null $selectedSystemView Currently active system view
     * @param array $skipFilterColumns Columns that show as pills but don't query
     * @return array Grouping metadata with 'orGroups' array
     */
    protected function extractFilterGrouping(?array $selectedSystemView, array $skipFilterColumns): array
    {
        $grouping = [
            'orGroups' => [], // Array of arrays - each inner array is a group of fields joined by OR
        ];

        if (!$selectedSystemView || empty($selectedSystemView['config']['expression'])) {
            return $grouping;
        }

        $expression = $selectedSystemView['config']['expression'];

        // Only process if we have skipFilterColumns (indicating expression handles query)
        if (empty($skipFilterColumns)) {
            return $grouping;
        }

        // Check if this is a top-level OR expression
        if (isset($expression['type']) && strtoupper($expression['type']) === 'OR') {
            $conditions = $expression['conditions'] ?? [];
            $orGroup = [];

            foreach ($conditions as $condition) {
                // Extract field from leaf condition or nested AND group
                if (isset($condition['field'])) {
                    // Simple leaf condition
                    $field = $condition['field'];
                    if (in_array($field, $skipFilterColumns, true)) {
                        $orGroup[] = $field;
                    }
                } elseif (isset($condition['type']) && strtoupper($condition['type']) === 'AND') {
                    // Nested AND group - extract all fields
                    $nestedConditions = $condition['conditions'] ?? [];
                    foreach ($nestedConditions as $nested) {
                        if (isset($nested['field'])) {
                            $field = $nested['field'];
                            if (in_array($field, $skipFilterColumns, true)) {
                                $orGroup[] = $field;
                            }
                        }
                    }
                }
            }

            if (!empty($orGroup)) {
                $grouping['orGroups'][] = $orGroup;
            }
        }

        return $grouping;
    }
}
