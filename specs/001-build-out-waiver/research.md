# Research Document - Gathering Waiver Tracking System
**Feature**: 001-build-out-waiver  
**Date**: 2025-06-19  
**Phase**: 0 - Research

## Purpose
This document resolves all technical unknowns identified during the planning phase. Each item requiring clarification is researched and documented with a decision, rationale, and alternatives considered.

---

## Research Items

### 1. Image-to-PDF Conversion Library

**Question**: Which PHP library should be used for converting JPEG/PNG images to compressed black and white PDFs?

**Options Evaluated**:

1. **Imagick (ImageMagick PHP Extension)**
   - Pros: Powerful image manipulation, excellent compression control, native black and white conversion, widely used
   - Cons: Requires ImageMagick system library, memory intensive for large images
   - Quality: Excellent control over compression and quality settings
   - Performance: Fast for single images, can handle batch processing with memory management

2. **FPDF + TCPDF**
   - Pros: Pure PHP (no extensions required), good PDF generation
   - Cons: Limited image manipulation capabilities, must pre-convert images to black and white separately
   - Quality: Good for simple embedding, limited compression options
   - Performance: Slower for image processing

3. **Intervention Image + FPDF**
   - Pros: Modern PHP image library, chainable API, supports both GD and Imagick drivers
   - Cons: Additional dependency layer, two-step process (convert then embed)
   - Quality: Good, but relies on underlying driver (GD or Imagick)
   - Performance: Moderate

**Decision**: **Imagick (ImageMagick PHP Extension)**

**Rationale**:
- Best compression control for reducing file sizes while maintaining readability
- Native black and white conversion with quality tuning
- Single library handles both conversion and PDF generation
- Well-documented for document scanning use cases
- Already commonly available in PHP hosting environments
- Can leverage ImageMagick's `-compress` options (Group4, Fax, JPEG) for optimal black and white compression

**Implementation Notes**:
```php
// Pseudo-code for conversion process
$imagick = new Imagick($imagePath);
$imagick->setImageType(Imagick::IMGTYPE_BILEVEL); // Black and white
$imagick->setImageFormat('pdf');
$imagick->setImageCompression(Imagick::COMPRESSION_GROUP4); // Fax compression for B&W
$imagick->setImageCompressionQuality(85);
$imagick->writeImage($outputPath);
```

**Fallback Plan**: If Imagick is unavailable in deployment environment, implement adapter pattern with fallback to GD + TCPDF (with reduced quality).

---

### 2. Mobile Camera HTML5 Integration

**Question**: What is the best practice for implementing mobile camera capture in a web application?

**Options Evaluated**:

1. **File Input with `accept="image/*" capture="environment"`**
   - Pros: Simplest implementation, native OS camera handling, works on all modern mobile browsers
   - Cons: Limited control over camera settings (resolution, flash)
   - Browser Support: Excellent (iOS Safari, Android Chrome, Android Firefox)
   
2. **MediaDevices API (getUserMedia)**
   - Pros: Full control over camera, can overlay UI, preview before capture
   - Cons: More complex implementation, requires HTTPS, permission prompts
   - Browser Support: Good, but iOS Safari has limitations
   
3. **Third-Party Libraries (e.g., html5-qrcode, adapter.js)**
   - Pros: Abstracts browser differences, additional features
   - Cons: Additional dependency, bundle size increase

**Decision**: **File Input with `accept="image/*" capture="environment"`**

**Rationale**:
- Simplest and most reliable approach for mobile web apps
- Native OS camera UI is familiar to users
- Automatic handling of permissions
- Works consistently across iOS and Android
- No additional JavaScript complexity
- Supports multiple file selection for batch uploads
- Falls back gracefully on desktop (opens file picker)

**Implementation Pattern**:
```html
<input type="file" 
       accept="image/*" 
       capture="environment" 
       multiple 
       data-controller="waiver-upload"
       data-action="change->waiver-upload#handleFiles">
```

**Stimulus Controller** (`waiver-upload-controller.js`):
- Validate file types (JPEG, PNG)
- Validate file sizes (e.g., max 10MB per image)
- Show preview thumbnails
- Queue files for upload
- Handle batch conversion

**Progressive Enhancement**: For desktop users, provide drag-and-drop upload zone as alternative.

---

### 3. Black and White Compression Techniques

**Question**: What compression method achieves the best file size reduction for black and white document scans?

**Research Findings**:

