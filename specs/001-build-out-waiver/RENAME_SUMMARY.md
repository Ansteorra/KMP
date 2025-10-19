# Terminology Change Summary: Event → Gathering

**Date**: October 19, 2025  
**Reason**: To avoid confusion with different types of "events" (system events, UI events, etc.)

## Changes Made

All references to "Event" as the core entity/object have been renamed to "Gathering" throughout the specification.

### Core Entity Names

| Before | After |
|--------|-------|
| Event | Gathering |
| Event Type | Gathering Type |
| Event Activity | Gathering Activity |
| Event Waiver | Gathering Waiver |
| Event Activity Waiver | Gathering Activity Waiver |

### Database Field Names

| Before | After |
|--------|-------|
| event_id | gathering_id |
| event_type_id | gathering_type_id |
| event_activity_id | gathering_activity_id |
| event_end_date | gathering_end_date |
| award_events | award_gatherings |

### Common Phrases

| Before | After |
|--------|-------|
| event steward | gathering steward |
| event stewards | gathering stewards |
| SCA event | SCA gathering |
| at events | at gatherings |
| for events | for gatherings |

## Statistics

- **Gathering** (capitalized): 100 occurrences
- **gathering** (lowercase): 115 occurrences
- **Gatherings** (capitalized plural): 12 occurrences
- **gatherings** (lowercase plural): 43 occurrences
- **Total replacements**: 270+ occurrences

## Sections Updated

1. ✅ **Feature Title**: "Event Waiver Tracking System" → "Gathering Waiver Tracking System"
2. ✅ **Clarifications**: All sessions updated
3. ✅ **User Stories**: All 5 user stories updated
4. ✅ **Edge Cases**: All edge cases updated
5. ✅ **Functional Requirements**: All FRs updated (FR-001 through FR-040)
6. ✅ **Key Entities**: 
   - Core Entities: Gathering Type, Gathering, Gathering Activity
   - Plugin Entities: Waiver Type, Gathering Activity Waiver, Gathering Waiver
7. ✅ **Success Criteria**: All SC items updated
8. ✅ **Assumptions**: All 19 assumptions updated
9. ✅ **Architecture Overview**: Core vs Plugin structure updated
10. ✅ **Migration Strategy**: All phases updated
11. ✅ **Dependencies**: All 9 dependencies updated
12. ✅ **Out of Scope**: All items updated

## Key Terminology

### What Changed
- **Gathering** now refers to what was previously called an "Event" (tournaments, practices, feasts, etc.)
- **Gathering Type** classifies gatherings (Tournament, Practice, Arts & Sciences Day)
- **Gathering Activity** defines activities at gatherings (Armored Combat, Archery, Feast, Court)
- **Gathering stewards** manage gatherings and upload waivers

### What Stayed the Same
- **Waiver Type**: Still "Waiver Type" (no change)
- **Waiver tracking**: Still about tracking waivers
- **Plugin architecture**: Still using plugin system
- **Core entities**: Still in core KMP

## Database Schema Impact

The following table and column names will need to be updated during implementation:

### Tables
- `events` → `gatherings`
- `event_types` → `gathering_types`  
- `event_activities` → `gathering_activities`
- `event_waivers` → `gathering_waivers` (in Waivers plugin)
- `event_activity_waivers` → `gathering_activity_waivers` (in Waivers plugin)
- `award_events` → `award_gatherings` (in Awards plugin - to be migrated)

### Foreign Key Columns
- `event_id` → `gathering_id`
- `event_type_id` → `gathering_type_id`
- `event_activity_id` → `gathering_activity_id`

### Date Columns
- `event_end_date` → `gathering_end_date`

## Code Impact

### Model/Table Files
- `EventsTable` → `GatheringsTable`
- `EventTypesTable` → `GatheringTypesTable`
- `EventActivitiesTable` → `GatheringActivitiesTable`
- `EventWaiversTable` → `GatheringWaiversTable` (plugin)
- `EventActivityWaiversTable` → `GatheringActivityWaiversTable` (plugin)

### Entity Files
- `Event` → `Gathering`
- `EventType` → `GatheringType`
- `EventActivity` → `GatheringActivity`
- `EventWaiver` → `GatheringWaiver` (plugin)
- `EventActivityWaiver` → `GatheringActivityWaiver` (plugin)

### Controller Files
- `EventsController` → `GatheringsController`
- `EventTypesController` → `GatheringTypesController`
- `EventActivitiesController` → `GatheringActivitiesController`

### Template Directories
- `templates/Events/` → `templates/Gatherings/`
- `templates/EventTypes/` → `templates/GatheringTypes/`
- `templates/EventActivities/` → `templates/GatheringActivities/`

## Migration Considerations

1. **Data Migration Required**: When migrating `award_events` to core, table should be renamed to `award_gatherings` first, then data moved to `gatherings`

2. **Foreign Key Updates**: All foreign keys referencing event tables will need to be updated

3. **Existing Data**: Any existing references in the database will need migration scripts

4. **Backward Compatibility**: Consider if any external systems reference the old naming

5. **Documentation**: All technical documentation should be updated to use "Gathering" terminology

## Benefits of This Change

1. **Clarity**: "Gathering" is distinct from programming/system "events"
2. **Domain Language**: More closely matches SCA terminology
3. **Consistency**: Single term used throughout application
4. **Maintainability**: Easier to search and understand code
5. **Future-Proofing**: Avoids conflicts with event-driven architecture patterns

## Backup

A backup of the original specification has been saved as `spec.md.backup` in case rollback is needed.

---

**Status**: ✅ Complete - All terminology updated throughout specification
