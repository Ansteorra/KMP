# Effective Reports-To with can_skip_report Support

## Overview

The KMP Officers plugin includes sophisticated support for handling reporting hierarchies with the `can_skip_report` flag. This feature allows offices to "skip" up the reporting chain when the direct reporting office has no current officer assigned.

## The Problem

Consider this organizational hierarchy:

```
Society
  └─ Kingdom Officer (Office A)
      └─ Regional Officer (Office B) [can_skip_report = true]
          └─ Branch Officer (Office C)
```

**Scenario:** 
- Branch Officer (C) is assigned and active
- Regional Officer (B) position is **vacant** (no current officer)
- Regional Office (B) has `can_skip_report = true`

**Challenge:** Who should Branch Officer (C) actually report to?

**Answer:** Branch Officer should skip the vacant Regional Office and report directly to Kingdom Officer (A).

## Solution Architecture

### 1. Standard Association: `reports_to_currently`

The standard `reports_to_currently` association provides a simple lookup:

```php
// Simple association - only looks at direct reporting relationship
$officer = $officersTable->get($officerId, [
    'contain' => ['ReportsToCurrently']
]);

$directReports = $officer->reports_to_currently;
// Returns: [] (empty, because Regional Office B has no officer)
```

**Limitation:** This doesn't handle the skip logic when the office is vacant.

### 2. Smart Resolution: `effective_reports_to_currently`

The new virtual property handles recursive hierarchy traversal:

```php
// Smart resolution - traverses hierarchy with skip logic
$officer = $officersTable->get($officerId, [
    'contain' => ['Offices', 'ReportsToOffices']
]);

$effectiveReports = $officer->effective_reports_to_currently;
// Returns: [Kingdom Officer from Office A]
// Automatically skipped vacant Office B because can_skip_report = true
```

### 3. Table-Level Method: `findEffectiveReportsTo()`

For programmatic access and custom queries:

```php
$officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');

$officer = $officersTable->get($officerId, [
    'contain' => ['Offices', 'ReportsToOffices', 'ReportsToBranches']
]);

$effectiveOfficers = $officersTable->findEffectiveReportsTo($officer);

foreach ($effectiveOfficers as $reportingOfficer) {
    echo "Effective supervisor: {$reportingOfficer->member->sca_name}\n";
    echo "Office: {$reportingOfficer->office->name}\n";
}
```

## Algorithm Logic

The `findEffectiveReportsTo()` method implements a dual-hierarchy traversal algorithm:

### Dual Hierarchy Traversal

The method traverses TWO hierarchies simultaneously:
1. **Office Hierarchy**: Using `reports_to_id` (e.g., Local → Regional → Kingdom)
2. **Branch Hierarchy**: Using `parent_id` (e.g., Barony → Region → Kingdom)

### Algorithm Steps

```
1. Base Case: Check if officer has reports_to_office_id and reports_to_branch_id
   └─ NO → Return [] (top level, reports to Society)
   
2. Circular Reference Check: Has this office+branch been visited?
   └─ YES → Return [] (prevent infinite loops)
   └─ NO → Add to visited list and continue
   
3. Exact Match: Look for current officers in EXACT office_id + branch_id
   └─ FOUND → Return those officers (success!)
   └─ NOT FOUND → Continue to step 4
   
4. Office Vacant: Load the reporting office and branch
   └─ Check if office has reports_to_id
      └─ NO → Return [] (reached top of office hierarchy)
   └─ Check if branch has parent_id
      └─ NO → Return [] (reached top of branch hierarchy)
   
5. Traverse Up Both Hierarchies:
   └─ Move UP office hierarchy: next_office_id = current_office.reports_to_id
   └─ Move UP branch hierarchy: next_branch_id = current_branch.parent_id
   └─ Create temp officer with reports_to_office_id = next_office_id
   └─ and reports_to_branch_id = next_branch_id
   └─ Recursively call findEffectiveReportsTo() (back to step 1)
```

### Example: Bjornsborg Local Webminister

**Hierarchy Structure:**
- **Offices**: Local Webminister (21) → Regional Webminister (70) → Kingdom Webminister (18)
- **Branches**: Bjornsborg (41) → Southern Region (13) → Ansteorra (2)

**Execution Trace:**
```
Call 1: office_id=70, branch_id=13 (Regional in Southern Region)
  → Exact match query: No officers found (VACANT)
  → Load office 70: reports_to_id=18 (Kingdom Webminister)
  → Load branch 13: parent_id=2 (Ansteorra)
  → Recurse with: office_id=18, branch_id=2

Call 2: office_id=18, branch_id=2 (Kingdom in Ansteorra)
  → Exact match query: Found 1 officer (Asa In Blinda)
  → Return [Asa In Blinda]
```

