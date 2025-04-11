# Database Structure and Models

This document outlines the database structure, entity relationships, and model layer implementation in the Kingdom Management Portal (KMP).

## Database Schema Overview

The KMP database is structured around several core entity types that represent members, branches, roles, permissions, warrants, and other kingdom-specific concepts. The database includes components for membership management, role-based permissions, branch organization, warrant tracking, officer management, activity tracking, and award systems.

## Core Entities

### Members

The `members` table stores information about individual SCA members:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the member |
| `email_address` | Email address (used for authentication) |
| `password` | Hashed password |
| `sca_name` | Member's SCA name |
| `membership_number` | SCA membership number |
| `membership_expires_on` | Membership expiration date |
| `first_name`, `last_name`, `middle_name` | Legal name |
| `title` | Member's title |
| `pronouns` | Member's preferred pronouns |
| `pronunciation` | Pronunciation guide for member's name |
| `phone_number` | Contact phone number |
| `street_address`, `city`, `state`, `zip` | Physical address |
| `warrantable` | Flag indicating if member can receive warrants |
| `birth_month`, `birth_year` | Birth date information |
| `status` | Account status (active, disabled, etc.) |
| `branch_id` | Member's home branch |
| `additional_info` | Additional member information |
| `background_check_expires_on` | Date background check expires |
| `mobile_card_token` | Token for mobile membership card |
| `membership_card_path` | Path to stored membership card |
| `parent_id` | Parent ID for minor members |
| `verified_by`, `verified_date` | Account verification information |

### Branches

The `branches` table represents SCA kingdom organizational units:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the branch |
| `name` | Branch name |
| `parent_id` | ID of parent branch (for hierarchy) |
| `type` | Branch type (Kingdom, Principality, Region, Local Group) |
| `domain` | Domain/URL for branch website |
| `links` | JSON array of external links |
| `location` | Geographic location information |
| `can_have_members` | Flag indicating if members can belong to this branch |
| `lft`, `rght` | Nested set model fields for tree structure |

### Roles

The `roles` table defines user roles in the system:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the role |
| `name` | Role name |
| `is_system` | Flag indicating system-defined role |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Permissions

The `permissions` table defines access permissions:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the permission |
| `name` | Permission name |
| `requires_warrant` | Whether the permission requires a warrant |
| `is_super_user` | Flag for superuser permissions |
| `is_system` | Flag for system-defined permissions |
| `require_active_membership` | Whether an active membership is required |
| `require_active_background_check` | Whether an active background check is required |
| `require_min_age` | Minimum age requirement |
| `scoping_rule` | Rule for permission scope |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Permission Policies

The `permission_policies` table maps permissions to policy classes and methods:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the policy |
| `permission_id` | Associated permission ID |
| `policy_class` | PHP class name for the policy |
| `policy_method` | Method name in the policy class |

### MemberRoles

The `member_roles` table associates members with roles:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the member-role association |
| `member_id` | Member ID |
| `role_id` | Role ID |
| `start_on` | When the role begins |
| `expires_on` | When the role expires |
| `approver_id` | Who approved the role assignment |
| `revoker_id` | Who revoked the role assignment |
| `branch_id` | Branch scope of the role |
| `entity_type`, `entity_id` | For roles associated with entities |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |

### Warrants

The `warrants` table handles official authorizations:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the warrant |
| `member_id` | Member ID |
| `warrant_roster_id` | Associated roster ID |
| `entity_type` | Type of entity being warranted |
| `entity_id` | ID of the entity being warranted |
| `member_role_id` | Associated member role |
| `name` | Warrant name/description |
| `start_on` | When the warrant begins |
| `expires_on` | When the warrant expires |
| `status` | Warrant status |
| `approved_date` | When the warrant was approved |
| `revoked_reason` | Reason for revocation |
| `revoker_id` | Who revoked the warrant |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |

### Warrant Rosters

The `warrant_rosters` table manages collections of warrants:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the roster |
| `name` | Name of the roster |
| `status` | Status of the roster |
| `approval_count` | Number of approvals received |
| `approvals_required` | Number of approvals needed |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |

### Warrant Roster Approvals

The `warrant_roster_approvals` table tracks approvals for warrant rosters:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the approval |
| `warrant_roster_id` | ID of the warrant roster |
| `approver_id` | ID of the member who approved |
| `approved_on` | When the approval was given |

### Warrant Periods

The `warrant_periods` table defines standard warrant timeframes:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the period |
| `start_date` | When the period begins |
| `end_date` | When the period ends |
| `created`, `created_by` | Creation audit fields |

