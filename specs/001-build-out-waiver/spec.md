# Feature Specification: Gathering Waiver Tracking System

**Feature Branch**: `001-build-out-waiver`  
**Created**: 2025-10-07  
**Status**: Draft  
**Input**: User description: "build out Waiver tracking for gatherings. * Waiver Type is a database configurable PDF templ15. **File Size Limits**: Individual waiver image uploads will be limited to 25MB per file before conversion, which accommodates high-resolution scanned documents. After conversion to compressed black and white PDF, storage size will typically be 1-3MB per document.

16. **Activity Overlap**: If an gathering has multiple activities requiring the same waiver type, the system will consolidate the requirement (waiver appears once, not multiple times).

16. **Activity Overlap**: If an gathering has multiple activities requiring the same waiver type, the system will consolidate the requirement (waiver appears once, not multiple times).

17. **Automatic PDF Conversion**: Image-to-PDF conversion happens synchronously during upload. Users receive immediate confirmation that their image was converted and stored as a PDF. No background processing delay.

18. **Mobile Camera Support**: Gathering stewards can use mobile devices (phones/tablets) to capture waiver images directly using device cameras. HTML5 file input with `capture` attribute enables native camera access on mobile browsers (iOS Safari, Android Chrome). Users can choose between capturing new photos or selecting existing images from device gallery.

19. **Mobile-First Usage Pattern**: Many gathering stewards will use mobile devices at gatherings to photograph signed paper waivers immediately after collection. Mobile camera capture provides faster workflow than scanning later. Desktop/laptop access remains available for users who prefer to scan waivers. which should also include a simple Document retention policy. * Gathering Activity is a database configurable table of activities that might happen at an gathering, an Gathering Activity can have 0 or more Waivers that are a part of that Gathering Activity and of those 0 or more Waivers, the Waivers can be marked as required for that Activity * Gathering Type, a simple configurable list of Gathering types * Gathering, an Gathering consists of a Name, Gathering Type, list of Activities, Branch that ran the gathering, Date(s) the gathering ran, notes about the gathering, Boolean of if Waivers were collected. * If Waivers are collected for an gathering, the list of required waivers will be defined by the Gathering Activities selected for the gathering. Users can then upload 1 or more PDFs per Waiver identified for the Gathering Activity. When a Waiver for an gathering is uploaded, the Retention policy for that waiver based on the Waiver type should be stored with the Waiver in case the policy changes the document will retain the policy it had at time of upload."

## Clarifications

### Session 2025-10-07

