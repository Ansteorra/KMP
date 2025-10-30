---
layout: default
---
[← Back to Table of Contents](index.md)

# 4. Core Modules

This section documents the primary modules that make up the foundation of the Kingdom Management Portal. These modules manage the essential data and functionality required across the entire application.

## Module Documentation

For detailed documentation on each core module, see:

- **[4.1 Member Lifecycle](4.1-member-lifecycle.md)** - Complete member lifecycle and data flow documentation
- **[4.2 Branch Hierarchy](4.2-branch-hierarchy.md)** - Organizational structure and tree management
- **[4.3 Warrant Lifecycle](4.3-warrant-lifecycle.md)** - Warrant state machine and approval processes  
- **[4.4 RBAC Security Architecture](4.4-rbac-security-architecture.md)** - Role-based access control with warrant temporal validation
- **[4.5 View Patterns](4.5-view-patterns.md)** - Template system, helpers, and UI components
- **[4.6 Gatherings System](4.6-gatherings-system.md)** - Event management, calendar views, and attendance tracking
- **[4.7 Document Management & Retention System](4.7-document-management-system.md)** - File uploads, storage, and retention policies

## Overview

Below is a high-level overview of the core modules. For comprehensive documentation, refer to the individual module pages listed above.

## 4.1 Member Management

The member management module is the cornerstone of the KMP system, handling all aspects of member records, registration, and profile management.

### Data Model

```mermaid
classDiagram
    class Member {
        +id: int
        +email_address: string
        +sca_name: string
        +legal_name: string
        +title: string
        +pronunciation: string
        +phone: string
        +address: string
        +is_minor: bool
        +password: string
        +active: bool
        +warrantable: bool
        +created: datetime
        +modified: datetime
    }
    
    class MemberRole {
        +id: int
        +member_id: int
        +role_id: int
        +scope: string
        +active: bool
        +created: datetime
        +modified: datetime
    }
    
    class Role {
        +id: int
        +name: string
        +admin: bool
        +created: datetime
        +modified: datetime
    }
    
    Member "1" -- "0..*" MemberRole
    MemberRole "0..*" -- "1" Role
```

### Registration Process

The member registration process follows this flow:

```mermaid
graph TD
    Start[Start] --> NewUser{Is New User?}
    NewUser -->|Yes| RegisterForm[Registration Form]
    NewUser -->|No| LoginForm[Login Form]
    RegisterForm --> ValidateEmail[Validate Email]
    ValidateEmail --> CreateAccount[Create Account]
    CreateAccount --> SendVerification[Send Verification Email]
    SendVerification --> AwaitVerification[Await Verification]
    AwaitVerification --> Verified{Verified?}
    Verified -->|Yes| Authenticated[Authenticated User]
    Verified -->|No| ExpiredReg{Expired?}
    ExpiredReg -->|Yes| RegisterForm
    ExpiredReg -->|No| AwaitVerification
    LoginForm --> AuthCheck{Valid Credentials?}
    AuthCheck -->|Yes| Authenticated
    AuthCheck -->|No| LoginForm
```

### Member Lifecycle Management

The KMP system implements a comprehensive member lifecycle management system that tracks members through various states and automatically manages transitions based on age, activity, and administrative actions.

#### Member Status System

KMP uses a seven-level status system to track member states:

```mermaid
stateDiagram-v2
    [*] --> Unverified
    Unverified --> Active: Email Verification
    Active --> Inactive: Administrative Action
    Inactive --> Active: Reactivation
    Active --> Suspended: Disciplinary Action
    Suspended --> Active: Suspension Lifted
    Active --> AgedUp: Age-up Review
    AgedUp --> Active: Manual Verification
    Active --> Expired: Account Expiration
    Expired --> Active: Account Renewal
    note right of AgedUp
        Automatic transition when
        member reaches majority age
    end note
```

