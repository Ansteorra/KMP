# Critical Design Decisions

**Feature**: Gathering Waiver Tracking System  
**Date**: October 21, 2025  
**Status**: Approved  

This document records all critical design decisions made during the pre-task checklist review. These decisions resolve ambiguities and establish implementation patterns for the feature.

---

## Decision 1: Image Conversion Failure Handling (CHK003)

**Question**: What should happen when image-to-PDF conversion fails?

**Decision**: Show error with diagnostic information, allow retry with same file

**Rationale**:
- Conversion failures often transient (memory issues, file locks)
- User shouldn't have to re-select file from camera/gallery
- Diagnostic messages help troubleshooting

**Implementation**:
```php
try {
    $pdfPath = $this->ImageToPdfService->convert($imagePath);
} catch (ImagickException $e) {
    $this->Flash->error(
        'Failed to convert image to PDF: ' . $e->getMessage() . 
        '. Please try uploading again. If problem persists, try reducing image size or contact support.'
    );
    Log::error('Image conversion failed', [
        'file' => $imagePath,
        'error' => $e->getMessage(),
        'user_id' => $this->Authentication->getIdentity()->getIdentifier()
    ]);
    // Keep form data so user can retry
    return $this->render();
}
```

**Impact**: Improves user experience, reduces support burden with diagnostic info

---

## Decision 2: Polymorphic Entity Types Whitelist (CHK006)

**Question**: What entity_type values should Documents support?

**Decision**: Start with whitelist of 2 types, expand as needed

**Initial Whitelist**:
1. `Waivers.GatheringWaivers` (this feature)
2. `Waivers.WaiverTypes` (PDF templates per Assumption 4)

**Future Candidates** (not in scope now):
- `Member` (profile photos)
- `Awards.Recommendation` (supporting documents)
- `Activities.Activity` (documentation)

**Rationale**:
- Prevents accidental misuse
- Easy to expand by adding to constant
- Type safety for initial implementation

**Implementation**:
```php
// DocumentsTable.php
const ALLOWED_ENTITY_TYPES = [
    'Waivers.GatheringWaivers',
    'Waivers.WaiverTypes'
];

public function validationDefault(Validator $validator): Validator
{
    $validator
        ->inList('entity_type', self::ALLOWED_ENTITY_TYPES, 
            'Invalid entity type. Must be one of: ' . implode(', ', self::ALLOWED_ENTITY_TYPES)
        );
    return $validator;
}
```

**Impact**: Type safety, clear expansion path

---

## Decision 3: Orphaned Document Prevention (CHK008)

**Question**: How to handle Documents with NULL entity_id or deleted entities?

**Decision**: Prevent orphans - entity_id always required, never NULL

**Rationale**:
- Best practice: Never create orphans
- Simpler than cleanup jobs
- Aligns with Decision 11 (stub-first pattern)

**Implementation**:
```php
// DocumentsTable.php
public function validationDefault(Validator $validator): Validator
{
    $validator
        ->integer('entity_id')
        ->requirePresence('entity_id', 'create')
        ->notEmptyString('entity_id')
        ->add('entity_id', 'validEntity', [
            'rule' => function ($value, $context) {
                return $this->validateEntityExists(
                    $context['data']['entity_type'], 
                    $value
                );
            },
            'message' => 'Referenced entity does not exist'
        ]);
    return $validator;
}
```

**Impact**: No orphan cleanup needed, cleaner data model

---

## Decision 4: Invalid Retention Policy Handling (CHK010)

**Question**: What if retention_periods JSON is malformed?

**Decision**: Strict validation - reject waiver type save with validation error

**Rationale**:
- Legal compliance critical - can't have invalid retention policies
- Better to catch at configuration than at runtime
- Clear error guides administrator to fix

**Implementation**:
```php
// WaiverTypesTable.php
public function validationDefault(Validator $validator): Validator
{
    $validator
        ->add('retention_periods', 'validJson', [
            'rule' => function ($value) {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
                return $this->validateRetentionPeriodStructure($decoded);
            },
            'message' => 'Invalid retention period format. Must include "anchor" and at least one time period.'
        ]);
    return $validator;
}

private function validateRetentionPeriodStructure(array $data): bool
{
    // Must have valid anchor
    if (!in_array($data['anchor'] ?? null, ['gathering_end_date', 'upload_date', 'permanent'])) {
        return false;
    }
    
    // If not permanent, must have time period
    if ($data['anchor'] !== 'permanent') {
        $hasTimePeriod = !empty($data['years']) || !empty($data['months']) || !empty($data['days']);
        if (!$hasTimePeriod) {
            return false;
        }
    }
    
    return true;
}
```

**Impact**: Ensures data integrity for legal compliance

---

## Decision 5: Waiver Deletion Workflow (CHK011/CHK012)

**Question**: What process for deleting expired waivers? Support bulk operations?

**Decision**: Two-stage process with bulk deletion support

**Stage 1 - Automated Identification**:
- Daily Queue job identifies expired waivers
- Marks status as 'expired'
- Sends notification to compliance officer