- Q: How should retention policies be defined and stored to enable automated expiration calculation? → A: Structured format with separate fields (number + unit + anchor): e.g., 7 years + gathering_end_date
- Q: Where should uploaded waiver PDF files be stored? → A: Configurable - system supports both local and cloud storage via configuration setting
- Q: Who should be authorized to delete waivers that have reached their retention expiration date? → A: Explicit authorization policy (configurable, can be applied to any role via KMP's authorization system)
- Q: What should happen when someone tries to modify an gathering's activities after waivers have been uploaded? → A: Block the change - prevent editing activities once any waivers are uploaded
- Q: How should the system handle multiple users uploading waivers for the same gathering simultaneously? → A: Allow all uploads - no restriction, all PDFs are accepted and added to the gathering

### Session 2025-10-08

- Q: Should Gathering Activity be a core entity or part of the Waivers plugin? → A: **Gathering Activity should be a CORE entity** (`src/Model/`). Gathering Activities (Armored Combat, Archery, Feast, Court) are reusable domain concepts that can be used by multiple plugins: Waivers plugin (track waiver requirements), Awards plugin (track which activity an award was given at), and future plugins (registrations, schedules).
- Q: What is the correct data flow for waiver configuration? → A: **Configuration Flow**: Gathering Type (core) → Gathering Activities (core) → Gathering Activity Waivers (plugin). Gathering Types define categories, Gathering Activities define what happens at gatherings, Gathering Activity Waivers define which waiver types are required for each activity.
- Q: What is the correct data flow for waiver uploads? → A: **Upload Flow**: Gatherings (core) → Gathering Waivers (plugin). Gatherings are specific instances, Gathering Waivers are the actual uploaded files for those gatherings.
- Q: How should existing Awards plugin data be handled? → A: Migrate award_gatherings data to new core Gathering entity; refactor Awards plugin to use core Gathering and optionally Gathering Activity (to track which activity an award was given at).
- Q: What file format should be used for waiver uploads? → A: **Image files (JPEG, PNG, TIFF)** uploaded by users. System automatically converts images to **compressed black and white PDF** to optimize storage space. Conversion happens synchronously during upload with immediate feedback to user. Storage savings: 60-80% compared to original high-resolution scans.
- Q: How should mobile device users capture waiver images? → A: **HTML5 camera capture** using `<input type="file" accept="image/*" capture="environment">`. Mobile users can choose to either **take a new photo directly with device camera** or **select existing image from gallery**. Supports iOS Safari and Android Chrome. Enables on-site waiver collection at gatherings using phones/tablets.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure Waiver Types and Retention Policies (Priority: P1)

As a Kingdom officer, I need to define waiver types (e.g., "Combat Waiver", "Minor's Consent Form", "Photo Release") with PDF templates and document retention policies so that we can maintain proper legal documentation and comply with retention requirements.

**Why this priority**: Without waiver types configured, no other waiver functionality can work. This is the foundational configuration that enables all other user stories.

**Independent Test**: Can be fully tested by creating, editing, and viewing waiver types with retention policies. Delivers immediate value by establishing the legal document library for the kingdom.

**Acceptance Scenarios**:

1. **Given** I am a Kingdom officer, **When** I create a new waiver type with a name, PDF template URL, and retention policy (e.g., "7 years from gathering date"), **Then** the waiver type is saved and available for use in gathering activities
2. **Given** a waiver type exists, **When** I edit its retention policy, **Then** the policy is updated but does not affect already-uploaded waivers (they retain their original policy)
3. **Given** multiple waiver types exist, **When** I view the waiver type list, **Then** I see all waiver types with their retention policies clearly displayed
4. **Given** I am creating a waiver type, **When** I provide a PDF template URL, **Then** the system validates that it's accessible and is a PDF document

---

### User Story 2 - Configure Gathering Types and Activities (Priority: P2)

As a Kingdom officer, I need to define gathering types (e.g., "Tournament", "Practice", "Arts & Sciences") and gathering activities (e.g., "Armored Combat", "Archery", "Fencing") with their associated required waivers so that gathering stewards know which waivers must be collected.

**Why this priority**: This builds on the waiver types (P1) and establishes the gathering framework. It's needed before creating actual gatherings but is secondary to defining the waiver types themselves.

**Independent Test**: Can be fully tested by creating gathering types, creating activities, and associating waivers with activities. Delivers value by providing a standardized catalog of gathering configurations.

**Acceptance Scenarios**:

1. **Given** I am a Kingdom officer, **When** I create a new gathering type with a name, **Then** the gathering type is saved and available for gathering creation
2. **Given** I am a Kingdom officer, **When** I create a new gathering activity with a name, **Then** the activity is saved
3. **Given** an gathering activity exists and waiver types are defined, **When** I associate waiver types with the activity, **Then** I can mark each associated waiver as required or optional
4. **Given** an gathering activity has associated waivers, **When** I view the activity details, **Then** I see all associated waivers with clear indication of which are required
5. **Given** I am editing an gathering activity, **When** I add or remove waiver associations, **Then** the changes are saved and do not affect existing gatherings (they retain their original waiver requirements)

---

### User Story 3 - Create and Manage Gatherings (Priority: P3)

As an gathering steward or branch officer, I need to create gatherings with basic information (name, type, branch, dates, activities) and indicate whether waivers were collected so that we can track gathering occurrences and their waiver requirements.

**Why this priority**: This depends on gathering types and activities (P2) being configured. It enables gathering tracking but doesn't yet include waiver uploads, which is the most complex part.

**Independent Test**: Can be fully tested by creating gatherings with various configurations, selecting activities, and marking waiver collection status. Delivers value by providing gathering record-keeping and waiver requirement determination.

**Acceptance Scenarios**:

1. **Given** I am an gathering steward, **When** I create a new gathering with name, gathering type, branch, start date, end date, and notes, **Then** the gathering is saved
2. **Given** I am creating an gathering, **When** I select gathering activities from the available list, **Then** the system automatically determines the required waivers based on those activities
3. **Given** an gathering has activities with required waivers, **When** I view the gathering details, **Then** I see a list of all required waivers for this gathering
4. **Given** I am creating an gathering, **When** I mark "waivers were collected" as true, **Then** the system enables waiver upload functionality for this gathering
5. **Given** I am creating an gathering, **When** I mark "waivers were collected" as false, **Then** the system does not require or allow waiver uploads
6. **Given** an gathering spans multiple days, **When** I provide start and end dates, **Then** the system accepts the date range and displays it correctly
7. **Given** I have appropriate permissions, **When** I edit an existing gathering that has no uploaded waivers, **Then** I can modify all gathering details including activities
8. **Given** an gathering has uploaded waivers, **When** I attempt to modify the gathering's activities, **Then** the system prevents the change and displays a message explaining that activities are locked once waivers are uploaded

---

### User Story 4 - Upload and Manage Gathering Waivers (Priority: P4)

As an gathering steward, I need to upload signed waiver PDFs for gatherings where waivers were collected so that we maintain legal documentation and comply with retention policies.

**Why this priority**: This is the final piece that depends on all previous stories. It's the most complex functionality but provides the complete waiver tracking system.

**Independent Test**: Can be fully tested by uploading waivers to gatherings, viewing uploaded waivers, and verifying retention policies are captured. Delivers complete waiver management capability.

**Acceptance Scenarios**:

1. **Given** an gathering has "waivers collected" marked as true, **When** I navigate to the gathering's waiver upload section, **Then** I see a list of all required waivers for this gathering with image upload interfaces
2. **Given** I am uploading a waiver for an gathering, **When** I select an image file (JPEG, PNG, or TIFF) and specify which waiver type it represents, **Then** the system converts the image to compressed black and white PDF, uploads it, and stores the current retention policy from the waiver type with the upload record
3. **Given** I am uploading a waiver image, **When** the conversion completes, **Then** I receive immediate confirmation that my image was converted to PDF and stored successfully
4. **Given** a required waiver for an gathering, **When** I upload multiple image files for that waiver (e.g., batch of signed forms), **Then** the system accepts all uploads, converts each to PDF, and associates them with the gathering and waiver type
5. **Given** waivers have been uploaded for an gathering, **When** I view the gathering details, **Then** I see a count of how many converted PDFs exist for each required waiver
6. **Given** I am viewing uploaded waivers, **When** I check a waiver's retention policy, **Then** I see the policy that was active at the time of upload, not the current policy
7. **Given** a waiver's retention period has expired, **When** I view the waiver list, **Then** the system clearly indicates which waivers are eligible for deletion based on their captured retention policy
8. **Given** I have appropriate permissions, **When** I download an uploaded waiver PDF, **Then** the converted PDF file downloads successfully with appropriate security checks
9. **Given** I am uploading a waiver image, **When** I select a non-image file (e.g., Word document), **Then** the system rejects the upload with a clear error message indicating only image files are accepted
10. **Given** I am using a mobile device, **When** I click the upload button, **Then** the system prompts me to either take a new photo with my camera or choose an existing photo from my gallery
11. **Given** I am using a mobile device, **When** I choose to take a photo and capture an image, **Then** the system immediately converts the photo to PDF and uploads it with confirmation
12. **Given** I am at an gathering with signed waivers, **When** I use my mobile phone to photograph multiple waivers in sequence, **Then** I can upload them one by one or in batch, each converting to PDF automatically

---

### User Story 5 - Search and Report on Gathering Waivers (Priority: P5)

As a Kingdom officer or legal compliance officer, I need to search for gatherings and their associated waivers and generate reports so that I can respond to legal inquiries and manage document retention.

**Why this priority**: This is an enhancement that provides searchability and reporting on top of the core waiver tracking functionality. Valuable but not essential for initial operation.

**Independent Test**: Can be fully tested by searching gatherings by various criteria and generating reports. Delivers value through improved accessibility and compliance management.

**Acceptance Scenarios**:

1. **Given** multiple gatherings exist, **When** I search for gatherings by date range, branch, or gathering type, **Then** I see filtered results with waiver collection status
2. **Given** I am viewing search results, **When** I filter to show only gatherings where waivers were collected, **Then** I see only those gatherings
3. **Given** I need to respond to a legal inquiry, **When** I search for gatherings involving a specific member and activity within a date range, **Then** I can identify which converted waiver PDFs may contain that member's signature
4. **Given** I am managing retention compliance, **When** I generate a report of waivers eligible for deletion, **Then** I see all converted PDFs whose retention period has expired with their gathering and date information
5. **Given** I am viewing gathering statistics, **When** I generate a compliance report, **Then** I see what percentage of gatherings have waiver collection marked and what percentage have waivers uploaded and converted

---

### Edge Cases

- What happens when an gathering activity's waiver requirements change after gatherings using that activity already exist? (Answer: Existing gatherings retain their original waiver requirements; only new gatherings use the updated requirements)
- What happens when a waiver type's retention policy changes after waivers have been uploaded? (Answer: Uploaded waivers retain the retention policy that was active at upload time)
- What happens when an gathering steward tries to upload a non-image file as a waiver? (Answer: System validates file type and rejects non-image files with clear error message indicating only JPEG, PNG, and TIFF are accepted)
- What happens when an gathering has multiple activities that require the same waiver type? (Answer: The waiver appears once in the required list, with multiple images/converted PDFs uploadable)
- What happens when someone tries to upload waivers for an gathering marked as "waivers not collected"? (Answer: System prevents waiver uploads and displays message explaining why)
- What happens when trying to delete an gathering that has uploaded waivers? (Answer: System prevents deletion or requires explicit confirmation with warning about losing waiver records)
- What happens when someone tries to edit an gathering's activities after waivers have been uploaded? (Answer: System blocks the change and displays a message explaining that activities cannot be modified once waivers are uploaded)
- What happens when multiple users upload waivers to the same gathering simultaneously? (Answer: System accepts all uploads concurrently without restrictions; all images are converted and stored as PDFs)
- What happens when an image upload fails during transmission or conversion? (Answer: System reports error clearly and allows retry without losing other uploaded waivers)
- What happens when viewing retention period for waivers from a multi-day gathering? (Answer: Retention period is calculated from the gathering end date, not start date)
- What happens when an uploaded image is corrupted or unreadable? (Answer: System detects invalid image during validation and rejects upload with clear error message before attempting conversion)
- What happens to very large high-resolution scanned images? (Answer: System converts them to compressed black and white PDF, significantly reducing file size while maintaining legibility)
- What happens when a mobile user's camera permissions are denied? (Answer: System falls back to file gallery selection mode; displays message explaining camera permission is optional but convenient)
- What happens when a mobile user takes a blurry or poorly lit photo? (Answer: System still converts and uploads the image; responsibility for photo quality is on the user)
- What happens when using mobile camera in landscape vs portrait orientation? (Answer: System accepts images in any orientation; converted PDF maintains original orientation)
- What happens when a mobile device runs out of storage during camera capture? (Answer: Device's native camera app handles the error; system never receives the image so no conversion attempted)
- What happens on older mobile browsers that don't support the capture attribute? (Answer: Falls back to standard file input; user can still select images from gallery but won't get direct camera prompt)

## Requirements *(mandatory)*

### Functional Requirements

**Waiver Type Management**:
- **FR-001**: System MUST allow authorized users to create, edit, and view waiver types
- **FR-002**: Each waiver type MUST have a unique name, PDF template reference (URL or file path), and document retention policy
- **FR-003**: Retention policy MUST use structured format with three components: duration number (integer), duration unit (years/months/days), and anchor point (gathering_end_date/upload_date/permanent). Examples: "7 years from gathering_end_date", "5 years from upload_date", "permanent"
- **FR-004**: System MUST prevent deletion of waiver types that are referenced by gathering activities or have uploaded waivers
- **FR-005**: When a waiver type's retention policy is updated, existing uploaded waivers MUST retain their original retention policy

**Gathering Type Management**:
- **FR-006**: System MUST allow authorized users to create, edit, and view gathering types
- **FR-007**: Each gathering type MUST have a unique name
- **FR-008**: System MUST prevent deletion of gathering types that are referenced by existing gatherings

**Gathering Activity Management**:
- **FR-009**: System MUST allow authorized users to create, edit, and view gathering activities
- **FR-010**: Each gathering activity MUST have a unique name
- **FR-011**: System MUST allow associating zero or more waiver types with each gathering activity
- **FR-012**: For each waiver type associated with an activity, system MUST allow marking it as required or optional
- **FR-013**: System MUST prevent deletion of gathering activities that are referenced by existing gatherings
- **FR-014**: When an gathering activity's waiver associations are modified, existing gatherings MUST retain their original waiver requirements

**Gathering Management**:
- **FR-015**: System MUST allow authorized users to create, edit, and view gatherings
- **FR-016**: Each gathering MUST have a name, gathering type, branch, start date, end date, notes field, and "waivers collected" boolean
- **FR-017**: System MUST allow associating multiple gathering activities with each gathering
- **FR-017a**: System MUST prevent editing an gathering's associated activities once any waivers have been uploaded for that gathering
- **FR-018**: System MUST automatically compile the list of required waivers based on selected gathering activities
- **FR-019**: System MUST support single-day and multi-day gatherings (start date may equal end date)
- **FR-020**: System MUST display the complete list of required waivers for each gathering where waivers were collected
- **FR-021**: System MUST prevent waiver uploads when "waivers collected" is marked false

**Waiver Upload and Storage**:
- **FR-022**: System MUST allow uploading image files (JPEG, PNG, TIFF) as waivers for gatherings marked with "waivers collected" as true
- **FR-022a**: System MUST provide HTML file input with mobile camera capture support using `accept="image/*"` and `capture="environment"` attributes for direct camera access on mobile devices
- **FR-022b**: System MUST allow users to choose between taking a new photo with their device camera or selecting existing images from their device gallery
- **FR-023**: System MUST validate that uploaded files are valid image formats (JPEG, PNG, TIFF) and reject other file types
- **FR-023a**: System MUST automatically convert uploaded image files to compressed black and white PDF format
- **FR-023b**: System MUST perform image-to-PDF conversion synchronously during upload and provide immediate feedback to user
- **FR-023c**: System MUST optimize converted PDFs for storage efficiency (target: 1-3MB per converted document)
- **FR-024**: System MUST allow uploading multiple image files for each required waiver type (each converted to separate PDF)
- **FR-024a**: System MUST support concurrent waiver uploads by multiple users for the same gathering without restrictions (all uploads are accepted, converted, and added to the gathering)
- **FR-025**: When a waiver is uploaded and converted, system MUST capture and store the retention policy from the waiver type at that moment
- **FR-026**: System MUST securely store converted waiver PDFs with access controls based on user permissions
- **FR-026a**: System MUST support configurable file storage backend (local filesystem or cloud storage) for converted waiver PDFs
- **FR-027**: System MUST associate each uploaded waiver with the gathering, waiver type, upload date, uploader identity, and captured retention policy
- **FR-028**: System MUST display upload count for each required waiver type per gathering

**Retention and Compliance**:
- **FR-029**: System MUST calculate waiver retention expiration dates based on the captured retention policy's structured format (duration + unit + anchor) and the relevant date (gathering end date or upload date)
- **FR-030**: System MUST identify waivers eligible for deletion based on expired retention periods
- **FR-031**: System MUST prevent accidental deletion of waivers that have not reached retention expiration
- **FR-031a**: System MUST enforce waiver deletion authorization through explicit authorization policy that can be applied to any role via KMP's existing authorization system
- **FR-032**: System MUST provide clear indication of which waivers are within retention period and which have expired

**Search and Reporting**:
- **FR-033**: System MUST allow searching gatherings by date range, branch, gathering type, and waiver collection status
- **FR-034**: System MUST allow filtering gatherings to show only those where waivers were collected
- **FR-035**: System MUST allow generating reports of waivers eligible for deletion based on retention policy expiration
- **FR-036**: System MUST track and display waiver collection compliance metrics (percentage of gatherings with waivers collected and uploaded)

**Security and Permissions**:
- **FR-037**: System MUST enforce role-based access control for waiver configuration, gathering creation, and waiver uploads
- **FR-038**: System MUST log all waiver uploads with user identity, timestamp, and gathering association
- **FR-039**: System MUST restrict waiver PDF downloads to authorized users only
- **FR-040**: System MUST integrate with existing KMP authorization policies for branch-level and kingdom-level permissions

### Key Entities

#### Core Entities (KMP Core Application)

Located in `src/Model/Entity/` and `src/Model/Table/`:

- **Gathering Type**: Simple classification of gatherings (e.g., "Tournament", "Practice", "Arts & Sciences Day", "Feast"). Contains: unique name, description, active status. Related to Gatherings (one-to-many) and Gathering Activities (one-to-many). **Used by**: Awards plugin, Waivers plugin, future plugins.

- **Gathering**: A specific occurrence of an SCA gathering. Contains: name, gathering type reference (FK), branch reference (FK), start date, end date, location, notes. Related to Gathering Type (many-to-one), Branch (many-to-one). **Note**: Replaces/consolidates award_gatherings from Awards plugin. **Used by**: Awards plugin (track where awards were given), Waivers plugin (track waiver uploads for gatherings), future plugins.

- **Gathering Activity**: Specific activities that occur at gatherings (e.g., "Armored Combat", "Rapier Combat", "Archery", "Feast", "Court"). Contains: unique name, description, gathering_type_id (FK), active status. Related to Gathering Type (many-to-one). **Used by**: Waivers plugin (determine waiver requirements), Awards plugin (track which activity an award was given at), future plugins (registrations, schedules).

#### Waivers Plugin Entities

Located in `plugins/Waivers/src/Model/Entity/` and `plugins/Waivers/src/Model/Table/`:

- **Waiver Type**: Represents a category of legal waiver document (e.g., "Adult Combat Waiver", "Minor Waiver", "Archery Waiver"). Contains: unique name, description, PDF template reference, current retention policy (structured: duration + unit + anchor), active status. Related to Gathering Activity Waivers (one-to-many) and Gathering Waivers (one-to-many).

- **Gathering Activity Waiver**: Junction entity linking core Gathering Activities to required Waiver Types. Contains: gathering_activity_id (FK to core Gathering Activity), waiver_type_id (FK), required boolean (indicates if waiver is required or optional for this activity). Defines which waivers are needed for which activities. Example: "Armored Combat" activity requires "Adult Combat Waiver".

- **Gathering Waiver**: A converted PDF file from uploaded waiver image. Contains: gathering_id (FK to core Gathering), member_id (FK), waiver_type_id (FK), converted PDF file reference/path, original_filename, upload date, uploaded_by_id (FK), captured retention policy (from waiver type at upload time), calculated expiration date, status. Related to Gathering (many-to-one), Member (many-to-one), and Waiver Type (many-to-one). Tracks waiver images uploaded and converted to compressed black and white PDFs for specific gatherings.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Kingdom officers can configure all waiver types, gathering types, and gathering activities within 1 hour of system launch
- **SC-002**: Gathering stewards can create a new gathering with activities and determine required waivers in under 5 minutes
- **SC-003**: Gathering stewards can upload and convert batch waiver images (10-50 files) for an gathering within 10 minutes, with conversion happening synchronously
- **SC-004**: Officers can identify waivers eligible for deletion based on retention policies in under 2 minutes
- **SC-005**: System maintains 100% accuracy in capturing retention policies with uploaded waivers (policy at upload time is preserved)
- **SC-006**: 95% of gathering stewards successfully upload and convert waiver images without requiring support assistance
- **SC-007**: Legal compliance officers can locate all converted waiver PDFs for a specific gathering or time period within 3 minutes
- **SC-008**: System reduces time spent on manual waiver tracking by 75% compared to spreadsheet-based tracking
- **SC-009**: Zero instances of waiver retention policy confusion (captured policy always matches what was active at upload)
- **SC-010**: Gathering waiver collection compliance improves to 90% of applicable gatherings within 6 months of deployment
- **SC-011**: Image-to-PDF conversion reduces average storage requirements by 60-80% compared to storing original high-resolution scanned images
- **SC-012**: 98% of uploaded images convert successfully to compressed black and white PDF on first attempt
- **SC-013**: Mobile device users can successfully capture and upload waiver photos directly from their device camera without additional training
- **SC-014**: At least 70% of waiver uploads occur via mobile devices using camera capture within 3 months of deployment (indicating mobile-first adoption)
- **SC-015**: Average time to upload a single waiver via mobile camera capture is under 30 seconds from camera open to conversion confirmation

## Assumptions

1. **Image Upload with PDF Conversion**: Gathering stewards will upload waiver documents as image files (JPEG, PNG, TIFF - common scanner output formats). The system will automatically convert uploaded images to compressed black and white PDF format to optimize storage space. Final storage format is always PDF.

2. **Retention Policy Format**: Retention policies use a structured format with three fields: duration number (integer), duration unit (years/months/days), and anchor point (gathering_end_date/upload_date/permanent). The system calculates expiration dates based on this structured data.

3. **Single Branch Per Gathering**: Each gathering is run by one primary branch, though the gathering may involve participants from multiple branches.

4. **Waiver Template Storage**: Waiver type PDF templates are stored externally (file server or cloud storage) and referenced by URL/path. The system stores the reference, not the template file itself.

5. **Uploaded Waiver Storage**: Uploaded waiver PDFs can be stored either on local filesystem or cloud storage, configured at deployment time. The system abstracts the storage mechanism to support both options.

6. **Batch Uploads**: Gathering stewards will upload waiver PDFs one at a time or in small batches through a web interface. Large-scale bulk imports are not required for initial release.

7. **Concurrent Uploads**: Multiple users may upload waivers to the same gathering simultaneously. The system handles concurrent uploads without locking or queuing; all uploads are accepted and stored.

8. **Member Signature Tracking**: Individual member signatures within waiver PDFs are not tracked or indexed. Waivers are tracked at the document level per gathering, not per member per waiver.

9. **Existing KMP Integration**: The system will integrate with existing KMP entities (Branches, Members, Authorization Policies) using established patterns.

10. **Manual Retention Enforcement**: The system will identify waivers eligible for deletion but will not automatically delete them. Deletion will be a manual administrative action with appropriate safeguards, controlled by authorization policy.

11. **Gathering Immutability After Upload**: Once waivers are uploaded for an gathering, the gathering's activity associations become immutable to preserve data integrity and prevent orphaned waivers.

12. **Core vs Plugin Architecture**: Gathering, Gathering Type, and Gathering Activity are core KMP entities available to all plugins. Waiver-specific functionality (Waiver Type, Gathering Activity Waiver, Gathering Waiver) resides in the Waivers plugin.

13. **Awards Plugin Migration**: Existing award_gatherings data will be migrated to the core Gathering entity. The Awards plugin will be refactored to use core Gathering and optionally Gathering Activity (to track which activity an award was given at), eliminating the award_gatherings table.

14. **Cross-Plugin Compatibility**: The Waivers plugin references core Gathering and Gathering Activity entities via foreign keys. Core Gatherings and Activities remain functional without the Waivers plugin installed (waiver-related associations are optional). Gathering Activities can be used by any plugin for activity tracking.

15. **File Size Limits**: Individual waiver PDF uploads will be limited to 10MB per file, which accommodates scanned multi-page documents.

16. **Activity Overlap**: If an gathering has multiple activities requiring the same waiver type, the system will consolidate the requirement (waiver appears once, not multiple times).

17. **DPI and Pixel Size Minimums**: Individual waiver PDF uploads will be at least 100DPI at 8"x11" or 2048x2048px in raw pixel sizes. The PDF may be bigger but not smaller so that it is readable.

## Architecture Overview

### Core vs Plugin Structure

**Core KMP Entities** (Broadly Applicable):
- **Gathering Type**: Classification system for all gatherings across the kingdom (Tournament, Feast, Workshop, Practice)
- **Gathering**: Represents any SCA gathering occurrence (tournaments, practices, meetings, etc.)
- **Gathering Activity**: Specific activities that happen at gatherings (Armored Combat, Archery, Feast, Court, etc.)
- These entities are core because they have utility beyond waivers - they can be used by Awards (track which activity an award was given at), Waivers (track which activities require waivers), Officers, and other plugins

**Waivers Plugin Entities** (Waiver-Specific):
- **Waiver Type**: Legal document categories with retention policies (Adult Combat, Minor, Archery)
- **Gathering Activity Waiver**: Junction entity linking Gathering Activities (core) to required Waiver Types
- **Gathering Waiver**: Actual waiver PDFs uploaded for gatherings
- This plugin references core entities via foreign keys but keeps waiver-specific logic isolated

**Data Flow**:
- **Configuration**: Gathering Type (core) → Gathering Activities (core) → Gathering Activity Waivers (plugin)
  - Gathering Types define categories
  - Gathering Activities define what happens at gatherings
  - Gathering Activity Waivers define which waiver types are required for each activity
- **Waiver Upload**: Gatherings (core) → Gathering Waivers (plugin)
  - Gatherings are specific instances
  - Gathering Waivers are the actual uploaded files for those gatherings

**Rationale**: 
- Gatherings and activities are fundamental SCA concepts that multiple systems need to track
- Gathering activities are reusable domain concepts (combat, archery, feast) independent of waivers
- Waivers are a specialized legal/compliance concern best isolated in a plugin
- This separation allows the Awards plugin to track "Best Fighter at Armored Combat activity" and Waivers plugin to track "Combat Waiver required for Armored Combat activity" using the same Gathering Activity entity
- Clear plugin boundaries enable independent testing and optional deployment

### Migration Strategy

**Phase 1: Core Gathering System**
1. Create Gathering Type, Gathering, and Gathering Activity entities in core KMP (`src/Model/`)
2. Gathering Activities can be associated with Gathering Types (defining typical activities for gathering categories)
3. Migrate data from Awards plugin's award_gatherings table to new core Gathering
4. Maintain backward compatibility during transition
5. Test Gathering and Activity CRUD operations independently

**Phase 2: Waivers Plugin**
1. Create Waivers plugin structure (`plugins/Waivers/`)
2. Implement Waiver Type entity (Adult Combat, Minor, etc.)
3. Implement Gathering Activity Waiver junction entity (links core Gathering Activities to required Waiver Types)
4. Implement Gathering Waiver entity (uploaded files linked to core Gatherings)
5. Create foreign key relationships to core Gathering and Gathering Activity entities
6. Test Waivers plugin functionality

**Phase 3: Awards Plugin Refactoring**
1. Refactor Awards plugin to reference core Gathering and Gathering Activity entities
2. Optionally link awards to Gathering Activities (e.g., "Best Fighter" at "Armored Combat" activity)
3. Remove award_gatherings table (data already migrated)
4. Update Awards plugin queries, relationships, and tests

**Phase 4: Integration Testing**
1. Verify Awards plugin functionality with core Gatherings and Activities
2. Test Waivers plugin integration with core Gatherings and Activities
3. Test both plugins coexisting
4. Validate data migration completeness
5. Verify activity reusability across plugins

## Dependencies

1. **Existing KMP Branch System**: Gatherings must be associated with existing branches in the KMP system
2. **Awards Plugin**: Requires data migration from award_gatherings to new core Gathering entity; Awards plugin will be refactored to use core Gathering
3. **File Storage Infrastructure**: Requires secure file storage for converted PDF files (file system or cloud storage)
4. **Image Validation Library**: Requires ability to validate uploaded files are valid image formats (JPEG, PNG, TIFF)
5. **Image-to-PDF Conversion Library**: Requires library to convert images to compressed black and white PDF (e.g., Imagick/ImageMagick with Ghostscript, or FPDF/TCPDF with GD)
6. **Authorization System**: Integrates with existing KMP authorization policies and role system
7. **CakePHP Framework**: Built on existing KMP CakePHP 5.x foundation
8. **Existing Member System**: May need to reference members for permission checks (who can create gatherings, upload waivers)
9. **Plugin System**: Waivers plugin will depend on core Gathering and Gathering Type entities

## Out of Scope

1. **Digital Signature Collection**: This feature tracks uploaded paper waivers (scanned to PDF). Digital signature collection within the application is not included.
2. **Waiver Template Creation**: The system references waiver templates but does not include tools to create or edit the PDF templates themselves.
3. **OCR or Text Extraction**: The system does not read or extract text from waiver PDFs. No member name extraction or signature recognition.
4. **Automated Waiver Reminders**: No automatic notifications to gathering stewards about missing waivers.
5. **Waiver Statistics Per Member**: No tracking of which members have signed which waivers across multiple gatherings.
6. **Versioning of Waiver Templates**: If a waiver template PDF changes, the system does not track versions. Only the retention policy is captured with uploads.
7. **Integration with External Legal Systems**: No integration with external legal document management systems.
8. **Mobile App for Waiver Upload**: Initial release is web-only. Native mobile apps for waiver upload are not included.
9. **Waiver Preview/Rendering**: The system stores and serves PDF files but does not render them inline. Users download PDFs to view them.
10. **Audit Trail Beyond Upload**: The system logs uploads but does not track who viewed or downloaded waivers after upload.
