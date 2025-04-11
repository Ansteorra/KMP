# System Architecture

## Overview

The Kingdom Management Portal (KMP) is built on the CakePHP framework, utilizing its MVC (Model-View-Controller) architecture pattern. This document provides an overview of the system's architecture, key components, and how they interact.

## Application Structure

The KMP follows the standard CakePHP directory structure with some additional custom components:

```
app/
├── assets/              # Frontend assets (CSS, JavaScript)
├── config/              # Configuration files and database migrations
├── plugins/             # Custom plugins (Activities, Awards, Bootstrap, Officers, etc.)
├── src/                 # Application source code
│   ├── Application.php  # Main application class
│   ├── Command/         # Command line commands
│   ├── Controller/      # Controllers
│   ├── Event/           # Event listeners
│   ├── Form/            # Form classes
│   ├── Identifier/      # Authentication identifiers
│   ├── KMP/             # Core KMP functionality
│   ├── Mailer/          # Email functionality
│   ├── Model/           # Models and tables
│   ├── Policy/          # Authorization policies
│   ├── Services/        # Service classes
│   └── View/            # View classes and helpers
├── templates/           # Template files for rendering HTML
├── tests/               # Test files
├── tmp/                 # Temporary files
└── webroot/             # Publicly accessible files
```

## Core Components

### Application Class

The `Application.php` file serves as the entry point for the application, setting up:

- Authentication and authorization services
- Middleware configuration
- Plugin loading
- Database connections
- Event handlers
- Default application settings

### Request Lifecycle

1. A request is received and routed to the appropriate controller action
2. Middleware processes the request (authentication, authorization, CSRF protection)
3. The controller processes the request, interacting with models as needed
4. The controller renders a view or returns data
5. The response is sent back to the user

## Key Architectural Patterns

### Model-View-Controller (MVC)

KMP strictly follows the MVC pattern provided by CakePHP:
- **Models**: Handle data validation, retrieval, and manipulation
- **Views**: Present data to users
- **Controllers**: Process user input, coordinate model operations, and select views

### Service Layer

The application implements a service layer pattern in `src/Services/` to encapsulate complex business logic and operations:

- `WarrantManager`: Manages warrant-related operations
- `ActiveWindowManager`: Manages time-bounded entities (like warrants and roles)
- `AuthorizationService`: Custom authorization logic

### Plugins

The KMP extends its functionality through plugins:
- `Activities`: Manages member activities and events
- `Awards`: Handles awards and recognitions
- `Bootstrap`: UI components based on Bootstrap
- `GitHubIssueSubmitter`: Integration for submitting issues to GitHub
- `Officers`: Officer management functionality
- `Queue`: Background job processing

### Event System

The application uses CakePHP's event system to handle cross-cutting concerns and plugin communication:

- `CallForNavHandler`: Manages navigation menu construction
- Other event listeners for various system events

## Data Flow

1. Users interact with the application through web interfaces
2. Controllers receive and process user requests
3. Services handle complex business logic
4. Models interact with the database
5. Views render data for user presentation

## Dependency Injection

KMP uses CakePHP's dependency injection container in `Application::services()` to manage service dependencies:

```php
public function services(ContainerInterface $container): void
{
    $container->add(
        ActiveWindowManagerInterface::class,
        DefaultActiveWindowManager::class,
    );
    $container->add(
        WarrantManagerInterface::class,
        DefaultWarrantManager::class,
    )->addArgument(ActiveWindowManagerInterface::class);
}
```

## Configuration Management

Application settings are managed through a combination of:
- CakePHP configuration files in `config/`
- Database-stored settings accessed via `StaticHelpers::getAppSetting()`
- Environment-specific settings in `config/app_local.php`

## Security Architecture

The application implements several security measures:
- Authentication through the CakePHP Authentication plugin
- Authorization using the CakePHP Authorization plugin
- CSRF protection middleware
- Custom permission policies for fine-grained access control
- Secure password handling with fallback support for legacy passwords

## Next Steps

- For more information on specific components, see [Core Components](./core-components.md)
- To understand the database structure, see [Database Structure and Models](./database-models.md)
- To learn about the plugin system, see [Plugin System](./plugins.md)