**Stage 2 - Manual Deletion**:
- Compliance officer reviews expired list
- Selects multiple waivers (checkboxes)
- Clicks "Delete Selected" → multi-layer confirmation
- Provides deletion reason (required for audit)
- Batch deletion executes

**Rationale**:
- Efficient for compliance officer (bulk operations)
- Safe (must be expired first)
- Auditable (reason required, logs everything)
- Aligns with Assumption 10 (manual enforcement)

**Implementation**:
```php
// GatheringWaiversController::bulkDelete()
public function bulkDelete()
{
    $this->Authorization->authorize($this, 'bulkDelete');
    
    if ($this->request->is('post')) {
        $ids = $this->request->getData('waiver_ids');
        $reason = $this->request->getData('deletion_reason');
        
        if (empty($reason)) {
            $this->Flash->error('Deletion reason required for audit trail.');
            return $this->redirect($this->referer());
        }
        
        $count = 0;
        foreach ($ids as $id) {
            $waiver = $this->GatheringWaivers->get($id);
            
            // Verify status is expired
            if ($waiver->status !== GatheringWaiversTable::STATUS_EXPIRED) {
                continue;
            }
            
            // Audit log
            $this->AuditLog->log([
                'action' => 'waiver_deleted',
                'entity_id' => $id,
                'reason' => $reason,
                'user_id' => $this->Authentication->getIdentity()->getIdentifier()
            ]);
            
            // Delete document file and records
            if ($this->GatheringWaivers->deleteWithDocument($waiver)) {
                $count++;
            }
        }
        
        $this->Flash->success("Deleted {$count} expired waivers.");
    }
    
    return $this->redirect(['action' => 'expired']);
}
```

**Impact**: Balances efficiency with safety and compliance

---

## Decision 6: Awards Plugin Migration (CHK015)

**Question**: How to migrate award_events to gatherings?

**Decision**: Create "Kingdom Calendar Event" gathering type, migrate all data, rewire Awards plugin

**Migration Steps**:

1. **Create new gathering type**:
   - Name: "Kingdom Calendar Event"
   - Description: "Official kingdom calendar events (migrated from Awards plugin)"

2. **Migrate award_events → gatherings**:
   - Copy all event data to gatherings table
   - Maintain created/modified timestamps
   - Set waivers_collected = FALSE by default

3. **Update Awards plugin foreign keys**:
   - recommendations.event_id → gathering_id
   - Any other tables with event_id FK

4. **Update Awards plugin code**:
   - Model associations (belongsTo Gatherings)
   - Controller queries
   - View references

5. **Verification & cleanup**:
   - Verify all records migrated
   - Test Awards functionality
   - Backup award_events table (30-day retention)
   - Drop award_events after verification

**Rationale**:
- Consolidates event management in one place
- Enables waivers for Awards events
- Maintains backwards compatibility during transition

**Impact**: Major refactoring of Awards plugin, requires thorough testing

**Reference**: See detailed migration code in clarifications.md

---

## Decision 7: Storage Efficiency Target (CHK018)

**Question**: Is "1-3MB per converted document" a hard requirement or estimate?

**Decision**: Target estimate with warning thresholds, not hard limit

**Thresholds**:
- ✅ **Under 3MB**: Success (no warning)
- ⚠️ **3-5MB**: Success with info message
- ⚠️ **Over 5MB**: Success with warning (suggest lower resolution)
- ❌ **Over 25MB** (pre-conversion): Reject upload

**Rationale**:
- Compression varies by image content
- Some legitimate waivers may exceed 3MB
- Better to warn than reject valid documents

**Implementation**:
```php
// ImageToPdfConversionService.php
const TARGET_MAX_SIZE = 3 * 1024 * 1024; // 3MB
const WARNING_THRESHOLD = 5 * 1024 * 1024; // 5MB

public function convertToPdf(string $imagePath, string $outputPath): array
{
    // ... conversion code ...
    
    $fileSize = filesize($outputPath);
    
    return [
        'success' => true,
        'file_size' => $fileSize,
        'warning' => $fileSize > self::WARNING_THRESHOLD ? 
            'Converted PDF is larger than expected. Consider using a lower resolution image.' : 
            null
    ];
}
```

**Impact**: Balances user experience with storage optimization goals

---

## Decision 8: Deletion Prevention Safeguards (CHK023)

**Question**: What safeguards prevent accidental waiver deletion?

**Decision**: Multi-layer approach with strong confirmation

**Layer 1 - Status Check**:
- Only expired waivers can be deleted (status = 'expired')
- Active waivers have no delete option in UI

**Layer 2 - Confirmation Dialog**:
- JavaScript prompt requiring "DELETE" to be typed
- Prevents accidental clicks

**Layer 3 - Audit Reason Required**:
- Form textarea field (required)
- Must explain why deletion is happening

**Layer 4 - Authorization Check**:
- Policy class verifies `delete_expired_waivers` permission
- Not available to general users

