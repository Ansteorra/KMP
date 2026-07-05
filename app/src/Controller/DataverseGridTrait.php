<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\DataverseGridQueryContext;
use App\KMP\GridViewConfig;
use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper as TzHelper;
use App\Model\Entity\Member;
use App\Services\Cache\TenantAwareCache;
use App\Services\GridViewService;
use Cake\Cache\Cache;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Utility\Inflector;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Unified grid processing for dataverse-style grids with saved views, filters, and sorting.
 *
 * Features: saved/system views, user defaults, column picker, advanced filters,
 * multi-column sorting, search, and pagination. See app/docs/dataverse-grid-*.md for usage.
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
     *   - canFilter (bool): Whether user filtering is enabled (default: true). When false,
     *       users cannot add/remove filters via the UI or query parameters. However, filters
     *       defined by system views are ALWAYS applied regardless of this setting.
     *   - canExportCsv (bool): Whether CSV export button is shown (default: true)
     *   - showFilterPills (bool): Whether active filter pills/badges are displayed (default: true)
     *   - showViewTabs (bool): Whether view tabs are displayed (default: true)
     *   - enableColumnPicker (bool): Whether column picker is available (default: true)
     *   - lockedFilters (array): Array of filter column keys that cannot be removed by users.
     *       Locked filters will not show remove (×) buttons and their values cannot be
     *       cleared via query string parameters. Useful for embedded grids where context
     *       filters (e.g., member_id) must always be applied.
     *   - enableBulkSelection (bool): Whether row selection checkboxes are shown (default: false)
     *   - bulkSelection (array): Bulk selection accessibility label configuration.
     *       Keys: selectAllLabel, rowLabelTemplate, disabledLabel. rowLabelTemplate supports
     *       {field_key} placeholders resolved from each row, including dotted paths and column renderField aliases.
     *   - bulkActions (array): Array of bulk action button configurations when enableBulkSelection is true.
     *       Each action is an array with keys: label, icon, modalTarget, permission.
     *   - disablePagination (bool): When true, bypasses the paginator and returns all matching
     *       records. Use for views (e.g. calendar) that are already filtered to a bounded date
     *       range and must show every result without an arbitrary row cap.
     *       **Caller responsibility**: the `baseQuery` MUST include WHERE clauses that bound
     *       the result set (e.g. a date range) to avoid fetching unbounded data. (default: false)
     *
     * NOTE: Authorization scope must be applied to baseQuery BEFORE calling this method.
     * Use `$baseQuery = $this->Authorization->applyScope($baseQuery, 'index');` in your
     * controller before passing the query to processDataverseGrid().
     * @return array Result array with keys: data, gridState, columnsMetadata, etc.
     */
    protected function processDataverseGrid(array $config): array
    {
        // Release PHP session lock early so concurrent turbo-frame requests
        // from the same user are not serialized by the file-based session handler.
        // Grid endpoints are read-only and do not need to write session data.
        $this->request->getSession()->close();

        // Extract configuration
        $gridKey = $config['gridKey'];
        $gridColumnsClass = $config['gridColumnsClass'];
        $baseQuery = $config['baseQuery'];
        $tableName = $config['tableName'];
        $defaultSort = $config['defaultSort'];
        $defaultPageSize = $config['defaultPageSize'] ?? 25;
        $disablePagination = $config['disablePagination'] ?? false;
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
        $lockedFilters = $config['lockedFilters'] ?? [];
        $enableBulkSelection = $config['enableBulkSelection'] ?? false;
        $bulkSelection = $config['bulkSelection'] ?? [];
        $bulkActions = $config['bulkActions'] ?? [];
        $bulkSelectionDataFields = $config['bulkSelectionDataFields'] ?? [];
        $bulkSelectionDisabledField = $config['bulkSelectionDisabledField'] ?? null;
        $bulkSelectionHideDisabledControl = $config['bulkSelectionHideDisabledControl'] ?? false;
        $metadataMode = $config['metadataMode'] ?? ($this->isDataverseTableFrameRequest() ? 'table' : 'full');
        // System-view grids (e.g. Officers warrant tabs) need view metadata even when custom views are disabled.
        $loadViewMetadata = $metadataMode === 'full' && ($canAddViews || $systemViews !== null);
        $loadFilterMetadata = $metadataMode === 'full';

        // Load column metadata
        $columnsMetadata = $gridColumnsClass::getColumns();

        // Get columns with custom filter handlers
        // These columns have complex filter logic defined in the GridColumns class
        $customFilterColumns = array_filter(
            $columnsMetadata,
            fn($col) => !empty($col['customFilterHandler']),
        );

        // Get columns that require custom filtering (skipAutoFilter: true OR have customFilterHandler)
        // These columns should be excluded from automatic WHERE clause application
        $autoSkipFilterColumns = array_keys(array_filter(
            $columnsMetadata,
            fn($col) => !empty($col['skipAutoFilter']) || !empty($col['customFilterHandler']),
        ));

        // Get date-range filter columns (needed for filter application)
        // Note: Always populate this regardless of canFilter, since system view filters
        // need to be applied even when user filtering is disabled
        $dateRangeFilterColumns = array_filter(
            $columnsMetadata,
            fn($col) => !empty($col['filterable']) && ($col['filterType'] ?? null) === 'date-range',
        );

        // Get grid view service
        $gridViewService = new GridViewService(
            $this->fetchTable('GridViews'),
            new GridViewConfig(),
        );

        // Get current member
        $currentMember = $this->request->getAttribute('identity');

        // Get dirty flags (user modified view configuration)
        // Note: dirty flags for filters are only respected when canFilter is true
        $dirtyFlags = $this->request->getQuery('dirty', []);
        $dirtyFilters = $canFilter && isset($dirtyFlags['filters']);
        $dirtySearch = isset($dirtyFlags['search']);
        $dirtySort = isset($dirtyFlags['sort']);

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
        if ($currentMember instanceof Member) {
            $preferredViewId = $gridViewService->getUserPreferenceViewId($gridKey, $currentMember);
        }

        if ($systemViews !== null) {
            // System views mode (Warrants-style) - now supports user views too

            // Check if user explicitly wants to show "All" (ignore default view)
            if ($ignoreDefault) {
                // Show all records - no system view filters applied
                $selectedSystemView = null;
                $requestedViewId = null;
            } else {
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

            if (!is_int($preferredViewId)) {
                $preferredViewId = null;
            }
        }

        if ($selectedSystemView && isset($selectedSystemView['config']) && is_array($selectedSystemView['config'])) {
            $systemViewDefaults = $this->extractSystemViewDefaults($selectedSystemView['config']);
        }

        // Get available user views for toolbar metadata (skip on table-frame refreshes).
        $availableViews = [];
        if ($loadViewMetadata && $currentMember instanceof Member) {
            $availableViews = $this->loadAvailableViews($gridKey, (int)$currentMember->id);
        }

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
                // Check if columnKey is a full queryField path (e.g., 'AppSettings.value')
                // or just a column key (e.g., 'name')
                if (str_contains($columnKey, '.')) {
                    // It's a full queryField path - use directly
                    $searchCondition = $this->buildDataverseGridSearchCondition(
                        $columnKey,
                        null,
                        (string)$searchTerm,
                    );
                    if ($searchCondition !== null) {
                        $searchConditions['OR'][] = $searchCondition;
                    }
                    continue;
                }

                $columnMeta = $columnsMetadata[$columnKey] ?? null;

                if ($columnMeta) {
                    if ($columnMeta['type'] === 'relation' && !empty($columnMeta['renderField'])) {
                        if (!empty($columnMeta['queryField'])) {
                            $searchCondition = $this->buildDataverseGridSearchCondition(
                                (string)$columnMeta['queryField'],
                                $columnMeta,
                                (string)$searchTerm,
                            );
                            if ($searchCondition !== null) {
                                $searchConditions['OR'][] = $searchCondition;
                            }
                        } else {
                            $relationParts = explode('.', $columnMeta['renderField']);
                            if (count($relationParts) === 2) {
                                $associationName = Inflector::pluralize(Inflector::camelize($relationParts[0]));
                                $fieldName = $relationParts[1];
                                $searchCondition = $this->buildDataverseGridSearchCondition(
                                    $associationName . '.' . $fieldName,
                                    $columnMeta,
                                    (string)$searchTerm,
                                );
                                if ($searchCondition !== null) {
                                    $searchConditions['OR'][] = $searchCondition;
                                }
                            }
                        }
                    } elseif (!empty($columnMeta['queryField'])) {
                        $searchCondition = $this->buildDataverseGridSearchCondition(
                            (string)$columnMeta['queryField'],
                            $columnMeta,
                            (string)$searchTerm,
                        );
                        if ($searchCondition !== null) {
                            $searchConditions['OR'][] = $searchCondition;
                        }
                    } else {
                        $searchCondition = $this->buildDataverseGridSearchCondition(
                            $tableName . '.' . $columnKey,
                            $columnMeta,
                            (string)$searchTerm,
                        );
                        if ($searchCondition !== null) {
                            $searchConditions['OR'][] = $searchCondition;
                        }
                    }
                }
            }

            if (!empty($searchConditions['OR'])) {
                $baseQuery->where($searchConditions);
            }
        }

        // Apply dropdown filters
        // Note: System view filters are ALWAYS applied. User-submitted filters only apply when canFilter is true.
        $incomingFilters = $this->request->getQuery('filter', []);
        $currentFilters = is_array($incomingFilters) ? $incomingFilters : [];
        // Always include config skipFilterColumns (columns that should never be filtered by the trait)
        // Also include columns with skipAutoFilter: true from column metadata
        $configSkipFilterColumns = $config['skipFilterColumns'] ?? [];
        $skipFilterColumns = array_unique(array_merge($configSkipFilterColumns, $autoSkipFilterColumns));
        $systemViewFilters = [];

        if ($selectedSystemView) {
            // When canFilter is false, ignore user-submitted filters entirely
            $hasIncomingFilters = $canFilter && !empty($incomingFilters);

            // Always use system view filters as the base (unless user explicitly changed them)
            if (!$dirtyFilters && !$hasIncomingFilters) {
                $currentFilters = $systemViewDefaults['filters'];
            }

            // Store system view filters separately so they're always applied
            $systemViewFilters = $systemViewDefaults['filters'];

            if (!$dirtyFilters && !$hasIncomingFilters && !empty($systemViewDefaults['dateRange'])) {
                foreach ($systemViewDefaults['dateRange'] as $filterKey => $value) {
                    if ($value !== null && $value !== '' && !array_key_exists($filterKey, $currentFilters)) {
                        $currentFilters[$filterKey] = $value;
                    }
                }
            }

            // Merge system view skipFilterColumns with config and autoSkip columns
            $systemViewSkipColumns = $dirtyFilters || $hasIncomingFilters
                ? []
                : ($systemViewDefaults['skipFilterColumns'] ?? []);
            $skipFilterColumns = array_unique(array_merge(
                $configSkipFilterColumns,
                $autoSkipFilterColumns,
                $systemViewSkipColumns,
            ));
        }

        // Determine which filters to apply:
        // - If canFilter is true: apply all currentFilters (system view + user submitted)
        // - If canFilter is false: only apply system view filters
        $filtersToApply = $canFilter ? $currentFilters : $systemViewFilters;

        if (!empty($filtersToApply) && is_array($filtersToApply)) {
            foreach ($filtersToApply as $columnKey => $filterValue) {
                if ($filterValue === '' || $filterValue === null || (is_array($filterValue) && empty($filterValue))) {
                    continue;
                }

                $columnMeta = $columnsMetadata[$columnKey] ?? null;

                if ($columnMeta && !empty($columnMeta['filterable'])) {
                    if (in_array($columnKey, $skipFilterColumns, true)) {
                        continue;
                    }

                    // Handle "is-populated" filter type (check if field is not empty/null)
                    if (($columnMeta['filterType'] ?? null) === 'is-populated') {
                        // Use filterQueryField if specified, otherwise use queryField, fallback to column key
                        $fieldToCheck = $columnMeta['filterQueryField'] ?? $columnMeta['queryField'] ?? $columnKey;
                        $qualifiedField = strpos(
                            $fieldToCheck,
                            '.',
                        ) === false ? $tableName . '.' . $fieldToCheck : $fieldToCheck;

                        // Normalize filterValue - if it's an array, take the first value
                        $normalizedValue = is_array($filterValue) ? ($filterValue[0] ?? null) : $filterValue;

                        // filterValue should be 'yes' (populated) or 'no' (not populated)
                        if (
                            $normalizedValue === 'yes'
                            || $normalizedValue === '1'
                            || $normalizedValue === 1
                            || $normalizedValue === true
                        ) {
                            // IS NOT NULL AND NOT EMPTY
                            $baseQuery->where(function ($exp) use ($qualifiedField) {
                                return $exp->and([
                                    $qualifiedField . ' IS NOT' => null,
                                ]);
                            });
                        } elseif (
                            $normalizedValue === 'no'
                            || $normalizedValue === '0'
                            || $normalizedValue === 0
                            || $normalizedValue === false
                        ) {
                            // IS NULL OR EMPTY
                            $baseQuery->where(function ($exp) use ($qualifiedField) {
                                return $exp->or([
                                    $qualifiedField . ' IS' => null,
                                ]);
                            });
                        }
                        continue;
                    }

                    // Use filterQueryField for filter value matching when provided.
                    // This lets relation columns sort/search on a display field while
                    // filtering against the underlying foreign key value.
                    $fieldToFilter = $columnMeta['filterQueryField'] ?? $columnMeta['queryField'] ?? $columnKey;
                    $qualifiedField = strpos(
                        $fieldToFilter,
                        '.',
                    ) === false ? $tableName . '.' . $fieldToFilter : $fieldToFilter;

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

        // Apply date range filters
        // Note: System view date ranges are ALWAYS applied. User-submitted date ranges only apply when canFilter is true.
        $dateRangeDefaults = [];
        $systemDateRangeDefaults = [];
        if ($selectedSystemView && !$dirtyFilters) {
            // When canFilter is false, still apply system view date ranges
            $systemDateRangeDefaults = $systemViewDefaults['dateRange'];
            // Only use as defaults if canFilter is true and no user-submitted filters
            if ($canFilter && empty($incomingFilters)) {
                $dateRangeDefaults = $systemViewDefaults['dateRange'];
            }
        }
        if (!empty($dateRangeFilterColumns)) {
            foreach ($dateRangeFilterColumns as $columnKey => $columnMeta) {
                $startParam = $columnKey . '_start';
                $endParam = $columnKey . '_end';

                // Get user-submitted values only if canFilter is true
                $startDate = $canFilter ? $this->request->getQuery($startParam) : null;
                $endDate = $canFilter ? $this->request->getQuery($endParam) : null;

                // Apply user defaults only if canFilter is true
                if (
                    $canFilter
                    && ($startDate === null || $startDate === '')
                    && isset($dateRangeDefaults[$startParam])
                ) {
                    $startDate = $dateRangeDefaults[$startParam];
                }
                if ($canFilter && ($endDate === null || $endDate === '') && isset($dateRangeDefaults[$endParam])) {
                    $endDate = $dateRangeDefaults[$endParam];
                }

                // Always apply system view date range defaults (even when canFilter is false)
                if (($startDate === null || $startDate === '') && isset($systemDateRangeDefaults[$startParam])) {
                    $startDate = $systemDateRangeDefaults[$startParam];
                }
                if (($endDate === null || $endDate === '') && isset($systemDateRangeDefaults[$endParam])) {
                    $endDate = $systemDateRangeDefaults[$endParam];
                }

                if (($startDate !== null && $startDate !== '') || ($endDate !== null && $endDate !== '')) {
                    // Use queryField from metadata if available, otherwise qualify with table name
                    $qualifiedField = $columnMeta['queryField'] ??
                        (strpos($columnKey, '.') === false ? $tableName . '.' . $columnKey : $columnKey);

                    // Check if this column has nullMeansActive flag (for expires_on/end_date fields)
                    $nullMeansActive = $columnMeta['nullMeansActive'] ?? false;

                    if ($startDate !== null && $startDate !== '') {
                        if (!in_array($columnKey, $skipFilterColumns, true)) {
                            // Convert date-only strings from kingdom timezone to UTC
                            // so SQL comparisons work correctly against UTC-stored datetimes.
                            $effectiveStartDate = $this->convertDateBoundaryToUtc($startDate, true);

                            // For lower bound (start >= value), check nullMeansActive flag
                            // If true, NULL means "never expires" so include it in results
                            if ($nullMeansActive) {
                                $baseQuery->where(function ($exp) use ($qualifiedField, $effectiveStartDate) {
                                    return $exp->or([
                                        $qualifiedField . ' >=' => $effectiveStartDate,
                                        $qualifiedField . ' IS' => null,
                                    ]);
                                });
                            } else {
                                $baseQuery->where([$qualifiedField . ' >=' => $effectiveStartDate]);
                            }
                        }
                        // Add to current filters for display as pill (original kingdom-tz value)
                        $currentFilters[$startParam] = $startDate;
                    }
                    if ($endDate !== null && $endDate !== '') {
                        if (!in_array($columnKey, $skipFilterColumns, true)) {
                            // Convert date-only strings from kingdom timezone to UTC
                            // so SQL comparisons work correctly against UTC-stored datetimes.
                            $effectiveEndDate = $this->convertDateBoundaryToUtc($endDate, false);

                            $baseQuery->where([$qualifiedField . ' <=' => $effectiveEndDate]);
                        }
                        // Add to current filters for display as pill (original kingdom-tz value)
                        $currentFilters[$endParam] = $endDate;
                    }
                }
            }
        }

        // Apply custom filter handlers
        // These are columns with complex filtering logic defined in the GridColumns class
        if (!empty($customFilterColumns)) {
            $baseQuery = $this->applyCustomFilterHandlers(
                $baseQuery,
                $customFilterColumns,
                $tableName,
                $canFilter,
                $currentFilters,
                $currentView,
                $selectedSystemView,
                $dirtyFilters,
            );
        }

        $queryContext = $this->resolveDataverseGridQueryContext(
            $config + ['columnsMetadata' => $columnsMetadata],
            $selectedSystemView,
            $currentView,
            true,
        );
        $visibleColumns = $queryContext->gridVisibleColumns();

        // Apply expression tree from system view (if present and not dirty)
        if ($selectedSystemView && !$dirtyFilters && !empty($selectedSystemView['config']['expression'])) {
            $gridViewConfig = new GridViewConfig();
            // Only pass config/metadata-level skip columns to expression tree, NOT system view
            // skipFilterColumns. The expression tree IS the authoritative filter for those columns;
            // system view skipFilterColumns only prevents flat filters from duplicating them.
            $expressionSkipColumns = array_unique(array_merge($configSkipFilterColumns, $autoSkipFilterColumns));
            $expression = $gridViewConfig->extractExpression(
                $selectedSystemView['config'],
                $baseQuery->newExpr(),
                $tableName,
                $expressionSkipColumns,
                $columnsMetadata,
            );

            if ($expression !== null && count($expression) > 0) {
                $baseQuery->where($expression);
            }
        }

        // Apply view configuration filters (only if not dirty)
        // Note: This handles legacy flat filters. New views should use expressions instead.
        if ($currentView && !$dirtyFilters) {
            $gridViewConfig = new GridViewConfig();
            $viewConfig = $currentView->getConfigArray();

            // Check if view has expression tree (preferred)
            $expression = $gridViewConfig->extractExpression(
                $viewConfig,
                $baseQuery->newExpr(),
                $tableName,
                $skipFilterColumns,
                $columnsMetadata,
            );

            if ($expression !== null && count($expression) > 0) {
                $baseQuery->where($expression);
            } else {
                // Fallback to legacy flat filters
                $filterConditions = $gridViewConfig->extractFilters($viewConfig);

                if (!empty($filterConditions)) {
                    $qualifiedFilters = [];
                    foreach ($filterConditions as $fieldCondition => $value) {
                        $parts = explode(' ', $fieldCondition, 2);
                        $field = $parts[0];
                        $operator = $parts[1] ?? '';

                        // Skip columns that require custom filtering
                        if (in_array($field, $skipFilterColumns, true)) {
                            continue;
                        }

                        // Use queryField from column metadata if available
                        $columnMeta = $columnsMetadata[$field] ?? null;
                        $queryField = $columnMeta['queryField'] ?? null;

                        if ($queryField !== null) {
                            $qualifiedField = $queryField;
                        } elseif (strpos($field, '.') === false) {
                            $qualifiedField = $tableName . '.' . $field;
                        } else {
                            $qualifiedField = $field;
                        }

                        $qualifiedKey = $operator ? $qualifiedField . ' ' . $operator : $qualifiedField;
                        $qualifiedFilters[$qualifiedKey] = $value;
                    }
                    if (!empty($qualifiedFilters)) {
                        $baseQuery->where($qualifiedFilters);
                    }
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
            if ($columnMeta && !empty($columnMeta['sortable'])) {
                if (isset($columnMeta['queryField'])) {
                    $actualSortField = $columnMeta['queryField'];
                } elseif (strpos($sortField, '.') === false) {
                    $actualSortField = $tableName . '.' . $sortField;
                } else {
                    $actualSortField = $sortField;
                }
                $baseQuery->orderBy([$actualSortField => strtoupper($sortDirection)]);
                $currentSort = ['field' => $sortField, 'direction' => $sortDirection];
            } else {
                // Column not sortable or not found — fall back to default sort
                $baseQuery->orderBy($defaultSort);
            }
        } elseif ($currentView && !$dirtySort) {
            $viewConfig = $currentView->getConfigArray();
            if (!empty($viewConfig['sort'])) {
                $sortConfig = $viewConfig['sort'][0] ?? null;
                if ($sortConfig) {
                    $sortField = $sortConfig['field'];
                    $sortDirection = $sortConfig['direction'];
                    $columnMeta = $columnsMetadata[$sortField] ?? null;
                    if ($columnMeta && !empty($columnMeta['sortable'])) {
                        if (isset($columnMeta['queryField'])) {
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
        } else {
            $baseQuery->orderBy($defaultSort);
        }

        // Check for CSV export request
        if ($this->isCsvExportRequest()) {
            // For CSV export, include exportOnly columns in addition to visible columns
            $exportColumns = $visibleColumns;
            foreach ($columnsMetadata as $columnKey => $columnMeta) {
                // Add exportOnly columns that aren't already in visible columns
                if (!empty($columnMeta['exportOnly']) && !in_array($columnKey, $exportColumns)) {
                    $exportColumns[] = $columnKey;
                }
            }

            // Return query for CSV export (controller will handle the export)
            return [
                'isCsvExport' => true,
                'query' => $baseQuery,
                'visibleColumns' => $exportColumns,
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
        if ($disablePagination) {
            $data = $baseQuery->all();
        } else {
            $this->paginate = ['limit' => $pageSize];
            $data = $this->paginate($baseQuery);
        }

        // Check if there are any searchable columns (for search functionality)
        $hasSearchableColumns = !empty(array_filter(
            $columnsMetadata,
            fn($col) => !empty($col['searchable']),
        ));

        // Get dropdown filter columns and prepare filter options
        $dropdownFilterColumns = [];
        $filterOptions = [];
        if ($canFilter && method_exists($gridColumnsClass, 'getDropdownFilterColumns')) {
            $dropdownFilterColumns = $gridColumnsClass::getDropdownFilterColumns();

            foreach ($dropdownFilterColumns as $columnKey => $columnMeta) {
                if (!empty($columnMeta['filterOptions'])) {
                    $filterOptions[$columnKey] = $columnMeta['filterOptions'];
                } elseif (!empty($columnMeta['filterOptionsSource']) && $loadFilterMetadata) {
                    $filterOptions[$columnKey] = $this->loadFilterOptionsCached($columnMeta['filterOptionsSource']);
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
            enableColumnPicker: $enableColumnPicker,
            lockedFilters: $lockedFilters,
            enableBulkSelection: $enableBulkSelection,
            bulkSelection: $bulkSelection,
            bulkActions: $bulkActions,
            bulkSelectionDataFields: $bulkSelectionDataFields,
            bulkSelectionDisabledField: $bulkSelectionDisabledField,
            bulkSelectionHideDisabledControl: $bulkSelectionHideDisabledControl,
            includeViewMetadata: $loadViewMetadata,
            includeAllColumns: $metadataMode === 'full',
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
            'availableViews' => is_array($availableViews) ? $availableViews : iterator_to_array($availableViews),
            'gridKey' => $gridKey,
            'currentSort' => $currentSort,
            'currentMember' => $currentMember,
        ];
    }

    /**
     * Build a search condition for one Dataverse grid column.
     *
     * @param string $field Fully-qualified database field.
     * @param array<string,mixed>|null $columnMeta Column metadata, when available.
     * @param string $searchTerm User-entered search term.
     * @return array<string,mixed>|null
     */
    protected function buildDataverseGridSearchCondition(
        string $field,
        ?array $columnMeta,
        string $searchTerm,
    ): ?array {
        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            return null;
        }

        if ($this->isDataverseGridNumericSearchColumn($columnMeta)) {
            if (!preg_match('/^-?\d+$/', $searchTerm)) {
                return null;
            }

            return [$field => (int)$searchTerm];
        }

        return [$field . ' LIKE' => '%' . $searchTerm . '%'];
    }

    /**
     * @param array<string,mixed>|null $columnMeta Column metadata.
     * @return bool
     */
    private function isDataverseGridNumericSearchColumn(?array $columnMeta): bool
    {
        return in_array($columnMeta['type'] ?? null, ['integer', 'number'], true);
    }

    /**
     * Resolve Dataverse grid column context for early query construction.
     *
     * @param array<string,mixed> $config Grid configuration.
     * @param array<string,mixed>|null $selectedSystemView Already-resolved system view when called from processDataverseGrid().
     * @param mixed $currentView Already-resolved saved view when called from processDataverseGrid().
     * @param bool $viewContextResolved Whether null selected/current view values are already authoritative.
     */
    protected function resolveDataverseGridQueryContext(
        array $config,
        ?array $selectedSystemView = null,
        mixed $currentView = null,
        bool $viewContextResolved = false,
    ): DataverseGridQueryContext {
        $gridColumnsClass = $config['gridColumnsClass'];
        $columnsMetadata = $config['columnsMetadata'] ?? $gridColumnsClass::getColumns();

        if (!$viewContextResolved) {
            $viewContext = $this->resolveDataverseGridViewContext($config);
            $selectedSystemView = $viewContext['selectedSystemView'];
            $currentView = $viewContext['currentView'];
        }

        $gridVisibleColumns = $this->resolveDataverseGridVisibleColumns(
            $gridColumnsClass,
            $columnsMetadata,
            $selectedSystemView,
            $currentView,
        );

        if ($this->isCsvExportRequest()) {
            return new DataverseGridQueryContext($gridVisibleColumns, null, $columnsMetadata);
        }

        $activeColumns = $this->resolveDataverseGridActiveColumns(
            $config,
            $columnsMetadata,
            $selectedSystemView,
            $currentView,
        );
        $queryVisibleColumns = array_values(array_unique(array_merge($gridVisibleColumns, $activeColumns)));

        return new DataverseGridQueryContext($gridVisibleColumns, $queryVisibleColumns, $columnsMetadata);
    }

    /**
     * Resolve active saved/system view enough for pre-query column dependency planning.
     *
     * @param array<string,mixed> $config Grid configuration.
     * @return array{selectedSystemView: array<string,mixed>|null, currentView: mixed}
     */
    private function resolveDataverseGridViewContext(array $config): array
    {
        $gridKey = $config['gridKey'];
        $systemViews = $config['systemViews'] ?? null;
        $defaultSystemView = $config['defaultSystemView'] ?? null;
        $currentMember = $this->request->getAttribute('identity');
        $viewId = $this->request->getQuery('view_id');
        $ignoreDefault = $this->request->getQuery('ignore_default');
        $gridViewService = new GridViewService(
            $this->fetchTable('GridViews'),
            new GridViewConfig(),
        );
        $preferredViewId = $currentMember instanceof Member
            ? $gridViewService->getUserPreferenceViewId($gridKey, $currentMember)
            : null;

        if ($viewId !== null && $viewId !== '') {
            $viewId = $systemViews !== null ? (string)$viewId : (int)$viewId;
        }

        if ($systemViews !== null) {
            if ($ignoreDefault) {
                return ['selectedSystemView' => null, 'currentView' => null];
            }

            $requestedViewId = $viewId ?? $preferredViewId ?? $defaultSystemView;
            if (is_numeric($requestedViewId)) {
                $currentView = $gridViewService->getEffectiveView($gridKey, $currentMember, (int)$requestedViewId);
                if ($currentView !== null) {
                    return ['selectedSystemView' => null, 'currentView' => $currentView];
                }
                $requestedViewId = $defaultSystemView;
            }

            if (!is_string($requestedViewId) || !isset($systemViews[$requestedViewId])) {
                $requestedViewId = $defaultSystemView;
            }

            return [
                'selectedSystemView' => is_string($requestedViewId) && isset($systemViews[$requestedViewId])
                    ? $systemViews[$requestedViewId]
                    : null,
                'currentView' => null,
            ];
        }

        if ($ignoreDefault) {
            return ['selectedSystemView' => null, 'currentView' => null];
        }

        return [
            'selectedSystemView' => null,
            'currentView' => $gridViewService->getEffectiveView($gridKey, $currentMember, $viewId),
        ];
    }

    /**
     * Resolve UI-visible columns using the same precedence as processDataverseGrid().
     *
     * @param class-string $gridColumnsClass
     * @param array<string,array<string,mixed>> $columnsMetadata
     * @param array<string,mixed>|null $selectedSystemView
     * @param mixed $currentView
     * @return array<int,string>
     */
    private function resolveDataverseGridVisibleColumns(
        string $gridColumnsClass,
        array $columnsMetadata,
        ?array $selectedSystemView,
        mixed $currentView,
    ): array {
        $columnsParam = $this->request->getQuery('columns');
        if (is_string($columnsParam) && $columnsParam !== '') {
            $visibleColumns = array_values(array_filter(explode(',', $columnsParam), 'strlen'));
        } elseif ($selectedSystemView && !empty($selectedSystemView['config']['columns'])) {
            $visibleColumns = $this->normalizeDataverseGridColumnConfig($selectedSystemView['config']['columns']);
        } elseif ($currentView && method_exists($currentView, 'getConfigArray')) {
            $config = new GridViewConfig();
            $visibleColumns = $config->extractVisibleColumns($currentView->getConfigArray(), $columnsMetadata);
        } else {
            $visibleColumns = array_values(array_filter(
                array_keys($columnsMetadata),
                fn($key) => $columnsMetadata[$key]['defaultVisible'] ?? false,
            ));
        }

        if (method_exists($gridColumnsClass, 'getRequiredColumns')) {
            $visibleColumns = array_merge($visibleColumns, $gridColumnsClass::getRequiredColumns());
        }

        $visibleColumns = array_values(array_unique(array_filter(
            $visibleColumns,
            fn($key) => is_string($key) && isset($columnsMetadata[$key]),
        )));

        if ($visibleColumns === []) {
            $visibleColumns = array_values(array_filter(
                array_keys($columnsMetadata),
                fn($key) => !($columnsMetadata[$key]['exportOnly'] ?? false)
                    && ($columnsMetadata[$key]['defaultVisible'] ?? false),
            ));
        }

        if ($visibleColumns === []) {
            $visibleColumns = array_values(array_filter(
                array_keys($columnsMetadata),
                fn($key) => !($columnsMetadata[$key]['exportOnly'] ?? false),
            ));
        }

        return $visibleColumns;
    }

    /**
     * Resolve hidden-but-active query dependency columns.
     *
     * @param array<string,mixed> $config Grid configuration.
     * @param array<string,array<string,mixed>> $columnsMetadata
     * @param array<string,mixed>|null $selectedSystemView
     * @param mixed $currentView
     * @return array<int,string>
     */
    private function resolveDataverseGridActiveColumns(
        array $config,
        array $columnsMetadata,
        ?array $selectedSystemView,
        mixed $currentView,
    ): array {
        $activeColumns = [];

        $sort = $this->request->getQuery('sort');
        if (is_string($sort) && $sort !== '') {
            $activeColumns[] = $sort;
        }

        foreach (array_keys((array)$config['defaultSort']) as $sortField) {
            if (is_string($sortField)) {
                $activeColumns[] = $this->columnKeyForDataverseField($sortField, $columnsMetadata);
            }
        }

        $filters = $this->request->getQuery('filter', []);
        if (is_array($filters)) {
            $activeColumns = array_merge($activeColumns, array_filter(array_keys($filters), 'is_string'));
        }

        foreach ($this->request->getQueryParams() as $key => $value) {
            if (!is_string($key) || $value === null || $value === '') {
                continue;
            }
            if (str_ends_with($key, '_start') || str_ends_with($key, '_end')) {
                $activeColumns[] = preg_replace('/_(start|end)$/', '', $key) ?? $key;
            }
        }

        $search = $this->request->getQuery('search');
        if (is_string($search) && $search !== '') {
            foreach ($columnsMetadata as $columnKey => $columnMeta) {
                if (!empty($columnMeta['searchable'])) {
                    $activeColumns[] = $columnKey;
                }
            }
        }

        $viewConfigs = [];
        if ($selectedSystemView && isset($selectedSystemView['config']) && is_array($selectedSystemView['config'])) {
            $viewConfigs[] = $selectedSystemView['config'];
        }
        if ($currentView && method_exists($currentView, 'getConfigArray')) {
            $viewConfigs[] = $currentView->getConfigArray();
        }

        foreach ($viewConfigs as $viewConfig) {
            $activeColumns = array_merge($activeColumns, $this->extractDataverseGridConfigColumnKeys($viewConfig));
        }

        return array_values(array_unique(array_filter($activeColumns, 'is_string')));
    }

    /**
     * @param mixed $columns
     * @return array<int,string>
     */
    private function normalizeDataverseGridColumnConfig(mixed $columns): array
    {
        if (!is_array($columns)) {
            return [];
        }

        $firstColumn = reset($columns);
        if (is_string($firstColumn)) {
            return array_values(array_filter($columns, 'is_string'));
        }

        return GridViewConfig::extractVisibleColumns(['columns' => $columns]);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    private function extractDataverseGridConfigColumnKeys(array $config): array
    {
        $columns = [];

        foreach (($config['filters'] ?? []) as $filter) {
            if (is_array($filter) && isset($filter['field']) && is_string($filter['field'])) {
                $columns[] = $filter['field'];
            }
        }

        foreach (($config['sort'] ?? []) as $sort) {
            if (is_array($sort) && isset($sort['field']) && is_string($sort['field'])) {
                $columns[] = $sort['field'];
            }
        }

        $this->collectDataverseExpressionColumnKeys($config['expression'] ?? [], $columns);

        return $columns;
    }

    /**
     * @param mixed $expression
     * @param array<int,string> $columns
     */
    private function collectDataverseExpressionColumnKeys(mixed $expression, array &$columns): void
    {
        if (!is_array($expression)) {
            return;
        }

        foreach ($expression as $key => $value) {
            if (($key === 'field' || $key === 'column' || $key === 'columnKey') && is_string($value)) {
                $columns[] = $value;
            }
            $this->collectDataverseExpressionColumnKeys($value, $columns);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $columnsMetadata
     */
    private function columnKeyForDataverseField(string $field, array $columnsMetadata): string
    {
        if (isset($columnsMetadata[$field])) {
            return $field;
        }

        foreach ($columnsMetadata as $columnKey => $columnMeta) {
            if (($columnMeta['queryField'] ?? null) === $field) {
                return $columnKey;
            }
        }

        $fieldParts = explode('.', $field);
        $fieldName = end($fieldParts);

        return is_string($fieldName) && $fieldName !== '' ? $fieldName : $field;
    }

    /**
     * Build complete grid state object (single source of truth)
     *
     * @param mixed $currentView Current saved view entity (null for system views or "All")
     * @param array|null $selectedSystemView Currently selected system view
     * @param array|null $systemViews All available system views
     * @param iterable $availableViews Collection of saved views
     * @param \App\Model\Entity\Member|null $currentMember Authenticated member
     * @param string|int|null $preferredViewId Preferred view ID from user preferences
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
     * @param array $lockedFilters Filter column keys that cannot be removed by users
     * @param array $bulkSelection Bulk selection accessibility label configuration
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
        bool $enableColumnPicker,
        array $lockedFilters = [],
        bool $enableBulkSelection = false,
        array $bulkSelection = [],
        array $bulkActions = [],
        array $bulkSelectionDataFields = [],
        ?string $bulkSelectionDisabledField = null,
        bool $bulkSelectionHideDisabledControl = false,
        bool $includeViewMetadata = true,
        bool $includeAllColumns = true,
    ): array {
        // Format views based on whether we're using system or saved views
        $formattedViews = [];
        $systemDefaultId = null;
        $currentId = null;
        $currentName = 'All';

        // Resolve the active view's id/name independent of whether the full view
        // catalog is serialized. Table-frame (minimal metadata) responses still
        // apply the selected view to the underlying query, so the state must
        // report which view is active. Otherwise the toolbar loses its
        // selected-tab indication after a view switch (a11y: WCAG 4.1.2 — the
        // tab's selected state is no longer conveyed). The available-views
        // catalog stays gated on $includeViewMetadata below (perf optimization).
        if ($systemViews !== null) {
            if ($currentView) {
                $currentId = $currentView->id;
                $currentName = $currentView->name;
            } elseif ($selectedSystemView) {
                $currentId = $selectedSystemView['id'];
                $currentName = $selectedSystemView['name'];
            }
        } elseif ($currentView) {
            $currentId = $currentView->id;
            $currentName = $currentView->name;
        }

        if ($includeViewMetadata && $systemViews !== null) {
            // System views mode - add system views first
            $effectiveDefaultId = $preferredViewId ?? array_key_first($systemViews);

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
        } elseif ($includeViewMetadata) {
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
                'isPreferred' => $currentView && $preferredViewId !== null
                    ? ((int)$currentView->id === $preferredViewId)
                    : ($selectedSystemView && $preferredViewId !== null
                        && $selectedSystemView['id'] === $preferredViewId),
                'isDefault' => $currentView
                    ? ($preferredViewId !== null
                        && (int)$currentView->id === $preferredViewId)
                    : ($selectedSystemView && $preferredViewId !== null
                        && $selectedSystemView['id'] === $preferredViewId),
                'isUserDefault' => $currentView ? $currentView->isUserDefault() : ($preferredViewId !== null),
                'available' => $includeViewMetadata ? $formattedViews : [],
            ],
            'search' => $search,
            'filters' => $filterState,
            'sort' => $sort,
            'columns' => [
                'visible' => $visibleColumns,
                'all' => $includeAllColumns ? $allColumns : [],
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
                'lockedFilters' => $lockedFilters,
                'enableBulkSelection' => $enableBulkSelection,
                'bulkSelection' => $bulkSelection,
                'bulkActions' => $bulkActions,
                'bulkSelectionDataFields' => $bulkSelectionDataFields,
                'bulkSelectionDisabledField' => $bulkSelectionDisabledField,
                'bulkSelectionHideDisabledControl' => $bulkSelectionHideDisabledControl,
            ],
            'dateRangeFilterColumns' => $dateRangeFilterColumns,
        ];
    }

    /**
     * Render consistent dataverse grid responses for outer/table turbo frames.
     *
     * @param array<string,mixed> $result processDataverseGrid() result
     * @param string $frameId Outer frame id (e.g. members-grid)
     * @param string|null $collectionVar Optional collection variable name to set (e.g. members)
     * @param array<string,mixed> $extraViewVars Additional vars to expose to template
     * @return void
     */
    protected function renderDataverseGridResponse(
        array $result,
        string $frameId,
        ?string $collectionVar = null,
        array $extraViewVars = [],
    ): void {
        $data = $result['data'];
        $viewVars = [
            'data' => $data,
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
        ];

        if ($collectionVar !== null) {
            $viewVars[$collectionVar] = $data;
        }
        if ($extraViewVars !== []) {
            $viewVars = array_merge($viewVars, $extraViewVars);
        }

        $this->set($viewVars);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $tableFrameId = $frameId . '-table';
        $this->viewBuilder()->setPlugin(null);
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplatePath('element');
        if ($turboFrame === $tableFrameId) {
            $this->set('tableFrameId', $tableFrameId);
            $this->viewBuilder()->setTemplate('dv_grid_table');

            return;
        }

        $this->set('frameId', $frameId);
        $this->viewBuilder()->setTemplate('dv_grid_content');
    }

    /**
     * @return bool Whether request is for inner table frame.
     */
    protected function isDataverseTableFrameRequest(): bool
    {
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        return is_string($turboFrame) && str_ends_with($turboFrame, '-table');
    }

    /**
     * Load and cache available views for the current request.
     *
     * @param string $gridKey Grid identifier
     * @param int $memberId Authenticated member id
     * @return array<int,mixed>
     */
    protected function loadAvailableViews(string $gridKey, int $memberId): array
    {
        $cache = (array)$this->request->getAttribute('__dataverse_grid_cache', []);
        $cacheKey = "views:$gridKey:$memberId";
        if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $views = $this->fetchTable('GridViews')
            ->find('byGrid', ['gridKey' => $gridKey, 'memberId' => $memberId])
            ->all()
            ->toList();
        $cache[$cacheKey] = $views;
        $this->request = $this->request->withAttribute('__dataverse_grid_cache', $cache);

        return $views;
    }

    /**
     * Load filter options with per-request cache.
     *
     * @param array|string $source filterOptionsSource configuration
     * @return array<int,array{value:string,label:string}>
     */
    protected function loadFilterOptionsCached(string|array $source): array
    {
        $cache = (array)$this->request->getAttribute('__dataverse_grid_cache', []);
        $cacheKey = 'filter-options:' . md5(json_encode($source));
        if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $persistentCacheKey = TenantAwareCache::tenantScopedKey($cacheKey);
        $persistentOptions = Cache::read($persistentCacheKey, 'grid_filter_options');
        if (is_array($persistentOptions)) {
            $cache[$cacheKey] = $persistentOptions;
            $this->request = $this->request->withAttribute('__dataverse_grid_cache', $cache);

            return $persistentOptions;
        }

        $options = $this->loadFilterOptions($source);
        $cache[$cacheKey] = $options;
        Cache::write($persistentCacheKey, $options, 'grid_filter_options');
        $this->request = $this->request->withAttribute('__dataverse_grid_cache', $cache);

        return $options;
    }

    /**
     * Load filter options from a data source
     *
     * Supports multiple formats for filterOptionsSource:
     *
     * 1. **Simple string** (table name): Uses 'id' for value, 'name' for label
     *    ```php
     *    'filterOptionsSource' => 'Branches'
     *    ```
     *
     * 2. **Array with table**: Database table with full control
     *    ```php
     *    'filterOptionsSource' => [
     *        'table' => 'Waivers.WaiverTypes',  // Required: table name for fetchTable()
     *        'valueField' => 'id',              // Optional: field for option value (default: 'id')
     *        'labelField' => 'name',            // Optional: field for option label (default: 'name')
     *        'conditions' => ['is_active' => true],  // Optional: filter conditions
     *        'order' => ['name' => 'ASC'],      // Optional: sort order (default: labelField ASC)
     *    ]
     *    ```
     *
     * 3. **Array with appSetting**: Load from app settings (YAML array)
     *    ```php
     *    'filterOptionsSource' => [
     *        'appSetting' => 'Branches.Types',  // Required: app setting key
     *    ]
     *    ```
     *    The app setting should contain a YAML array like: ['Kingdom', 'Principality', 'Barony']
     *    Both value and label will be set to the array item value.
     *
     * 4. **Array with method**: Call a static method on a class to get options
     *    ```php
     *    'filterOptionsSource' => [
     *        'method' => 'getGatheringsFilterOptions',  // Required: static method name
     *        'class' => 'Awards\\KMP\\GridColumns\\RecommendationsGridColumns',  // Required: fully qualified class name
     *    ]
     *    ```
     *    The method should return array of ['value' => string, 'label' => string].
     *
     * @param array|string $source Source identifier string (table name) or configuration array
     * @return array Filter options as array of ['value' => string, 'label' => string]
     */
    protected function loadFilterOptions(string|array $source): array
    {
        // Handle app setting source
        if (is_array($source) && !empty($source['appSetting'])) {
            $settingKey = $source['appSetting'];
            $values = StaticHelpers::getAppSetting($settingKey);

            if (!is_array($values)) {
                return [];
            }

            return array_map(
                fn($value) => ['value' => (string)$value, 'label' => (string)$value],
                $values,
            );
        }

        // Handle method source - call a static method on a class
        if (is_array($source) && !empty($source['method']) && !empty($source['class'])) {
            $className = $source['class'];
            $methodName = $source['method'];

            if (class_exists($className) && method_exists($className, $methodName)) {
                return $className::$methodName();
            }

            return [];
        }

        // Parse source configuration for table-based options
        if (is_string($source)) {
            // Simple string format - treat as table name
            $config = [
                'table' => $source,
                'valueField' => 'id',
                'labelField' => 'name',
                'conditions' => [],
                'order' => null,
            ];
        } else {
            // Array configuration format
            if (empty($source['table'])) {
                return [];
            }
            $config = [
                'table' => $source['table'],
                'valueField' => $source['valueField'] ?? 'id',
                'labelField' => $source['labelField'] ?? 'name',
                'conditions' => $source['conditions'] ?? [],
                'order' => $source['order'] ?? null,
            ];
        }

        // Build the query
        $query = $this->fetchTable($config['table'])
            ->find('list', [
                'keyField' => $config['valueField'],
                'valueField' => $config['labelField'],
            ]);

        // Apply conditions if specified
        if (!empty($config['conditions'])) {
            $query->where($config['conditions']);
        }

        // Apply ordering (default to labelField ASC if not specified)
        $orderBy = $config['order'] ?? [$config['labelField'] => 'ASC'];
        $query->orderBy($orderBy);

        $results = $query->toArray();

        return array_map(
            fn($id, $name) => ['value' => (string)$id, 'label' => $name],
            array_keys($results),
            $results,
        );
    }

    /**
     * Convert a date boundary string from kingdom timezone to UTC for SQL comparison.
     *
     * The database stores datetimes in UTC. Date-range filters use kingdom-timezone dates
     * (e.g., "today" = 2026-04-09 in US/Eastern). Without conversion, a SQL comparison
     * like `start_on <= '2026-04-09 23:59:59'` would miss records stored as
     * '2026-04-10 03:00:00' UTC (which is still April 9 in Eastern).
     *
     * @param string $dateValue Date string (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
     * @param bool $isStart True for start-of-day boundary (00:00:00), false for end-of-day (23:59:59)
     * @return string UTC datetime string for SQL comparison
     */
    protected function convertDateBoundaryToUtc(string $dateValue, bool $isStart): string
    {
        // Only convert date-only strings; full datetime strings pass through as-is
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }

        $kingdomTz = TzHelper::getAppTimezone() ?? 'UTC';

        // If kingdom timezone is UTC, use the simple approach (no conversion needed)
        if ($kingdomTz === 'UTC') {
            return $isStart ? $dateValue : $dateValue . ' 23:59:59';
        }

        // Build the boundary datetime in the kingdom timezone, then convert to UTC
        $boundaryTime = $isStart ? '00:00:00' : '23:59:59';
        $kingdomDatetime = new DateTime(
            $dateValue . ' ' . $boundaryTime,
            new DateTimeZone($kingdomTz),
        );
        $kingdomDatetime->setTimezone(new DateTimeZone('UTC'));

        return $kingdomDatetime->format('Y-m-d H:i:s');
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
                fn($value) => is_string($value) && $value !== '',
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
                        // Strip table prefix from field name for parameter keys
                        $fieldKey = strpos($field, '.') !== false ? substr($field, strrpos($field, '.') + 1) : $field;
                        if ($start !== null && $start !== '') {
                            $defaults['dateRange'][$fieldKey . '_start'] = (string)$start;
                        }
                        if ($end !== null && $end !== '') {
                            $defaults['dateRange'][$fieldKey . '_end'] = (string)$end;
                        }
                    }
                    break;
                case 'gte':
                case 'gt':
                    if ($value !== null && $value !== '') {
                        // Strip table prefix from field name for parameter keys
                        $fieldKey = strpos($field, '.') !== false ? substr($field, strrpos($field, '.') + 1) : $field;
                        $defaults['dateRange'][$fieldKey . '_start'] = (string)$value;
                    }
                    break;
                case 'lte':
                case 'lt':
                    if ($value !== null && $value !== '') {
                        // Strip table prefix from field name for parameter keys
                        $fieldKey = strpos($field, '.') !== false ? substr($field, strrpos($field, '.') + 1) : $field;
                        $defaults['dateRange'][$fieldKey . '_end'] = (string)$value;
                    }
                    break;
                case 'is-populated':
                    if ($value !== null && $value !== '') {
                        $defaults['filters'][$field] = (string)$value;
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
     * Supports two modes:
     *
     * 1. **Query Mode** (default): Uses the query from result to build SQL SELECT statements.
     *    Best for simple fields that map directly to database columns.
     *
     * 2. **Data Mode**: Pass pre-processed data with computed/virtual fields already populated.
     *    Best for exports that include calculated fields, virtual properties, or complex
     *    transformations that can't be done in SQL.
     *
     * ## Column Value Resolution (in order of precedence):
     * 1. `exportValue` callback in column metadata - custom formatting function
     * 2. `renderField` path (e.g., 'member.name_for_herald') - for nested entity access
     * 3. `queryField` for relation columns in query mode
     * 4. Direct column key access on entity/array
     *
     * @param array $result Result from processDataverseGrid() with isCsvExport flag
     * @param \App\Services\CsvExportService $csvExportService CSV export service instance
     * @param string $entityName Base name for the export file (e.g., 'members', 'warrants')
     * @param string|null $tableName Optional table name for fetchTable (e.g., 'Awards.Recommendations' for plugin tables)
     *                               If not provided, uses ucfirst($entityName)
     * @param iterable|null $data Optional pre-processed data. If provided, uses data mode instead of query mode.
     *                            Data should be an iterable of entities or arrays with all computed fields populated.
     * @return \Cake\Http\Response CSV download response
     * @throws \Authorization\Exception\ForbiddenException If user lacks export permission
     */
    protected function handleCsvExport(
        array $result,
        $csvExportService,
        string $entityName,
        ?string $tableName = null,
        ?iterable $data = null,
    ): Response {
        // Determine table name for fetchTable (supports plugin tables like 'Awards.Recommendations')
        if ($tableName === null) {
            $tableName = ucfirst($entityName); // Default: capitalize entity name (e.g., 'members' -> 'Members')
        }

        // Check authorization for export
        $table = $this->fetchTable($tableName);
        $this->Authorization->authorize($table, 'export');

        $visibleColumns = $result['visibleColumns'];
        $columnsMetadata = $result['columnsMetadata'];

        // Determine which mode to use
        $useDataMode = $data !== null;

        if ($useDataMode) {
            // Data Mode: Use pre-processed data with computed fields
            $transformedData = $this->buildExportDataFromEntities($data, $visibleColumns, $columnsMetadata);
        } else {
            // Query Mode: Build SQL SELECT and execute query
            $transformedData = $this->buildExportDataFromQuery(
                $result['query'],
                $visibleColumns,
                $columnsMetadata,
                $tableName,
            );
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
     * Build export data from pre-processed entities (Data Mode)
     *
     * Extracts values from entities using column metadata configuration.
     * Supports virtual properties, nested relations via renderField, and custom exportValue callbacks.
     *
     * @param iterable $data Pre-processed entities or arrays
     * @param array $visibleColumns List of visible column keys
     * @param array $columnsMetadata Column configuration metadata
     * @return array Transformed data ready for CSV export
     */
    protected function buildExportDataFromEntities(iterable $data, array $visibleColumns, array $columnsMetadata): array
    {
        $transformedData = [];

        foreach ($data as $entity) {
            $row = [];

            foreach ($visibleColumns as $columnKey) {
                $columnMeta = $columnsMetadata[$columnKey] ?? null;
                if (!$columnMeta) {
                    continue;
                }

                // Skip non-exportable columns
                if (isset($columnMeta['exportable']) && $columnMeta['exportable'] === false) {
                    continue;
                }

                $headerLabel = $columnMeta['label'] ?? $columnKey;
                $value = $this->extractExportValue($entity, $columnKey, $columnMeta);
                $row[$headerLabel] = $value;
            }

            $transformedData[] = $row;
        }

        return $transformedData;
    }

    /**
     * Build export data from database query (Query Mode)
     *
     * Builds SQL SELECT statements and executes query for simple database fields.
     * Best for exports that don't require computed fields.
     *
     * @param \Cake\ORM\Query $query Database query to execute
     * @param array $visibleColumns List of visible column keys
     * @param array $columnsMetadata Column configuration metadata
     * @param string $tableName Full table name for model alias extraction
     * @return array Transformed data ready for CSV export
     */
    protected function buildExportDataFromQuery(
        $query,
        array $visibleColumns,
        array $columnsMetadata,
        string $tableName,
    ): array {
        // Extract model alias for SQL field references (e.g., 'Awards.Recommendations' -> 'Recommendations')
        $modelAlias = str_contains($tableName, '.') ? substr($tableName, strrpos($tableName, '.') + 1) : $tableName;

        // Select only visible columns for export
        $selectFields = [];
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

            // Skip columns that require data mode (have renderField but no queryField, or have exportValue callback)
            if (
                !empty($columnMeta['exportValue'])
                || (!empty($columnMeta['renderField']) && empty($columnMeta['queryField']))
            ) {
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
                // For regular fields, use model alias (not plugin prefix) for SQL
                $selectFields[$columnKey] = $modelAlias . '.' . $columnKey;
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

        return $transformedData;
    }

    /**
     * Extract export value from entity using column metadata
     *
     * Resolution order:
     * 1. exportValue callback if defined in column metadata
     * 2. renderField path for nested entity access (e.g., 'member.name_for_herald')
     * 3. Direct property access using column key
     *
     * @param mixed $entity Entity or array to extract value from
     * @param string $columnKey Column key identifier
     * @param array $columnMeta Column metadata configuration
     * @return string Extracted and formatted value
     */
    protected function extractExportValue($entity, string $columnKey, array $columnMeta): string
    {
        // 1. Check for custom exportValue callback
        if (!empty($columnMeta['exportValue']) && is_callable($columnMeta['exportValue'])) {
            $value = call_user_func($columnMeta['exportValue'], $entity, $columnKey, $columnMeta);

            return $this->formatExportValue($value);
        }

        // 2. Check for renderField path (nested entity access)
        if (!empty($columnMeta['renderField'])) {
            $value = $this->resolveNestedValue($entity, $columnMeta['renderField']);

            return $this->formatExportValue($value);
        }

        // 3. Direct property access
        if (is_array($entity)) {
            $value = $entity[$columnKey] ?? null;
        } elseif (is_object($entity)) {
            $value = $entity->{$columnKey} ?? null;
        } else {
            $value = null;
        }

        return $this->formatExportValue($value);
    }

    /**
     * Resolve nested value from entity using dot notation path
     *
     * @param mixed $entity Entity to traverse
     * @param string $path Dot-notation path (e.g., 'member.name_for_herald')
     * @return mixed Resolved value or null if path doesn't exist
     */
    protected function resolveNestedValue($entity, string $path)
    {
        $parts = explode('.', $path);
        $current = $entity;

        foreach ($parts as $part) {
            if ($current === null) {
                return null;
            }

            if (is_array($current)) {
                $current = $current[$part] ?? null;
            } elseif (is_object($current)) {
                $current = $current->{$part} ?? null;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Format value for CSV export
     *
     * Handles various data types and converts to string representation.
     *
     * @param mixed $value Value to format
     * @return string Formatted string value
     */
    protected function formatExportValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn($v) => (string)$v, $value));
        }

        return (string)$value;
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

    /**
     * Apply custom filter handlers for columns with complex filtering logic
     *
     * Columns can define a `customFilterHandler` in their metadata to specify
     * a static method that handles their filtering. This allows complex filter
     * logic (like querying multiple tables) to be defined alongside the column
     * definition rather than requiring special controller knowledge.
     *
     * Filter values are extracted from:
     * 1. Query string parameters (user-applied filters)
     * 2. Saved user view configuration (when loading a saved view)
     * 3. System view configuration (when loading a system view)
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to filter
     * @param array $customFilterColumns Columns with customFilterHandler defined
     * @param string $tableName The main table name
     * @param bool $canFilter Whether user filtering is enabled
     * @param array $currentFilters Current filter values from query params
     * @param mixed $currentView Current saved user view (or null)
     * @param array|null $selectedSystemView Current system view config (or null)
     * @param bool $dirtyFilters Whether user explicitly modified filters
     * @return \Cake\ORM\Query\SelectQuery The filtered query
     */
    protected function applyCustomFilterHandlers(
        $query,
        array $customFilterColumns,
        string $tableName,
        bool $canFilter,
        array $currentFilters,
        $currentView,
        ?array $selectedSystemView,
        bool $dirtyFilters,
    ) {
        foreach ($customFilterColumns as $columnKey => $columnMeta) {
            $handler = $columnMeta['customFilterHandler'];
            $handlerClass = $handler['class'] ?? null;
            $handlerMethod = $handler['method'] ?? null;

            if (!$handlerClass || !$handlerMethod) {
                continue;
            }

            // Try to get filter value from multiple sources
            $filterValue = null;

            // 1. Check query string (user-applied filter) - highest priority
            if ($canFilter) {
                $queryValue = $this->request->getQuery('filter.' . $columnKey);
                // Use explicit null/empty-string check to allow valid falsey values like 0, "0", false
                if ($queryValue !== null && $queryValue !== '') {
                    $filterValue = $queryValue;
                }
            }

            // 2. Check current filters (may have been set from query or system view defaults)
            // Use explicit null/empty-string check to allow valid falsey values like 0, "0", false
            if (
                $filterValue === null
                && isset($currentFilters[$columnKey])
                && $currentFilters[$columnKey] !== null
                && $currentFilters[$columnKey] !== ''
            ) {
                $filterValue = $currentFilters[$columnKey];
            }

            // 3. Check saved user view configuration
            if ($filterValue === null && $currentView && !$dirtyFilters) {
                $filterValue = $this->extractFilterFromViewConfig($currentView, $columnKey);
            }

            // 4. Check system view configuration
            if ($filterValue === null && $selectedSystemView && !$dirtyFilters) {
                $filterValue = $this->extractFilterFromSystemView($selectedSystemView, $columnKey);
            }

            // If we have a filter value, call the custom handler
            if ($filterValue !== null && $filterValue !== '' && (!is_array($filterValue) || !empty($filterValue))) {
                // Verify the handler exists and is callable
                if (class_exists($handlerClass) && method_exists($handlerClass, $handlerMethod)) {
                    $context = [
                        'tableName' => $tableName,
                        'columnKey' => $columnKey,
                        'columnMeta' => $columnMeta,
                    ];
                    $query = call_user_func([$handlerClass, $handlerMethod], $query, $filterValue, $context);
                } else {
                    Log::warning("Custom filter handler not found: {$handlerClass}::{$handlerMethod}");
                }
            }
        }

        return $query;
    }

    /**
     * Extract a specific filter value from a saved user view's config
     *
     * @param mixed $view The GridView entity
     * @param string $columnKey The column key to find
     * @return mixed The filter value or null if not found
     */
    protected function extractFilterFromViewConfig($view, string $columnKey)
    {
        if (!$view || !method_exists($view, 'getConfigArray')) {
            return null;
        }

        $config = $view->getConfigArray();

        // Check filters array
        if (!empty($config['filters']) && is_array($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                if (isset($filter['field']) && $filter['field'] === $columnKey) {
                    return $filter['value'] ?? null;
                }
            }
        }

        // Check expression tree
        if (!empty($config['expression']) && is_array($config['expression'])) {
            return $this->extractFilterFromExpression($config['expression'], $columnKey);
        }

        return null;
    }

    /**
     * Extract a specific filter value from a system view configuration
     *
     * @param array $systemView The system view configuration
     * @param string $columnKey The column key to find
     * @return mixed The filter value or null if not found
     */
    protected function extractFilterFromSystemView(array $systemView, string $columnKey)
    {
        $config = $systemView['config'] ?? [];

        // Check filters array
        if (!empty($config['filters']) && is_array($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                if (isset($filter['field']) && $filter['field'] === $columnKey) {
                    return $filter['value'] ?? null;
                }
            }
        }

        // Check expression tree
        if (!empty($config['expression']) && is_array($config['expression'])) {
            return $this->extractFilterFromExpression($config['expression'], $columnKey);
        }

        return null;
    }

    /**
     * Recursively extract a filter value from an expression tree
     *
     * @param array $expression The expression node to search
     * @param string $columnKey The column key to find
     * @return mixed The filter value or null if not found
     */
    protected function extractFilterFromExpression(array $expression, string $columnKey)
    {
        // Check if this is a leaf condition with the target field
        if (isset($expression['field']) && $expression['field'] === $columnKey) {
            return $expression['value'] ?? null;
        }

        // Check conditions array for nested expressions
        if (!empty($expression['conditions']) && is_array($expression['conditions'])) {
            foreach ($expression['conditions'] as $condition) {
                if (is_array($condition)) {
                    $result = $this->extractFilterFromExpression($condition, $columnKey);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }
}
