# Data Model - Gathering Waiver Tracking System
**Feature**: 001-build-out-waiver  
**Date**: 2025-06-19  
**Phase**: 1 - Design

## Overview

This document defines the complete data model for the Gathering Waiver Tracking System, including both core entities and plugin-specific entities.

**Entity Count**: 10 entities total
- **Core Entities**: 4 (GatheringTypes, Gatherings, GatheringActivities, Documents)
- **Plugin Entities**: 5 (WaiverTypes, GatheringActivityWaivers, GatheringWaivers, GatheringWaiverActivities, WaiverConfiguration)
- **Existing Reference**: 1 (Members)

**Key Design Decisions**: 
- Many-to-many relationship between GatheringWaivers and GatheringActivities allows flexible waiver coverage (one waiver can cover multiple activities, one activity can have multiple waivers)
- Generic Documents entity using polymorphic pattern (like Notes) enables reuse for future document management needs (member photos, meeting minutes, financial records, etc.)
- GatheringWaivers references Documents via polymorphic relationship, enabling waiver-specific metadata while leveraging generic file storage

---

## Entity Relationship Diagram (ERD)

### Mermaid Diagram

```mermaid
erDiagram
    %% Core Entities
    GatheringTypes ||--o{ Gatherings : "has many"
    Gatherings ||--o{ GatheringsGatheringActivities : "has many"
    GatheringActivities ||--o{ GatheringsGatheringActivities : "used in"
    Gatherings ||--o{ GatheringWaivers : "has many"
    Members ||--o{ Gatherings : "creates"
    Members ||--o{ GatheringWaivers : "owns"
    Branches ||--o{ Gatherings : "hosts"
    
    %% Generic Document Storage (Polymorphic)
    Members ||--o{ Documents : "uploads"
    Documents }o--o{ GatheringWaivers : "when entity_type='Waivers.GatheringWaivers'"
    Documents }o--o{ Members : "when entity_type='Members' (future: profile photos)"
    
    %% Plugin Entities - Waiver Requirements
    GatheringActivities ||--o{ GatheringActivityWaivers : "requires"
    WaiverTypes ||--o{ GatheringActivityWaivers : "used by"
    WaiverTypes ||--o{ GatheringWaivers : "used by"
    
    %% Plugin Entities - Waiver Coverage (many-to-many)
    GatheringActivities ||--o{ GatheringWaiverActivities : "covered by"
    GatheringWaivers ||--o{ GatheringWaiverActivities : "covers"
    
    %% Core Entities Definition
    GatheringTypes {
        int id PK
        varchar name UK "UNIQUE"
        text description
        boolean clonable
        datetime created
        datetime modified
    }
    
    Gatherings {
        int id PK
        int branch_id FK
        int gathering_type_id FK
        varchar name
        text description
        date start_date
        date end_date
        varchar location
        int created_by FK
        datetime created
        datetime modified
    }
    
    GatheringActivities {
        int id PK
        varchar name "Configuration/template object"
        text description
        datetime created
        datetime modified
    }
    
    GatheringsGatheringActivities {
        int id PK
        int gathering_id FK "Which gathering"
        int gathering_activity_id FK "Which activity"
        int sort_order "Display order"
        datetime created
        datetime modified
    }
    
    Members {
        int id PK
        varchar sca_name
        varchar legal_name
        varchar email
        varchar phone
    }
    Branches {
        int id PK
        varchar name
    }
    
    Documents {
        int id PK
        varchar entity_type "Polymorphic"
        int entity_id "Polymorphic"
        int uploaded_by FK
        varchar original_filename
        varchar stored_filename
        varchar file_path UK "UNIQUE"
        varchar mime_type
        int file_size
        varchar checksum "SHA-256"
        varchar storage_adapter "local|s3"
        json metadata
        datetime created
        datetime modified
        int created_by FK
        int modified_by FK "NULLABLE"
    }
    
    %% Plugin Entities Definition
    WaiverTypes {
        int id PK
        varchar name UK "UNIQUE"
        text description
        varchar template_path
        json retention_periods
        tinyint is_active
        datetime created
        datetime modified
    }
    
    GatheringActivityWaivers {
        int id PK
        int gathering_activity_id FK
        int waiver_type_id FK
        datetime created
        datetime modified
    }
    
    GatheringWaivers {
        int id PK
        int gathering_id FK
        int waiver_type_id FK
        int member_id FK "NULLABLE"
        int document_id FK UK "UNIQUE"
        date retention_date
        enum status "active|expired|deleted"
        text notes "NULLABLE"
        datetime created
        datetime modified
        int created_by FK
        int modified_by FK "NULLABLE"
    }
    
    GatheringWaiverActivities {
        int id PK
        int gathering_waiver_id FK
        int gathering_activity_id FK
        datetime created
        datetime modified
    }
    
```

### Visual Summary

**Core Entities** (broadly reusable):
- `GatheringTypes` → `Gatherings` ↔ `GatheringsGatheringActivities` ↔ `GatheringActivities` (gathering structure with many-to-many)
  - Note: `GatheringActivities` are configuration/template objects that can be reused across many gatherings
- `Documents` (polymorphic file storage for all document types)

**Plugin Entities** (waiver-specific):
- `WaiverTypes` ↔ `GatheringActivityWaivers` ↔ `GatheringActivities` (defines which waivers are required)
- `GatheringWaivers` (waiver metadata + reference to Documents)
- `GatheringWaiverActivities` (joins waivers to activities they cover - many-to-many)

**Polymorphic Pattern**:
- GatheringWaivers references Documents via `document_id` (one-to-one)
- Documents identifies parent entity via `entity_type='Waivers.GatheringWaivers'` + `entity_id`
- This pattern enables future document types (member photos, meeting minutes, etc.) without schema changes

**Key Relationships**:
- `Members` create `Gatherings` and upload `Documents`
- `Gatherings` have `GatheringWaivers` (waiver metadata)
- `GatheringWaivers` reference `Documents` (actual files)
- `Documents` use polymorphic pattern to link back to any entity type

**Entity Location**:
- Core entities (GatheringTypes, Gatherings, GatheringActivities, Documents) → `src/Model/`
- Plugin entities (WaiverTypes, GatheringActivityWaivers, GatheringWaivers, GatheringWaiverActivities, WaiverConfiguration) → `plugins/Waivers/src/Model/`
- **Many-to-Many**: A single waiver can cover multiple activities (e.g., general waiver covers all), and an activity can have multiple waivers (different participants, multiple days)

### Workflow Diagram

