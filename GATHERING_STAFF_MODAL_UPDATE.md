# Gathering Staff Modal UI Update

**Date:** November 3, 2025  
**Change:** Converted staff management from separate pages to modal-based UI

## Summary

Updated the gathering staff management interface to use Bootstrap modals instead of separate add/edit pages. This provides a more seamless user experience by keeping users on the gathering view page while managing staff.

## Changes Made

### 1. Updated Staff Tab Element
**File:** `templates/element/gatherings/staffTab.php`

- Changed "Add Staff Member" link to button that opens modal
- Changed "Edit" links to buttons that open pre-populated modal
- Added two Bootstrap modals:
  - **Add Staff Modal** (`#addStaffModal`)
  - **Edit Staff Modal** (`#editStaffModal`)
- Added JavaScript for modal functionality:
  - Form field management (steward vs. non-steward)
  - AJAX member contact info lookup
  - Dynamic notice display
  - Edit modal data population from button attributes
  - Form reset on modal close

### 2. Updated GatheringsController
**File:** `src/Controller/GatheringsController.php`

- Added `$members` list to the `view()` method
- Members list is now available for the add staff modal dropdown

### 3. Modal Features

#### Add Staff Modal
- Full form with all fields from the original add page
- Dynamic field behavior based on steward checkbox
- AJAX lookup for member contact info
- Validation notices
- Submits to `GatheringStaffController::add()`
- Redirects back to gathering view on success

#### Edit Staff Modal
- Pre-populated with existing staff data using data attributes
- Data passed via button data attributes:
  - `data-staff-id`
  - `data-staff-name`
  - `data-staff-role`
  - `data-staff-email`
  - `data-staff-phone`
  - `data-staff-notes`
  - `data-is-steward`
- Form action URL is dynamically updated
- Member/SCA name shown as read-only (frozen)
- Submits to `GatheringStaffController::edit($id)`
- Redirects back to gathering view on success

### 4. User Experience Improvements

**Before:**
1. Click "Add Staff Member" → Navigate to new page
2. Fill form → Submit
3. Redirect back to gathering view

**After:**
1. Click "Add Staff Member" → Modal opens
2. Fill form → Submit
3. Modal closes, page refreshes with new staff

**Benefits:**
- ✅ No page navigation required
- ✅ Context preserved (stay on gathering view)
- ✅ Faster interaction
- ✅ Modern, clean UI
- ✅ Consistent with other modal patterns in KMP (activities, attendance)

### 5. Backward Compatibility

The original controller actions remain unchanged and continue to work:
- `GatheringStaffController::add()` - Still accepts POST data and redirects
- `GatheringStaffController::edit()` - Still accepts POST data and redirects
- `GatheringStaffController::delete()` - Still works via postLink
- `GatheringStaffController::getMemberContactInfo()` - AJAX endpoint unchanged

The standalone templates (`add.php` and `edit.php`) are **deprecated but not removed** - they would still work if accessed directly but are no longer linked from the UI.

## Technical Implementation

### JavaScript Modal Management

```javascript
// Add Modal - Field management
- Steward checkbox toggles notices and field availability
- Member select triggers AJAX lookup and disables SCA name
- Form reset on modal close

// Edit Modal - Data population
- Modal show event populates fields from button data attributes
- Form action URL updated dynamically with staff ID
- Steward status controls notice visibility
```

### Data Flow

```
User clicks "Add" button
  ↓
Modal opens with empty form
  ↓
User fills form & submits
  ↓
POST to GatheringStaffController::add($gatheringId)
  ↓
Controller saves & sets flash message
  ↓
Redirect to Gatherings::view($gatheringId)
  ↓
Page refreshes, modal is closed, new staff visible
```

## Files Modified

1. `templates/element/gatherings/staffTab.php`
   - Added modals
   - Changed buttons
   - Added JavaScript

2. `src/Controller/GatheringsController.php`
   - Added `$members` to view method

3. `GATHERING_STAFF_QUICK_REFERENCE.md`
   - Updated usage examples
   - Updated view templates section

4. `GATHERING_STAFF_IMPLEMENTATION_SUMMARY.md`
   - Updated user flow documentation
   - Noted modal-based approach

## Testing Checklist

- [x] Add steward via modal
- [x] Add non-steward AMP member via modal
- [x] Add non-steward non-AMP member via modal
- [x] Edit steward contact info via modal
- [x] Edit other staff via modal
- [x] Remove staff (postLink still works)
- [x] AJAX member lookup works
- [x] Form validation works
- [x] Modal closes on cancel
- [x] Modal closes on submit success
- [x] Page refreshes show updated staff list

## Migration Notes

**For Users:**
- UI change is transparent - same functionality, better UX
- All staff management now happens in modals
- No separate pages to navigate

**For Developers:**
- Modal HTML is in `staffTab.php` element
- JavaScript is inline in same file
- Controller actions unchanged
- Can remove standalone templates if desired (currently kept for backward compatibility)

## Future Enhancements

Potential improvements:
1. Real-time updates without page refresh (AJAX save + DOM update)
2. Inline editing in the staff table
3. Drag-and-drop staff reordering
4. Bulk staff import modal
5. Staff role suggestions/autocomplete
