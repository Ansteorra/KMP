# Plugin System

This document describes the plugin architecture of the Kingdom Management Portal (KMP), explaining the available plugins, their functionality, and how they integrate with the core system.

## Plugin Architecture Overview

KMP uses CakePHP's plugin system to modularize functionality and allow for extensibility. Plugins in KMP can:

- Add new controllers, models, and views
- Extend existing functionality
- Provide specialized features for specific kingdom needs
- Isolate related functionality into cohesive modules

## Core Plugins

### Activities Plugin

The Activities plugin manages member activities and events within the kingdom.

**Key Features:**
- Activity tracking and management
- Event participation records
- Activity reporting

**Key Components:**
- Controllers for managing activities
- Models for storing activity data
- Views for displaying activity information

**Integration Points:**
- Member participation tracking
- Secretary email notifications
- Authorization through core permission system

### Awards Plugin

The Awards plugin manages the various awards and recognitions within the SCA kingdom.

**Key Features:**
- Award tracking and management
- Award recommendation workflows
- Award recipient history

**Key Components:**
- Award entity models
- Award recommendation controllers
- Award display views

**Integration Points:**
- Member profiles for displaying awards
- Recommendation workflows
- Authorization through core permission system

### Bootstrap Plugin

The Bootstrap plugin provides UI components based on the Bootstrap framework, enhancing the default CakePHP UI components.

**Key Features:**
- Bootstrap-compatible UI components
- Custom form controls
- Responsive layout helpers

**Key Components:**
- View helpers for rendering Bootstrap components
- Layout templates
- CSS and JavaScript assets

**Integration Points:**
- Used throughout the application for UI rendering
- Extends CakePHP's form helpers

### GitHubIssueSubmitter Plugin

The GitHubIssueSubmitter plugin allows users to submit issues directly to the KMP GitHub repository.

**Key Features:**
- Issue submission forms
- GitHub API integration
- Issue tracking

**Key Components:**
- Issue submission controllers
- GitHub API service
- Issue templates

**Integration Points:**
- User feedback mechanisms
- Error reporting

### Officers Plugin

The Officers plugin manages kingdom officers, their appointments, and responsibilities.

**Key Features:**
- Officer roster management
- Officer warrant management
- Department and office organization

**Key Components:**
- `DepartmentsController`: Manages departments
- `OfficesController`: Manages offices within departments
- `OfficersController`: Manages officer appointments
- `RostersController`: Manages officer rosters and warrant renewals

**Integration Points:**
- Deeply integrated with the warrant system
- Uses the core branch management system
- Authorization through core permission system

### Queue Plugin

The Queue plugin provides background job processing capabilities for time-consuming or scheduled tasks.

**Key Features:**
- Background job processing
- Scheduled task execution
- Job status monitoring

**Key Components:**
- Queue engine for job processing
- Task classes for job definitions
- Queue monitoring UI

**Integration Points:**
- Email sending
- Report generation
- Data synchronization

## Extending the System with Plugins

### Creating a New Plugin

To create a new plugin for KMP:

1. Use CakePHP's bake command to generate the plugin structure:
   ```bash
   bin/cake bake plugin PluginName
   ```

2. Add the plugin to the application in `src/Application.php`:
   ```php
   public function bootstrap(): void
   {
       parent::bootstrap();
       
       $this->addPlugin('PluginName');
   }
   ```

3. Implement controllers, models, and views within the plugin directory structure

### Plugin Directory Structure

A typical KMP plugin follows this structure:

```
plugins/PluginName/
├── config/
│   ├── routes.php
│   └── bootstrap.php
├── src/
│   ├── Controller/
│   ├── Model/
│   │   ├── Entity/
│   │   └── Table/
│   ├── View/
│   │   └── Helper/
│   └── Plugin.php
├── templates/
└── tests/
```

### Plugin Integration

Plugins in KMP integrate with the core system through:

1. **Event Listeners**: Subscribing to application events
2. **Service Providers**: Registering services in the DI container
3. **Templates**: Providing or extending view templates
4. **Middleware**: Adding plugin-specific middleware
5. **Controller Actions**: Exposing new functionality via HTTP endpoints

### Event Integration

Plugins can register event listeners to respond to core system events:

```php
// In Plugin.php
public function bootstrap(PluginApplicationInterface $app): void
{
    EventManager::instance()->on(new MyEventListener());
}
```

## Plugin Configuration

Plugins can be configured through:

1. **Plugin-specific configuration files** in the plugin's `config/` directory
2. **Application configuration** in `config/app.php` using the plugin name as a key
3. **Environment-specific configuration** in `config/app_local.php`

Example configuration in `app.php`:

```php
'Plugins' => [
    'Officers' => [
        'departmentTypes' => ['Kingdom', 'Regional', 'Local'],
        'warrantRequired' => true,
    ],
],
```

## Best Practices for Plugin Development

1. **Maintain clear boundaries**: Keep plugin functionality clearly separated from core functionality
2. **Use dependency injection**: Avoid direct static calls to core components
3. **Follow naming conventions**: Use consistent naming for plugin components
4. **Provide migration files**: Include database migrations for plugin-specific tables
5. **Include tests**: Write tests for plugin functionality
6. **Document integration points**: Clearly document how the plugin integrates with the core system

## Next Steps

- For more information on the core components that plugins interact with, see [Core Components](./core-components.md)
- To understand the database structure that plugins may extend, see [Database Structure and Models](./database-models.md)
- For authentication and authorization that plugins utilize, see [Authentication and Authorization](./auth.md)