```mermaid
flowchart TD
    Start([Officer Creates Configuration]) --> ConfigTypes[Configure Gathering Types]
    ConfigTypes --> ConfigWaivers[Configure Waiver Types]
    ConfigWaivers --> ConfigActivities[Link Activities to Waivers]
    
    ConfigActivities --> CreateGathering[Steward Creates Gathering]
    CreateGathering --> SelectType[Select Gathering Type]
    SelectType --> AddActivities[Add Activities to Gathering]
    AddActivities --> AutoLink[System Auto-Links Required Waivers]
    
    AutoLink --> Upload[Upload Waiver Images]
    Upload --> Camera{Mobile Device?}
    Camera -->|Yes| MobileCamera[HTML5 Camera Capture]
    Camera -->|No| Desktop[Drag & Drop Upload]
    
    MobileCamera --> Convert[Image to PDF Conversion]
    Desktop --> Convert
    Convert --> Compress[Black & White Compression]
    Compress --> Calculate[Calculate Retention Date]
    Calculate --> Store[Store in Flysystem]
    Store --> Record[Create GatheringWaiver Record]
    
    Record --> Queue[Queue Retention Check Job]
    Queue --> Wait{Retention Period Passed?}
    Wait -->|No| Queue
    Wait -->|Yes| MarkExpired[Mark as Expired]
    MarkExpired --> Review[Compliance Officer Reviews]
    Review --> Delete[Confirm Deletion]
    
    style ConfigTypes fill:#e1f5ff
    style ConfigWaivers fill:#e1f5ff
    style ConfigActivities fill:#e1f5ff
    style CreateGathering fill:#fff4e6
    style Upload fill:#e8f5e9
    style Convert fill:#f3e5f5
    style Store fill:#f3e5f5
    style Delete fill:#ffebee
```



---

## Core Entities (in `src/Model/`)

### 1. Documents

**Purpose**: Generic polymorphic document storage system for all file types across the application (waivers, photos, meeting minutes, financial records, etc.)

**Pattern**: Follows the same polymorphic pattern as Notes (entity_type + entity_id)

**Table**: `documents`

| Column            | Type         | Attributes                          | Description                         |
|-------------------|--------------|-------------------------------------|-------------------------------------|
| id                | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                   |
| entity_type       | VARCHAR(255) | NOT NULL                            | Polymorphic class name (e.g., 'Waivers.GatheringWaivers', 'Members') |
| entity_id         | INT          | NOT NULL                            | Polymorphic entity ID               |
| uploaded_by       | INT          | NOT NULL, FOREIGN KEY               | FK to members.id (uploader)         |
| original_filename | VARCHAR(255) | NOT NULL                            | Original uploaded filename          |
| stored_filename   | VARCHAR(255) | NOT NULL                            | Sanitized filename for storage      |
| file_path         | VARCHAR(255) | NOT NULL                            | Full path in Flysystem              |
| mime_type         | VARCHAR(100) | NOT NULL                            | MIME type (e.g., 'application/pdf') |
| file_size         | INT          | NOT NULL                            | File size in bytes                  |
| checksum          | VARCHAR(64)  | NULL                                | SHA-256 hash for integrity          |
| storage_adapter   | VARCHAR(50)  | NOT NULL, DEFAULT 'local'           | 'local' or 's3'                     |
| metadata          | JSON         | NULL                                | Flexible metadata storage           |
| created           | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Upload timestamp                    |
| modified          | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp         |
| created_by        | INT          | NOT NULL, FOREIGN KEY               | FK to members.id (audit trail)      |
| modified_by       | INT          | NULL, FOREIGN KEY                   | FK to members.id (audit trail)      |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `uploaded_by` → `members.id`
- FOREIGN KEY: `created_by` → `members.id`
- FOREIGN KEY: `modified_by` → `members.id` (nullable)
- INDEX: `entity_type, entity_id` (polymorphic lookup - **CRITICAL**)
- INDEX: `checksum` (duplicate detection)
- INDEX: `created` (chronological queries)
- UNIQUE: `file_path` (prevent storage conflicts)

**Validation Rules**:
- `entity_type`: Required, max 255 chars, format: 'PluginName.ModelName' or 'ModelName'
- `entity_id`: Required, integer > 0
- `uploaded_by`: Required, must exist in `members`
- `original_filename`: Required, max 255 chars
- `stored_filename`: Required, max 255 chars, sanitized (alphanumeric + underscores + hyphens)
- `file_path`: Required, max 255 chars, unique
- `mime_type`: Required, max 100 chars, valid MIME type
- `file_size`: Required, integer > 0
- `checksum`: Optional, 64 chars (SHA-256 hex)
- `storage_adapter`: Required, one of: 'local', 's3'
- `metadata`: Optional, valid JSON

**Metadata JSON Examples**:
```json
// For waiver documents
{
  "source": "mobile_camera",
  "compression_ratio": 0.92,
  "page_count": 2,
  "converted_from": "image/jpeg"
}

// For member photos (future)
{
  "photo_type": "profile",
  "dimensions": "800x600",
  "cropped": true
}
```

**Sample Data**:
```php
[
    'entity_type' => 'Waivers.GatheringWaivers',
    'entity_id' => 1,
    'uploaded_by' => 42,
    'original_filename' => 'John_Doe_Waiver.pdf',
    'stored_filename' => 'john_doe_waiver_20250615_143022.pdf',
    'file_path' => 'waivers/2025/06/john_doe_waiver_20250615_143022.pdf',
    'mime_type' => 'application/pdf',
    'file_size' => 245678,
    'checksum' => 'a3f5b8c9d2e1f4a7b6c5d8e9f2a1b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0',
    'storage_adapter' => 'local',
    'metadata' => json_encode(['source' => 'mobile_camera', 'compression_ratio' => 0.92])
]
```

---

### 2. GatheringTypes

**Purpose**: Define types of gatherings (practices, tournaments, meetings, feasts, wars)

**Table**: `gathering_types`

| Column      | Type         | Attributes                          | Description                        |
|-------------|--------------|-------------------------------------|------------------------------------|
| id          | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| name        | VARCHAR(100) | NOT NULL, UNIQUE                    | Type name (e.g., "Practice")       |
| description | TEXT         | NULL                                | Detailed description               |
| clonable    | BOOLEAN      | FALSE                               | Allows users to clone gatherings of this type.               |
| created     | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified    | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `name`

**Validation Rules**:
- `name`: Required, max 100 chars, unique
- `description`: Optional, plain text

**Sample Data**:
```php
['name' => 'Practice', 'description' => 'Regular fighter practice', 'clonable' => 'true']
['name' => 'Tournament', 'description' => 'Competitive fighting event', 'clonable' => 'false']
['name' => 'Meeting', 'description' => 'Branch business meeting', 'clonable' => 'true']
['name' => 'Feast', 'description' => 'Social feast event', 'clonable' => 'false']
['name' => 'War', 'description' => 'Multi-day SCA war event', 'clonable' => 'false']
```

---

### 2. Gatherings

