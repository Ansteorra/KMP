# Pre-Task Checklist Clarifications

**Purpose**: Document clarifications for checklist items based on established patterns, conventions, and decisions  
**Status**: RESOLVED - Obvious items | PENDING - Items requiring user decision  
**Created**: 2025-10-21

---

## ✅ RESOLVED - Obvious Clarifications

### Completeness

**CHK001** - Supported image formats defined  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: System will accept JPEG, PNG, and TIFF formats as specified in FR-023 and Edge Cases  
**IMPLEMENTATION**:
```php
$validator
    ->uploadedFile('waiver_image', [
        'types' => ['image/jpeg', 'image/png', 'image/tiff'],
        'optional' => false,
    ])
    ->requirePresence('waiver_image', 'create')
    ->notEmptyFile('waiver_image');
```
**DOCUMENT UPDATE**: Add to spec.md FR-023 details

---

**CHK002** - Image validation requirements specified  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Standard CakePHP file upload validation with additional checks:
- File size limit: 25MB per file (per Assumption 15)
- Format detection: Via MIME type validation
- Corruption handling: Imagick will throw exception, catch and show error message
- Filename sanitization: Alphanumeric + underscores + hyphens only

**IMPLEMENTATION**:
```php
$validator
    ->uploadedFile('waiver_image', [
        'types' => ['image/jpeg', 'image/png', 'image/tiff'],
        'maxSize' => 25 * 1024 * 1024, // 25MB in bytes
        'optional' => false,
    ])
    ->add('waiver_image', 'fileExtension', [
        'rule' => ['extension', ['jpg', 'jpeg', 'png', 'tif', 'tiff']],
        'message' => 'Only JPEG, PNG, and TIFF image files are allowed'
    ]);
```
**DOCUMENT UPDATE**: Add to spec.md as new requirement FR-023d

---

**CHK004** - File size limits codified  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: 25MB per file limit (from Assumption 15), enforce at both client and server  
**CLIENT-SIDE VALIDATION** (Stimulus):
```javascript
handleFiles(event) {
    const MAX_SIZE = 25 * 1024 * 1024; // 25MB
    const files = Array.from(event.target.files);
    
    const validFiles = files.filter(file => {
        if (file.size > MAX_SIZE) {
            this.showError(`${file.name} exceeds 25MB limit`);
            return false;
        }
        return true;
    });
}
```
**DOCUMENT UPDATE**: Already in Assumption 15, mark as requirement

---

**CHK007** - Checksum validation requirements  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Documents entity has checksum field (SHA-256), calculated on save and verified on retrieval  
**IMPLEMENTATION**: In DocumentsTable.php
```php
public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
{
    if ($entity->isNew() || $entity->isDirty('file_path')) {
        $filePath = $this->getFilesystemPath($entity->file_path);
        if (file_exists($filePath)) {
            $entity->checksum = hash_file('sha256', $filePath);
        }
    }
}

public function verifyChecksum(Document $document): bool
{
    $filePath = $this->getFilesystemPath($document->file_path);
    if (!file_exists($filePath)) {
        return false;
    }
    $currentChecksum = hash_file('sha256', $filePath);
    return $currentChecksum === $document->checksum;
}
```
**DOCUMENT UPDATE**: Add to data-model.md implementation notes for Documents entity

---

**CHK013** - Concurrent upload conflict resolution  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Use CakePHP optimistic locking via modified timestamp (no explicit locks per Assumption 7)  
**PATTERN**: Multiple users can upload waivers simultaneously; conflicts only occur on *edit* (rare)  
**IMPLEMENTATION**: Standard CakePHP behavior with `modified` field in Gatherings/GatheringWaivers tables  
**DOCUMENT UPDATE**: Add clarification to Assumption 7

---

**CHK014** - Gathering immutability enforcement  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Validation rules + clear error messages when attempting to modify gathering with waivers  
**IMPLEMENTATION**: In GatheringsTable.php
```php
public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
{
    if (!$entity->isNew() && $entity->isDirty('start_date', 'end_date')) {
        // Check if gathering has waivers
        $hasWaivers = $this->GatheringWaivers->exists(['gathering_id' => $entity->id]);
        if ($hasWaivers) {
            $entity->setError('start_date', [
                'immutable' => 'Cannot modify dates after waivers have been uploaded. Retention policies are calculated from original dates.'
            ]);
            $event->stopPropagation();
            return;
        }
    }
}
```
**DOCUMENT UPDATE**: Add to spec.md FR-017a with error message specification

---

### Clarity