**Compression Methods**:
1. **Group4 (CCITT T.6)**: Fax standard, lossless, excellent for true black and white
2. **JBIG2**: Superior compression to Group4, but complex implementation
3. **JPEG with high compression**: Lossy, poor for text documents
4. **PNG with palette**: Good for web, but larger file sizes than Group4

**Decision**: **Group4 (CCITT T.6) Compression**

**Rationale**:
- Industry standard for black and white document compression
- Lossless compression maintains readability for legal documents
- Supported natively by ImageMagick
- Excellent compression ratios (typically 10:1 to 20:1 for document scans)
- Compatible with all PDF readers

**Quality Settings**:
- Convert to 1-bit black and white (BILEVEL) before compression
- Resolution: Maintain original or downsample to 300 DPI (sufficient for readability)
- Threshold: Auto-threshold or use Otsu's method for optimal text clarity

**Expected Results**:
- Input: 3-5 MB color JPEG from phone camera
- Output: 100-300 KB black and white PDF
- Typical reduction: 90-95% file size

---

### 4. Configurable Storage Backend

**Question**: How should file storage be abstracted to support both local filesystem and cloud storage (e.g., AWS S3)?

**Options Evaluated**:

1. **Flysystem (PHP League)**
   - Pros: Industry-standard abstraction, many adapters (S3, Azure, local), CakePHP integration available
   - Cons: Additional dependency
   
2. **CakePHP Native (File class)**
   - Pros: Built-in, no dependencies
   - Cons: No cloud storage support, limited features
   
3. **Custom Adapter Pattern**
   - Pros: Lightweight, only implement what's needed
   - Cons: Reinventing the wheel, maintenance burden

**Decision**: **Flysystem (PHP League)**

**Rationale**:
- Mature, well-tested library used by Laravel, Drupal, and other frameworks
- Supports 20+ storage adapters (S3, Azure Blob, Google Cloud, FTP, SFTP, local)
- Consistent API regardless of storage backend
- CakePHP plugin available (`leagues/flysystem-cakephp`)
- Easy configuration via environment variables
- Handles streaming for large files

**Implementation Plan**:
1. Install Flysystem: `composer require league/flysystem-cakephp`
2. Configure in `config/app_local.php`:
```php
'Waiver' => [
    'storage' => env('WAIVER_STORAGE_ADAPTER', 'local'), // 'local' or 's3'
    's3' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
        'bucket' => env('AWS_S3_BUCKET', 'kmp-waivers'),
    ],
    'local' => [
        'path' => WWW_ROOT . 'files' . DS . 'waivers',
    ],
],
```
3. Create `WaiverStorageService` that wraps Flysystem adapter
4. All file operations go through service (save, retrieve, delete)

**Migration Path**: If switching from local to S3, write one-time migration script to copy files using Flysystem's adapter.

---

### 5. Mobile-First UI Framework Patterns

**Question**: What are the best practices for mobile-first forms and navigation in the Gathering Waiver workflow?

**Research Findings**:

**Mobile-First Principles**:
1. **Touch-Friendly Targets**: Minimum 44x44px tap targets
2. **Progressive Disclosure**: Show only relevant fields per step
3. **Minimal Typing**: Use dropdowns, radio buttons, checkboxes instead of text input
4. **Inline Validation**: Real-time feedback on errors
5. **Native Controls**: Leverage HTML5 input types (date, email, tel)

**Multi-Step Workflow Pattern** (for Gathering Creation + Waiver Upload):

**Option 1: Wizard with Turbo Frames**
- Pros: Clear step-by-step progression, easy to implement with Turbo
- Cons: Requires multiple server round-trips

**Option 2: Single Page with Accordion**
- Pros: All content loaded, no server round-trips for navigation
- Cons: More complex state management, heavier initial load

**Decision**: **Wizard with Turbo Frames**

**Rationale**:
- Turbo Frames provide seamless navigation without full page reloads
- Each step loads only when needed (better mobile performance)
- Can save progress at each step (resilient to connection issues)
- Aligns with CakePHP controller actions (one action per step)
- Easy to add validation gates between steps

**Implementation Pattern**:
```html
<!-- Step 1: Select Gathering -->
<turbo-frame id="waiver-workflow" src="/waivers/gatherings/select">
  <!-- Server renders gathering selection form -->
  <!-- On submit: <turbo-frame id="waiver-workflow" src="/waivers/activities/select?gathering_id=X"> -->
</turbo-frame>

<!-- Step 2: Select Activities (auto-loads after Step 1 submit) -->
<!-- Step 3: Upload Waivers (auto-loads after Step 2 submit) -->
```

