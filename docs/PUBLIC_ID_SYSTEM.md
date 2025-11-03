# Public ID System - Architecture and Implementation

## Overview

The KMP application implements a **Public ID system** to replace the exposure of internal database IDs to client-side code. This is a critical security and privacy enhancement that prevents information leakage and enumeration attacks.

## The Problem: Exposing Internal IDs

### Security Issues

Exposing sequential database IDs to clients creates several problems:

1. **Information Leakage**
   ```
   User ID: 1, 2, 3, 4, 5...
   → Attacker knows: "There are 5 users"
   → Attacker knows: "User #3 was created between #2 and #4"
   ```

2. **Enumeration Attacks**
   ```javascript
   // Attacker can iterate through all records
   for (let id = 1; id < 10000; id++) {
       fetch(`/members/view/${id}`)
   }
   ```

3. **Privacy Violations**
   ```
   Award ID: 1000 → First award
   Award ID: 1050 → 50 awards given
   Award ID: 900 → Deleted (gap reveals deletion)
   ```

4. **Predictability**
   ```
   Know member #123 exists → Can guess #122 and #124 probably exist
   ```

## The Solution: Public IDs

### What Are Public IDs?

Non-sequential, unpredictable identifiers that are safe to expose to clients:

```
Internal ID: 123 (sequential, predictable)
Public ID: a7fK9mP2 (random, unpredictable)
```

### Characteristics

- **Format**: 8-character alphanumeric (Base62: a-z, A-Z, 2-9)
- **Uniqueness**: 62^8 = 218 trillion possible combinations
- **Collision Probability**: Negligible for < 1M records per table
- **Performance**: Indexed for O(1) lookups (same as primary keys)
- **Human-Readable**: Excludes confusing characters (0/O, 1/l/I)

### Example

```php
// Database record
id: 123
public_id: 'a7fK9mP2'
sca_name: 'John of Example'

// Client sees
<a href="/members/view/a7fK9mP2">John of Example</a>

// Client never sees
id: 123
```

## Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────┐
│                   Client (Browser)                      │
│  - Uses public_id in URLs, AJAX calls, autocomplete    │
│  - Never sees internal database IDs                    │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ public_id: 'a7fK9mP2'
                     ▼
┌─────────────────────────────────────────────────────────┐
│              Controller Layer                           │
│  - Accepts public_id from requests                     │
│  - Uses PublicIdBehavior to lookup by public_id        │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ table->getByPublicId('a7fK9mP2')
                     ▼
┌─────────────────────────────────────────────────────────┐
│           PublicIdBehavior (Trait/Behavior)            │
│  - Auto-generates public_id on entity creation         │
│  - Provides finder methods                             │
│  - Validates uniqueness                                │
└────────────────────┬────────────────────────────────────┘
                     │
                     │ WHERE public_id = 'a7fK9mP2'
                     ▼
┌─────────────────────────────────────────────────────────┐
│                  Database Layer                         │
│  - Stores both id and public_id                        │
│  - public_id has unique index for performance          │
│  - Internal relations still use id (foreign keys)      │
└─────────────────────────────────────────────────────────┘
```

### PublicIdBehavior

The core component that provides public ID functionality to any table:

```php
// app/src/Model/Behavior/PublicIdBehavior.php

class PublicIdBehavior extends Behavior
{
    // Auto-generates public_id on entity creation
    public function beforeSave($event, $entity, $options);
    
    // Find entity by public_id
    public function findByPublicId($query, $publicId);
    
    // Get entity by public_id (like Table::get())
    public function getByPublicId($publicId, $options = []);
    
    // Generate unique random public_id
    public function generatePublicId();
}
```

### Usage in Tables

```php
// app/src/Model/Table/MembersTable.php

class MembersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        // Add PublicId behavior
        $this->addBehavior('PublicId');
        
        // Optional: Configure
        $this->addBehavior('PublicId', [
            'field' => 'public_id',  // default
            'length' => 8,            // default
        ]);
    }
}
```

## Implementation

### Step 1: Migration

Add `public_id` column to all entity tables:

```bash
bin/cake migrations migrate
```

This adds to tables:
- members
- branches
- roles
- gatherings
- gathering_staff
- authorizations
- awards
- recommendations
- activities
- notes

Each table gets:
- `public_id VARCHAR(8)` column
- Unique index: `idx_<table>_public_id`

### Step 2: Generate Public IDs

Populate public_id for existing records:

```bash
# Generate for all tables
bin/cake generate_public_ids --all

# Or generate for specific table
bin/cake generate_public_ids members