**CHK016** - PDF compression quantified  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Imagick Group4 (CCITT T.6) compression from Research Decision #3  
**SPECIFIC SETTINGS**:
- Format: PDF
- Color: Bilevel (black and white)
- Compression: COMPRESSION_GROUP4
- Quality: 85 (balance legibility vs size)
**EXPECTED RESULTS**: 90-95% reduction (3-5MB → 100-300KB)  
**DOCUMENT UPDATE**: Add to FR-023a with specific Imagick settings

---

**CHK017** - Conversion timing quantified  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: **Synchronous** conversion (from Assumption 17 and FR-023b)  
- User uploads image → Server immediately converts → Returns success/error
- No background queue for conversion (Queue plugin used only for retention checks)
- Typical timing: 2-5 seconds per image (from USER_FLOWS.md)
**DOCUMENT UPDATE**: Emphasize "synchronous" in FR-023b, add timing expectation

---

**CHK019** - "Immediate feedback" quantified  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Standard UX definition of "immediate" = under 200ms perceived response  
- For conversion status: Progress indicator appears within 200ms of upload start
- For completion: Success message appears within 500ms of conversion complete
- For errors: Error message appears within 200ms of error detection
**IMPLEMENTATION**: Turbo Streams for real-time updates during batch processing  
**DOCUMENT UPDATE**: Add to FR-023b with specific timing thresholds

---

**CHK020** - "Authorized users" explicitly defined  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Follow existing KMP RBAC pattern (from Section 4.4 Architecture docs)  
- **Configuration** (waiver types, gathering types): Members with `manage_waivers` permission
- **Upload**: Gathering steward (creator) OR members with `upload_waivers` permission
- **Deletion**: Gathering steward OR members with `delete_waivers` permission
- **View**: Gathering steward OR branch officers OR members with `view_waivers` permission

**IMPLEMENTATION**: Policy classes following existing pattern
```php
// GatheringWaiverPolicy.php
public function canUpload(KmpIdentityInterface $user, BaseEntity $gathering): bool
{
    // Gathering steward can upload
    if ($gathering->created_by === $user->getIdentifier()) {
        return true;
    }
    
    // Check for upload_waivers permission
    return $this->_hasPolicy($user, 'upload_waivers');
}
```
**DOCUMENT UPDATE**: Add to FR-037-040 with specific permission names

---

**CHK021** - "Secure storage" defined  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Follow CakePHP security best practices:
- Files stored **outside** webroot (not publicly accessible)
- Access via controller action with authorization check
- Flysystem abstraction layer (local or S3)
- No directory traversal (sanitized filenames)
- HTTPS required for transmission (production)
**IMPLEMENTATION**: Files in `/images/uploaded/waivers/` (outside `/webroot/`)  
**DOCUMENT UPDATE**: Add to FR-026 with specific security controls

---

**CHK022** - "Access controls" specified  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Use existing Authorization plugin with policy classes (see CHK020)  
- Every download request checked via GatheringWaiverPolicy
- Audit log records access (CHK076)
- Failed authorization returns 403 with Flash error message
**DOCUMENT UPDATE**: Add to FR-026 referencing policy-based authorization

---

**CHK027** - Polymorphic naming convention  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: CakePHP convention for entity_type = Plugin.ModelName or ModelName  
**EXAMPLES**:
- `Waivers.GatheringWaivers` (plugin entity)
- `Member` (core entity - future)
- `Activities.Activity` (plugin entity - future)

**IMPLEMENTATION**: In Documents associations
```php
// DocumentsTable.php - No explicit belongsTo (polymorphic)
// GatheringWaiversTable.php
$this->hasOne('Documents', [
    'foreignKey' => 'entity_id',
    'conditions' => ['Documents.entity_type' => 'Waivers.GatheringWaivers']
]);
```
**DOCUMENT UPDATE**: Add to data-model.md Documents implementation notes

---

### Consistency Checks

**CHK028** - Retention policy format consistency  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**: 
- FR-003 specifies: "JSON with anchor + offset"
- Data Model §4 WaiverTypes shows: `retention_periods` JSON field
- Example: `{"anchor": "gathering_end_date", "years": 7, "months": 6}`
**RESULT**: Consistent across spec and data model  
**ACTION**: None needed

---

**CHK029** - Deletion requirements vs manual enforcement  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**:
- FR-030-032 specify: Identify expired waivers, mark for deletion, confirm deletion
- Assumption 10 states: Manual deletion enforcement (not automatic)
- This aligns: System *identifies* expired, human *confirms* deletion
**RESULT**: Consistent - no automatic deletion, requires human review  
**ACTION**: None needed

---

