# Gathering Staff - Quick Reference Guide

## Overview

The Gathering Staff system allows gatherings to have multiple stewards and other staff members with customizable roles. This is separate from the event creator.

## Key Features

### Stewards
- Must be linked to AMP member accounts
- Require either email or phone contact information
- Contact info is auto-populated from AMP account when assigned (but editable)
- Can have contact notes (e.g., "Please text, no calls after 9 PM")
- Multiple stewards can be assigned to a single gathering

### Other Staff
- Can be AMP members OR generic SCA names
- Contact information is optional
- Custom role names (e.g., "Herald", "List Master", "Water Bearer")
- Flexible for volunteers without AMP accounts

## Database Schema

**Table:** `gathering_staff`

Key columns:
- `gathering_id` - FK to gatherings
- `member_id` - FK to members (nullable for non-AMP staff)
- `sca_name` - For non-AMP staff (nullable when member_id is set)
- `role` - Role name (e.g., "Steward", "Herald")
- `is_steward` - Boolean flag for steward role
- `email` - Contact email (copied from member for stewards, editable)
- `phone` - Contact phone (copied from member for stewards, editable)
- `contact_notes` - Optional contact preferences
- `sort_order` - Display order

## Business Rules

1. **XOR Rule:** Staff must have EITHER `member_id` OR `sca_name`, not both
2. **Steward Contact Rule:** Stewards must have `email` OR `phone` (at least one required)
3. **Non-steward Contact Rule:** Other staff can have optional contact info
4. **Auto-population:** When a steward with `member_id` is created, email/phone are auto-filled from member record

## Controller Actions

### GatheringStaffController

- `add($gatheringId)` - Add staff to a gathering
- `edit($id)` - Edit staff member details
- `delete($id)` - Remove staff from gathering
- `getMemberContactInfo()` - AJAX endpoint to fetch member contact info

**Authorization:** All actions require edit permission on the parent gathering

## View Templates

- `/templates/element/gatherings/staffTab.php` - Staff display tab with embedded modals for add/edit
- Modal-based UI for seamless staff management without leaving the gathering view
- Add and Edit forms are presented as Bootstrap modals

## Tab Order

The Staff tab is positioned at **order 3** in the gathering view:
1. Description (order 1)
2. *Staff (order 3)* ← NEW
3. Schedule (order 4)
4. Activities (order 5)
5. Location (order 6)
6. Attendance (order 7)
7. Waivers Plugin (order 10)

## JavaScript Features

The staff modals include:
- **Add Staff Modal**: Dynamic form with auto-disable fields based on selection (steward vs. other staff)
- **Edit Staff Modal**: Pre-populated form with data attributes for seamless editing
- AJAX contact info lookup when selecting AMP member
- Dynamic validation notices
- Field interaction management
- Form reset on modal close

## Usage Examples

### Adding a Steward
1. Navigate to gathering view
2. Click "Staff" tab
3. Click "Add Staff Member" button (opens modal)
4. Check "This person is a Steward"
5. Select AMP member from dropdown
6. Email/phone auto-populate
7. Edit contact info if needed for privacy
8. Add contact notes (optional)
9. Save

### Adding Other Staff
1. Navigate to gathering view
2. Click "Staff" tab
3. Click "Add Staff Member" button (opens modal)
4. Leave "This person is a Steward" unchecked
5. Either select AMP member OR enter SCA name
6. Enter custom role (e.g., "Herald", "List Master")
7. Optionally add contact info
8. Click "Add Staff Member" to save

## Files Changed/Created

### Database
- `config/Migrations/20251103120000_CreateGatheringStaff.php`

### Models
- `src/Model/Table/GatheringStaffTable.php`
- `src/Model/Entity/GatheringStaff.php`
- Updated: `src/Model/Table/GatheringsTable.php` (added hasMany relationship)
- Updated: `src/Model/Entity/Gathering.php` (added property)

### Controllers
- `src/Controller/GatheringStaffController.php`
- Updated: `src/Controller/GatheringsController.php` (view method contains GatheringStaff)

### Views
- `templates/GatheringStaff/add.php`
- `templates/GatheringStaff/edit.php`
- `templates/element/gatherings/staffTab.php`
- Updated: `templates/Gatherings/view.php` (added staff tab)

### Authorization
- `src/Policy/GatheringStaffPolicy.php`

### Tests
- `tests/Fixture/GatheringStaffFixture.php`
- `tests/TestCase/Model/Table/GatheringStaffTableTest.php`

## API Endpoints

### Get Member Contact Info (AJAX)
```
GET /gathering-staff/get-member-contact-info?member_id=123
```

Returns:
```json
Returns:
```json
{
  "email": "member@example.com",
  "phone": "555-0123"
}
```
```

## Best Practices

1. **Stewards:** Always assign at least one steward to each gathering
2. **Privacy:** Edit steward contact info if they want to keep personal info private
3. **Contact Notes:** Use notes to specify preferences like texting vs. calling
4. **Roles:** Use clear, descriptive role names for other staff
5. **Non-AMP Staff:** For volunteers without AMP accounts, use generic SCA names

## Common Scenarios

### Multiple Stewards
For large events, you can assign multiple co-stewards. Each will:
- Have their own contact info
- Be marked with the steward badge in the UI
- Be listed first in the staff tab

### Privacy-Conscious Stewards
1. Add steward (auto-populates from AMP account)
2. Click "Edit" on the steward
3. Change email/phone to event-specific contacts
4. Add note like "Event contact only - do not share"

### Mixed Staff Types
Typical staff roster might include:
- 2 Stewards (AMP members, full contact)
- Herald (AMP member, optional contact)
- List Master (non-AMP, SCA name only)
- Water Bearer volunteers (non-AMP, no contact)

## Troubleshooting

**Problem:** Can't save steward without contact info
- **Solution:** Add either email or phone - at least one is required

**Problem:** Can't select both member and SCA name
- **Solution:** Choose one or the other - if it's an AMP member, use the dropdown; if not, enter SCA name

**Problem:** Contact info not auto-filling
- **Solution:** Check that the member has email/phone in their AMP account, or enter manually

**Problem:** Can't remove steward
- **Solution:** Ensure you have edit permissions on the gathering
