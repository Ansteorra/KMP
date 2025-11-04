# Gathering Staff Feature - Implementation Summary

**Date:** November 3, 2025  
**Feature:** Gathering Staff Management  
**Status:** Complete

## Overview

Implemented a comprehensive gathering staff management system that allows gatherings to have multiple stewards and other staff members with customizable roles. This addresses the requirement that event stewards are separate from the event creator and provides flexible contact management.

## Requirements Met

### Stewards
✅ Multiple stewards can be assigned to a gathering (1+N)  
✅ Stewards must be linked to AMP member accounts  
✅ Email and phone auto-populate from member account when assigned  
✅ Contact info is editable for privacy reasons  
✅ Email OR phone is required (not both, at least one)  
✅ Contact notes field for preferences ("text only", "no calls after 9 PM", etc.)

### Other Staff
✅ Can be AMP members OR generic SCA names  
✅ Custom role names (free-text)  
✅ Contact information is optional  
✅ Flexible for volunteers without AMP accounts

## Implementation Details

### 1. Database Schema

**Migration:** `20251103120000_CreateGatheringStaff.php`

Created `gathering_staff` table with:
- Primary key and foreign keys (gathering_id, member_id)
- XOR fields: member_id (nullable) and sca_name (nullable)
- Role information: role (varchar), is_steward (boolean)
- Contact fields: email, phone, contact_notes
- Sort order for display organization
- Standard audit fields (created, modified, created_by, modified_by, deleted)

**Indexes:**
- gathering_id, member_id, is_steward, sort_order, deleted

**Foreign Keys:**
- gathering_id → gatherings.id (CASCADE on delete)
- member_id → members.id (NO_ACTION on delete)

### 2. Model Layer

#### GatheringStaffTable
**Location:** `src/Model/Table/GatheringStaffTable.php`

**Features:**
- Validation rules for all fields
- Custom business rules:
  - XOR rule: Must have member_id OR sca_name (not both)
  - Steward contact rule: Must have email OR phone
- Behaviors: Timestamp, Footprint, Trash
- Custom finders: findStewards(), findOtherStaff()
- beforeSave hook to auto-populate contact info from member

**Relationships:**
- belongsTo Gatherings (INNER)
- belongsTo Members (LEFT)

#### GatheringStaff Entity
**Location:** `src/Model/Entity/GatheringStaff.php`

**Features:**
- Virtual field: display_name (uses member.sca_name or sca_name)
- Virtual field: has_contact_info (boolean check)
- Accessible fields configuration

#### Updated Gatherings Model
**Changes:**
- Added hasMany relationship to GatheringStaff
- Sort by: is_steward DESC, sort_order ASC (stewards first)
- Updated entity properties and docblocks

### 3. Controller Layer

#### GatheringStaffController
**Location:** `src/Controller/GatheringStaffController.php`

**Actions:**
- `add($gatheringId)` - Add staff with auto-sort ordering
  - Stewards: sort_order 0-99
  - Other staff: sort_order 100+
- `edit($id)` - Edit staff (member/sca_name frozen after creation)
- `delete($id)` - Remove staff from gathering
- `getMemberContactInfo()` - AJAX endpoint for contact info lookup

**Authorization:** All actions check edit permission on parent gathering

#### Updated GatheringsController
**Changes:**
- view() method contains GatheringStaff with Members
- Sorted by is_steward DESC, sort_order ASC

### 4. View Layer

#### Add Form
**Location:** `templates/GatheringStaff/add.php`

**Features:**
- Dynamic form that changes based on steward checkbox
- Member dropdown with AJAX contact info lookup
- SCA name input for non-AMP staff
- Contact fields with validation notices
- JavaScript for field interaction management
- Auto-disable conflicting fields

#### Edit Form
**Location:** `templates/GatheringStaff/edit.php`

**Features:**
- Displays current member/SCA name (read-only)
- Editable contact information
- Editable role and contact notes
- Delete option in sidebar

#### Staff Tab
**Location:** `templates/element/gatherings/staffTab.php`