**Status Definitions:**
- **Unverified**: New account awaiting email verification
- **Active**: Fully active member with all privileges
- **Inactive**: Temporarily deactivated by administrator
- **Suspended**: Disciplinary suspension with restricted access
- **AgedUp**: Minor member who has reached majority age pending review
- **Expired**: Account expired due to inactivity or non-payment
- **Deleted**: Soft-deleted account (retained for historical records)

#### Age-Up Workflow

The system automatically manages the transition of minor members to adult status:

```mermaid
sequenceDiagram
    participant System
    participant Member
    participant Admin
    participant Email
    
    System->>System: Daily cron check member ages
    System->>System: Identify members reaching 18
    System->>Member: Update status to 'AgedUp'
    System->>Admin: Queue for verification
    System->>Email: Send age-up notification
    Admin->>System: Review member information
    Admin->>System: Verify adult status
    System->>Member: Update status to 'Active'
    System->>Email: Send confirmation to member
```

#### Warrant Eligibility System

Members' warrant eligibility is automatically calculated based on multiple factors:

```mermaid
flowchart TD
    A[Member Evaluation] --> B{Age Check}
    B -->|Under 18| C[Not Warrantable]
    B -->|18 or Over| D{Status Check}
    D -->|Not Active| C
    D -->|Active| E{Role Check}
    E -->|Has Disqualifying Role| C
    E -->|No Conflicts| F{Historical Check}
    F -->|Past Issues| C
    F -->|Clean Record| G[Warrantable]
    
    C --> H[Generate Reason List]
    G --> I[Eligible for Warrants]
    H --> J[Store in warrantable_review]
    I --> K[Available for Appointments]
```

#### Privacy and Data Protection

The system implements comprehensive privacy controls:

```mermaid
classDiagram
    class PrivacyLevel {
        <<enumeration>>
        PUBLIC
        MEMBERS_ONLY
        OFFICERS_ONLY
        PRIVATE
    }
    
    class DataFilter {
        +publicData() object
        +memberData() object
        +officerData() object
        +fullData() object
    }
    
    class MinorProtection {
        +isMinor() boolean
        +parentalConsent() boolean
        +restrictedFields() array
    }
    
    Member --> PrivacyLevel
    Member --> DataFilter
    Member --> MinorProtection
```

#### Registration and Verification Process

The complete member registration flow with verification:

```mermaid
sequenceDiagram
    participant User
    participant Web
    participant Controller
    participant Database
    participant Email
    participant Admin
    
    User->>Web: Submit Registration Form
    Web->>Controller: POST /members/add
    Controller->>Controller: Validate Input
    Controller->>Database: Check Email Uniqueness
    Database-->>Controller: Email Available
    Controller->>Database: Create Member Record
    Database-->>Controller: Member Created (Unverified)
    Controller->>Email: Send Verification Email
    Email-->>User: Verification Link
    Controller-->>Web: Registration Success Message
    
    User->>Web: Click Verification Link
    Web->>Controller: GET /members/verify/{token}
    Controller->>Database: Validate Token
    Database-->>Controller: Token Valid
    Controller->>Database: Update Status to Active
    Controller->>Admin: Add to Verification Queue
    Controller-->>Web: Account Activated
    
    Admin->>Web: Review New Members
    Web->>Controller: GET /members/verify_queue
    Controller->>Database: Fetch Unverified Members
    Database-->>Controller: Member List
    Controller-->>Web: Display Queue
    Admin->>Web: Approve/Reject Members
    Web->>Controller: POST verification decisions
    Controller->>Database: Update Member Status
    Controller->>Email: Send approval/rejection emails
```

### Member Permissions

Members have permissions through their assigned roles. The system supports multiple roles per member, with different scopes (global, branch-specific, etc.).

#### Role-Based Access Control