## Officers Plugin Entities

### Departments

The `officers_departments` table defines officer departments:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the department |
| `name` | Department name |
| `domain` | Department domain/email domain |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Offices

The `officers_offices` table defines officer positions:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the office |
| `name` | Office name |
| `department_id` | Department ID |
| `reports_to_id` | ID of the office this reports to |
| `deputy_to_id` | ID of the office this is deputy to |
| `grants_role_id` | Role ID granted by this office |
| `requires_warrant` | Whether the office requires a warrant |
| `term_length` | Length of the office term in days |
| `applicable_branch_types` | Branch types where this office applies |
| `can_skip_report` | Whether the office can skip reports |
| `required_office` | Whether the office is required |
| `only_one_per_branch` | Whether only one of this office is allowed per branch |
| `default_contact_address` | Default contact email |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Officers

The `officers_officers` table tracks officer appointments:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the officer record |
| `member_id` | Member ID |
| `office_id` | Office ID |
| `branch_id` | Branch ID |
| `start_on` | Start date of the appointment |
| `expires_on` | End date of the appointment |
| `status` | Status of the appointment |
| `email_address` | Officer email address |
| `reports_to_office_id` | Office ID this officer reports to |
| `reports_to_branch_id` | Branch ID this officer reports to |
| `deputy_to_office_id` | Office ID this officer is deputy to |
| `deputy_to_branch_id` | Branch ID this officer is deputy to |
| `deputy_description` | Description of deputy role |
| `granted_member_role_id` | Member role granted by this position |
| `approval_date` | When the appointment was approved |
| `approver_id` | Who approved the appointment |
| `revoked_reason` | Reason for revocation |
| `revoker_id` | Who revoked the appointment |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |

## Activities Plugin Entities

### Activity Groups

The `activities_activity_groups` table organizes activities into groups:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the group |
| `name` | Group name |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Activities

The `activities_activities` table defines different activities:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the activity |
| `name` | Activity name |
| `activity_group_id` | Group this activity belongs to |
| `permission_id` | Permission required for this activity |
| `grants_role_id` | Role granted by this activity |
| `term_length` | How long the authorization lasts |
| `num_required_authorizors` | Required number of approvers |
| `num_required_renewers` | Required number of renewal approvers |
| `minimum_age`, `maximum_age` | Age restrictions |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Authorizations

The `activities_authorizations` table tracks activity authorizations:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the authorization |
| `member_id` | Member ID |
| `activity_id` | Activity ID |
| `status` | Authorization status |
| `start_on` | Start date |
| `expires_on` | Expiration date |
| `is_renewal` | Whether this is a renewal |
| `approval_count` | Number of approvals received |
| `granted_member_role_id` | Member role granted by this authorization |
| `revoked_reason` | Reason for revocation |
| `revoker_id` | Who revoked the authorization |
| `created` | Creation timestamp |

### Authorization Approvals

The `activities_authorization_approvals` table tracks approvals for authorizations:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the approval |
| `authorization_id` | Authorization ID |
| `approver_id` | Approver member ID |
| `authorization_token` | Token for the approval process |
| `approved` | Whether approved or declined |
| `approver_notes` | Notes from the approver |
| `requested_on` | When the approval was requested |
| `responded_on` | When the approver responded |

## Awards Plugin Entities

### Award Domains

The `awards_domains` table defines domains for awards:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the domain |
| `name` | Domain name |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Award Levels

The `awards_levels` table defines award levels:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the level |
| `name` | Level name |
| `progression_order` | Order in the progression hierarchy |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Awards

The `awards_awards` table defines available awards:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the award |
| `name` | Award name |
| `abbreviation` | Award abbreviation |
| `domain_id` | Award domain ID |
| `level_id` | Award level ID |
| `branch_id` | Branch ID |
| `description` | Award description |
| `charter` | Award charter text |
| `badge` | Badge description/image |
| `insignia` | Insignia description/image |
| `specialties` | Award specialties |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Events

The `awards_events` table tracks events where awards can be given:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the event |
| `name` | Event name |
| `branch_id` | Branch hosting the event |
| `start_date` | Event start date |
| `end_date` | Event end date |
| `description` | Event description |
| `closed` | Whether the event is closed |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

### Recommendations

