---
layout: default
---
[← Back to Table of Contents](index.md)

# 4. Core Modules

This section documents the primary modules that make up the foundation of the Kingdom Management Portal. These modules manage the essential data and functionality required across the entire application.

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

### Member Permissions

Members have permissions through their assigned roles. The system supports multiple roles per member, with different scopes (global, branch-specific, etc.).

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

The Warrants module manages the official appointments of officers and other warranted positions within the Kingdom.

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
        +value_type: string
        +description: string
        +required: bool
        +created: datetime
        +modified: datetime
    }
```

### Value Types

AppSettings supports several value types:
- **string**: Simple text values
- **int**: Integer values
- **bool**: Boolean values (yes/no)
- **yaml**: Structured data stored as YAML
- **json**: Structured data stored as JSON

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