# Dry run to see what would happen
bin/cake generate_public_ids --all --dry-run
```

### Step 3: Add Behavior to Tables

Add `PublicIdBehavior` to each Table class:

```php
public function initialize(array $config): void
{
    parent::initialize($config);
    $this->addBehavior('PublicId');
}
```

### Step 4: Update Controllers

Change controllers to accept and use public_id:

**Before:**
```php
public function view($id = null)
{
    $member = $this->Members->get($id);
}
```

**After:**
```php
public function view($publicId = null)
{
    $member = $this->Members->getByPublicId($publicId);
}
```

### Step 5: Update Routes

Change route parameter names:

**Before:**
```php
$routes->connect('/members/view/:id', [
    'controller' => 'Members',
    'action' => 'view'
], [
    'pass' => ['id'],
    'id' => '\d+' // numeric only
]);
```

**After:**
```php
$routes->connect('/members/view/:public_id', [
    'controller' => 'Members',
    'action' => 'view'
], [
    'pass' => ['public_id'],
    'public_id' => '[a-zA-Z0-9]{8}' // alphanumeric, 8 chars
]);
```

### Step 6: Update Templates

Change URLs to use public_id:

**Before:**
```php
<?= $this->Html->link('View', ['action' => 'view', $member->id]) ?>
```

**After:**
```php
<?= $this->Html->link('View', ['action' => 'view', $member->public_id]) ?>
```

### Step 7: Update JavaScript/Autocomplete

Change AJAX calls and autocomplete to use public_id:

**Before:**
```javascript
fetch('/members/get/' + memberId)
```

**After:**
```javascript
fetch('/members/get/' + publicId)
```

**Autocomplete:**
```php
// Controller
public function autoComplete()
{
    $members = $this->Members->find()
        ->select(['public_id', 'sca_name']) // Use public_id instead of id
        ->toArray();
    
    return $this->response->withType('application/json')
        ->withStringBody(json_encode($members));
}
```

## Database Schema

### Before

```sql
CREATE TABLE members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sca_name VARCHAR(255),
    ...
);

-- Client sees: /members/view/123
```

### After

```sql
CREATE TABLE members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    public_id VARCHAR(8) UNIQUE NOT NULL,
    sca_name VARCHAR(255),
    ...
    
    INDEX idx_members_public_id (public_id)
);

