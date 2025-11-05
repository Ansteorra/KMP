# Public ID Implementation - Focused Approach

## Scope: Members & Gatherings Only

Based on feedback, we're starting with a **focused implementation** that adds public IDs only to the tables needed for the gathering staff feature:

- ✅ **members** table
- ✅ **gatherings** table

Other tables will be added in future migrations as features require them.

## Why This Approach?

1. **Minimal Impact** - Only 2 tables affected initially
2. **Focused Testing** - Test with one feature (gathering staff) first
3. **Proven Pattern** - Once working, easy to expand to other tables
4. **Non-Breaking** - No changes to existing features
5. **Gradual Rollout** - Add public_ids to other tables as needed

## Implementation Steps

### Step 1: Run Migration ✅
```bash
bin/cake migrations migrate
```

This adds `public_id` column to:
- `members` table
- `gatherings` table

### Step 2: Generate Public IDs ✅
```bash
bin/cake generate_public_ids members gatherings
```

This populates public_id values for all existing members and gatherings.

### Step 3: Add Behavior to Tables ✅

**MembersTable:**
```php
// src/Model/Table/MembersTable.php
public function initialize(array $config): void
{
    parent::initialize($config);
    $this->addBehavior('PublicId');
}
```

**GatheringsTable:**
```php
// src/Model/Table/GatheringsTable.php
public function initialize(array $config): void
{
    parent::initialize($config);
    $this->addBehavior('PublicId');
}
```

### Step 4: Update Gathering Staff Token System 🔄

**GatheringStaffController** - Change token methods:

```php
// BEFORE: Uses internal IDs
$memberId = $this->request->getData('member_id');
$gatheringId = $this->request->getData('gathering_id');

// AFTER: Uses public IDs
$memberPublicId = $this->request->getData('member_public_id');
$gatheringPublicId = $this->request->getData('gathering_public_id');

// Lookup by public_id
$member = $this->GatheringStaff->Members->getByPublicId($memberPublicId);
$gathering = $this->GatheringStaff->Gatherings->getByPublicId($gatheringPublicId);

// Store in token
Cache::write('contact_token_' . $token, [
    'member_public_id' => $memberPublicId,    // Changed
    'gathering_public_id' => $gatheringPublicId,  // Changed
    'user_id' => $currentUserId,
    'created' => time(),
]);
```

**staffTab.php** - Update JavaScript:

```javascript
// BEFORE: Sends internal IDs
{
    member_id: memberId,
    gathering_id: gatheringId
}

// AFTER: Sends public IDs
{
    member_public_id: memberPublicId,  // From autocomplete value
    gathering_public_id: gatheringPublicId  // From page data
}
```

### Step 5: Update Member Autocomplete 🔄

**MembersController::autoComplete():**

```php
// BEFORE: Returns internal ID
$members = $this->Members->find()
    ->select(['id', 'sca_name', 'email_address'])  // id field
    ->toArray();

// Returns: {value: 123, label: "John of Example"}

// AFTER: Returns public ID
$members = $this->Members->find()
    ->select(['public_id', 'sca_name', 'email_address'])  // public_id field
    ->toArray();

// Returns: {value: "a7fK9mP2", label: "John of Example"}
```

### Step 6: Test End-to-End ✅

1. Open gathering view
2. Click "Add Staff Member"
3. Type member name in autocomplete
4. Select member → Should return public_id
5. Contact info should auto-fill using public_id
6. Save staff member
7. Verify in browser DevTools → No internal IDs visible

## Security Verification

### Before (Vulnerable)
```
Network Tab:
POST /gathering-staff/generate-contact-info-token
{
    "member_id": 123,        ❌ Internal ID exposed
    "gathering_id": 51       ❌ Internal ID exposed
}

Autocomplete:
{value: 123, label: "..."}   ❌ Internal ID exposed
```

### After (Secure)
```
Network Tab:
POST /gathering-staff/generate-contact-info-token
{
    "member_public_id": "a7fK9mP2",      ✅ Public ID
    "gathering_public_id": "m3Qr8Nk5"    ✅ Public ID
}

Autocomplete:
{value: "a7fK9mP2", label: "..."}   ✅ Public ID
```

## What Stays the Same

### Internal Database Relations
```sql
-- Foreign keys still use internal IDs
CREATE TABLE gathering_staff (
    id INT,
    gathering_id INT,  -- Still uses internal ID
    member_id INT,     -- Still uses internal ID
    FOREIGN KEY (gathering_id) REFERENCES gatherings(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
);
```

### Internal Lookups
```php
// Internal code can still use IDs
$member = $this->Members->get(123);  // Still works

// Public-facing code uses public_id
$member = $this->Members->getByPublicId('a7fK9mP2');  // New
```

## Future Expansion

When other features need public IDs:

1. Create migration for those specific tables
2. Run `bin/cake generate_public_ids table1 table2`
3. Add `PublicIdBehavior` to those tables
4. Update controllers/templates for those features

**Examples:**
- Award recommendations → Add to `awards`, `recommendations` tables
- Member profiles → Update member view URLs
- Branch pages → Add to `branches` table

## Files to Modify

### Database
- [x] Migration created: `config/Migrations/20251103140000_AddPublicIdToMembersAndGatherings.php`

### Models
- [ ] `src/Model/Table/MembersTable.php` - Add `PublicIdBehavior`
- [ ] `src/Model/Table/GatheringsTable.php` - Add `PublicIdBehavior`

### Controllers
- [ ] `src/Controller/GatheringStaffController.php` - Use public_ids in token methods
- [ ] `src/Controller/MembersController.php` - Return public_id in autocomplete

### Templates
- [ ] `templates/element/gatherings/staffTab.php` - Use public_id in JavaScript

## Testing Checklist

- [ ] Migration runs successfully
- [ ] Public IDs generated for existing records
- [ ] New members get public_id automatically
- [ ] New gatherings get public_id automatically
- [ ] Autocomplete returns public_id values
- [ ] Token system uses public_ids
- [ ] Contact info auto-fill works
- [ ] Staff members save successfully
- [ ] No internal IDs visible in browser DevTools
- [ ] Network tab shows only public_ids

## Summary

**Scope:** Members & Gatherings tables only
**Impact:** Minimal - Only gathering staff feature affected
**Testing:** Focused on one feature
**Rollout:** Non-breaking, gradual expansion possible
**Security:** Prevents ID exposure in gathering staff feature

**Next:** After this works, we can expand to other tables/features as needed!

---

**Status:** Ready to implement
**Files:** Migration and behavior created, ready to add to tables and update controllers
**Documentation:** Complete guides available in `docs/` folder