**Features:**
- Separated sections: Stewards (with star icon) and Other Staff
- Warning if no stewards assigned
- Table displays: Name, Role, Contact Info, Actions
- Edit/Remove buttons for authorized users
- Staff management tips panel
- Visual badges for AMP members
- Clickable email/phone links

#### Updated Gathering View
**Location:** `templates/Gatherings/view.php`

**Changes:**
- Added Staff tab button (order 3) with staff count badge
- Included staffTab element
- Updated tab order comments

### 5. Authorization

#### GatheringStaffPolicy
**Location:** `src/Policy/GatheringStaffPolicy.php`

**Rules:**
- All actions (add, edit, delete) defer to gathering's edit permission
- Actual authorization check happens in controller using gathering entity

### 6. Testing

#### Fixtures
**Location:** `tests/Fixture/GatheringStaffFixture.php`

Sample data includes:
- Two stewards with different contact preferences
- AMP member staff with role
- Non-AMP staff (generic SCA name)

#### Unit Tests
**Location:** `tests/TestCase/Model/Table/GatheringStaffTableTest.php`

**Test Coverage:**
- Validation: member_id OR sca_name requirement
- Validation: Steward contact info requirement
- Successful saves with various configurations
- Custom finders (stewards, otherStaff)

### 7. User Interface Flow

#### Adding a Steward (Modal-Based)
1. User navigates to gathering view
2. Clicks "Staff" tab
3. Clicks "Add Staff Member" button - **modal opens**
4. Checks "This person is a Steward"
5. Selects AMP member from dropdown
6. Contact info auto-fills via AJAX
7. User can edit contact info for privacy
8. User adds contact notes (optional)
9. Clicks "Add Staff Member" button in modal
10. **Modal closes**, page refreshes with new staff member

#### Adding Other Staff (AMP Member)
1. Same as above but don't check steward
2. Select AMP member from modal
3. Enter custom role
4. Contact info optional
5. Submit via modal

#### Adding Other Staff (Non-AMP)
1. Open Add Staff modal
2. Leave member dropdown empty
3. Enter SCA name manually
4. Enter custom role
5. Contact info optional
6. Submit via modal

#### Editing Staff (Modal-Based)
1. Click "Edit" button next to staff member
2. **Edit modal opens** with pre-populated data
3. User edits role, contact info, or notes
4. Clicks "Save Changes"
5. **Modal closes**, page refreshes with updated info

## Technical Highlights

### Auto-Population Logic
```php
// In GatheringStaffTable::beforeSave()
if ($entity->isNew() && $entity->is_steward && !empty($entity->member_id)) {
    if (empty($entity->email) && empty($entity->phone)) {
        $member = $this->Members->get($entity->member_id);
        $entity->email = $member->email_address;
        $entity->phone = $member->phone_number;
    }
}
```

### XOR Validation
```php
$rules->add(
    function ($entity, $options) {
        $hasMember = !empty($entity->member_id);
        $hasScaName = !empty($entity->sca_name);
        return $hasMember xor $hasScaName; // Exclusive OR
    },
    'memberOrScaName',
    [...]
);
```

### Steward Contact Validation
```php
$rules->add(
    function ($entity, $options) {
        if (!$entity->is_steward) {
            return true; // Only applies to stewards
        }
        return !empty($entity->email) || !empty($entity->phone);
    },
    'stewardContactInfo',
    [...]
);
```

### Sort Order Strategy
- **Stewards:** 0-99 (assigned sequentially: 0, 1, 2...)
- **Other Staff:** 100+ (assigned sequentially: 100, 101, 102...)
- This ensures stewards always appear first in listings

## Tab Ordering

Following KMP's tab ordering system:
- **Order 1:** Description
- **Order 3:** Staff ← NEW
- **Order 4:** Schedule
- **Order 5:** Activities
- **Order 6:** Location
- **Order 7:** Attendance
- **Order 10:** Waivers (plugin)

## Files Created

### Database
- `config/Migrations/20251103120000_CreateGatheringStaff.php`

### Models
- `src/Model/Table/GatheringStaffTable.php`
- `src/Model/Entity/GatheringStaff.php`

### Controllers
- `src/Controller/GatheringStaffController.php`

### Views
- `templates/element/gatherings/staffTab.php` (includes embedded modals for add/edit)