-- Client sees: /members/view/a7fK9mP2
-- Internal: Relations still use id as foreign key
```

### Foreign Keys Stay the Same

```sql
CREATE TABLE gathering_staff (
    id INT PRIMARY KEY,
    public_id VARCHAR(8) UNIQUE,
    gathering_id INT,  -- Still uses internal ID
    member_id INT,     -- Still uses internal ID
    
    FOREIGN KEY (gathering_id) REFERENCES gatherings(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
);
```

**Why?**
- Foreign keys are internal database relationships
- Never exposed to client
- Changing them would require massive refactoring
- Public IDs are only for client-facing references

## API Changes

### Finder Methods

```php
// Find by public_id
$member = $this->Members->find('publicId', publicId: 'a7fK9mP2')->first();

// Or use convenience method
$member = $this->Members->getByPublicId('a7fK9mP2');

// Still works with internal ID for internal use
$member = $this->Members->get(123);
```

### Controller Examples

```php
// MembersController::view()
public function view($publicId = null)
{
    $member = $this->Members->getByPublicId($publicId);
    $this->set(compact('member'));
}

// MembersController::edit()
public function edit($publicId = null)
{
    $member = $this->Members->getByPublicId($publicId);
    
    if ($this->request->is(['patch', 'post', 'put'])) {
        // Internal ID still used for updates
        $member = $this->Members->patchEntity($member, $this->request->getData());
        $this->Members->save($member);
    }
}

// AutocompleteController
public function members()
{
    $members = $this->Members->find()
        ->select(['public_id', 'sca_name', 'email_address'])
        ->toArray();
    
    // Returns: [{public_id: 'a7fK9mP2', sca_name: 'John'}, ...]
}
```

### JavaScript Examples

```javascript
// Autocomplete result
{
    value: 'a7fK9mP2',        // public_id
    label: 'John of Example'   // display text
}

// AJAX call
fetch('/gathering-staff/generate-contact-info-token', {
    body: JSON.stringify({
        member_public_id: 'a7fK9mP2',  // Use public_id
        gathering_public_id: 'b3nM8kQ5'
    })
})
```

## Security Benefits

### Before: Vulnerable

```
❌ Sequential IDs exposed
❌ Information leakage (count, order, deletions)
❌ Enumeration attacks possible
❌ Predictable identifiers
```

### After: Secure

```
✅ Random, non-sequential IDs
✅ No information leakage
✅ Enumeration attacks prevented
✅ Unpredictable identifiers
✅ Same performance (indexed lookups)
```

### Attack Prevention

**Attack 1: Record Enumeration**
```javascript
// Before: Attacker can iterate all users
for (let id = 1; id < 10000; id++) {
    fetch(`/members/view/${id}`)
}

// After: Attacker doesn't know any valid public_ids
fetch(`/members/view/a7fK9mP2`) // Only works if they know this specific ID
```

**Attack 2: Information Gathering**
```
Before: 
- User #5 exists → Probably 5 users total
- User #100 exists → At least 100 users (or deletions)
- Gap between #50 and #75 → Deleted users

After:
- User a7fK9mP2 exists → No information about total count
- No way to determine creation order
- No way to detect deletions
```

**Attack 3: Guessing Related Records**
```
Before:
- Event #50 has staff members → Try IDs 1-100 to find staff
- Member #123 → Try #122, #124 for related members

After:
- Event a7fK9mP2 → No way to guess related IDs
- All lookups require knowing exact public_id
```

## Performance

### Lookup Performance

```sql
-- Both are O(1) with indexes
SELECT * FROM members WHERE id = 123;              -- Primary key
SELECT * FROM members WHERE public_id = 'a7fK9mP2'; -- Unique index

-- Both use index scan, same performance
```

### Storage Overhead

```
Per record: 8 bytes (VARCHAR(8))
Per table with 50K records: 400 KB
Total for all tables: ~5 MB

Negligible compared to benefits
```

### Index Overhead

```
Unique index on VARCHAR(8): Similar to INT index
Slightly larger, but still very fast for lookups
```

## Migration Strategy

### Phase 1: Add Columns (Zero Downtime)
1. Run migration to add `public_id` columns
2. Generate public_ids for existing records
3. Application still uses `id` - no breakage

### Phase 2: Update Code (Gradual)
1. Add `PublicIdBehavior` to tables
2. Update one controller at a time
3. Test each controller before moving to next
4. Old URLs with numeric IDs can coexist temporarily

### Phase 3: Update Routes (Coordinated)
1. Update routes to accept public_id
2. Update all templates to use public_id
3. Update JavaScript/AJAX to use public_id

### Phase 4: Deprecate ID URLs (Future)
1. Add redirects from old numeric ID URLs to public_id URLs
2. Eventually remove numeric ID support from routes

## Testing

### Unit Tests

```php
// Test public_id generation
public function testPublicIdGeneration()
{
    $member = $this->Members->newEntity(['sca_name' => 'Test']);
    $this->Members->save($member);
    
    $this->assertNotNull($member->public_id);
    $this->assertEquals(8, strlen($member->public_id));
    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $member->public_id);
}

// Test uniqueness
public function testPublicIdUniqueness()
{
    $member1 = $this->Members->newEntity(['sca_name' => 'Test1']);
    $this->Members->save($member1);
    
    $member2 = $this->Members->newEntity(['sca_name' => 'Test2']);
    $this->Members->save($member2);
    
    $this->assertNotEquals($member1->public_id, $member2->public_id);
}

// Test finder
public function testFindByPublicId()
{
    $member = $this->Members->newEntity(['sca_name' => 'Test']);
    $this->Members->save($member);
    
    $found = $this->Members->getByPublicId($member->public_id);
    $this->assertEquals($member->id, $found->id);
}
```

### Integration Tests

```php
// Test controller with public_id
public function testViewWithPublicId()
{
    $member = $this->createMember();
    $this->get('/members/view/' . $member->public_id);
    $this->assertResponseOk();
    $this->assertResponseContains($member->sca_name);
}

// Test 404 for invalid public_id
public function testViewWithInvalidPublicId()
{
    $this->get('/members/view/invalid1');
    $this->assertResponseCode(404);
}
```

## Files Modified

### Created
- `app/src/Model/Behavior/PublicIdBehavior.php`
- `app/src/Command/GeneratePublicIdsCommand.php`
- `app/config/Migrations/20251103140000_AddPublicIdToAllTables.php`

### To Be Modified
- All Table classes (add behavior)
- All Controllers (use public_id instead of id)
- All templates (use public_id in URLs)
- All JavaScript (use public_id in AJAX)
- All routes (accept public_id parameter)

## Conclusion

The Public ID system provides a robust, performant, and secure way to reference entities in client-facing code while keeping internal database IDs private. This is a best-practice architectural pattern that prevents information leakage and enumeration attacks with minimal performance overhead.

**Status**: Ready for implementation across the application

**Priority**: High - Security and privacy enhancement

**Impact**: Application-wide - Requires systematic updates to controllers, templates, and JavaScript