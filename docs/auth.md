# Authentication and Authorization

This document details the authentication and authorization systems in the Kingdom Management Portal (KMP), explaining how users are authenticated and how permissions are enforced throughout the application.

## Authentication System

KMP uses CakePHP's Authentication plugin to handle user authentication, with custom extensions for the specific needs of the SCA kingdom.

### Authentication Configuration

Authentication is configured in the `Application.php` file in the `getAuthenticationService()` method:

```php
public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
{
    $service = new AuthenticationService();

    // Configure redirect for unauthenticated users
    $service->setConfig([
        "unauthenticatedRedirect" => Router::url([
            "prefix" => false,
            "plugin" => null,
            "controller" => "Members",
            "action" => "login",
        ]),
        "queryParam" => "redirect",
    ]);

    // Define credential fields
    $fields = [
        AbstractIdentifier::CREDENTIAL_USERNAME => "email_address",
        AbstractIdentifier::CREDENTIAL_PASSWORD => "password",
    ];

    // Load authenticators (Session first, then Form)
    $service->loadAuthenticator("Authentication.Session");
    $service->loadAuthenticator("Authentication.Form", [
        "fields" => $fields,
        "loginUrl" => Router::url([
            "prefix" => false,
            "plugin" => null,
            "controller" => "Members",
            "action" => "login",
        ]),
    ]);

    // Load identifiers with fallback password support
    $service->loadIdentifier("KMPBruteForcePassword", [
        "resolver" => [
            "className" => "Authentication.Orm",
            "userModel" => "Members",
        ],
        "fields" => $fields,
        "passwordHasher" => [
            "className" => "Authentication.Fallback",
            "hashers" => [
                "Authentication.Default",
                [
                    "className" => "Authentication.Legacy",
                    "hashType" => "md5",
                    "salt" => false,
                ],
            ],
        ],
    ]);

    return $service;
}
```

### Authentication Flow

1. User attempts to access a protected resource
2. If not authenticated, user is redirected to the login page
3. User submits credentials (email and password)
4. Authentication system verifies credentials against the Members table
5. If valid, user identity is stored in the session
6. User is redirected to the originally requested page

### Custom Identifier

KMP uses a custom `KMPBruteForcePassword` identifier that extends the standard password identifier with brute force protection mechanisms.

### Password Handling

The system supports both modern and legacy password hashing through the Fallback hasher configuration:

- Modern passwords use the default CakePHP password hasher
- Legacy passwords use MD5 hashing (for backward compatibility)
- When a user logs in with a legacy password, it's automatically upgraded to the modern format

## Authorization System

KMP implements a comprehensive Role-Based Access Control (RBAC) system using CakePHP's Authorization plugin, with custom extensions for time-bounded roles and warrants.

### Authorization Configuration

Authorization is configured in the `Application.php` file in the `getAuthorizationService()` method:

```php
public function getAuthorizationService(ServerRequestInterface $request): AuthorizationServiceInterface
{
    $lastResortResolver = new ControllerResolver();
    $ormResolver = new OrmResolver();
    $resolver = new ResolverCollection([$ormResolver, $lastResortResolver]);

    return new KmpAuthorizationService($resolver);
}
```

### Policy Resolver Chain

KMP uses a chain of policy resolvers to determine the appropriate policy for an authorization check:

1. **ORM Resolver**: Maps entities to their corresponding policy classes
2. **Controller Resolver**: Provides fallback policies for controllers

### Custom Authorization Service

The `KmpAuthorizationService` extends the standard Authorization service with kingdom-specific capabilities:

- Time-bounded role checking
- Warrant verification
- Permission inheritance

### Policy Classes

Policy classes encapsulate authorization logic for specific resources:

```php
class MemberPolicy
{
    public function canView($user, $member)
    {
        // Check if user has permission to view members
        return $user->can('view_members') || $member->id === $user->id;
    }

    public function canEdit($user, $member)
    {
        // Check if user has permission to edit members
        return $user->can('edit_members') || $member->id === $user->id;
    }
}
```

### Permission-Based Authorization

Permissions in KMP:

1. Are assigned to roles
2. Roles are assigned to members with validity windows
3. Members can have multiple roles
4. Some permissions require warrants for enforcement

### Warrant-Based Authorization

For certain actions, having the appropriate role is not enough—a valid warrant is also required:

```php
// Example policy check involving warrants
public function canApproveRoster($user, $roster)
{
    // Must have both the permission and a valid warrant
    return $user->can('approve_warrants') && $user->hasValidWarrant('warrant_approver');
}
```

### Authorization Middleware

The authorization middleware:

1. Intercepts all requests
2. Decorates the authenticated user with authorization capabilities
3. Enforces authorization checks throughout the application
4. Redirects unauthorized users to an appropriate page

## Identity Object

The authenticated user identity is enhanced with authorization methods:

- `can($permission)`: Checks if the user has a given permission
- `hasRole($role)`: Checks if the user has a given role
- `hasValidWarrant($type)`: Checks if the user has a valid warrant of the specified type

## Usage in Controllers

Authorization is used in controllers through the `Authorization` component:

```php
// Authorize based on the current controller/action
$this->Authorization->authorize($this);

// Authorize based on an entity
$member = $this->Members->get($id);
$this->Authorization->authorize($member, 'edit');

// Authorize based on a custom policy action
$this->Authorization->authorize($report, 'generate');
```

## Usage in Templates

Authorization checks can be performed in templates:

```php
<?php if ($this->Identity->can('edit_members')): ?>
    <?= $this->Html->link('Edit', ['action' => 'edit', $member->id]) ?>
<?php endif; ?>
```

## Security Considerations

- Failed login attempts are tracked to prevent brute force attacks
- Permissions are checked at multiple levels (controller, entity, view)
- Sensitive operations require valid warrants
- User sessions have appropriate timeout settings
- CSRF protection is enabled for all state-changing operations

## Next Steps

- For more information on the role system, see [Core Components](./core-components.md)
- To understand how warrants extend authorization, see [Warrant System](./warrant-system.md)
- For plugin-specific authorization, see [Plugin System](./plugins.md)