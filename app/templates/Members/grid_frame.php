<?php

/**
 * Grid Content Template - Wrapper for rendering just the grid content element
 * Used for Turbo Frame requests
 */

// DEBUG: Output what variables are available
echo "<!-- DEBUG grid_frame.php:\n";
echo "currentView: " . (isset($currentView) ? ($currentView ? $currentView->id : 'null object') : 'undefined') . "\n";
echo "currentSearch: " . (isset($currentSearch) ? var_export($currentSearch, true) : 'undefined') . "\n";
echo "currentFilters: " . (isset($currentFilters) ? json_encode($currentFilters) : 'undefined') . "\n";
echo "-->\n";

echo $this->element('Members/grid_content', [
    'columns' => $columns ?? [],
    'visibleColumns' => $visibleColumns ?? [],
    'members' => $members ?? [],
    'currentSort' => $currentSort ?? [],
    'currentFilters' => $currentFilters ?? [],
    'currentSearch' => $currentSearch ?? '',
    'currentView' => $currentView ?? null,
    'dropdownFilterColumns' => $dropdownFilterColumns ?? [],
    'filterOptions' => $filterOptions ?? [],
    'gridKey' => $gridKey ?? null,
]);
