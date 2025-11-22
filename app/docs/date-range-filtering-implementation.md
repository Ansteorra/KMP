# Date Range Filtering Implementation Guide

## Overview

This document describes how to implement date range filtering in the Dataverse grid system, allowing users to filter date columns by selecting start and end dates.

## Column Configuration

### Date Filter Type

To enable date range filtering on a column, set the `filterType` to `'date-range'` in the grid columns metadata:

```php
'start_on' => [
    'key' => 'start_on',
    'label' => 'Starts',
    'type' => 'date',
    'sortable' => true,
    'filterable' => true,
    'filterType' => 'date-range',  // Enable date range filtering
    'defaultVisible' => true,
    'width' => '140px',
    'alignment' => 'left',
],
```

### Filter Type Options

- `'dropdown'` - Standard dropdown with predefined options
- `'date-range'` - Date range picker (start and end dates)
- `null` or not specified - Column is not filterable via dropdown

## Backend Implementation

### 1. Define Date Range Filter Columns

Add a `getDateRangeFilterColumns()` method to your GridColumns class:

```php
public static function getDateRangeFilterColumns(): array
{
    return array_filter(
        static::getColumns(),
        fn($col) => ($col['filterType'] ?? null) === 'date-range'
    );
}
```

### 2. Process Date Range Filters in Trait

The `DataverseGridTrait` will automatically handle date range filters:

```php
// Extract date range filters from query
$dateRangeFilters = [];
foreach ($dateRangeFilterColumns as $columnKey => $columnMeta) {
    $startKey = $columnKey . '_start';
    $endKey = $columnKey . '_end';
    
    $startDate = $this->request->getQuery($startKey);
    $endDate = $this->request->getQuery($endKey);
    
    if ($startDate || $endDate) {
        $dateRangeFilters[$columnKey] = [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }
}

// Apply date range filters to query
foreach ($dateRangeFilters as $field => $range) {
    if (!empty($range['start'])) {
        $baseQuery->where([$tableName . '.' . $field . ' >=' => $range['start']]);
    }
    if (!empty($range['end'])) {
        $baseQuery->where([$tableName . '.' . $field . ' <=' => $range['end']]);
    }
}
```

## Frontend Implementation

### Template Structure

Date range filters will render as two date input fields in the filter dropdown:

```html
<div class="filter-date-range">
    <div class="mb-2">
        <label class="form-label small">From</label>
        <input type="date" class="form-control form-control-sm" 
               data-filter-field="start_on_start"
               data-action="change->grid-view#applyDateRangeFilter">
    </div>
    <div>
        <label class="form-label small">To</label>
        <input type="date" class="form-control form-control-sm" 
               data-filter-field="start_on_end"
               data-action="change->grid-view#applyDateRangeFilter">
    </div>
</div>
```

### JavaScript Controller

The GridViewController will need methods to handle date range filtering:

```javascript
applyDateRangeFilter(event) {
    const input = event.target;
    const field = input.dataset.filterField;
    const value = input.value;
    
    // Update internal state
    this.dateRangeFilters[field] = value;
    
    // Rebuild URL and navigate
    this.applyFiltersAndNavigate();
}

buildFilterUrl() {
    const url = new URL(window.location);
    
    // Add date range filters to URL
    for (const [field, value] of Object.entries(this.dateRangeFilters)) {
        if (value) {
            url.searchParams.set(field, value);
        } else {
            url.searchParams.delete(field);
        }
    }
    
    return url.toString();
}
```

## Filter State Management

### Filter Badge Display

Date range filters should display as a combined badge:

```
Starts: Jan 1, 2025 - Dec 31, 2025
```

### Clear Filter

Users should be able to clear individual date inputs or the entire date range filter.

## Example Use Cases

### Warrant Date Filtering

Filter warrants by their start or expiration dates:

```php
// WarrantsGridColumns.php
'start_on' => [
    'filterType' => 'date-range',
    // ... other config
],

'expires_on' => [
    'filterType' => 'date-range',
    // ... other config
],
```

### Gathering Date Filtering

Filter gatherings by date range:

```php
// GatheringsGridColumns.php
'start_date' => [
    'filterType' => 'date-range',
    // ... other config
],
```

## Technical Considerations

### Timezone Handling

- Date inputs are in the user's local timezone
- Backend should convert to UTC for database queries
- Use `TimezoneHelper` for consistent timezone handling

### Query Performance

- Add database indexes on date columns used for filtering
- Consider date range validation (start must be before end)

### User Experience

- Provide clear labels ("From" / "To")
- Allow clearing individual date inputs
- Show active date range filters in badge format
- Consider adding quick date range presets (This Week, This Month, etc.)

## Future Enhancements

1. **Date Range Presets**: Quick buttons for common ranges
2. **Relative Dates**: Filter by "Last 7 days", "Next 30 days", etc.
3. **Date Picker UI**: Enhanced calendar picker instead of native date input
4. **Time Support**: Extend to datetime-range for hour/minute precision
