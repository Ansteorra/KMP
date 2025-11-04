# Public ID Implementation - Step-by-Step Guide

## Prerequisites

1. ✅ PublicIdBehavior created
2. ✅ GeneratePublicIdsCommand created  
3. ✅ Migration created for members and gatherings
4. ⏳ Migration needs to be run
5. ⏳ Public IDs need to be generated

## Step 1: Database Setup

### Run Migration
```bash
cd /workspaces/KMP
bin/cake migrations migrate
```

**Expected Output:**
```
Running migrations for default database...
== 20251103140000 AddPublicIdToMembersAndGatherings: migrating
== 20251103140000 AddPublicIdToMembersAndGatherings: migrated (X.XXXXs)
```

### Generate Public IDs
```bash
bin/cake generate_public_ids members gatherings
```

**Expected Output:**
```
Processing table: members
  Found X records without public IDs
  Generated X / X...
Processed X records in members

Processing table: gatherings
  Found Y records without public IDs
  Generated Y / Y...
Processed Y records in gatherings

Total: Processed X+Y records across 2 table(s)
```

### Verify Database
```bash
# Using MySQL client
mysql -u [user] -p KMP_DEV

# Check members table
DESC members;
SELECT id, public_id, sca_name FROM members LIMIT 5;

# Check gatherings table
DESC gatherings;
SELECT id, public_id, name FROM gatherings LIMIT 5;
```

**Expected:**
- Both tables have `public_id` column (VARCHAR(8))
- Both tables have unique index on `public_id`
- All existing records have 8-character alphanumeric public_ids

## Step 2: Add Behavior to Tables

### MembersTable

**File:** `src/Model/Table/MembersTable.php`

```php
public function initialize(array $config): void
{
    parent::initialize($config);
    
    // ...existing code...
    
    // Add PublicId behavior
    $this->addBehavior('PublicId');
}
```

### GatheringsTable

**File:** `src/Model/Table/GatheringsTable.php`

```php
public function initialize(array $config): void
{
    parent::initialize($config);
    
    // ...existing code...
    
    // Add PublicId behavior
    $this->addBehavior('PublicId');
}
```

### Test Behavior Works

```bash
# In CakePHP console
bin/cake console

# Test member lookup by public_id
$member = $this->loadModel('Members')->getByPublicId('a7fK9mP2');
debug($member->sca_name);

# Test gathering lookup by public_id
$gathering = $this->loadModel('Gatherings')->getByPublicId('m3Qr8Nk5');
debug($gathering->name);
```

## Step 3: Update Core Autocomplete

### MembersController::autoComplete()

**File:** `src/Controller/MembersController.php`

**Find this method and update:**

```php
public function autoComplete()
{
    $this->request->allowMethod(['get']);
    
    $term = $this->request->getQuery('term');
    
    $members = $this->Members
        ->find()
        ->select([
            'public_id',  // CHANGED: Was 'id'
            'sca_name',
            'email_address'
        ])
        ->where(['sca_name LIKE' => '%' . $term . '%'])
        ->order(['sca_name' => 'ASC'])
        ->limit(20)
        ->all();
    
    $results = [];
    foreach ($members as $member) {
        $results[] = [
            'value' => $member->public_id,  // CHANGED: Was $member->id
            'label' => $member->sca_name,
            'email' => $member->email_address,
        ];
    }
    
    $this->set('results', $results);
    $this->viewBuilder()->setOption('serialize', 'results');
}
```

### Test Autocomplete

```bash
# Start server if not running
bin/cake server

# In browser or using curl
curl "http://localhost:8080/members/auto-complete?term=admin"
```

**Expected Response:**
```json
{
  "results": [
    {
      "value": "a7fK9mP2",  // public_id, not numeric id
      "label": "Admin von Admin",
      "email": "admin@amp.ansteorra.org"
    }
  ]
}
```

## Step 4: Update Gathering Staff Feature

### Update GatheringStaffController

**File:** `src/Controller/GatheringStaffController.php`

#### Update generateContactInfoToken()

**Find and replace:**

```php
public function generateContactInfoToken()
{
    $this->request->allowMethod(['post']);
    $this->viewBuilder()->setClassName('Json');

    // CHANGED: Accept public_ids instead of ids
    $memberPublicId = $this->request->getData('member_public_id');
    $gatheringPublicId = $this->request->getData('gathering_public_id');
    
    if (!$memberPublicId || !$gatheringPublicId) {
        $this->set('data', ['error' => 'Member public ID and Gathering public ID required']);
        $this->viewBuilder()->setOption('serialize', 'data');
        return;
    }

    try {
        // CHANGED: Lookup by public_id
        $gathering = $this->GatheringStaff->Gatherings->getByPublicId($gatheringPublicId);
        $this->Authorization->authorize($gathering, 'edit');
        
        // CHANGED: Verify member exists by public_id
        $member = $this->GatheringStaff->Members->getByPublicId($memberPublicId);
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        
        // CHANGED: Store public_ids in token
        \Cake\Cache\Cache::write(
            'contact_token_' . $token,
            [
                'member_public_id' => $memberPublicId,
                'gathering_public_id' => $gatheringPublicId,
                'user_id' => $this->request->getAttribute('identity')->getIdentifier(),
                'created' => time(),
            ],
            '+5 minutes'
        );

        $this->set('data', ['token' => $token]);
    } catch (\Cake\Http\Exception\ForbiddenException $e) {
        $this->set('data', ['error' => 'Not authorized']);
    } catch (\Exception $e) {
        $this->set('data', ['error' => 'Unable to generate token']);
    }

    $this->viewBuilder()->setOption('serialize', 'data');
}
```