**CHK030** - Concurrent uploads vs no-locking  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**:
- FR-024a allows concurrent uploads from multiple stewards
- Assumption 7 states: No explicit file locking
- Uploads create *new* records (no conflicts), only *edits* use optimistic locking
**RESULT**: Consistent - concurrent creates are safe without locks  
**ACTION**: None needed

---

**CHK031** - Immutability requirements alignment  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**:
- FR-017a prohibits modifying gathering dates after waivers uploaded
- Assumption 11 explains: Retention policies calculated from original dates
**RESULT**: Consistent - immutability protects retention calculation integrity  
**ACTION**: None needed

---

**CHK032** - Documents entity consistency  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**:
- Spec §Key Entities describes Documents as generic polymorphic storage
- Data Model §1 shows Documents with entity_type/entity_id pattern
**RESULT**: Consistent across all documentation  
**ACTION**: None needed

---

**CHK033** - GatheringWaivers/GatheringWaiverActivities alignment  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**:
- Spec implies many-to-many: waiver covers multiple activities
- Data Model §6-7 shows: GatheringWaiverActivities join table
- One waiver can cover multiple activities (e.g., general waiver for combat + archery)
**RESULT**: Consistent - many-to-many relationship properly modeled  
**ACTION**: None needed

---

**CHK034** - waiver_type_id semantics consistency  
**STATUS**: ✅ VERIFIED CONSISTENT  
**VALIDATION**:
- Data Model §6 notes: waiver_type_id is "declared intent"
- Used for retention calculation and compliance checking
- Not enforcing content verification (human responsibility)
**RESULT**: Consistent - design acknowledges upload-time declaration  
**ACTION**: None needed

---

### Measurability Validation

**CHK035-CHK044** - Success Criteria Measurability  
**STATUS**: ✅ ALL VERIFIED MEASURABLE  
**VALIDATION**:
- SC-001: Upload within 1 hour → Timestamp comparison (measurable)
- SC-005: 100% accuracy → Database query (retention_date exists for all waivers)
- SC-006: 95% successful upload → Success rate from logs (measurable)
- SC-008: 75% time reduction → Compare before/after timing (measurable with baseline)
- SC-009: Zero policy confusion → Support ticket count (measurable)
- SC-010: 90% compliance → Database query (waivers collected / gatherings requiring)
- SC-011: 60-80% storage reduction → File size before/after (measurable)
- SC-012: 98% conversion success → Success count / total uploads (measurable)
- SC-014: 70% mobile adoption → User agent detection (measurable)
- SC-015: Under 30 seconds → Request timing (measurable)

**RESULT**: All success criteria have objective measurement methods  
**ACTION**: None needed - criteria are well-defined

---

### NFR - Security (CakePHP Provides)

**CHK071** - Input sanitization  
**STATUS**: ✅ RESOLVED (Framework provides)  
**CLARIFICATION**: CakePHP automatically sanitizes input via:
- Form->create() with SecurityComponent (HMAC field protection)
- ORM escapes all SQL parameters (prevents injection)
- Template escaping via `h()` helper (prevents XSS)
**IMPLEMENTATION**: Standard CakePHP patterns, no additional requirements  
**DOCUMENT UPDATE**: Add NFR section referencing CakePHP security features

---

**CHK072** - SQL injection prevention  
**STATUS**: ✅ RESOLVED (Framework provides)  
**CLARIFICATION**: CakePHP ORM uses prepared statements, no raw SQL in feature  
**DOCUMENT UPDATE**: Add to NFR section

---

**CHK073** - CSRF protection  
**STATUS**: ✅ RESOLVED (Framework provides)  
**CLARIFICATION**: CakePHP FormProtection component (commented in AppController but available)  
- Turbo Forms automatically include CSRF token
- All POST/PUT/DELETE require valid token
**DOCUMENT UPDATE**: Add to NFR section, note Turbo integration

---

**CHK074** - File upload security  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: 
- Path traversal: Filename sanitization (alphanumeric + _ + -)
- Filename sanitization: Via Documents.stored_filename validation
- MIME type validation: CakePHP uploadedFile validator
- Files stored outside webroot
**IMPLEMENTATION**: Already covered in CHK001, CHK002, CHK021  
**DOCUMENT UPDATE**: Consolidate in NFR section

---

**CHK076** - Audit logging requirements  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Comprehensive audit logging for compliance:
- **Events to log**: Upload, download, deletion, configuration changes
- **Data captured**: User ID, timestamp, action, entity ID, IP address
- **Retention**: 7 years minimum (matches waiver retention)
- **Storage**: Separate `audit_logs` table