**Result:** Bjornsborg officer successfully reports to Kingdom officer, skipping the vacant Regional position.

### Important Notes

1. **Vacant Offices Always Skip**: The algorithm always traverses up when an office is vacant, regardless of the `can_skip_report` flag. The flag is only relevant when officers ARE present.

2. **Dual Traversal**: Both hierarchies must move up together. You cannot skip office levels without also moving up branch levels.

3. **Exact Matching**: The query always uses BOTH office_id AND branch_id. This ensures officers only report within their proper branch hierarchy.
```

## Usage Examples

### Example 1: Report Submission Workflow

```php
/**
 * Determine who should receive a branch officer's report
 */
public function submitReport($officerId, $reportData)
{
    $officer = $this->Officers->get($officerId, [
        'contain' => ['Offices', 'ReportsToOffices', 'Members']
    ]);
    
    // Get effective reporting officers (handles skip logic)
    $recipients = $officer->effective_reports_to_currently;
    
    if (empty($recipients)) {
        // Top-level position, report goes to society records
        $this->sendToSocietyArchive($reportData);
    } else {
        // Send to actual supervisors
        foreach ($recipients as $supervisor) {
            $this->sendReportNotification($supervisor, $reportData);
        }
    }
}
```

### Example 2: Notification System

```php
/**
 * Notify supervisors of officer activity
 */
public function notifySupervisors($officerId, $message)
{
    $officer = $this->Officers->get($officerId, [
        'contain' => ['Offices', 'ReportsToOffices']
    ]);
    
    $supervisors = $officer->effective_reports_to_currently;
    
    foreach ($supervisors as $supervisor) {
        $email = $supervisor->email_address ?? $supervisor->member->email;
        
        $this->sendEmail([
            'to' => $email,
            'subject' => "Notification from {$officer->member->sca_name}",
            'message' => $message
        ]);
    }
}
```

### Example 3: Organizational Chart Display

```php
/**
 * Build hierarchical org chart data
 */
public function getOrgChartData($branchId)
{
    $officers = $this->Officers->find('current')
        ->where(['branch_id' => $branchId])
        ->contain(['Members', 'Offices', 'ReportsToOffices'])
        ->all();
    
    $chartData = [];
    
    foreach ($officers as $officer) {
        $effectiveReports = $officer->effective_reports_to_currently;
        
        $chartData[] = [
            'id' => $officer->id,
            'name' => $officer->member->sca_name,
            'office' => $officer->office->name,
            'reports_to_ids' => array_map(function($o) { 
                return $o->id; 
            }, $effectiveReports),
            'is_top_level' => empty($effectiveReports)
        ];
    }
    
    return $chartData;
}
```

### Example 4: Permission Validation

```php
/**
 * Check if user can approve actions for an officer
 */
public function canApproveFor($currentUserId, $officerId)
{
    $officer = $this->Officers->find()
        ->where(['id' => $officerId])
        ->contain(['Offices', 'ReportsToOffices'])
        ->first();
    
    // Get effective supervisors
    $supervisors = $officer->effective_reports_to_currently;
    
    // Check if current user is one of the effective supervisors
    foreach ($supervisors as $supervisor) {
        if ($supervisor->member_id === $currentUserId) {
            return true;
        }
    }
    
    return false;
}
```

## Edge Cases Handled

### 1. Multiple Vacant Levels

```
Society
  └─ Kingdom Officer (vacant, can_skip_report = true)
      └─ Regional Officer (vacant, can_skip_report = true)
          └─ Branch Officer (active)
```

**Result:** Branch Officer effectively reports to Society (empty array).

### 2. Cannot Skip Vacant Office

```
Society
  └─ Kingdom Officer (active)
      └─ Regional Officer (vacant, can_skip_report = false)
          └─ Branch Officer (active)
```

**Result:** Branch Officer has no effective reporting officer (empty array) because Regional Office cannot be skipped.

### 3. Circular Reference Prevention

```
Office A reports_to Office B
Office B reports_to Office C
Office C reports_to Office A  (circular!)
```

**Result:** Algorithm tracks visited offices and returns empty array to prevent infinite loop.

### 4. Cross-Branch Reporting

```
Branch 1 Officer → reports to → Kingdom Officer (Branch: Kingdom)
```

**Result:** Algorithm respects branch boundaries and properly resolves cross-branch reporting relationships.

## Performance Considerations

### Database Queries

- **Direct Association (`reports_to_currently`)**: 0-1 queries (association loaded)
- **Effective Resolution (`effective_reports_to_currently`)**: 1-3 queries depending on hierarchy depth

### Optimization Strategies

1. **Eager Loading**: Always load necessary associations
   ```php
   $officer = $officersTable->get($id, [
       'contain' => [
           'Offices',
           'ReportsToOffices',
           'ReportsToBranches'
       ]
   ]);
   ```

2. **Caching Results**: Cache effective reports for frequently accessed officers
   ```php
   $cacheKey = "effective_reports_{$officerId}";
   $effective = Cache::remember($cacheKey, function() use ($officer) {
       return $officer->effective_reports_to_currently;
   }, 'officers');
   ```

3. **Batch Processing**: When processing multiple officers, load offices in bulk
   ```php
   $officesTable->find()->where(['id IN' => $officeIds])->all();
   ```

## Testing Scenarios

### Test 1: Direct Reporting (No Skip Needed)

```php
// Setup
$kingdomOfficer = createOfficer(['office_id' => 1, 'branch_id' => 1]);
$branchOfficer = createOfficer([
    'office_id' => 2, 
    'branch_id' => 2,
    'reports_to_office_id' => 1,
    'reports_to_branch_id' => 1
]);

