# Public ID Implementation - Update Tracking

## Overview

This document tracks all the locations where member lookups need to be updated to use `public_id` instead of internal `id`.

## Core Application

### MembersController
**File:** `src/Controller/MembersController.php`
- [ ] `autoComplete()` - Return `public_id` in results instead of `id`
- [ ] Add `PublicIdBehavior` to MembersTable

### GatheringsController  
**File:** `src/Controller/GatheringsController.php`
- [ ] Add `PublicIdBehavior` to GatheringsTable

### GatheringStaffController
**File:** `src/Controller/GatheringStaffController.php`
- [ ] `generateContactInfoToken()` - Accept `member_public_id` and `gathering_public_id`
- [ ] `getMemberContactInfo()` - Validate token with public_ids
- [ ] `add()` - Accept `member_public_id` or `member_sca_name` from autocomplete
- [ ] `edit()` - Handle `member_public_id` if needed

### Templates
**File:** `templates/element/gatherings/staffTab.php`
- [ ] JavaScript - Send `member_public_id` and `gathering_public_id` to token endpoint
- [ ] JavaScript - Handle `public_id` from autocomplete
- [ ] Pass `gathering->public_id` to JavaScript

## Awards Plugin

### RecommendationsController
**File:** `plugins/Awards/src/Controller/RecommendationsController.php`
- [ ] `add()` - Accept `member_public_id` from autocomplete
- [ ] `edit()` - Handle `member_public_id` if needed

### AwardsController
**File:** `plugins/Awards/src/Controller/AwardsController.php`
- [ ] Any member lookup/autocomplete usage

### Templates
**File:** `plugins/Awards/templates/Recommendations/add.php`
- [ ] Autocomplete field - Use `member_public_id`
- [ ] Handle autocomplete returning `public_id`

**File:** `plugins/Awards/templates/Recommendations/edit.php`
- [ ] Autocomplete field - Use `member_public_id`

## Officers Plugin

### OfficersController
**File:** `plugins/Officers/src/Controller/OfficersController.php`
- [ ] `add()` - Accept `member_public_id` from autocomplete
- [ ] `edit()` - Handle `member_public_id`

### Templates
**File:** `plugins/Officers/templates/Officers/add.php`
- [ ] Autocomplete field - Use `member_public_id`

**File:** `plugins/Officers/templates/Officers/edit.php`
- [ ] Autocomplete field - Use `member_public_id`

## Activities Plugin

### ActivitiesController
**File:** `plugins/Activities/src/Controller/ActivitiesController.php`
- [ ] Any member lookup/autocomplete usage

### ActivityParticipantsController (if exists)
**File:** `plugins/Activities/src/Controller/ActivityParticipantsController.php`
- [ ] `add()` - Accept `member_public_id` from autocomplete
- [ ] Any member assignments

### Templates
- [ ] Check all templates for member autocomplete usage

## Common Patterns to Update

### Pattern 1: Autocomplete Response
```php
// BEFORE
$results[] = [
    'value' => $member->id,
    'label' => $member->sca_name,
];

// AFTER
$results[] = [
    'value' => $member->public_id,
    'label' => $member->sca_name,
];
```

### Pattern 2: Autocomplete Field in Templates
```php
// BEFORE
<?= $this->Form->control('member_id', [
    'type' => 'text',
    'data-autocomplete-url' => $this->Url->build(['controller' => 'Members', 'action' => 'autoComplete']),
]) ?>

// AFTER
<?= $this->Form->control('member_public_id', [
    'type' => 'text',
    'data-autocomplete-url' => $this->Url->build(['controller' => 'Members', 'action' => 'autoComplete']),
]) ?>
```

### Pattern 3: Controller Handling Autocomplete Data
```php
// BEFORE
$memberId = $this->request->getData('member_id');
$member = $this->Members->get($memberId);

// AFTER
$memberPublicId = $this->request->getData('member_public_id');
if ($memberPublicId) {
    $member = $this->Members->getByPublicId($memberPublicId);
}
```

### Pattern 4: JavaScript Autocomplete Handler
```javascript
// BEFORE
autocompleteContainer.addEventListener('autocomplete.change', function(event) {
    const memberId = event.detail.value;
    // Use memberId...
});

// AFTER
autocompleteContainer.addEventListener('autocomplete.change', function(event) {
    const memberPublicId = event.detail.value;
    // Use memberPublicId...
});
```

## Database Changes

### Tables Needing public_id Column
- [x] members
- [x] gatherings
- [ ] awards (if needed)
- [ ] recommendations (if needed)
- [ ] activities (if needed)

## Testing Checklist

### Core
- [ ] Gathering staff autocomplete works with public_id
- [ ] Gathering staff contact info token works with public_ids
- [ ] New gathering staff members save correctly

### Awards Plugin
- [ ] Recommendation add works with member public_id
- [ ] Recommendation edit works with member public_id
- [ ] Award assignment works (if applicable)

### Officers Plugin
- [ ] Officer assignment works with member public_id
- [ ] Officer edit works with member public_id

### Activities Plugin
- [ ] Activity participant assignment works (if applicable)
- [ ] Activity leader assignment works (if applicable)

## Migration Order

1. **Core Application First**
   - Add PublicIdBehavior to MembersTable and GatheringsTable
   - Update MembersController autoComplete
   - Update GatheringStaffController
   - Update gathering staffTab template
   - Test thoroughly

2. **Awards Plugin**
   - Update RecommendationsController
   - Update Awards templates
   - Test award recommendations

3. **Officers Plugin**
   - Update OfficersController
   - Update Officers templates
   - Test officer assignments

4. **Activities Plugin**
   - Update ActivitiesController
   - Update Activities templates
   - Test activity participation

## Security Verification

For each updated feature:
- [ ] Verify no internal IDs in browser DevTools Network tab
- [ ] Verify no internal IDs in page source
- [ ] Verify no internal IDs in JavaScript variables
- [ ] Verify autocomplete returns only public_ids
- [ ] Verify database still uses internal IDs for foreign keys

## Documentation Updates

- [ ] Update API documentation for autocomplete endpoints
- [ ] Update developer guide for autocomplete pattern
- [ ] Add examples of public_id usage to coding standards
- [ ] Document when to use public_id vs internal id

---

**Status:** Planning complete, ready for systematic implementation
**Approach:** Update core first, then plugins one at a time
**Testing:** Test each component before moving to next