**IMPLEMENTATION**:
```php
// In GatheringWaiversController
$this->AuditLog->log([
    'user_id' => $this->Authentication->getIdentity()->getIdentifier(),
    'action' => 'waiver_uploaded',
    'entity_type' => 'Waivers.GatheringWaivers',
    'entity_id' => $waiver->id,
    'ip_address' => $this->request->clientIp(),
    'metadata' => json_encode(['gathering_id' => $gathering->id])
]);
```
**DOCUMENT UPDATE**: Add FR-038 details with specific events and retention

---

**CHK077** - Privilege escalation prevention  
**STATUS**: ✅ RESOLVED (Framework provides)  
**CLARIFICATION**: Authorization plugin prevents privilege escalation:
- Every action checked via policy classes
- No role assignment changes without permission
- Policy classes cannot be overridden by request data
**DOCUMENT UPDATE**: Add to NFR section

---

### NFR - Accessibility (Bootstrap 5 Standards)

**CHK078-CHK082** - Accessibility requirements  
**STATUS**: ✅ RESOLVED (Framework + Bootstrap provide)  
**CLARIFICATION**:
- **Keyboard navigation**: Bootstrap 5 components are keyboard accessible
- **Screen readers**: ARIA labels on form controls, buttons
- **Color contrast**: Bootstrap 5 meets WCAG AA standards
- **ARIA labels**: Required on all interactive elements
- **Mobile**: Touch targets minimum 44x44px (Bootstrap standard)

**IMPLEMENTATION**: Follow Bootstrap 5 accessibility patterns
```php
// Form controls
echo $this->Form->control('waiver_type_id', [
    'label' => 'Waiver Type',
    'aria-describedby' => 'waiver-type-help'
]);
echo $this->Html->tag('small', 'Select the type of waiver being uploaded', [
    'id' => 'waiver-type-help',
    'class' => 'form-text'
]);

// File upload button
echo $this->Form->file('waiver_images[]', [
    'accept' => 'image/*',
    'capture' => 'environment',
    'multiple' => true,
    'aria-label' => 'Upload waiver images',
    'class' => 'form-control'
]);
```
**DOCUMENT UPDATE**: Add NFR section with Bootstrap accessibility standards

---

### NFR - Usability (CakePHP/Bootstrap Conventions)

**CHK083-CHK087** - Usability requirements  
**STATUS**: ✅ RESOLVED (Framework patterns provide)  
**CLARIFICATION**:

**Error messages** (CHK083):
```php
// Specific validation messages
$validator->add('waiver_image', 'fileSize', [
    'rule' => ['fileSize', '<=', '25MB'],
    'message' => 'Image file must be 25MB or smaller. Please reduce file size and try again.'
]);

// Conversion error
$this->Flash->error('Failed to convert image to PDF. Please ensure the image is not corrupted and try again. If the problem persists, contact support.');
```

**Loading indicators** (CHK084):
- Turbo: Automatic progress bar on top of page
- Custom: Spinner during conversion (Stimulus controller)

**Progress feedback** (CHK085):
```javascript
// In waiver-upload-controller.js
updateProgress(completed, total) {
    this.progressTarget.textContent = `Converting ${completed} of ${total} images...`;
    this.progressBarTarget.style.width = `${(completed/total)*100}%`;
}
```

**Confirmation dialogs** (CHK086):
```javascript
// Delete confirmation
if (!confirm('Are you sure you want to delete this waiver? This action cannot be undone.')) {
    return false;
}
```

**Help text** (CHK087):
- Form hints via Bootstrap `form-text` class
- Tooltips on complex fields (Bootstrap popovers)
- Inline examples (e.g., "Example: 7 years, 6 months")

**DOCUMENT UPDATE**: Add NFR section with usability patterns

---

### Dependencies

**CHK088** - Imagick library availability  
**STATUS**: ✅ RESOLVED (Already documented)  
**CLARIFICATION**: System requirement from README.md:
- ImageMagick library installed on server
- PHP `imagick` extension enabled
- Verification command: `php -m | grep imagick`
**DOCUMENT UPDATE**: Already in README.md dependencies section

---

**CHK089** - Flysystem adapter requirements  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: From Research Decision #4:
- Default: Local filesystem adapter
- Optional: AWS S3 adapter
- Configuration in `config/app_local.php`
```php
'Flysystem' => [
    'adapters' => [
        'local' => [
            'class' => 'Local',
            'options' => [
                'root' => ROOT . DS . 'images' . DS . 'uploaded' . DS . 'waivers'
            ]
        ],
        's3' => [
            'class' => 'AwsS3',
            'options' => [
                'key' => env('AWS_KEY'),
                'secret' => env('AWS_SECRET'),
                'region' => env('AWS_REGION'),
                'bucket' => env('AWS_BUCKET')
            ]
        ]
    ]
]
```
**DOCUMENT UPDATE**: Add to plan.md configuration section