**Purpose**: Represent specific gathering instances (e.g., "June 2025 Practice", "Crown Tournament 2025")

**Table**: `gatherings`

| Column            | Type         | Attributes                          | Description                        |
|-------------------|--------------|-------------------------------------|------------------------------------|
| id                | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| branch_id         | INT          | NOT NULL, FOREIGN KEY               | FK to hosting branch               |
| gathering_type_id | INT          | NOT NULL, FOREIGN KEY               | FK to gathering_types.id           |
| name              | VARCHAR(200) | NOT NULL                            | Gathering name                     |
| description       | TEXT         | NULL                                | Detailed description               |
| start_date        | DATE         | NOT NULL                            | Gathering start date               |
| end_date          | DATE         | NOT NULL                            | Gathering end date                 |
| location          | VARCHAR(200) | NULL                                | Physical location                  |
| created_by        | INT          | NOT NULL, FOREIGN KEY               | FK to members.id (creator)         |
| created           | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified          | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `branch_id` → `branches.id`
- FOREIGN KEY: `gathering_type_id` → `gathering_types.id`
- FOREIGN KEY: `created_by` → `members.id`
- INDEX: `start_date`, `end_date` (for date range queries)
- INDEX: `gathering_type_id` (for filtering by type)

**Validation Rules**:
- `gathering_type_id`: Required, must exist in `gathering_types`
- `branch_id`: Required, must exist in `branches`
- `name`: Required, max 200 chars
- `start_date`: Required, valid date
- `end_date`: Required, valid date, must be >= `start_date`
- `location`: Optional, max 200 chars
- `created_by`: Required, must exist in `members`

**Sample Data**:
```php
[
    'gathering_type_id' => 1, // Practice
    'branch_id' => 10
    'name' => 'June 2025 Armored Combat Practice',
    'start_date' => '2025-06-15',
    'end_date' => '2025-06-15',
    'location' => 'City Park, Springfield',
    'created_by' => 42
]
```

---

### 3. GatheringActivities

**Purpose**: Define activity templates/configurations that can be reused across multiple gatherings (e.g., "Armored Combat", "Archery")

**Important**: GatheringActivities are **configuration objects**, not tied to specific gatherings. They are linked to gatherings through the `gatherings_gathering_activities` join table, allowing the same activity template to be used at many different gatherings.

**Table**: `gathering_activities`

| Column       | Type         | Attributes                          | Description                        |
|--------------|--------------|-------------------------------------|------------------------------------|
| id           | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| name         | VARCHAR(255) | NOT NULL                            | Activity template name             |
| description  | TEXT         | NULL                                | Detailed description               |
| created      | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified     | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |
| created_by   | INT          | NULL, FOREIGN KEY                   | FK to members.id                   |
| modified_by  | INT          | NULL, FOREIGN KEY                   | FK to members.id                   |
| deleted      | DATETIME     | NULL                                | Soft delete timestamp              |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `created_by` → `members.id`
- FOREIGN KEY: `modified_by` → `members.id`

**Validation Rules**:
- `name`: Required, max 255 chars
- `description`: Optional, plain text

**Sample Data**:
```php
[
    'id' => 1,
    'name' => 'Armored Combat',
    'description' => 'Heavy armored combat activities'
]
[
    'id' => 2,
    'name' => 'Archery',
    'description' => 'Target and combat archery activities'
]
```

---

### 3a. GatheringsGatheringActivities (Join Table)

**Purpose**: Link gatherings to activity templates with ordering information

**Table**: `gatherings_gathering_activities`

| Column                | Type     | Attributes                          | Description                        |
|-----------------------|----------|-------------------------------------|------------------------------------|
| id                    | INT      | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| gathering_id          | INT      | NOT NULL, FOREIGN KEY               | FK to gatherings.id                |
| gathering_activity_id | INT      | NOT NULL, FOREIGN KEY               | FK to gathering_activities.id      |
| sort_order            | INT      | NOT NULL, DEFAULT 0                 | Display order within gathering     |
| created               | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified              | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |
| created_by            | INT      | NULL, FOREIGN KEY                   | FK to members.id                   |
| modified_by           | INT      | NULL, FOREIGN KEY                   | FK to members.id                   |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `gathering_id` → `gatherings.id` (ON DELETE CASCADE)
- FOREIGN KEY: `gathering_activity_id` → `gathering_activities.id` (ON DELETE CASCADE)
- FOREIGN KEY: `created_by` → `members.id`
- UNIQUE INDEX: `gathering_id`, `gathering_activity_id` (prevent duplicate activity assignments)
- INDEX: `sort_order` (for ordering)

**Validation Rules**:
- `gathering_id`: Required, must exist in `gatherings`
- `gathering_activity_id`: Required, must exist in `gathering_activities`
- Unique combination of `gathering_id` and `gathering_activity_id`

**Sample Data**:
```php
[
    'gathering_id' => 1,
    'gathering_activity_id' => 1,  // Armored Combat
    'sort_order' => 1
]
[
    'gathering_id' => 1,
    'gathering_activity_id' => 2,  // Archery
    'sort_order' => 2
]
```

---

## Plugin Entities (in `plugins/Waivers/src/Model/`)

### 4. WaiverTypes

**Purpose**: Define types of waivers (Adult General, Minor General, Armored Combat, Equestrian)

**Table**: `waiver_types`

| Column            | Type         | Attributes                          | Description                        |
|-------------------|--------------|-------------------------------------|------------------------------------|
| id                | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| name              | VARCHAR(100) | NOT NULL, UNIQUE                    | Waiver type name                   |
| description       | TEXT         | NULL                                | Detailed description               |
| template_path     | VARCHAR(255) | NULL                                | Path to blank waiver template      |
| retention_periods | JSON         | NOT NULL                            | Retention policy (JSON array)      |
| is_active         | TINYINT(1)   | NOT NULL, DEFAULT 1                 | Active status                      |
| created           | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified          | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `name`
- INDEX: `is_active` (for filtering active waivers)

**Validation Rules**:
- `name`: Required, max 100 chars, unique
- `description`: Optional, plain text
- `template_path`: Optional, max 255 chars, valid file path
- `retention_periods`: Required, valid JSON array of `{amount: INT, unit: STRING}`
- `is_active`: Required, boolean (0 or 1)

**Sample Data**:
```php
[
    'name' => 'Adult General Waiver',
    'description' => 'Standard adult participation waiver',
    'retention_periods' => json_encode([
        ['amount' => 7, 'unit' => 'years'],
        ['amount' => 6, 'unit' => 'months']
    ]),
    'is_active' => 1
]
[
    'name' => 'Armored Combat Waiver',
    'description' => 'Additional waiver for heavy combat participation',
    'retention_periods' => json_encode([
        ['amount' => 10, 'unit' => 'years']
    ]),
    'is_active' => 1
]
```