#### Update getMemberContactInfo()

**Find and replace:**

```php
public function getMemberContactInfo()
{
    $this->request->allowMethod(['get']);
    $this->viewBuilder()->setClassName('Json');

    $token = $this->request->getQuery('token');
    
    if (!$token) {
        $this->set('data', ['error' => 'Token required']);
        $this->viewBuilder()->setOption('serialize', 'data');
        return;
    }

    try {
        $tokenData = \Cake\Cache\Cache::read('contact_token_' . $token);
        
        if (!$tokenData) {
            $this->set('data', ['error' => 'Invalid or expired token']);
            $this->viewBuilder()->setOption('serialize', 'data');
            return;
        }

        if ($tokenData['user_id'] !== $this->request->getAttribute('identity')->getIdentifier()) {
            \Cake\Cache\Cache::delete('contact_token_' . $token);
            $this->set('data', ['error' => 'Token does not belong to current user']);
            $this->viewBuilder()->setOption('serialize', 'data');
            return;
        }

        \Cake\Cache\Cache::delete('contact_token_' . $token);
        
        // CHANGED: Lookup member by public_id from token
        $member = $this->GatheringStaff->Members->getByPublicId($tokenData['member_public_id']);

        $this->set('data', [
            'email' => $member->email_address,
            'phone' => $member->phone_number,
        ]);
    } catch (\Exception $e) {
        $this->set('data', ['error' => 'Unable to retrieve contact information']);
    }

    $this->viewBuilder()->setOption('serialize', 'data');
}
```

#### Update add() method

**Find the part that handles member_id and update:**

```php
public function add($gatheringId = null)
{
    // ...existing code...
    
    if ($this->request->is('post')) {
        $data = $this->request->getData();
        $data['gathering_id'] = $gatheringId;
        
        // CHANGED: Handle public_id from autocomplete
        if (!empty($data['member_public_id'])) {
            $member = $this->GatheringStaff->Members->getByPublicId($data['member_public_id']);
            $data['member_id'] = $member->id;  // Set internal ID for foreign key
            unset($data['member_public_id']);  // Remove public_id from data
        }
        
        // ...rest of existing code...
    }
}
```

### Update staffTab.php Template

**File:** `templates/element/gatherings/staffTab.php`

#### Update JavaScript to use public_ids

**Find the fetchMemberContactInfo function and update:**

```javascript
function fetchMemberContactInfo(memberPublicId) {  // CHANGED: parameter name
    if (!memberPublicId) return;
    
    if (autoFillNotice) autoFillNotice.style.display = 'block';
    
    const gatheringPublicId = '<?= $gathering->public_id ?>';  // CHANGED: Use public_id
    
    // STEP 1: Request token with public_ids
    fetch('<?= $this->Url->build(['controller' => 'GatheringStaff', 'action' => 'generateContactInfoToken']) ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>'
        },
        body: JSON.stringify({
            member_public_id: memberPublicId,  // CHANGED: Use public_id
            gathering_public_id: gatheringPublicId  // CHANGED: Use public_id
        })
    })
    .then(response => response.json())
    .then(tokenResponse => {
        if (tokenResponse.error) {
            console.error('Error generating token:', tokenResponse.error);
            if (autoFillAlert) {
                autoFillAlert.className = 'alert alert-danger';
                autoFillAlert.textContent = 'Error: ' + tokenResponse.error;
                autoFillAlert.style.display = 'block';
            }
            return;
        }
        
        // STEP 2: Use token to fetch contact info
        return fetch('<?= $this->Url->build(['controller' => 'GatheringStaff', 'action' => 'getMemberContactInfo']) ?>?token=' + tokenResponse.token);
    })
    .then(response => response ? response.json() : null)
    .then(data => {
        if (!data) return;
        
        if (data.error) {
            console.error('Error fetching member info:', data.error);
            if (autoFillAlert) {
                autoFillAlert.className = 'alert alert-danger';
                autoFillAlert.textContent = 'Error: ' + data.error;
                autoFillAlert.style.display = 'block';
            }
            return;
        }
        
        // Auto-fill email and phone
        if (data.email && emailInput && !emailInput.value) {
            emailInput.value = data.email;
        }
        if (data.phone && phoneInput && !phoneInput.value) {
            phoneInput.value = data.phone;
        }
        
        // Show success message
        if (autoFillAlert && (data.email || data.phone)) {
            autoFillAlert.className = 'alert alert-success';
            const items = [];
            if (data.email) items.push('Email');
            if (data.phone) items.push('Phone');
            autoFillAlert.innerHTML = '<strong>Contact Info Auto-Filled</strong> - ' + items.join(' and ') + ' copied from AMP member account';
            autoFillAlert.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error fetching member info:', error);
        if (autoFillAlert) {
            autoFillAlert.className = 'alert alert-danger';
            autoFillAlert.textContent = 'Network error - Unable to fetch contact info';
            autoFillAlert.style.display = 'block';
        }
    });
}
```

