# Core Components

This document provides detailed information about the core components of the Kingdom Management Portal (KMP) system.

## Member Management

The member management system is the foundation of the KMP, providing functionality for registering, authenticating, and managing SCA Kingdom members.

### Key Components

- **MembersController**: Handles member registration, authentication, profile management
- **MembersTable**: Data validation, retrieval, and business logic for members
- **Member Entity**: Represents individual kingdom members with properties like:
  - Personal information (name, contact details)
  - SCA-specific information (SCA name, membership number)
  - Authentication information (email, password)
  - Membership status and expiration

### Features

- Member registration and account management
- Profile management
- Membership verification and status tracking
- Authentication and session management
- Mobile card generation for digital identification

## Role and Permission System

KMP implements a comprehensive role-based access control (RBAC) system to manage permissions across the application.

### Key Components

- **RolesController**: Manages roles within the system
- **RolesTable**: Handles role data management
- **PermissionsController**: Manages permissions for roles
- **PermissionsTable**: Handles permission data and relationships
- **MemberRolesTable**: Manages the assignments of roles to members
- **AuthorizationService**: Custom service for authorization logic

### Features

- Role creation and management
- Permission assignment to roles
- Assigning roles to members with time constraints (validity windows)
- Runtime permission checking and enforcement
- Permission policies for different system components

## Branch Management

The branch management system handles the hierarchical structure of SCA Kingdom branches (kingdoms, principalities, regions, local groups).

### Key Components

- **BranchesController**: Manages branch creation, updates, and hierarchy
- **BranchesTable**: Branch data management and relationships
- **Branch Entity**: Represents different branch types with properties like:
  - Branch name and type
  - Parent branch (for hierarchical relationships)
  - Geographic information
  - Contact information and website links

### Features

- Branch creation and hierarchy management
- Branch officer assignments and tracking
- Branch contact information management
- Branch location and geographic details

## Warrant System

The warrant system manages official authorizations and appointments within the Kingdom.

### Key Components

- **WarrantsController**: Manages warrant lifecycle
- **WarrantRostersController**: Handles grouped warrant approvals
- **WarrantPeriodsController**: Manages warrant validity periods
- **WarrantManagerService**: Business logic for warrant operations

### Features

- Warrant request and approval workflows
- Roster-based warrant management
- Warrant period tracking
- Warrant renewal and expiration handling
- Warrant revocation

## Static Helpers

The `StaticHelpers` class provides utility functions used throughout the application, particularly for accessing application settings.

### Key Methods

- `getAppSetting()`: Retrieves application settings from the database
- `setAppSetting()`: Updates application settings in the database
- Various utility methods for data formatting and manipulation

## Active Window Management

The Active Window Management system handles time-bounded entities like roles and warrants.

### Key Components

- **ActiveWindowManagerInterface**: Interface for active window operations
- **DefaultActiveWindowManager**: Implementation of the active window manager
- **ActiveWindowBaseEntity**: Base entity class for time-bounded entities

### Features

- Validity window enforcement
- Current/upcoming/expired status determination
- Filtering entities by validity status

## UI Components

The UI system provides consistent user interface components across the application.

### Key Components

- **Bootstrap Plugin**: Provides Bootstrap UI components
- **AppView**: Base view class with common functionality
- **KMPHelper**: Template helper for common UI operations
- **Layout Templates**: Base layouts for different page types

### Features

- Consistent UI presentation
- Navigation generation based on user permissions
- Form rendering and validation presentation
- Notification and flash message display

## Next Steps

- For information about the database structure behind these components, see [Database Structure and Models](./database-models.md)
- To understand the plugin system that extends these core components, see [Plugin System](./plugins.md)
- For authentication and authorization details, see [Authentication and Authorization](./auth.md)