// Test
$effective = $branchOfficer->effective_reports_to_currently;

// Assert
assertEquals(1, count($effective));
assertEquals($kingdomOfficer->id, $effective[0]->id);
```

### Test 2: Skip Vacant Office

```php
// Setup
$kingdomOfficer = createOfficer(['office_id' => 1, 'branch_id' => 1]);
$regionalOffice = createOffice([
    'id' => 2,
    'reports_to_id' => 1,
    'can_skip_report' => true
]); // No officer assigned!
$branchOfficer = createOfficer([
    'office_id' => 3,
    'branch_id' => 3,
    'reports_to_office_id' => 2,
    'reports_to_branch_id' => 1
]);

// Test
$effective = $branchOfficer->effective_reports_to_currently;

// Assert
assertEquals(1, count($effective));
assertEquals($kingdomOfficer->id, $effective[0]->id);
```

### Test 3: Cannot Skip Vacant Office

```php
// Setup
$regionalOffice = createOffice([
    'id' => 2,
    'reports_to_id' => 1,
    'can_skip_report' => false
]); // No officer assigned!
$branchOfficer = createOfficer([
    'office_id' => 3,
    'reports_to_office_id' => 2
]);

// Test
$effective = $branchOfficer->effective_reports_to_currently;

// Assert
assertEquals(0, count($effective)); // Cannot skip, no officers found
```

### Test 4: Cross-Branch Kingdom Officer Reporting

```php
// Setup
// Bjornsborg is in Southern Region, but Kingdom officers are in Ansteorra branch
$kingdomOfficer = createOfficer([
    'office_id' => 18, // Kingdom Webminister
    'branch_id' => 2,  // Ansteorra Kingdom
    'status' => 'CURRENT'
]);
$branchOfficer = createOfficer([
    'office_id' => 20, // Local Webminister
    'branch_id' => 41, // Bjornsborg
    'reports_to_office_id' => 18, // Kingdom Webminister
    'reports_to_branch_id' => 13, // Southern Region (intermediate structure)
    'status' => 'CURRENT'
]);

// Test
$effective = $branchOfficer->effective_reports_to_currently;

// Assert
assertEquals(1, count($effective)); // Found Kingdom officer despite branch mismatch
assertEquals($kingdomOfficer->id, $effective[0]->id);
```

## Integration with reports_to_list

The `reports_to_list` virtual property has been **automatically updated** to use `effective_reports_to_currently`, which means it now handles the skip logic transparently!

### Before (Old Behavior)

```php
$officer = $officersTable->get($branchOfficerId, [
    'contain' => ['ReportsToCurrently']
]);

// If Regional Office was vacant, this would show "Not Filled"
echo $officer->reports_to_list;  // "Not Filled"
```

### After (New Behavior with Automatic Skip)

```php
$officer = $officersTable->get($branchOfficerId, [
    'contain' => ['Offices', 'ReportsToOffices']
]);

// Now automatically skips vacant Regional Office and shows Kingdom Officer
echo $officer->reports_to_list;  // "<a href='mailto:...'>Kingdom Officer Name</a>"
```

### Key Benefits

1. **No Code Changes Required**: Existing code using `reports_to_list` automatically gets skip logic
2. **Accurate Display**: "Not Filled" only shows when there truly are no reporting officers
3. **Better UX**: Users see who they actually should contact, not vacant positions
4. **Consistent Behavior**: All reporting displays now handle skip logic uniformly

### What Changed Internally

The `_getReportsToList()` method now:
- Uses `effective_reports_to_currently` (with skip logic) instead of `reports_to_currently` (direct only)
- Automatically traverses vacant offices when `can_skip_report = true`
- Returns "Not Filled" only when the chain is truly broken (cannot skip or reached top)

## Migration from reports_to_currently

If you have existing code using `reports_to_currently` directly, you can migrate:

```php
// Old code (may return empty for vacant offices)
$reports = $officer->reports_to_currently;