**Also update the autocomplete event listener:**

```javascript
autocompleteContainer.addEventListener('autocomplete.change', function(event) {
    if (event.detail && event.detail.value) {
        fetchMemberContactInfo(event.detail.value);  // value is now public_id
    }
});
```

**Update hidden field name in form:**

```php
// CHANGED: Use member_public_id instead of member_id
<?= $this->Form->hidden('member_public_id', ['id' => 'add-member-id']) ?>
```

## Step 5: Test End-to-End

### Manual Testing

1. **Start server:**
   ```bash
   bin/cake server
   ```

2. **Login:**
   - Go to http://localhost:8080
   - Login as admin@amp.ansteorra.org / TestPassword

3. **Navigate to gathering:**
   - Go to Gatherings
   - Click on any gathering

4. **Test Add Staff:**
   - Click "Staff" tab
   - Click "Add Staff Member"
   - Type a name in autocomplete
   - **Verify in DevTools Network tab:** Autocomplete returns public_id
   - Select a member
   - **Verify in DevTools Network tab:** Token request uses public_ids
   - **Verify:** Email/phone auto-fill
   - Fill out rest of form
   - Save

5. **Verify in Database:**
   ```sql
   SELECT * FROM gathering_staff ORDER BY id DESC LIMIT 1;
   -- Should show member_id (internal) and gathering_id (internal)
   -- NOT public_ids (those are only for client-side)
   ```

### Browser DevTools Checks

**Network Tab - Autocomplete:**
```
Request: /members/auto-complete?term=admin
Response: 
{
  "results": [{
    "value": "a7fK9mP2",  ✅ Public ID
    "label": "Admin von Admin",
    "email": "admin@amp.ansteorra.org"
  }]
}
```

**Network Tab - Token Generation:**
```
Request: /gathering-staff/generate-contact-info-token
Payload:
{
  "member_public_id": "a7fK9mP2",      ✅ Public ID
  "gathering_public_id": "m3Qr8Nk5"    ✅ Public ID
}
```

**Network Tab - Contact Info:**
```
Request: /gathering-staff/get-member-contact-info?token=abc123...
Response:
{
  "email": "...",
  "phone": "..."
}
```

**Console Tab:**
- No errors
- No warnings about missing IDs

**Elements Tab:**
- Inspect form
- Hidden input should have name="member_public_id"
- Value should be 8-char alphanumeric (public_id)

## Step 6: Update Plugins (Future)

After core is working, follow similar pattern for:
- Awards plugin (recommendations)
- Officers plugin (officer assignments)
- Activities plugin (participant assignments)

See `docs/PUBLIC_ID_UPDATE_TRACKING.md` for complete checklist.

## Troubleshooting

### Issue: "Column 'public_id' not found"
**Solution:** Run migration: `bin/cake migrations migrate`

### Issue: "Public IDs are NULL"
**Solution:** Run generator: `bin/cake generate_public_ids members gatherings`

###Issue: "Method getByPublicId does not exist"
**Solution:** Add `PublicIdBehavior` to table's `initialize()` method

### Issue: Autocomplete returns numeric IDs
**Solution:** Check MembersController::autoComplete() - should select `public_id` not `id`

### Issue: Token validation fails
**Solution:** Verify controller is sending and receiving `public_id` fields, not `id` fields

### Issue: Foreign key constraint error
**Solution:** Make sure controller converts `public_id` to internal `id` before saving:
```php
$member = $this->Members->getByPublicId($publicId);
$data['member_id'] = $member->id;  // Use internal ID for FK
```

## Success Criteria

- ✅ Migration ran successfully
- ✅ Public IDs generated for all existing records
- ✅ New members/gatherings get public_id automatically
- ✅ Autocomplete returns public_ids
- ✅ Token system uses public_ids
- ✅ Contact info auto-fill works
- ✅ Staff members save correctly
- ✅ NO internal IDs visible in browser DevTools
- ✅ Database still uses internal IDs for foreign keys

---

**Next:** After core is working, repeat similar process for Awards, Officers, and Activities plugins.