**Note:** Standalone add.php and edit.php templates are no longer used as staff management is now modal-based within the gathering view.

### Authorization
- `src/Policy/GatheringStaffPolicy.php`

### Tests
- `tests/Fixture/GatheringStaffFixture.php`
- `tests/TestCase/Model/Table/GatheringStaffTableTest.php`

### Documentation
- `GATHERING_STAFF_QUICK_REFERENCE.md`

## Files Modified

### Models
- `src/Model/Table/GatheringsTable.php` - Added hasMany GatheringStaff
- `src/Model/Entity/Gathering.php` - Added gathering_staff property

### Controllers
- `src/Controller/GatheringsController.php` - Updated view() to contain GatheringStaff

### Views
- `templates/Gatherings/view.php` - Added staff tab button and content

## Migration Execution

```bash
cd /workspaces/KMP/app && bin/cake migrations migrate
```

**Result:** Successfully created `gathering_staff` table

## Design Decisions

### 1. XOR Constraint for Member vs. SCA Name
**Decision:** Enforce at application level (not database CHECK constraint)  
**Reason:** CakePHP ORM rules provide better error messaging and are easier to test

### 2. Sort Order Strategy
**Decision:** Use numeric ranges (0-99 stewards, 100+ others)  
**Reason:** Allows easy insertion and guarantees stewards appear first without complex sorting

### 3. Contact Info Auto-Population
**Decision:** Auto-fill in beforeSave() hook  
**Reason:** Centralized logic, works for both controller and potential API usage

### 4. Editable Contact Info for Stewards
**Decision:** Allow editing even though auto-populated  
**Reason:** Privacy - stewards may want event-specific contact info

### 5. Member/SCA Name Frozen After Creation
**Decision:** Cannot change member_id or sca_name in edit form  
**Reason:** Prevents confusion and maintains data integrity (delete and re-add instead)

### 6. Authorization via Parent Gathering
**Decision:** Check gathering.edit permission instead of separate staff permissions  
**Reason:** Simplifies authorization - staff management is part of gathering management

## Future Enhancements

Potential future improvements:
1. **Email Templates:** Auto-generate emails to staff with event details
2. **Staff Roles Enum:** Predefined role list with autocomplete
3. **Staff Confirmation:** Track whether staff have confirmed their role
4. **Staff Availability:** Calendar integration for staff availability
5. **Public Display:** Option to show/hide staff on public landing page
6. **Staff Permissions:** Give stewards specific gathering management permissions
7. **Bulk Import:** Import staff list from CSV
8. **Historical Tracking:** View staff assignments across multiple events

## Testing Recommendations

### Manual Testing Checklist
- [ ] Add steward with email only
- [ ] Add steward with phone only
- [ ] Try to add steward without contact info (should fail)
- [ ] Add steward and verify auto-population of contact info
- [ ] Edit steward contact info
- [ ] Add non-steward AMP member with contact info
- [ ] Add non-steward AMP member without contact info
- [ ] Add non-steward with generic SCA name
- [ ] Delete staff member
- [ ] Verify tab ordering
- [ ] Test AJAX member lookup
- [ ] Verify authorization (non-editors can't add/edit/delete)

### Automated Testing
Run the test suite:
```bash
cd /workspaces/KMP/app
vendor/bin/phpunit tests/TestCase/Model/Table/GatheringStaffTableTest.php
```

## Best Practices Followed

✅ **CakePHP Conventions:** Naming, structure, associations  
✅ **Security:** Authorization on all actions  
✅ **User Experience:** Auto-population, dynamic forms, clear messaging  
✅ **Data Integrity:** Validation rules, business rules  
✅ **Documentation:** Inline comments, docblocks, reference guide  
✅ **Testing:** Fixtures and unit tests  
✅ **Accessibility:** Proper labels, ARIA attributes  
✅ **Responsive Design:** Bootstrap classes, mobile-friendly

## Conclusion

The Gathering Staff feature is fully implemented and ready for use. It provides a flexible, user-friendly way to manage stewards and other staff for gatherings, with appropriate validation, authorization, and privacy controls. The system distinguishes between event creators and event stewards while allowing multiple stewards and flexible staff roles.