The `awards_recommendations` table tracks award recommendations:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the recommendation |
| `award_id` | Award ID |
| `member_id` | Member ID (recipient) |
| `member_sca_name` | Recipient SCA name |
| `requester_id` | Member ID (requester) |
| `requester_sca_name` | Requester SCA name |
| `branch_id` | Branch ID |
| `reason` | Reason for the recommendation |
| `event_id` | Target event ID |
| `specialty` | Award specialty |
| `status` | Recommendation status |
| `state` | Processing state |
| `state_date` | State date |
| `stack_rank` | Priority/ranking |
| `contact_email` | Contact email |
| `contact_number` | Contact phone number |
| `court_availability` | Court availability information |
| `call_into_court` | Call into court information |
| `person_to_notify` | Person to notify about the award |
| `given` | Date the award was given |
| `no_action_reason` | Reason for no action |
| `close_reason` | Reason for closing the recommendation |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |
| `deleted` | Soft-delete timestamp |

## Other Entities

### App Settings

The `app_settings` table stores application configuration settings:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the setting |
| `name` | Setting name |
| `value` | Setting value |
| `type` | Data type of the value |
| `required` | Whether the setting is required |
| `created`, `created_by`, `modified`, `modified_by` | Audit fields |

### Notes

The `notes` table provides a general-purpose notes system:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the note |
| `subject` | Note subject |
| `body` | Note text |
| `entity_type` | Type of entity the note is attached to |
| `entity_id` | ID of the entity the note is attached to |
| `author_id` | Author member ID |
| `private` | Whether the note is private |
| `created` | Creation timestamp |

### Queue System

The system includes several tables for background job processing:

- `queued_jobs`: Stores jobs to be processed
- `queue_processes`: Tracks queue worker processes

## Key Relationships

The database includes numerous relationships between entities:

- **Members to Branches**: Members belong to branches
- **Branches to Branches**: Branches have parent-child relationships
- **Members to Roles**: Many-to-many through `member_roles`
- **Roles to Permissions**: Many-to-many through `roles_permissions`
- **Members to Warrants**: One-to-many
- **Warrants to WarrantRosters**: Many-to-one
- **Members to Officers**: One-to-many
- **Offices to Departments**: Many-to-one
- **Members to Authorizations**: One-to-many
- **Activities to ActivityGroups**: Many-to-one
- **Awards to Domains and Levels**: Many-to-one relationships
- **Members to Award Recommendations**: One-to-many as both recipients and requesters

## Model Implementation

### Table Classes

Table classes in KMP extend CakePHP's `Table` class and define:

- Associations between models
- Validation rules
- Default query scopes
- Behavior attachments
- Custom finder methods

Example from `MembersTable`:

```php
// Defining associations
$this->hasMany('MemberRoles');
$this->belongsTo('Branches');
$this->hasMany('Warrants');

// Adding behaviors
$this->addBehavior('Timestamp');

// Defining validation rules
$validator = new Validator();
$validator->email('email_address')
    ->notEmptyString('email_address', 'An email address is required');
// ... more validation rules
```

### Entity Classes

Entity classes encapsulate data and provide accessors/mutators:

- Virtual properties
- Hidden properties (for security)
- Accessor/mutator methods
- Business logic specific to the entity

Example from the `Member` entity:

```php
protected $_hidden = [
    'password',
];

protected function _getFullName(): string
{
    return $this->first_name . ' ' . $this->last_name;
}
```

### ActiveWindow Pattern

The KMP implements an "Active Window" pattern for time-bounded entities:

- `ActiveWindowBaseEntity`: Base class for entities with start/end dates
- `ActiveWindowManagerInterface`: Interface for managing time windows
- `setValidFilter()`: Method to filter entities by validity

This pattern is used for both roles and warrants to manage their lifecycle and validity.

## Database Migrations

KMP uses CakePHP's migration system to manage database schema changes:

- Migration files are stored in `config/Migrations/`
- Each migration file includes both up and down methods
- Migrations are version-controlled and applied sequentially

Example migration:

```php
public function up(): void
{
    $table = $this->table('branches');
    $table->addColumn('domain', 'string', [
        'default' => null,
        'limit' => 255,
        'null' => true,
    ]);
    $table->update();
}
```

## Query Building

KMP uses CakePHP's query builder for database operations:

```php
// Example: Finding active members with specific roles
$members = $this->Members->find()
    ->contain([
        'MemberRoles' => function ($q) use ($validOn) {
            return $q->where([
                'start_on <=' => $validOn,
                'OR' => [
                    'expires_on >=' => $validOn,
                    'expires_on IS' => null,
                ],
            ]);
        },
        'MemberRoles.Roles.Permissions',
    ])
    ->all();
```

## Next Steps

- For more information on application services, see [Core Components](./core-components.md)
- To understand warrant-specific functionality, see [Warrant System](./warrant-system.md)
- To learn about plugins that extend the data model, see [Plugin System](./plugins.md)