**Rationale**:
- Deletion is irreversible and legally significant
- Strong safeguards protect against accidents
- Audit trail ensures accountability

**Implementation**:
```javascript
// Stimulus controller
deletionConfirm(event) {
    const count = this.selectedWaivers.length;
    const message = `Are you sure you want to permanently delete ${count} expired waiver(s)?

This action cannot be undone. The PDF files will be permanently removed.

Type DELETE to confirm:`;
    
    const confirmation = prompt(message);
    
    if (confirmation !== 'DELETE') {
        event.preventDefault();
        alert('Deletion cancelled. You must type DELETE to confirm.');
        return false;
    }
}
```

**Impact**: Prevents accidental deletion while allowing legitimate operations

---

## Decision 9: waiver_type_id Assignment Timing (CHK096)

**Question**: Is waiver_type_id assigned at upload or during later review?

**Decision**: Assign at upload time (user declares waiver type)

**Rationale**:
- Retention calculation requires waiver_type immediately
- User knows what they're uploading
- Matches FR-002 requirement
- Enables immediate compliance checking

**Upload Form Pattern**:
```php
<div class="mb-3">
    <?= $this->Form->control('waiver_type_id', [
        'type' => 'select',
        'options' => $waiverTypes,
        'empty' => '-- Select Waiver Type --',
        'label' => 'What type of waiver is this?',
        'required' => true,
        'help' => 'Select the waiver type that matches the form being uploaded'
    ]) ?>
</div>
```

**Note**: waiver_type_id represents user's **declared intent**, not verified content. System trusts user selection. Future enhancement could add admin review/correction if needed.

**Impact**: Enables immediate retention calculation and compliance tracking

---

## Decision 10: Ambiguity Resolution - Conversion Performance (CHK097)

**Question**: Can synchronous conversion handle "10-50 files in 10 minutes"?

**Clarification**: Yes, with sequential processing

**Math**:
- 50 files × 5 seconds each = 250 seconds (4.2 minutes)
- Well within 10-minute target
- Sequential processing acceptable for initial implementation

**Future Optimization** (if needed):
- Parallel processing (multiple workers)
- Background queue for large batches
- Progress bar for user feedback

**Impact**: Synchronous conversion is acceptable for initial release

---

## Decision 11: Documents.entity_id Lifecycle (CHK099)

**Question**: When is entity_id set? Can it be NULL?

**Decision**: Create entity stub first, entity_id never NULL

**Pattern**: Stub-First Creation

**Workflow**:
1. Create GatheringWaiver stub (no document_id yet)
2. Convert image to PDF
3. Save PDF to storage (Flysystem)
4. Create Document record (entity_id = waiver.id from step 1)
5. Update GatheringWaiver.document_id
6. Cleanup temp files

**Rationale**:
- Cleaner data model (no NULL entity_id)
- No orphan cleanup needed
- Transaction ensures consistency
- Entity always exists before document

**Implementation**:
```php
$connection->begin();
try {
    // Step 1: Create stub
    $waiver = $this->GatheringWaivers->newEntity([...]);
    $this->GatheringWaivers->saveOrFail($waiver);
    
    // Steps 2-3: Convert and store
    $pdfPath = $this->ImageToPdfService->convert($imagePath);
    $storedPath = $this->Filesystem->write('waivers/' . $filename, $pdfContent);
    
    // Step 4: Create document (entity_id known)
    $document = $this->Documents->newEntity([
        'entity_type' => 'Waivers.GatheringWaivers',
        'entity_id' => $waiver->id, // ✅ Never NULL
        // ... other fields
    ]);
    $this->Documents->saveOrFail($document);
    
    // Step 5: Complete the link
    $waiver->document_id = $document->id;
    $this->GatheringWaivers->saveOrFail($waiver);
    
    $connection->commit();
} catch (\Exception $e) {
    $connection->rollback();
    throw $e;
}
```

**Benefits**:
- No NULL entity_id ever
- No orphan cleanup jobs needed
- Transaction rollback on failure
- Simpler validation rules

**Trade-off**: Brief window where GatheringWaiver exists without document_id (acceptable within transaction)

**Impact**: Cleaner architecture, prevents orphaned documents

---

## Summary

All 11 critical design decisions have been made and documented. These decisions establish clear implementation patterns and resolve ambiguities in the original specification.

**Key Principles Established**:
1. **User Experience**: Helpful error messages, allow retries
2. **Data Integrity**: Strict validation, no orphans, transaction safety
3. **Legal Compliance**: Auditable deletion, accurate retention policies
4. **Type Safety**: Whitelisted entity types, expandable design
5. **Efficiency**: Bulk operations with safeguards
6. **Simplicity**: Stub-first pattern, synchronous conversion

**Next Steps**:
- Update spec.md with new requirements
- Update data-model.md with constraints
- Update plan.md with migration strategy
- Update contracts with error responses
- Generate task breakdown with /speckit.tasks

---

**Approved By**: User  
**Date**: October 21, 2025  
**Status**: Ready for Implementation