```mermaid
erDiagram
    Member {
        int id PK
        string email_address UK
        string sca_name
        enum status
        boolean is_minor
        datetime birth_date
        json warrantable_review
    }
    
    MemberRole {
        int id PK
        int member_id FK
        int role_id FK
        int branch_id FK
        datetime start_date
        datetime end_date
        boolean active
    }
    
    Role {
        int id PK
        string name UK
        boolean admin
        json permissions
    }
    
    Permission {
        int id PK
        string name UK
        string resource
        string action
    }
    
    RolePermission {
        int role_id FK
        int permission_id FK
    }
    
    Member ||--o{ MemberRole : has
    Role ||--o{ MemberRole : assigned
    Role ||--o{ RolePermission : contains
    Permission ||--o{ RolePermission : granted
```

## 4.2 Branches

The Branches module manages the hierarchical organization of the Kingdom's geographic structure.

### Branch Hierarchy

```mermaid
graph TD
    Kingdom[Kingdom] --> Principality
    Kingdom --> Region
    Principality --> Barony
    Region --> Barony
    Barony --> Canton
    Barony --> College
    Region --> Shire
```

### Data Model

```mermaid
classDiagram
    class Branch {
        +id: int
        +name: string
        +type: string
        +domain: string
        +parent_id: int
        +active: bool
        +lft: int
        +rght: int
        +created: datetime
        +modified: datetime
    }
    
    Branch "0..1" -- "0..*" Branch : parent
```

Branches use the nested set model (lft/rght fields) for efficient tree operations and querying.

### Branch Operations

Key operations on branches include:
- Creating new branches with proper hierarchy positioning
- Moving branches within the hierarchy
- Activating/deactivating branches
- Associating officers with branches
- Setting branch domains for authorization

## 4.3 Warrants

The Warrants module manages the official appointments of officers and other warranted positions within the Kingdom. It provides temporal validation for role-based access control through a sophisticated state machine and multi-level approval process.

**Detailed Documentation:** [4.3 Warrant Lifecycle](4.3-warrant-lifecycle.md)

### Data Model

```mermaid
classDiagram
    class Warrant {
        +id: int
        +member_id: int
        +office_id: int
        +branch_id: int
        +start_date: date
        +end_date: date
        +state: string
        +created: datetime
        +modified: datetime
    }
    
    class WarrantPeriod {
        +id: int
        +name: string
        +start_date: date
        +end_date: date
        +created: datetime
        +modified: datetime
    }
    
    class WarrantRoster {
        +id: int
        +officer_id: int
        +branch_id: int
        +warrant_period_id: int
        +status: string
        +created: datetime
        +modified: datetime
    }
    
    Member "1" -- "0..*" Warrant
    Office "1" -- "0..*" Warrant
    Branch "1" -- "0..*" Warrant
    WarrantPeriod "1" -- "0..*" WarrantRoster
    WarrantRoster "0..*" -- "1" Office
    WarrantRoster "0..*" -- "1" Branch
```

### Warrant States

Warrants progress through several states during their lifecycle:

```mermaid
stateDiagram-v2
    [*] --> Pending
    Pending --> Active: Approval
    Pending --> Rejected: Rejection
    Active --> Expired: End date reached
    Active --> Revoked: Administrative action
    Expired --> [*]
    Rejected --> [*]
    Revoked --> [*]
```

### Warrant Roster Process

The warrant roster process allows bulk management of warrants:

1. A warrant period is created (e.g., "Q2 2025")
2. Rosters are created for each branch/office combination
3. Officers are assigned to rosters
4. Rosters go through approval workflow
5. Upon approval, individual warrants are created

## 4.4 Permissions & Roles

The Permissions and Roles module implements the role-based access control (RBAC) system used throughout KMP.

### Data Model

```mermaid
classDiagram
    class Permission {
        +id: int
        +name: string
        +description: string
        +created: datetime
        +modified: datetime
    }
    
    class Role {
        +id: int
        +name: string
        +admin: bool
        +created: datetime
        +modified: datetime
    }
    
    class RolesPermission {
        +id: int
        +role_id: int
        +permission_id: int
        +created: datetime
        +modified: datetime
    }
    
    class PermissionPolicy {
        +id: int
        +permission_id: int
        +policy: string
        +created: datetime
        +modified: datetime
    }
    
    Role "1" -- "0..*" RolesPermission
    Permission "1" -- "0..*" RolesPermission
    Permission "1" -- "0..*" PermissionPolicy
```