**Navigation**:
- Back button: Use browser history (Turbo handles this)
- Progress indicator: Breadcrumb at top showing Step 1 of 3, Step 2 of 3, etc.
- Mobile menu: Collapsible sidebar, hamburger icon

---

### 6. Retention Policy Date Calculation

**Question**: How should retention policy date calculations be implemented to ensure accuracy for legal compliance?

**Requirements** (from spec):
- Policies define retention periods: `[{amount: 7, unit: 'years'}, {amount: 6, unit: 'months'}]`
- Support multiple units: years, months, days
- Calculate from gathering end date
- Handle edge cases (leap years, month boundaries)

**Options Evaluated**:

1. **PHP DateTime with `add()` method**
   - Pros: Native PHP, handles leap years and DST automatically
   - Cons: None
   
2. **Carbon (DateTime extension library)**
   - Pros: More readable API, additional helper methods
   - Cons: Additional dependency, CakePHP has native FrozenTime
   
3. **CakePHP FrozenTime**
   - Pros: Immutable dates (prevents bugs), integrated with CakePHP, same API as Carbon
   - Cons: None

**Decision**: **CakePHP FrozenTime**

**Rationale**:
- Already available in CakePHP (no additional dependency)
- Immutable dates prevent accidental mutation bugs (critical for legal dates)
- Carbon-compatible API (e.g., `addYears()`, `addMonths()`, `addDays()`)
- Handles all edge cases (leap years, month boundaries, DST)
- Strong type safety with PHP 7.1+ strict types

**Implementation**:
```php
// RetentionPolicyService::calculateExpirationDate()
use Cake\I18n\FrozenTime;

public function calculateExpirationDate(FrozenTime $gatheringEndDate, array $retentionPeriods): FrozenTime
{
    $expirationDate = $gatheringEndDate;
    
    foreach ($retentionPeriods as $period) {
        $amount = $period['amount'];
        $unit = $period['unit'];
        
        switch ($unit) {
            case 'years':
                $expirationDate = $expirationDate->addYears($amount);
                break;
            case 'months':
                $expirationDate = $expirationDate->addMonths($amount);
                break;
            case 'days':
                $expirationDate = $expirationDate->addDays($amount);
                break;
            default:
                throw new \InvalidArgumentException("Invalid retention unit: {$unit}");
        }
    }
    
    return $expirationDate;
}
```

**Testing Strategy**:
- Unit tests for edge cases:
  - Leap year boundaries (Feb 28 + 1 year)
  - Month boundaries (Jan 31 + 1 month)
  - Multiple periods (7 years + 6 months)
  - DST transitions
- Fixtures with known dates and expected expiration dates

---

### 7. Automated Retention Policy Execution

**Question**: How should the automated deletion of expired waivers be implemented?

**Options Evaluated**:

1. **CakePHP Shell (CLI Task)**
   - Pros: Standard CakePHP approach, easy to test, can run via cron
   - Cons: Requires server cron access
   
2. **Queue Plugin Job**
   - Pros: Built-in to KMP, resilient, can retry failures, monitoring via UI
   - Cons: Depends on queue workers running
   
3. **On-Demand (Manual Button)**
   - Pros: User control, explicit action
   - Cons: Not automated, relies on manual intervention

**Decision**: **Queue Plugin Job** (with manual trigger option)

**Rationale**:
- KMP already uses Queue plugin for background jobs
- Queue provides retry logic for transient failures (e.g., file system issues)
- Queue jobs can be monitored via Queue plugin UI
- Can schedule job to run daily/weekly using Queue's built-in scheduler
- Can also provide manual "Run Now" button for compliance officers
- Safe deletion: job marks waivers as "eligible for deletion", compliance officer reviews, then confirms deletion

**Implementation**:
1. Create `WaiverRetentionJob` in `plugins/Waivers/src/Job/WaiverRetentionJob.php`
2. Job logic:
   - Query gatherings with `end_date + retention_periods < now()`
   - For each expired gathering, find associated waivers
   - Mark waivers as `status = 'expired'` (soft delete, not immediate deletion)
   - Log expiration events for audit trail
3. Separate action: Compliance officer reviews expired waivers, confirms batch deletion
4. Schedule job via Queue plugin configuration to run daily at 2 AM