**Retention Periods JSON Schema**:
```json
[
  {
    "amount": 7,
    "unit": "years"
  },
  {
    "amount": 6,
    "unit": "months"
  }
]
```

---

### 5. GatheringActivityWaivers

**Purpose**: Associate activities with required waiver types (many-to-many join table)

**Table**: `gathering_activity_waivers`

| Column                 | Type     | Attributes                          | Description                        |
|------------------------|----------|-------------------------------------|------------------------------------|
| id                     | INT      | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| gathering_activity_id  | INT      | NOT NULL, FOREIGN KEY               | FK to gathering_activities.id      |
| waiver_type_id         | INT      | NOT NULL, FOREIGN KEY               | FK to waiver_types.id              |
| created                | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified               | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `gathering_activity_id` → `gathering_activities.id` (ON DELETE CASCADE)
- FOREIGN KEY: `waiver_type_id` → `waiver_types.id`
- UNIQUE: `gathering_activity_id, waiver_type_id` (prevent duplicates)

**Validation Rules**:
- `gathering_activity_id`: Required, must exist in `gathering_activities`
- `waiver_type_id`: Required, must exist in `waiver_types`
- Combination must be unique

**Sample Data**:
```php
[
    'gathering_activity_id' => 1, // Armored Combat
    'waiver_type_id' => 1 // Adult General Waiver
]
[
    'gathering_activity_id' => 1, // Armored Combat
    'waiver_type_id' => 3 // Armored Combat Waiver
]
```

---

### 6. GatheringWaivers

**Purpose**: Store waiver metadata and link to Documents for file storage (waiver-specific business logic)

**Polymorphic Relationship**: References Documents via entity_type='Waivers.GatheringWaivers' and entity_id=this.id

**Table**: `gathering_waivers`