---

**CHK090** - Queue Plugin requirements  
**STATUS**: ✅ RESOLVED (Already documented)  
**CLARIFICATION**: From Research Decision #7:
- Used for daily retention policy checks (not conversion)
- Already installed in KMP
- Configuration in `config/app_queue.php`
**DOCUMENT UPDATE**: Already documented in research.md and plan.md

---

**CHK091** - Mobile browser compatibility  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: From Assumption 18:
- iOS Safari 14+ (released 2020)
- Android Chrome 90+ (released 2021)
- HTML5 File API support required
- Camera capture support required
**TESTING REQUIRED**: Cross-browser testing on real devices  
**DOCUMENT UPDATE**: Add to assumptions with specific browser versions

---

**CHK092** - PDF template storage  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: From Assumption 4:
- Waiver type PDF templates stored in Documents entity
- entity_type = 'Waivers.WaiverTypes'
- Separate from signed waiver PDFs
**DOCUMENT UPDATE**: Already in Assumption 4

---

**CHK093** - Branch entity schema  
**STATUS**: ✅ RESOLVED (Existing KMP entity)  
**CLARIFICATION**: Branch entity exists in core KMP:
- Table: `branches`
- Fields: id, name, branch_type_id, parent_id (hierarchy), etc.
- Used for: Gathering ownership, authorization scopes
**DOCUMENT UPDATE**: Add reference to core entities in plan.md

---

**CHK094** - Member entity schema  
**STATUS**: ✅ RESOLVED (Existing KMP entity)  
**CLARIFICATION**: Member entity exists in core KMP:
- Table: `members`
- Fields: id, sca_name, member_number, email, etc.
- Used for: Waiver uploader, gathering steward, authorization
**DOCUMENT UPDATE**: Add reference to core entities in plan.md

---

**CHK095** - Authorization system requirements  
**STATUS**: ✅ RESOLVED (Existing KMP system)  
**CLARIFICATION**: From Assumption 9 and Architecture docs:
- CakePHP Authorization plugin
- Policy classes for entity-level authorization
- Permission system via Roles → Permissions → MemberRoles
- Already implemented in KMP core
**DOCUMENT UPDATE**: Reference existing authorization in plan.md

---

### Data Model Quality

**CHK107-CHK115** - Data model documentation  
**STATUS**: ✅ RESOLVED  
**CLARIFICATION**: Add standard CakePHP conventions to data-model.md:

**Foreign key constraints** (CHK107):
```sql
FOREIGN KEY (gathering_id) REFERENCES gatherings(id) ON DELETE CASCADE
FOREIGN KEY (waiver_type_id) REFERENCES waiver_types(id) ON DELETE RESTRICT
FOREIGN KEY (uploaded_by) REFERENCES members(id) ON DELETE RESTRICT
FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT
```

**Database indexes** (CHK108):
```sql
-- Performance indexes
CREATE INDEX idx_gathering_waivers_gathering ON gathering_waivers(gathering_id);
CREATE INDEX idx_gathering_waivers_retention ON gathering_waivers(retention_date);
CREATE INDEX idx_gathering_waivers_created ON gathering_waivers(created);
CREATE INDEX idx_documents_entity ON documents(entity_type, entity_id);
CREATE INDEX idx_documents_checksum ON documents(checksum);

-- Unique constraints
CREATE UNIQUE INDEX idx_documents_file_path ON documents(file_path);
```

**Cascade behaviors** (CHK109):
- Gathering deleted → CASCADE delete GatheringWaivers and GatheringActivities
- WaiverType deleted → RESTRICT (prevent if waivers exist)
- Member deleted → RESTRICT (prevent if uploaded waivers exist)
- Document deleted → Manually delete file then delete record

**Unique constraints** (CHK110):
```sql
UNIQUE (name) -- gathering_types, waiver_types
UNIQUE (file_path) -- documents
```

**Default values** (CHK111):
```sql
waivers_collected BOOLEAN DEFAULT FALSE
storage_adapter VARCHAR(50) DEFAULT 'local'
created DATETIME DEFAULT CURRENT_TIMESTAMP
modified DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

**NULL semantics** (CHK112):
- gathering_activities.notes: NULL = no notes provided
- gathering_waivers.member_id: NULL = unknown/anonymous participant
- documents.checksum: NULL = not calculated (legacy or error)
- documents.metadata: NULL = no additional metadata

**Enum values** (CHK113):
```php
// GatheringWaiver status (future)
const STATUS_UPLOADED = 'uploaded';
const STATUS_REVIEWED = 'reviewed';
const STATUS_EXPIRED = 'expired';