**Safety Measures**:
- Two-step process: Mark as expired → Confirm deletion
- Audit log of all deletions (who, when, which waivers)
- Backup waivers before deletion (optional)
- Grace period: Wait 30 days after marking expired before final deletion

---

### 8. Generic Document Storage vs Waiver-Specific Storage

**Question**: Should uploaded waiver files be stored directly in GatheringWaivers table, or should we create a generic Documents entity for reusable file storage?

**Options Evaluated**:

1. **Waiver-Specific Storage (Direct Fields in GatheringWaivers)**
   - Pros: Simpler initial implementation, direct relationship, fewer tables
   - Cons: Cannot reuse for other document types, duplicates storage logic, future features require new tables
   - Maintainability: Requires duplicating file management code for each document type

2. **Generic Documents Entity (Polymorphic Pattern)**
   - Pros: Reusable across features (member photos, meeting minutes, financial records), follows KMP's Notes pattern, single DocumentsTable with shared logic
   - Cons: Slightly more complex relationships, requires understanding polymorphic pattern
   - Maintainability: Centralizes file storage logic, easy to add new document types

**Decision**: **Generic Documents Entity with Polymorphic Relationships**

**Rationale**:
- **Established Pattern**: KMP already uses polymorphic pattern for Notes (entity_type + entity_id)
- **Future-Proof**: Enables member photos, meeting minutes, financial records without schema changes
- **Code Reuse**: Single DocumentsTable, DocumentsController, upload service for all document types
- **Consistency**: Follows KMP architectural conventions
- **Separation of Concerns**: Documents handles file storage/metadata, GatheringWaivers handles business logic
- **Query Flexibility**: Can find documents by entity or entities by document

**Implementation Pattern**:
```php
// Documents table (Core - src/Model/)
CREATE TABLE documents (
    id INT PRIMARY KEY,
    entity_type VARCHAR(255),  // 'Waivers.GatheringWaivers', 'Members', etc.
    entity_id INT,             // Polymorphic FK
    uploaded_by INT,           // FK to members
    file_path VARCHAR(255),
    mime_type VARCHAR(100),
    file_size INT,
    checksum VARCHAR(64),      // SHA-256
    storage_adapter VARCHAR(50), // 'local' or 's3'
    metadata JSON,
    // ... audit fields
    INDEX (entity_type, entity_id)  // CRITICAL for polymorphic lookups
);

// GatheringWaivers table (Plugin - plugins/Waivers/src/Model/)
CREATE TABLE gathering_waivers (
    id INT PRIMARY KEY,
    gathering_id INT,
    member_id INT,
    waiver_type_id INT,
    document_id INT UNIQUE,    // One-to-one with Documents
    retention_date DATE,
    status ENUM,
    // ... business logic fields
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT
);

// Usage examples
$waiver->document;  // Access file via association
$this->Documents->find('forEntity', [
    'entity_type' => 'Waivers.GatheringWaivers',
    'entity_id' => $waiverId
]);
```

**Future Use Cases Enabled**:
- Member profile photos: `entity_type='Members'`
- Meeting minutes: `entity_type='Meetings'`
- Financial records: `entity_type='Financial.Transactions'`
- Award certificates: `entity_type='Awards.Recommendations'`

---

## Research Summary

All technical unknowns have been researched and decisions made:

1. ✅ **Image Conversion**: Imagick (ImageMagick) with Group4 compression
2. ✅ **Mobile Camera**: HTML5 file input with `capture="environment"`
3. ✅ **Compression**: Group4 (CCITT T.6) for optimal black and white compression
4. ✅ **Storage**: Flysystem for flexible local/cloud storage
5. ✅ **Mobile UI**: Turbo Frame wizard pattern with progressive disclosure
6. ✅ **Date Calculation**: CakePHP FrozenTime for retention policy dates
7. ✅ **Automated Deletion**: Queue Plugin job with two-step deletion process
8. ✅ **Document Storage**: Generic Documents entity with polymorphic pattern (follows Notes model)

## Next Steps

Proceed to **Phase 1 - Design**:
1. Generate `data-model.md` with complete entity definitions
2. Generate `contracts/` directory with API endpoint specifications
3. Generate `quickstart.md` for developer onboarding
4. Update agent context with new technologies/patterns

**Gate Status**: ✅ Ready to proceed to Phase 1