| Column            | Type         | Attributes                          | Description                         |
|-------------------|--------------|-------------------------------------|-------------------------------------|
| id                | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                   |
| gathering_id      | INT          | NOT NULL, FOREIGN KEY               | FK to gatherings.id                 |
| waiver_type_id    | INT          | NOT NULL, FOREIGN KEY               | FK to waiver_types.id (user's intent) |
| member_id         | INT          | NULL, FOREIGN KEY                   | FK to members.id (if known)         |
| document_id       | INT          | NOT NULL, FOREIGN KEY               | FK to documents.id (polymorphic link) |
| retention_date    | DATE         | NOT NULL                            | Calculated expiration date          |
| status            | ENUM         | NOT NULL, DEFAULT 'active'          | 'active', 'expired', 'deleted'      |
| notes             | TEXT         | NULL                                | Admin notes about this waiver       |
| created           | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp           |
| modified          | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp         |
| created_by        | INT          | NOT NULL, FOREIGN KEY               | FK to members.id (audit trail)      |
| modified_by       | INT          | NULL, FOREIGN KEY                   | FK to members.id (audit trail)      |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `gathering_id` → `gatherings.id`
- FOREIGN KEY: `waiver_type_id` → `waiver_types.id`
- FOREIGN KEY: `member_id` → `members.id` (nullable, on delete SET NULL)
- FOREIGN KEY: `document_id` → `documents.id` (ON DELETE RESTRICT - prevent orphaned waivers)
- FOREIGN KEY: `created_by` → `members.id`
- FOREIGN KEY: `modified_by` → `members.id` (nullable)
- INDEX: `retention_date, status` (for expiration queries)
- INDEX: `gathering_id` (for gathering-specific queries)
- INDEX: `member_id` (for member-specific queries)
- INDEX: `waiver_type_id` (for waiver type reporting)
- UNIQUE: `document_id` (one-to-one relationship with Documents)

**Validation Rules**:
- `gathering_id`: Required, must exist in `gatherings`
- `waiver_type_id`: Required, must exist in `waiver_types`
- `member_id`: Optional, must exist in `members` if provided
- `document_id`: Required, must exist in `documents` AND documents.entity_type must be 'Waivers.GatheringWaivers'
- `retention_date`: Required, valid date, must be >= current date (calculated from `waiver_type.retention_periods`)
- `status`: Required, one of: 'active', 'expired', 'deleted'
- `notes`: Optional, plain text

**Status Values**:
- `active`: Waiver is valid and accessible
- `expired`: Retention period passed, eligible for deletion
- `deleted`: Waiver has been permanently deleted (soft delete marker)

**Polymorphic Lookup Pattern**:
```php
// To get document for a waiver
$waiver = $this->GatheringWaivers->get($id, ['contain' => ['Documents']]);
$document = $waiver->document;

// To find all waivers for a document
$document = $this->Documents->get($id);
$waivers = $this->GatheringWaivers->find()
    ->where(['document_id' => $document->id])
    ->all();

// Documents table will have entity_type='Waivers.GatheringWaivers' and entity_id=$waiver->id
```

**Sample Data**:
**Sample Data**:
```php
[
    'gathering_id' => 1,
    'waiver_type_id' => 1, // Adult General Waiver (user's declared intent)
    'member_id' => 123,
    'document_id' => 42, // FK to documents table
    'retention_date' => '2032-12-15', // 7 years 6 months from gathering end (from waiver_type_id=1)
    'status' => 'active',
    'notes' => NULL,
    'created_by' => 123
]

// Corresponding Documents record
[
    'id' => 42,
    'entity_type' => 'Waivers.GatheringWaivers',
    'entity_id' => 1, // gathering_waivers.id
    'uploaded_by' => 123,
    'original_filename' => 'john_smith_waiver_001.jpg',
    'stored_filename' => 'john_smith_waiver_001_20250615_143000.pdf',
    'file_path' => 'waivers/2025/06/john_smith_waiver_001_20250615_143000.pdf',
    'mime_type' => 'application/pdf',
    'file_size' => 245680,
    'checksum' => 'sha256_hash_here',
    'storage_adapter' => 'local',
    'metadata' => json_encode(['source' => 'mobile_camera', 'converted_from' => 'image/jpeg', 'compression_ratio' => 0.92]),
    'created_by' => 123
]
```

**Purpose of waiver_type_id**:
The `waiver_type_id` field stores the user's **declared intent** when uploading the waiver. This is critical because:
1. **Retention Calculation**: Uses the waiver_type's retention_periods to calculate retention_date
2. **Compliance Checking**: System can verify "Does participant have required waiver type?"
3. **Reporting**: "How many Adult General Waivers were collected at this gathering?"

This is separate from `GatheringWaiverActivities` which tracks **actual activity coverage** after review.

**Design Pattern Notes**:
- GatheringWaivers stores waiver-specific business logic (retention, status, type declaration)
- Documents stores generic file metadata and storage info (reusable for future features)
- Polymorphic relationship via document_id + Documents.entity_type/entity_id enables flexible queries
- ON DELETE RESTRICT on document_id prevents orphaned waivers (document must be explicitly deleted first)

---

### 7. GatheringWaiverActivities

**Purpose**: Associate uploaded waivers with the specific activities they cover (many-to-many join table)

**Table**: `gathering_waiver_activities`

| Column                 | Type     | Attributes                          | Description                        |
|------------------------|----------|-------------------------------------|------------------------------------|
| id                     | INT      | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| gathering_waiver_id    | INT      | NOT NULL, FOREIGN KEY               | FK to gathering_waivers.id         |
| gathering_activity_id  | INT      | NOT NULL, FOREIGN KEY               | FK to gathering_activities.id      |
| created                | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified               | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |

**Indexes**:
- PRIMARY KEY: `id`
- FOREIGN KEY: `gathering_waiver_id` → `gathering_waivers.id` (ON DELETE CASCADE)
- FOREIGN KEY: `gathering_activity_id` → `gathering_activities.id` (ON DELETE CASCADE)
- UNIQUE: `gathering_waiver_id, gathering_activity_id` (prevent duplicates)
- INDEX: `gathering_waiver_id` (for finding activities covered by a waiver)
- INDEX: `gathering_activity_id` (for finding waivers covering an activity)

**Validation Rules**:
- `gathering_waiver_id`: Required, must exist in `gathering_waivers`
- `gathering_activity_id`: Required, must exist in `gathering_activities`
- Combination must be unique

**Use Cases**:

1. **General Waiver Covers Multiple Activities**:
   ```php
   // One waiver covers all three activities
   ['gathering_waiver_id' => 1, 'gathering_activity_id' => 1], // Armored Combat
   ['gathering_waiver_id' => 1, 'gathering_activity_id' => 2], // Archery
   ['gathering_waiver_id' => 1, 'gathering_activity_id' => 3], // Rapier
   ```

2. **Multiple Waivers for Same Activity** (different participants or days):
   ```php
   // Armored Combat has three different participant waivers
   ['gathering_waiver_id' => 5, 'gathering_activity_id' => 1], // Participant A
   ['gathering_waiver_id' => 6, 'gathering_activity_id' => 1], // Participant B
   ['gathering_waiver_id' => 7, 'gathering_activity_id' => 1], // Participant C
   ```

3. **Specific Waiver for Specific Activity**:
   ```php
   // Armored Combat-specific waiver only covers that activity
   ['gathering_waiver_id' => 10, 'gathering_activity_id' => 1], // Armored Combat only
   ```

**Sample Data**:
```php
[
    'gathering_waiver_id' => 1, // John Smith's Adult General Waiver
    'gathering_activity_id' => 1 // Covers Armored Combat
]
[
    'gathering_waiver_id' => 1, // Same waiver
    'gathering_activity_id' => 2 // Also covers Archery
]
[
    'gathering_waiver_id' => 2, // Jane Doe's Armored Combat Waiver
    'gathering_activity_id' => 1 // Only covers Armored Combat
]
```

---

### 8. WaiverConfiguration

**Purpose**: Store global configuration settings for the Waivers plugin

**Table**: `waiver_configuration`

| Column       | Type         | Attributes                          | Description                        |
|--------------|--------------|-------------------------------------|------------------------------------|
| id           | INT          | PRIMARY KEY, AUTO_INCREMENT         | Unique identifier                  |
| config_key   | VARCHAR(100) | NOT NULL, UNIQUE                    | Configuration key                  |
| config_value | TEXT         | NULL                                | Configuration value (JSON or text) |
| description  | TEXT         | NULL                                | Human-readable description         |
| created      | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation timestamp          |
| modified     | DATETIME     | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Last modification timestamp        |

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `config_key`

**Validation Rules**:
- `config_key`: Required, max 100 chars, unique, alphanumeric with underscores
- `config_value`: Optional, plain text or JSON
- `description`: Optional, plain text

**Sample Configuration Keys**:
```php
['config_key' => 'storage_backend', 'config_value' => 'local'] // or 's3'
['config_key' => 's3_bucket', 'config_value' => 'kmp-waivers']
['config_key' => 's3_region', 'config_value' => 'us-east-1']
['config_key' => 'max_upload_size_mb', 'config_value' => '10']
['config_key' => 'allowed_image_types', 'config_value' => json_encode(['image/jpeg', 'image/png'])]
['config_key' => 'pdf_compression_quality', 'config_value' => '85']
['config_key' => 'retention_check_schedule', 'config_value' => 'daily']
```

---

## Relationships Summary

| Parent Entity        | Relationship | Child Entity                | Type   | Cascade |
|----------------------|--------------|-----------------------------|--------|---------|
| GatheringTypes       | has many     | Gatherings                  | 1:N    | No      |
| **Gatherings**       | **belongs to many** | **GatheringActivities**  | **N:M** | **Yes** |
| **GatheringActivities** | **belongs to many** | **Gatherings**        | **N:M** | **Yes** |
| Gatherings           | has many     | GatheringWaivers            | 1:N    | No      |
| GatheringActivities  | has many     | GatheringActivityWaivers    | 1:N    | Yes     |
| WaiverTypes          | has many     | GatheringActivityWaivers    | 1:N    | No      |
| WaiverTypes          | has many     | GatheringWaivers            | 1:N    | No      |
| **Documents**        | **polymorphic** | **GatheringWaivers**     | **1:1** | **Restrict** |
| **Documents**        | **polymorphic** | **Members** (future)     | **1:N** | **TBD** |
| **Documents**        | **polymorphic** | **Other Entities** (future) | **Varies** | **TBD** |
| Members              | uploads      | Documents                   | 1:N    | No      |
| Members              | has many     | Gatherings (as creator)     | 1:N    | No      |
| Members              | has many     | GatheringWaivers            | 1:N    | No      |
| **GatheringWaivers** | **belongs to** | **Documents**             | **N:1** | **Restrict** |
| **GatheringWaivers** | **belongs to many** | **GatheringActivities** | **N:M** | **Yes** |
| **GatheringActivities** | **belongs to many** | **GatheringWaivers** | **N:M** | **Yes** |

**Notes**: 
1. **GatheringActivities are Configuration Objects**: `GatheringActivities` are template/configuration objects (e.g., "Armored Combat", "Archery") that can be reused across many gatherings. The many-to-many relationship between `Gatherings` and `GatheringActivities` is implemented via the `GatheringsGatheringActivities` join table. This allows:
   - A single activity template to be used at multiple gatherings
   - A gathering to have multiple activities
   - Activities to be ordered within each gathering (via `sort_order` field)

2. **Polymorphic Pattern**: `Documents` uses `entity_type` + `entity_id` fields to link to any entity (follows Notes model pattern). This enables:
   - Waiver PDFs: `entity_type='Waivers.GatheringWaivers'`
   - Future member photos: `entity_type='Members'`
   - Future meeting minutes: `entity_type='Meetings'`
   - Any future document needs without schema changes

3. `WaiverTypes` → `GatheringWaivers`: Captures the user's **declared waiver type** at upload time, used to calculate retention periods and enable compliance checking

4. The many-to-many relationship between `GatheringWaivers` and `GatheringActivities` is implemented via the `GatheringWaiverActivities` join table. This allows:
   - A single waiver to cover multiple activities (e.g., general adult waiver covers all activities)
   - Multiple waivers to be submitted for the same activity (different participants, multiple days/sessions)

5. **ON DELETE RESTRICT** on `GatheringWaivers.document_id`: Prevents orphaned waivers. Document must be explicitly deleted from GatheringWaivers first, then from Documents table.

---

## Migration from Awards Plugin

The Awards plugin currently uses `award_gatherings` table with these columns:
- `id`, `award_id`, `name`, `location`, `event_date`, `created`, `modified`

**Migration Strategy**:

1. Create new `gatherings` table with schema above
2. Migrate data:
   ```sql
   INSERT INTO gatherings (name, location, start_date, end_date, gathering_type_id, created_by, created, modified)
   SELECT 
       name, 
       location, 
       event_date AS start_date, 
       event_date AS end_date,
       (SELECT id FROM gathering_types WHERE name = 'Award Ceremony' LIMIT 1) AS gathering_type_id,
       1 AS created_by, -- System user
       created,
       modified
   FROM award_gatherings;
   ```
3. Add `gathering_id` column to `awards` table
4. Update foreign key references:
   ```sql
   UPDATE awards a
   INNER JOIN gatherings g ON g.name = (
       SELECT ag.name FROM award_gatherings ag WHERE ag.id = a.award_gathering_id
   )
   SET a.gathering_id = g.id;
   ```
5. Drop `award_gathering_id` column from `awards` table
6. Drop `award_gatherings` table
7. Update Awards plugin code to use `Gatherings` association

---

## Implementation Notes: Polymorphic Documents Pattern

### Why Polymorphic vs Direct Foreign Keys?

**Decision**: Generic `Documents` entity with polymorphic relationships following KMP's existing Notes pattern

**Rationale**:
1. **Code Reuse**: Single DocumentsTable, DocumentsController, Document upload service
2. **Future-Proof**: Can add member photos, meeting minutes, financial records without schema changes
3. **Consistent Pattern**: Follows established KMP convention (Notes uses `entity_type` + `entity_id`)
4. **Flexibility**: Easy to add new document types by just changing `entity_type` value

### CakePHP Implementation Pattern

**DocumentsTable.php** (Core - `src/Model/Table/DocumentsTable.php`):
```php
// No explicit associations defined - polymorphic relationships handled in queries
// Similar to NotesTable.php which also doesn't define associations

public function findForEntity(Query $query, array $options): Query
{
    return $query->where([
        'Documents.entity_type' => $options['entity_type'],
        'Documents.entity_id' => $options['entity_id']
    ]);
}
```

**GatheringWaiversTable.php** (Plugin - `plugins/Waivers/src/Model/Table/GatheringWaiversTable.php`):
```php
// Define explicit belongsTo for document_id
$this->belongsTo('Documents', [
    'className' => 'Documents',
    'foreignKey' => 'document_id'
]);

// No need for hasMany back from Documents - use finder instead
```

**Query Examples**:
```php
// Get document for a waiver (via association)
$waiver = $this->GatheringWaivers->get($id, ['contain' => ['Documents']]);
$document = $waiver->document;

// Find all documents for an entity (via finder)
$documents = $this->Documents->find('forEntity', [
    'entity_type' => 'Waivers.GatheringWaivers',
    'entity_id' => $waiverId
])->all();

// Check if document is attached to specific entity
if ($document->entity_type === 'Waivers.GatheringWaivers') {
    $waiver = $this->GatheringWaivers->get($document->entity_id);
}
```

### Deletion Strategy

**Cascading Rules**:
- GatheringWaivers → Documents: **RESTRICT** (must delete waiver record first)
- Documents → Filesystem: Clean up file after Documents record deleted
- GatheringWaivers has `deleted` status enum (soft delete option)

**Deletion Workflow**:
```php
// 1. Mark waiver as deleted (soft delete)
$waiver->status = 'deleted';
$this->GatheringWaivers->save($waiver);

// 2. After compliance officer confirms, hard delete
$document = $waiver->document;
$this->GatheringWaivers->delete($waiver); // Deletes waiver record
$this->Documents->delete($document);      // Deletes document record
// 3. AfterDelete callback in DocumentsTable removes file from filesystem
```

---

## Next Steps

1. ✅ Data model complete (10 entities including polymorphic Documents)
2. ✅ Create `contracts/` directory with API endpoint specifications
3. ✅ Create `quickstart.md` for developer onboarding
4. ⏳ Update implementation plan with Documents entity and polymorphic pattern
5. ⏳ Update API contracts to include document management endpoints
6. ⏳ Update quickstart.md with Documents + GatheringWaivers association examples
7. ⏳ Update agent context with polymorphic pattern examples

**Important Architectural Decisions**:
- **Polymorphic Documents**: Enables future document types (member photos, meeting minutes) without schema changes
- **Many-to-Many Waivers ↔ Activities**: One waiver can cover multiple activities, multiple waivers per activity supported
- **Declared vs Actual Coverage**: `waiver_type_id` tracks user intent, `GatheringWaiverActivities` tracks actual coverage
- **Separation of Concerns**: Documents stores files, GatheringWaivers stores business logic (retention, status)

---

## Database Constraints & Validation

### Foreign Key Constraints

**Documents Table**:
```sql
ALTER TABLE documents
ADD CONSTRAINT fk_documents_uploaded_by 
  FOREIGN KEY (uploaded_by) REFERENCES members(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;

-- Note: entity_id uses dynamic FK validation in application layer
-- Cannot enforce at database level due to polymorphic relationship
```

**GatheringTypes Table** (Core):
```sql
-- No foreign keys, standalone reference table
```

**Gatherings Table** (Core):
```sql
ALTER TABLE gatherings
ADD CONSTRAINT fk_gatherings_gathering_type 
  FOREIGN KEY (gathering_type_id) REFERENCES gathering_types(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE gatherings
ADD CONSTRAINT fk_gatherings_branch 
  FOREIGN KEY (branch_id) REFERENCES branches(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE gatherings
ADD CONSTRAINT fk_gatherings_created_by 
  FOREIGN KEY (created_by) REFERENCES members(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;
```

**GatheringActivities Table** (Core):
```sql
ALTER TABLE gathering_activities
ADD CONSTRAINT fk_gathering_activities_gathering_type 
  FOREIGN KEY (gathering_type_id) REFERENCES gathering_types(id) 
  ON DELETE SET NULL ON UPDATE CASCADE;
```

**WaiverTypes Table** (Plugin):
```sql
ALTER TABLE waiver_types
ADD CONSTRAINT fk_waiver_types_document 
  FOREIGN KEY (document_id) REFERENCES documents(id) 
  ON DELETE SET NULL ON UPDATE CASCADE;
```

**GatheringActivityWaivers Table** (Plugin):
```sql
ALTER TABLE gathering_activity_waivers
ADD CONSTRAINT fk_gaw_gathering_activity 
  FOREIGN KEY (gathering_activity_id) REFERENCES gathering_activities(id) 
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE gathering_activity_waivers
ADD CONSTRAINT fk_gaw_waiver_type 
  FOREIGN KEY (waiver_type_id) REFERENCES waiver_types(id) 
  ON DELETE CASCADE ON UPDATE CASCADE;
```

**Gatherings Table** (Core - Waivers relationship):
```sql
ALTER TABLE gatherings
ADD CONSTRAINT fk_gatherings_waiver_configuration 
  FOREIGN KEY (waiver_configuration_id) REFERENCES waiver_configurations(id) 
  ON DELETE SET NULL ON UPDATE CASCADE;
```

**GatheringWaivers Table** (Plugin):
```sql
ALTER TABLE gathering_waivers
ADD CONSTRAINT fk_gathering_waivers_gathering 
  FOREIGN KEY (gathering_id) REFERENCES gatherings(id) 
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE gathering_waivers
ADD CONSTRAINT fk_gathering_waivers_waiver_type 
  FOREIGN KEY (waiver_type_id) REFERENCES waiver_types(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE gathering_waivers
ADD CONSTRAINT fk_gathering_waivers_member 
  FOREIGN KEY (member_id) REFERENCES members(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE gathering_waivers
ADD CONSTRAINT fk_gathering_waivers_document 
  FOREIGN KEY (document_id) REFERENCES documents(id) 
  ON DELETE RESTRICT ON UPDATE CASCADE;
```

**GatheringWaiverActivities Table** (Plugin):
```sql
ALTER TABLE gathering_waiver_activities
ADD CONSTRAINT fk_gwa_gathering_waiver 
  FOREIGN KEY (gathering_waiver_id) REFERENCES gathering_waivers(id) 
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE gathering_waiver_activities
ADD CONSTRAINT fk_gwa_gathering_activity 
  FOREIGN KEY (gathering_activity_id) REFERENCES gathering_activities(id) 
  ON DELETE CASCADE ON UPDATE CASCADE;
```

**WaiverConfiguration Table** (Plugin):
```sql
-- No foreign keys, standalone configuration table
```

### CASCADE Behavior Summary

| Parent → Child                          | ON DELETE    | Rationale                                                |
|-----------------------------------------|--------------|----------------------------------------------------------|
| Member → Document                       | RESTRICT     | Preserve audit trail, prevent member deletion with docs |
| GatheringType → Gathering               | RESTRICT     | Prevent type deletion if gatherings exist                |
| Branch → Gathering                      | RESTRICT     | Prevent branch deletion if gatherings exist              |
| Member → Gathering (created_by)         | RESTRICT     | Preserve gathering creator audit trail                   |
| GatheringType → GatheringActivity       | SET NULL     | Allow type deletion, activity becomes untyped            |
| WaiverType → Document (template)        | SET NULL     | Allow template deletion, type remains valid              |
| GatheringActivity → GatheringActivityWaiver | CASCADE  | Delete waiver requirements when activity deleted         |
| WaiverType → GatheringActivityWaiver    | CASCADE      | Delete waiver requirements when type deleted             |
| WaiverConfiguration → Gathering         | SET NULL     | Allow config deletion, gathering keeps default           |
| Gathering → GatheringWaiver             | CASCADE      | Delete waivers when gathering deleted                    |
| WaiverType → GatheringWaiver            | RESTRICT     | Prevent type deletion if waivers exist                   |
| Member → GatheringWaiver                | RESTRICT     | Preserve waiver uploader audit trail                     |
| Document → GatheringWaiver              | RESTRICT     | Prevent document deletion before waiver                  |
| GatheringWaiver → GatheringWaiverActivity | CASCADE    | Delete activity links when waiver deleted                |
| GatheringActivity → GatheringWaiverActivity | CASCADE  | Delete activity links when activity deleted              |

### Database Indexes

**Documents Table**:
```sql
CREATE INDEX idx_documents_entity ON documents(entity_type, entity_id);
CREATE INDEX idx_documents_uploaded_by ON documents(uploaded_by);
CREATE INDEX idx_documents_created ON documents(created);
CREATE INDEX idx_documents_checksum ON documents(checksum);
CREATE UNIQUE INDEX idx_documents_file_path ON documents(file_path);
```

**Gatherings Table**:
```sql
CREATE INDEX idx_gatherings_gathering_type ON gatherings(gathering_type_id);
CREATE INDEX idx_gatherings_branch ON gatherings(branch_id);
CREATE INDEX idx_gatherings_dates ON gatherings(start_date, end_date);
CREATE INDEX idx_gatherings_waivers_collected ON gatherings(waivers_collected);
CREATE INDEX idx_gatherings_created_by ON gatherings(created_by);
```

**GatheringActivities Table**:
```sql
CREATE INDEX idx_gathering_activities_type ON gathering_activities(gathering_type_id);
CREATE INDEX idx_gathering_activities_active ON gathering_activities(is_active);
```

**WaiverTypes Table**:
```sql
CREATE INDEX idx_waiver_types_active ON waiver_types(is_active);
```

**GatheringWaivers Table**:
```sql
CREATE INDEX idx_gathering_waivers_gathering ON gathering_waivers(gathering_id);
CREATE INDEX idx_gathering_waivers_type ON gathering_waivers(waiver_type_id);
CREATE INDEX idx_gathering_waivers_member ON gathering_waivers(member_id);
CREATE INDEX idx_gathering_waivers_document ON gathering_waivers(document_id);
CREATE INDEX idx_gathering_waivers_retention ON gathering_waivers(retention_date);
CREATE INDEX idx_gathering_waivers_status ON gathering_waivers(status);
CREATE INDEX idx_gathering_waivers_created ON gathering_waivers(created);
```

**GatheringActivityWaivers Table**:
```sql
CREATE UNIQUE INDEX idx_gaw_unique ON gathering_activity_waivers(gathering_activity_id, waiver_type_id);
```

**GatheringWaiverActivities Table**:
```sql
CREATE UNIQUE INDEX idx_gwa_unique ON gathering_waiver_activities(gathering_waiver_id, gathering_activity_id);
```

### Unique Constraints

```sql
-- Natural keys
ALTER TABLE gathering_types ADD CONSTRAINT uc_gathering_types_name UNIQUE (name);
ALTER TABLE documents ADD CONSTRAINT uc_documents_file_path UNIQUE (file_path);
ALTER TABLE waiver_types ADD CONSTRAINT uc_waiver_types_name UNIQUE (name);

-- Composite unique constraints
ALTER TABLE gathering_activity_waivers 
  ADD CONSTRAINT uc_gaw_activity_waiver UNIQUE (gathering_activity_id, waiver_type_id);

ALTER TABLE gathering_waiver_activities 
  ADD CONSTRAINT uc_gwa_waiver_activity UNIQUE (gathering_waiver_id, gathering_activity_id);
```

### Default Values

```sql
-- Documents
ALTER TABLE documents 
  ALTER COLUMN storage_adapter SET DEFAULT 'local';

-- Gatherings
ALTER TABLE gatherings 
  ALTER COLUMN waivers_collected SET DEFAULT FALSE;

-- GatheringActivities
ALTER TABLE gathering_activities 
  ALTER COLUMN is_active SET DEFAULT TRUE;

-- WaiverTypes
ALTER TABLE waiver_types 
  ALTER COLUMN is_active SET DEFAULT TRUE;

-- GatheringWaivers
ALTER TABLE gathering_waivers 
  ALTER COLUMN status SET DEFAULT 'active';

-- Timestamps (all tables)
ALTER TABLE [table_name] 
  ALTER COLUMN created SET DEFAULT CURRENT_TIMESTAMP,
  ALTER COLUMN modified SET DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

### NULL Semantics

**Documents Table**:
- `entity_id`: **NEVER NULL** - Stub-first pattern ensures entity exists before document
- `checksum`: NULL = not calculated (error during upload or legacy record)
- `metadata`: NULL = no additional metadata available

**Gatherings Table**:
- `waiver_configuration_id`: NULL = use default/global configuration
- `notes`: NULL = no notes provided

**GatheringActivities Table**:
- `gathering_type_id`: NULL = activity not associated with specific type (universal)
- `notes`: NULL = no notes provided

**WaiverTypes Table**:
- `document_id`: NULL = no template PDF uploaded yet

**GatheringWaivers Table**:
- `member_id`: NULL = unknown/anonymous participant (waiver not linked to member)
- `notes`: NULL = no notes provided

### Enum Values & Status Fields

**GatheringWaivers.status**:
```php
const STATUS_ACTIVE = 'active';        // Within retention period
const STATUS_EXPIRED = 'expired';      // Past retention date, eligible for deletion
const STATUS_DELETED = 'deleted';      // Soft deleted, pending permanent removal
```

**Documents.storage_adapter**:
```php
const ADAPTER_LOCAL = 'local';
const ADAPTER_S3 = 's3';
```

### JSON Schema Validation

**WaiverTypes.retention_periods**:
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["anchor"],
  "properties": {
    "anchor": {
      "type": "string",
      "enum": ["gathering_end_date", "upload_date", "permanent"]
    },
    "years": {
      "type": "integer",
      "minimum": 0
    },
    "months": {
      "type": "integer",
      "minimum": 0
    },
    "days": {
      "type": "integer",
      "minimum": 0
    }
  },
  "oneOf": [
    {
      "properties": {
        "anchor": {"const": "permanent"}
      }
    },
    {
      "anyOf": [
        {"required": ["years"]},
        {"required": ["months"]},
        {"required": ["days"]}
      ]
    }
  ]
}
```

**Example Valid Values**:
```json
{"anchor": "gathering_end_date", "years": 7, "months": 6}
{"anchor": "upload_date", "months": 18}
{"anchor": "permanent"}
```

**Example Invalid Values** (rejected by validation):
```json
{"anchor": "gathering_end_date"}  // Missing time period
{"anchor": "invalid_anchor"}       // Invalid anchor value
{"years": 7}                       // Missing anchor
{}                                 // Empty object
```

**Documents.metadata**:
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {
    "source": {"type": "string"},
    "converted_from": {"type": "string"},
    "compression_ratio": {"type": "number", "minimum": 0, "maximum": 1},
    "page_count": {"type": "integer", "minimum": 1},
    "original_size": {"type": "integer", "minimum": 0}
  }
}
```

**Example Values**:
```json
{
  "source": "mobile_camera",
  "converted_from": "image/jpeg",
  "compression_ratio": 0.92,
  "page_count": 2,
  "original_size": 5242880
}
```

### CakePHP Validation Implementation

**DocumentsTable.php**:
```php
public function validationDefault(Validator $validator): Validator
{
    $validator
        ->scalar('entity_type')
        ->maxLength('entity_type', 255)
        ->requirePresence('entity_type', 'create')
        ->notEmptyString('entity_type')
        ->inList('entity_type', self::ALLOWED_ENTITY_TYPES);

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
            }
        ]);

    $validator
        ->scalar('stored_filename')
        ->maxLength('stored_filename', 255)
        ->alphaNumeric('stored_filename', ['allowDash' => true, 'allowUnderscore' => true]);

    $validator
        ->scalar('file_path')
        ->maxLength('file_path', 255)
        ->requirePresence('file_path', 'create')
        ->notEmptyString('file_path');

    $validator
        ->scalar('checksum')
        ->maxLength('checksum', 64)
        ->regex('checksum', '/^[a-f0-9]{64}$/i');

    return $validator;
}
```

**WaiverTypesTable.php**:
```php
public function validationDefault(Validator $validator): Validator
{
    $validator
        ->scalar('retention_periods')
        ->requirePresence('retention_periods', 'create')
        ->notEmptyString('retention_periods')
        ->add('retention_periods', 'validJson', [
            'rule' => function ($value) {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
                return $this->validateRetentionPeriodStructure($decoded);
            },
            'message' => 'Invalid retention period format'
        ]);

    return $validator;
}

private function validateRetentionPeriodStructure(array $data): bool
{
    $validAnchors = ['gathering_end_date', 'upload_date', 'permanent'];
    
    if (!in_array($data['anchor'] ?? null, $validAnchors)) {
        return false;
    }
    
    if ($data['anchor'] !== 'permanent') {
        $hasTimePeriod = !empty($data['years']) || 
                        !empty($data['months']) || 
                        !empty($data['days']);
        if (!$hasTimePeriod) {
            return false;
        }
    }
    
    return true;
}
```

---

## Migration Considerations

**Phase 1**: Core entities (Documents, GatheringTypes, Gatherings, GatheringActivities)
**Phase 2**: Waivers plugin entities (WaiverTypes, GatheringActivityWaivers, etc.)
**Phase 3**: Awards plugin migration (award_events → Gatherings)
**Phase 4**: Add foreign key constraints after data migration complete