// Documents storage_adapter
const ADAPTER_LOCAL = 'local';
const ADAPTER_S3 = 's3';
```

**JSON schemas** (CHK114):
```json
// waiver_types.retention_periods
{
  "type": "object",
  "required": ["anchor"],
  "properties": {
    "anchor": {"enum": ["gathering_end_date", "upload_date", "permanent"]},
    "years": {"type": "integer", "minimum": 0},
    "months": {"type": "integer", "minimum": 0},
    "days": {"type": "integer", "minimum": 0}
  }
}

// documents.metadata
{
  "type": "object",
  "properties": {
    "source": {"type": "string"},
    "converted_from": {"type": "string"},
    "compression_ratio": {"type": "number"},
    "page_count": {"type": "integer"}
  }
}
```

**DOCUMENT UPDATE**: Add comprehensive constraints section to data-model.md

---

### API Contracts

**CHK116-CHK122** - API contract standards  
**STATUS**: ✅ RESOLVED (CakePHP conventions)  
**CLARIFICATION**:

**Naming conventions** (CHK116):
```
GET    /waivers/gathering-waivers              (index)
GET    /waivers/gathering-waivers/view/{id}    (view)
POST   /waivers/gathering-waivers/upload       (custom action)
POST   /waivers/gathering-waivers/add          (add)
DELETE /waivers/gathering-waivers/delete/{id}  (delete)
```

**Request/response schemas** (CHK117):
- Already specified in contracts/gathering-waivers.md
- Follow JSON:API or CakePHP default serialization

**Error responses** (CHK118):
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "waiver_image": ["File size must be 25MB or smaller"]
  }
}
```

**Pagination** (CHK119):
```json
{
  "gathering_waivers": [...],
  "paging": {
    "page": 1,
    "limit": 20,
    "total": 156,
    "pageCount": 8
  }
}
```

**Filtering/sorting** (CHK120):
```
GET /waivers/gathering-waivers?gathering_id=10&sort=created&direction=desc
```

**Validation errors** (CHK121):
- HTTP 422 Unprocessable Entity
- JSON response with field-level errors
- User-friendly messages

**Turbo integration** (CHK122):
- Upload returns Turbo Stream
- Partial updates via Turbo Frames
- Already documented in contracts/

**DOCUMENT UPDATE**: Add REST conventions section to contracts/

---

## 🔶 PENDING - Requires User Decision

These items require business logic decisions or policy clarifications from the user.

### ✅ CRITICAL Items - ALL RESOLVED

All 11 CRITICAL design decisions have been made and documented in **DECISIONS.md**. Summary:

- **CHK003** - ✅ RESOLVED: Retry with diagnostic error messages (See DECISIONS.md Decision #1)
- **CHK006** - ✅ RESOLVED: Whitelist 2 entity types initially ('Waivers.GatheringWaivers', 'Waivers.WaiverTypes') (See DECISIONS.md Decision #2)
- **CHK008** - ✅ RESOLVED: Prevent orphans - entity_id required, never NULL (See DECISIONS.md Decision #3)
- **CHK010** - ✅ RESOLVED: Strict JSON validation - reject invalid retention policies (See DECISIONS.md Decision #4)
- **CHK011/CHK012** - ✅ RESOLVED: Two-stage deletion with bulk support (See DECISIONS.md Decision #5)
- **CHK015** - ✅ RESOLVED: Migrate award_events → gatherings with "Kingdom Calendar Event" type (See DECISIONS.md Decision #6 & plan.md Awards Migration section)
- **CHK018** - ✅ RESOLVED: Target 3MB with warnings, not hard limit (See DECISIONS.md Decision #7)
- **CHK023** - ✅ RESOLVED: Multi-layer safeguards (status check + Type DELETE + reason + authorization) (See DECISIONS.md Decision #8)
- **CHK096** - ✅ RESOLVED: waiver_type_id assigned at upload time (user declares) (See DECISIONS.md Decision #9)
- **CHK099** - ✅ RESOLVED: Create entity stub first, entity_id never NULL (See DECISIONS.md Decision #11)

**All critical blockers cleared. Ready for task generation.**

---

### HIGH Priority (Affects Core Functionality)

**CHK024** - Retention status indication in UI  
**QUESTION**: How should retention status be displayed?
- **Option A**: Badge with color coding (green=active, yellow=expiring soon, red=expired)
- **Option B**: Date display with countdown ("Expires in 45 days")
- **Option C**: Status column in table ("Active" / "Expires 2026-12-31" / "Expired")
**IMPACT**: Affects UI implementation and user comprehension  
**RECOMMENDATION**: Option A (color badges) for at-a-glance status + Option B (countdown) on hover/tooltip

---

**CHK025** - Waiver collection compliance metrics calculation  
**QUESTION**: How are compliance metrics calculated?
- **Percentage**: (Gatherings with all required waivers / Total gatherings requiring waivers) * 100
- **Questions**:
  - Count only gatherings in date range?
  - Include gatherings marked "waivers not collected" in denominator?
  - Partial compliance tracking? (e.g., 2 of 3 required waivers uploaded)
**IMPACT**: Affects FR-036 reporting requirements  
**RECOMMENDATION**: Count only gatherings with waivers_collected=true in denominator, date range filter optional

---

**CHK049** - Duplicate waiver upload handling  
**QUESTION**: What happens if same file uploaded multiple times?
- **Option A**: Allow duplicates (multiple copies of same waiver)
- **Option B**: Detect via checksum, show warning, allow override
- **Option C**: Detect via checksum, prevent duplicate with error
**IMPACT**: Affects upload validation and storage efficiency  
**RECOMMENDATION**: Option B (warning + override) - prevents accidental duplicates but allows intentional re-upload

---

**CHK050** - Waiver upload for past gatherings  
**QUESTION**: Can waivers be uploaded for gatherings with past dates?
- **Scenario**: Gathering ended last week, steward uploading waivers late
- **Option A**: Allow with warning ("This gathering has ended")
- **Option B**: Prevent after gathering end_date
- **Option C**: Allow with expiration date calculated from upload_date instead
**IMPACT**: Affects real-world usage patterns and retention calculation  
**RECOMMENDATION**: Option A - Allow with warning (real-world scenario: late uploads common)

---

**CHK051** - Modifying gathering dates after waivers uploaded  
**QUESTION**: Confirmed immutability (FR-017a), but what about corrections?
- **Scenario**: Gathering dates entered incorrectly, waivers already uploaded
- **Option A**: Strict immutability (delete waivers, fix dates, re-upload)
- **Option B**: Allow with administrator override and audit log
- **Option C**: Recalculate retention dates for all affected waivers
**IMPACT**: Affects error recovery process  
**RECOMMENDATION**: Option A (strict) for MVP - cleaner data model, add admin override in future if needed

---

**CHK052** - Deactivating waiver types with existing references  
**QUESTION**: How to handle waiver type changes?
- **Current**: FR-004 has is_active field
- **Questions**:
  - Can inactive types be soft-deleted or must remain visible?
  - What happens to gatherings referencing inactive types?
  - Can retention policies be updated for inactive types?
**IMPACT**: Affects waiver type lifecycle  
**RECOMMENDATION**: Inactive types remain visible (soft delete), existing waivers unaffected, cannot update retention policy when inactive

---

**CHK053** - Branch mergers/reorganizations  
**QUESTION**: How do branch changes affect gathering ownership?
- **Scenario**: Two branches merge, gatherings from both need to be accessible
- **Questions**:
  - Do gatherings stay with original branch?
  - Can ownership be transferred?
  - How does authorization work after branch changes?
**IMPACT**: Affects organizational change management  
**RECOMMENDATION**: Out of scope for initial release - manual data updates if needed

---

**CHK054** - Member account deletion with uploaded waivers  
**QUESTION**: What happens when member who uploaded waivers is deleted?
- **Option A**: Prevent deletion (RESTRICT foreign key)
- **Option B**: Allow deletion, reassign waivers to "System" user
- **Option C**: Allow deletion, keep member_id but mark member as deleted
**IMPACT**: Affects data retention and GDPR compliance  
**RECOMMENDATION**: Option A (RESTRICT) - preserve audit trail, member deletion rare edge case

---

### Edge Cases (Lower Priority)

**CHK055-CHK064** - Edge case policies  
These are lower priority but should be addressed before implementation:
- Large date ranges (CHK055)
- Date validation (CHK056)
- Negative expiration dates (CHK057)
- Timezone handling (CHK058)
- Missing/corrupted files (CHK059)
- Invalid entity_type (CHK060)
- Multiple cameras (CHK061)
- Small file sizes (CHK062)
- Unusual aspect ratios (CHK063)
- Concurrent operations (CHK064)

**RECOMMENDATION**: Create follow-up discussion session for edge cases

---

### Performance (NFR)

**CHK065-CHK070** - Performance SLAs  
**QUESTION**: What are acceptable performance thresholds?
- **Conversion time**: Currently "2-5 seconds per image", is this acceptable?
- **Batch limits**: How many concurrent conversions? (CPU/memory bound)
- **Database queries**: What's acceptable for large collections? (<500ms?)
- **File I/O**: What's acceptable for retrieval? (<1s?)
- **Bulk operations**: Time limit for retention calculations?
- **Mobile network**: Timeout for slow connections? (30s? 60s?)

**RECOMMENDATION**: Start with reasonable defaults, optimize based on real usage

---

### Security (Encryption)

**CHK075** - Encryption requirements for waiver PDFs  
**QUESTION**: Are stored PDFs encrypted at rest?
- **Option A**: No encryption (PDFs behind authorization, outside webroot)
- **Option B**: Encrypt with application key (performance impact)
- **Option C**: Use S3 server-side encryption (if using S3)
**DECISION FACTORS**:
- Legal compliance requirements for PII
- Performance impact vs security benefit
- Complexity of key management

**RECOMMENDATION**: Discuss with legal/compliance team

---

### Ambiguities

**CHK096** - When is waiver_type_id assigned?  
**QUESTION**: At upload or later?
- **Current understanding**: At upload time (user declares waiver type)
- **Confirmation needed**: Is this correct, or should it be nullable + assigned during review?

**CHK097** - Synchronous conversion vs batch performance  
**QUESTION**: Can synchronous conversion handle "10-50 files in 10 minutes"?
- **Math**: 50 files * 5 seconds = 250 seconds (4.2 minutes) if sequential
- **Clarification needed**: Are conversions parallelized? What's the concurrency limit?

**CHK098** - GatheringWaiverActivities creation timing  
**QUESTION**: Automatic or manual relationship creation?
- **Option A**: Automatic when waiver uploaded (system infers coverage)
- **Option B**: Manual selection (user specifies which activities covered)
**IMPACT**: Affects upload workflow complexity

**CHK099** - Documents.entity_id lifecycle  
**QUESTION**: When is entity_id set?
- **Current understanding**: NULL at first, updated after GatheringWaiver created
- **Clarification needed**: Should Document save fail if entity_id is NULL? Or allow NULL temporarily?

**CHK100** - member_id nullable semantics  
**QUESTION**: NULL means "anonymous" or "unknown"?
- **Anonymous**: Intentionally not recorded (privacy)
- **Unknown**: Not yet identified from signed waiver
**IMPACT**: Affects reporting and compliance checking

---

## Summary

### Resolved: 89 items
- Completeness: 6 resolved
- Clarity: 7 resolved  
- Consistency: 7 verified
- Measurability: 10 verified
- NFR Security: 7 resolved (framework provides)
- NFR Accessibility: 5 resolved (Bootstrap provides)
- NFR Usability: 5 resolved (conventions provide)
- Dependencies: 8 resolved
- Data Model: 9 resolved
- API Contracts: 7 resolved
- **Total**: 71 resolved via conventions + 18 verified consistent

### Pending User Decision: 33 items
- **CRITICAL** (blocking task breakdown): 11 items
  - CHK003, CHK006, CHK008, CHK010, CHK011, CHK012, CHK015, CHK018, CHK023, CHK096, CHK099
- **HIGH** (affects core functionality): 11 items
  - CHK024, CHK025, CHK049-CHK054, CHK097, CHK098, CHK100
- **MEDIUM** (edge cases): 10 items
  - CHK055-CHK064
- **LOW** (optimization): 7 items
  - CHK065-CHK070, CHK075

### Next Steps

1. **Review CRITICAL pending items** (11 decisions needed)
2. **Update specification documents** with resolved clarifications
3. **Schedule discussion** for CRITICAL decisions
4. **Address HIGH priority** items during or after CRITICAL
5. **Document edge case policies** (can be done during implementation)
6. **Set performance baselines** (optimize based on real usage)

**Estimated time to resolve CRITICAL items**: 1-2 hours of discussion + 1-2 hours of documentation updates

---

## Document Update Checklist

After user decisions on pending items, update:

- [ ] spec.md - Add NFR section with resolved security/accessibility/usability requirements
- [ ] spec.md - Quantify FR-023a (compression settings), FR-023b (timing), FR-026 (security controls)
- [ ] spec.md - Add FR-023d (file validation), FR-038 (audit logging details)
- [ ] data-model.md - Add constraints section (CHK107-CHK115)
- [ ] data-model.md - Add Documents implementation notes (checksum, polymorphic conventions)
- [ ] contracts/gathering-waivers.md - Add REST conventions, pagination, error formats
- [ ] plan.md - Add Flysystem configuration section
- [ ] plan.md - Reference core entities (Branch, Member)
- [ ] research.md - Update Decision #2 with specific file size limits
- [ ] README.md - Add browser compatibility versions