// New code (handles skip logic)
$reports = $officer->effective_reports_to_currently;

// Comparison of both
$direct = $officer->reports_to_currently;  // Direct association only
$effective = $officer->effective_reports_to_currently;  // With skip logic

if (count($direct) != count($effective)) {
    // Skip logic was applied
    Log::info("Officer {$officer->id} skip logic: direct={count($direct)}, effective={count($effective)}");
}
```

### When to Use Each Property

| Property | Use Case | Skip Logic |
|----------|----------|------------|
| `reports_to_currently` | Administrative debugging, direct relationship inspection | No |
| `effective_reports_to_currently` | Report submission, notifications, org charts | Yes |
| `reports_to_list` | User-facing displays, email lists, contact info | Yes (automatic) |

## Real-World Example: reports_to_list Integration

Here's a complete example showing how `reports_to_list` automatically handles the skip logic:

```php
// Scenario: Branch Officer reporting to vacant Regional Office (can skip)
//
// Hierarchy:
//   Kingdom Officer (FILLED) ← reports_to_id
//     └─ Regional Office (VACANT, can_skip_report = true) ← reports_to_id  
//         └─ Branch Officer (FILLED) ← our officer

// Load the branch officer
$branchOfficer = $officersTable->get($branchOfficerId, [
    'contain' => [
        'Offices',
        'ReportsToOffices',
        'Members'
    ]
]);

// OLD BEHAVIOR (before effective_reports_to_currently integration):
// Would show "Not Filled" because Regional Office is vacant
echo $branchOfficer->reports_to_list;  
// Output: "Not Filled" ❌ (incorrect - there IS a Kingdom Officer)

// NEW BEHAVIOR (with automatic skip logic):
// Automatically skips vacant Regional Office and shows Kingdom Officer
echo $branchOfficer->reports_to_list;
// Output: "<a href='mailto:kingdom@example.com'>Sir Kingdom Officer</a>" ✓

// You can also see what happened:
$direct = $branchOfficer->reports_to_currently;  // []
$effective = $branchOfficer->effective_reports_to_currently;  // [Kingdom Officer]

echo "Direct reports: " . count($direct) . " (vacant Regional Office)\n";
echo "Effective reports: " . count($effective) . " (skipped to Kingdom Officer)\n";
```

### Display in Templates

In your view templates, you can now use `reports_to_list` with confidence:

```php
<!-- templates/Officers/view.php -->
<div class="officer-details">
    <h3><?= h($officer->member->sca_name) ?></h3>
    <p><strong>Office:</strong> <?= h($officer->office->name) ?></p>
    <p><strong>Reports To:</strong> <?= $officer->reports_to_list ?></p>
    <!-- This will show the correct supervisor even if intermediate offices are vacant -->
</div>
```

### Email Notification Example

```php
// Send report notification to effective supervisors
public function sendReportNotification($officerId, $reportContent)
{
    $officer = $this->Officers->get($officerId, [
        'contain' => ['Offices', 'ReportsToOffices', 'Members']
    ]);
    
    // Get HTML formatted list for email body
    $supervisorList = $officer->reports_to_list;
    
    // Get actual officer objects for sending individual emails
    $supervisors = $officer->effective_reports_to_currently;
    
    foreach ($supervisors as $supervisor) {
        $this->sendEmail([
            'to' => $supervisor->email_address,
            'subject' => "New Report from {$officer->member->sca_name}",
            'body' => "
                <p>You have received a new report.</p>
                <p><strong>From:</strong> {$officer->member->sca_name} ({$officer->office->name})</p>
                <p><strong>This officer reports to:</strong> {$supervisorList}</p>
                <hr>
                {$reportContent}
            "
        ]);
    }
    
    // If no supervisors (top-level or broken chain)
    if (empty($supervisors)) {
        Log::info("Report from {$officer->id} has no supervisors - reports to: {$supervisorList}");
    }
}
```

## Best Practices

1. **Use reports_to_list for user-facing displays** - it automatically handles skip logic
2. **Use effective_reports_to_currently for programmatic access** - when you need the actual officer objects
3. **Use reports_to_currently only for administrative/debugging** - viewing direct relationships without skip logic
4. **Always load required associations** (`Offices`, `ReportsToOffices`) before accessing effective properties
5. **Cache results** for frequently accessed officers
6. **Monitor performance** if calling in loops - consider batch operations
7. **Test edge cases** (vacant offices, circular refs, top-level positions)

## Conclusion

The `effective_reports_to_currently` virtual property and `findEffectiveReportsTo()` method provide a robust solution for handling vacant reporting offices with `can_skip_report` enabled. This ensures that reports and notifications always reach the appropriate supervisor, even when intermediate offices are unfilled.
