# Dataverse grid date-range filters

Date-range filtering is built into `DataverseGridTrait` and `grid-view-controller`. A grid
normally enables it through column metadata only; do not add a parallel controller parser or
a `getDateRangeFilterColumns()` method.

## Column metadata

```php
'start_on' => [
    'key' => 'start_on',
    'label' => 'Starts',
    'type' => 'date',
    'sortable' => true,
    'filterable' => true,
    'filterType' => 'date-range',
    'defaultVisible' => true,
],
```

`DataverseGridTrait::processDataverseGrid()` discovers columns where `filterable` is true and
`filterType` is `date-range`. The optional metadata used by the shared implementation includes:

- `queryField`: the qualified database field when it differs from the column key.
- `nullMeansActive`: include `NULL` values in a lower-bound query, for fields where `NULL`
  means “does not expire.”
- `skipAutoFilter` or `customFilterHandler`: opt out of the standard SQL predicate.
- `showInFilterMenu`: hide the control while preserving view/filter state support.

## URL and saved-view contract

A field named `start_on` uses direct query parameters:

```text
?start_on_start=2026-08-01&start_on_end=2026-08-31
```

The Stimulus controller groups the two generated filter descriptors, displays labeled
`From` and `To` date inputs, preserves the values in navigation URLs, and presents active
filter pills. Saved and system views encode the same rule as:

```json
{
  "field": "start_on",
  "operator": "dateRange",
  "value": ["2026-08-01", "2026-08-31"]
}
```

System-view and locked-filter bounds are reapplied by the server. Locked bounds cannot be
overridden with crafted query parameters. A caller that disables user filtering may still
receive the system-view constraints.

## Boundary semantics

- The lower bound is inclusive (`>=`) and starts at `00:00:00`.
- The upper bound is inclusive (`<=`) and ends at `23:59:59`.
- Date-only input is interpreted in the configured application/kingdom timezone and converted
  to UTC before comparing with UTC datetimes.
- A full datetime value passes through unchanged. Code that supplies one must already use the
  database comparison timezone.
- Empty endpoints are allowed. Views may also use `gt` or `lt` for an exclusive endpoint.

This is application-timezone behavior, not the viewing member's personal display timezone.
Keep that distinction when adding date filters.

## Verification

For a new field, cover metadata plus an actual boundary query, especially records near local
midnight and daylight-saving transitions. Existing examples live in:

- `src/KMP/GridColumns/WarrantsGridColumns.php`
- `tests/TestCase/KMP/DataverseGridTraitTest.php`
- `tests/TestCase/Controller/DateBoundaryConversionTest.php`
- `tests/TestCase/Controller/WarrantsGridSameDayTest.php`
- `tests/js/controllers/grid-view-controller.test.js`