### Access Control Flow

```mermaid
sequenceDiagram
    participant User
    participant Controller
    participant Authorization
    participant Role
    participant Permission
    participant Policy
    
    User->>Controller: Request Action
    Controller->>Authorization: Check permission
    Authorization->>Role: Get user roles
    Role-->>Authorization: User roles
    Authorization->>Permission: Check permissions
    Permission-->>Authorization: Permission status
    Authorization->>Policy: Evaluate policies
    Policy-->>Authorization: Policy result
    Authorization-->>Controller: Access decision
    Controller-->>User: Response
```

### Common Permissions

The system includes the following common permission categories:
- **View**: Read-only access to resources
- **Add**: Ability to create new resources
- **Edit**: Ability to modify existing resources
- **Delete**: Ability to remove resources
- **Admin**: Administrative functions for a module

## 4.5 AppSettings

The AppSettings module provides a flexible configuration system that can be modified at runtime through the application UI.

### Data Model

```mermaid
classDiagram
    class AppSetting {
        +id: int
        +name: string
        +value: text
        +type: string
        +required: bool
        +created: datetime
        +modified: datetime
        +created_by: int
        +modified_by: int
    }
```

### Value Types

AppSettings supports several value types through the `type` field:
- **string** (default): Simple text values
- **json**: Structured data stored as JSON
- **yaml**: Structured data stored as YAML

### Setting Categories

Settings are organized by prefix conventions:
- **KMP.**: Core application settings
- **Email.**: Email configuration
- **Member.**: Member-related settings
- **Activity.**: Activity module settings
- **Warrant.**: Warrant system settings
- **Branches.**: Branch management settings
- **Plugin.{PluginName}.**: Plugin-specific settings

### Accessing Settings

Settings can be accessed through the StaticHelpers class:

```php
// Get a setting with a default value
$siteTitle = StaticHelpers::getAppSetting("KMP.ShortSiteTitle", "KMP");

// Get with default value and create if missing
$setting = StaticHelpers::getAppSetting("KMP.Setting", "default", null, true);

// Set a setting value
StaticHelpers::setAppSetting("KMP.Setting", "new value");
```

### UI Management

AppSettings provides an admin interface for managing settings, including:
- Viewing all settings
- Filtering by name prefix
- Editing values
- Adding new settings
- Exporting settings as YAML

## 4.6 View Patterns

The View layer in KMP provides a comprehensive presentation system built on CakePHP's MVC architecture with Bootstrap UI integration and custom helpers for KMP-specific functionality.

**Detailed Documentation:** [4.5 View Patterns](4.5-view-patterns.md)

### Key Components

- **AppView**: Base view class with integrated helper loading and Bootstrap UI framework
- **KmpHelper**: Custom helper providing KMP-specific form controls, data conversion, and UI utilities
- **View Cells**: Reusable UI components including AppNavCell, NavigationCell, and NotesCell
- **Template System**: Hierarchical templates with responsive layouts and security integration

### Architecture Overview

```mermaid
classDiagram
    class AppView {
        +initialize()
        +loadHelper(string)
        +loadPlugin(string)
    }
    
    class KmpHelper {
        +autoCompleteControl()
        +comboBoxControl()
        +bool()
        +appNav()
        +getAppSetting()
    }
    
    class AppNavCell {
        +display(array, Member, array)
    }
    
    class NavigationCell {
        +display(array, array)
    }
    
    class NotesCell {
        +display(string, int, array)
    }
    
    AppView --> KmpHelper : uses
    AppView --> AppNavCell : renders
    AppView --> NavigationCell : renders
    AppView --> NotesCell : renders
```

The view system provides advanced form controls, permission-based rendering, asset optimization, and comprehensive security patterns for safe user interface generation.
