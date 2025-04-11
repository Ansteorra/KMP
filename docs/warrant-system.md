# Warrant System

This document details the Warrant System in the Kingdom Management Portal (KMP), a critical component for managing official authorizations and appointments within the SCA kingdom.

## Overview

In SCA kingdoms, many official positions and activities require formal authorization, known as a "warrant." The KMP Warrant System provides a comprehensive framework for managing these warrants, including requests, approvals, renewals, and tracking.

## Key Concepts

### Warrant

A warrant is an official authorization for a member to perform a specific role or function within the kingdom. Warrants have:

- A start date and expiration date (time window)
- A status (Pending, Approved, Declined, Expired, Revoked)
- A member to whom the warrant is issued
- A role associated with the warrant
- An entity type and ID (what is being warranted)

### Warrant Roster

A warrant roster is a collection of related warrants that are processed together. For example, all marshal warrants for a specific event or all officer warrants for a branch. Rosters enable efficient batch processing of related warrants.

### Warrant Period

Warrant periods define standard time frames for groups of warrants, helping to standardize and organize the renewal process.

## Database Structure

### Warrants Table

The `warrants` table stores individual warrant records:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the warrant |
| `member_id` | Member receiving the warrant |
| `warrant_roster_id` | Related roster (if any) |
| `entity_type` | Type of entity being warranted |
| `entity_id` | ID of the entity being warranted |
| `member_role_id` | Associated member role |
| `name` | Name/description of the warrant |
| `start_on` | When the warrant becomes active |
| `expires_on` | When the warrant expires |
| `approved_date` | When the warrant was approved |
| `status` | Current status of the warrant |
| `revoked_reason` | Reason if revoked |
| `revoker_id` | Who revoked the warrant (if applicable) |
| `created`, `created_by` | Creation audit information |
| `modified`, `modified_by` | Last modified audit information |

### Warrant Rosters Table

The `warrant_rosters` table manages collections of warrants:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the roster |
| `name` | Name of the roster |
| `status` | Status of the entire roster |
| `approval_count` | Current count of approvals |
| `approvals_required` | Number of approvals needed |
| `created`, `created_by` | Creation audit information |
| `modified`, `modified_by` | Last modified audit information |

### Warrant Roster Approvals Table

The `warrant_roster_approvals` table tracks who has approved a roster:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the approval |
| `warrant_roster_id` | ID of the approved roster |
| `approver_id` | ID of the member who approved |
| `approved_on` | When the approval was given |

### Warrant Periods Table

The `warrant_periods` table defines standard warrant timeframes:

| Field | Description |
|-------|-------------|
| `id` | Unique identifier for the period |
| `start_date` | When the period begins |
| `end_date` | When the period ends |
| `created`, `created_by` | Creation audit information |

## Components and Services

### Warrant Manager Service

The `WarrantManagerInterface` and its implementation `DefaultWarrantManager` provide the business logic for warrant operations:

```php
// Interface defining warrant operations
interface WarrantManagerInterface
{
    public function request(string $name, string $description, array $warrants): ServiceResult;
    public function approve(WarrantRoster $roster, Member $approvingMember): ServiceResult;
    public function decline(WarrantRoster $roster, string $reason, Member $decliningMember): ServiceResult;
    public function revoke(Warrant $warrant, string $reason, Member $revokingMember): ServiceResult;
    // Additional methods...
}
```

The service is registered in the application's service container:

```php
// In Application.php
public function services(ContainerInterface $container): void
{
    // Other services...
    $container->add(
        WarrantManagerInterface::class,
        DefaultWarrantManager::class,
    )->addArgument(ActiveWindowManagerInterface::class);
}
```

### Warrant Request

The `WarrantRequest` class encapsulates the data needed to create a new warrant:

```php
class WarrantRequest
{
    public function __construct(
        string $name,
        string $entityType,
        int $entityId,
        int $creatorId,
        int $memberId,
        DateTime $startOn,
        DateTime $expiresOn,
        ?int $memberRoleId = null
    ) {
        // Initialize properties
    }
}
```

### Controllers

- **WarrantsController**: Manages individual warrants
- **WarrantRostersController**: Manages warrant rosters and approval processes
- **WarrantPeriodsController**: Manages warrant periods

## Workflow

### Warrant Request Process

1. A warrant request is created (manually or via a roster)
2. The request is converted to a `WarrantRequest` object
3. The `WarrantManager` service processes the request, creating:
   - A warrant record in Pending status
   - Association with a warrant roster if applicable
4. Notifications are sent to approvers

### Approval Process

1. Authorized approvers review the warrant or roster
2. Approvers can approve or decline the warrant(s)
3. The `WarrantManager` service handles the approval process:
   - Updates the status of the warrant(s)
   - Creates associated member roles if needed
   - Sends notifications to relevant parties
   - Records the approval in `warrant_roster_approvals`
4. When the required number of approvals (`approvals_required`) is reached, the warrant is activated

### Renewal Process

1. As warrants approach expiration, they appear in renewal reports
2. New warrant requests can be created to replace expiring warrants
3. The renewal process follows the standard approval workflow
4. Upon approval, the new warrant becomes active when the old one expires

### Revocation Process

1. Authorized users can revoke active warrants
2. The `WarrantManager` service handles revocation:
   - Updates the warrant status to Revoked
   - Records the reason and the revoking user
   - Deactivates associated member roles
   - Sends notifications to relevant parties

## Integration with Authorization

The warrant system integrates with the authorization system:

```php
// Example policy check using warrant status
public function canApproveEvent($user, $event)
{
    // Check for the permission and an active event-steward warrant
    return $user->can('approve_events') && 
           $user->hasActiveWarrant('event-steward');
}
```

## Officers Plugin Integration

The Officers plugin makes extensive use of the warrant system for managing officer warrants:

- Officers are assigned warrants for their positions
- Warrant renewals are handled through officer rosters
- Officer reports can be filtered by warrant status

The `officers_officers` table includes a `granted_member_role_id` field that links to the `member_roles` table, which in turn can be associated with warrants.

## User Interface

The warrant system provides several UI components:

- Warrant management screens for individual warrants
- Roster management for batch processing
- Approval interfaces for reviewers
- Reports for tracking warrant status
- Renewal workflows for expiring warrants

## Example: Creating a Warrant Roster

```php
// In a controller action
public function createRoster()
{
    // Create warrant requests for each officer
    $warrants = [];
    foreach ($officers as $officer) {
        $warrants[] = new WarrantRequest(
            "Officer: " . $officer->branch->name . " " . $officer->office->name,
            'Officers.Officers',
            $officer->id,
            $currentUser->id,
            $officer->member_id,
            $startDate,
            $endDate,
            $officer->granted_member_role_id
        );
    }
    
    // Use the warrant manager service to create the roster
    $result = $this->warrantManager->request(
        "Officer Roster for Q2 2025",
        "Quarterly officer warrants",
        $warrants
    );
    
    if ($result->success) {
        // Handle success
    } else {
        // Handle error
    }
}
```

## Next Steps

- For more information on the authorization system, see [Authentication and Authorization](./auth.md)
- To understand how the Officers plugin uses warrants, see [Plugin System](./plugins.md)
- For core entity concepts, see [Database Structure and Models](./database-models.md)