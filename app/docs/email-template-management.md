# Email Template Management System

## Overview

The KMP Email Template Management System provides a centralized, database-driven approach to managing email templates. It allows administrators to edit email content through a user-friendly web interface without modifying code files.

## Key Features

- **Database-Stored Templates**: Email templates are stored in the database, making them easy to update without deploying code changes
- **WYSIWYG Markdown Editor**: Uses EasyMDE for intuitive template editing with live preview
- **Variable Insertion**: Click-to-insert buttons for available variables in each template
- **Auto-Discovery**: Automatically discovers all Mailer classes and their methods using PHP reflection
- **Dual Format Support**: Create both HTML and plain text versions of emails
- **Template Fallback**: Falls back to file-based templates if database template is inactive
- **Authorization Integration**: Full RBAC support through EmailTemplatePolicy

## Architecture

### Core Components

#### 1. Database Schema (`email_templates` table)

```sql
- id: Primary key
- mailer_class: Fully qualified class name (e.g., App\Mailer\KMPMailer)
- action_method: Method name (e.g., resetPassword)
- subject_template: Email subject with variable placeholders
- html_template: HTML version of the email
- text_template: Plain text version of the email
- available_vars: JSON array of available variables
- is_active: Whether to use this template instead of file-based template
- created/modified: Timestamps
```

#### 2. Services

**MailerDiscoveryService** (`src/Service/MailerDiscoveryService.php`)
- Discovers all Mailer classes in core app and plugins
- Uses PHP reflection to analyze methods and extract:
  - Method parameters
  - View variables set in the method
  - Default subject lines
- Provides information for template creation and editing

**EmailTemplateRendererService** (`src/Service/EmailTemplateRendererService.php`)
- Renders templates by replacing variable placeholders
- Supports `{{variableName}}` and `${variableName}` syntax
- Converts between HTML and plain text formats
- Generates previews with sample data

#### 3. TemplateAwareMailerTrait

The trait that integrates with CakePHP's Mailer pipeline:

```php
use App\Mailer\TemplateAwareMailerTrait;

class KMPMailer extends Mailer
{
    use TemplateAwareMailerTrait;
    // ... rest of class
}
```

**How it works:**
1. Intercepts the `render()` method before email generation
2. Checks database for active template matching the mailer class and action
3. If found, renders email from database template with provided variables
4. If not found or inactive, falls back to file-based templates
5. Logs template usage for debugging

#### 4. Model Layer

**EmailTemplatesTable** (`src/Model/Table/EmailTemplatesTable.php`)
- Validation rules for templates
- Unique constraint on (mailer_class, action_method)
- Helper methods for finding templates
- Business logic for template management

**EmailTemplate** Entity (`src/Model/Entity/EmailTemplate.php`)
- JSON encoding/decoding for available_vars
- Virtual field for display name
- Accessible properties configuration

#### 5. Controller

**EmailTemplatesController** (`src/Controller/EmailTemplatesController.php`)

Actions:
- `index()`: List all templates with filtering
- `view($id)`: View template details and preview
- `add()`: Create new template
- `edit($id)`: Edit existing template
- `delete($id)`: Delete template
- `discover()`: Show all discoverable mailer methods
- `sync()`: Auto-create templates for all discovered methods
- `preview($id)`: Generate template preview

#### 6. Frontend

**Stimulus Controller** (`assets/js/controllers/email-template-editor-controller.js`)
- Extends EasyMDE markdown editor
- Adds variable insertion buttons
- Highlights variables in preview
- Provides toolbar for template editing

**Views** (`templates/EmailTemplates/`)
- `index.php`: Template listing with filters
- `form.php`: Add/edit form with dual editors (HTML + Text)
- `view.php`: Template details and preview
- `discover.php`: Mailer discovery interface

## Usage Guide

### For Administrators

#### Discovering Available Email Templates

1. Navigate to `/email-templates/discover`
2. View all discovered mailer classes and their methods
3. See which methods have templates and which don't
4. Click "Create Template" for methods without templates

#### Creating a New Template

**Method 1: From Discovery Page**
1. Go to `/email-templates/discover`
2. Find the mailer method you want to template
3. Click "Create Template"
4. The form will be pre-populated with:
   - Mailer class and method
   - Available variables
   - Existing file-based template content (if any)
   - Default subject

**Method 2: Manual Creation**
1. Go to `/email-templates/add`
2. Select mailer class and action method from dropdowns
3. Available variables will be populated automatically

**Editing the Template:**
1. Enter subject template using variable placeholders
2. Edit plain text version using EasyMDE markdown editor
3. Edit HTML version using EasyMDE markdown editor
4. Click variable buttons to insert `{{variableName}}` placeholders
5. Check "Active" to use this template instead of file-based template
6. Click "Save"

#### Editing Existing Templates

1. Go to `/email-templates`
2. Find the template in the list
3. Click "Edit"
4. Make changes and save

#### Previewing Templates

- View page shows preview with placeholder values
- Variables are shown as `[variableName]`
- Both HTML and text versions are displayed

#### Synchronizing Templates

The "Sync" feature creates database records for all discovered mailer methods:

1. Click "Sync Templates" on the index page
2. System creates inactive templates for all methods without templates
3. Templates are created with:
   - Content from existing file-based templates (if any)
   - Default subject from code
   - Available variables detected from code
   - **Inactive status** (won't be used until you activate them)

### For Developers

#### Adding New Mailer Methods

When you create a new mailer method:

```php
public function welcomeEmail(string $to, string $userName, string $activationUrl): void
{
    $this->setTo($to)
        ->setFrom(StaticHelpers::getAppSetting('Email.SystemEmailFromAddress'))
        ->setSubject('Welcome to KMP!')
        ->setViewVars([
            'userName' => $userName,
            'activationUrl' => $activationUrl,
            'siteTitle' => StaticHelpers::getAppSetting('KMP.LongSiteTitle'),
        ]);
}
```

The system will:
1. Auto-discover this method
2. Extract available variables: `userName`, `activationUrl`, `siteTitle`
3. Extract subject: "Welcome to KMP!"
4. Make it available for template creation

#### Using the Trait

All mailer classes should use the trait:

```php
<?php
namespace App\Mailer;

use App\Mailer\TemplateAwareMailerTrait;
use Cake\Mailer\Mailer;

class MyMailer extends Mailer
{
    use TemplateAwareMailerTrait;
    
    // Your mailer methods...
}
```

#### Variable Syntax in Templates

Templates support two variable syntaxes:
- `{{variableName}}` - Primary syntax (shown in UI)
- `${variableName}` - Alternative syntax

Variables are replaced when the email is rendered.

#### Template Precedence

1. **Active database template** - Used if `is_active = true`
2. **File-based template** - Used if no active database template exists
3. Falls back to file-based templates in:
   - `templates/email/html/{actionMethod}.php`
   - `templates/email/text/{actionMethod}.php`
   - `plugins/{Plugin}/templates/email/html/{actionMethod}.php`
   - `plugins/{Plugin}/templates/email/text/{actionMethod}.php`

## Authorization

The system uses `EmailTemplatePolicy` for authorization:

- `canIndex`: View template list
- `canView`: View template details
- `canCreate`: Create new templates
- `canUpdate`/`canEdit`: Edit templates
- `canDelete`: Delete templates
- `canDiscover`: View mailer discovery page
- `canSync`: Synchronize templates
- `canPreview`: Preview templates

All actions require appropriate permissions assigned to user roles.

## Best Practices

### Template Design

1. **Always provide both HTML and text versions**
   - HTML for rich formatting
   - Text for email clients that don't support HTML

2. **Use descriptive subjects**
   - Include variables to personalize: `Welcome {{userName}} to {{siteTitle}}`

3. **Keep templates focused**
   - One template per mailer action
   - Don't try to handle multiple scenarios in one template

4. **Test before activating**
   - Use preview feature to check rendering
   - Test with real data if possible
   - Keep template inactive until verified

### Variable Usage

1. **Use consistent naming**
   - Match variable names to `setViewVars()` in code
   - Use camelCase for consistency

2. **Document in code**
   - Comment what variables are available
   - Include examples in docblocks

3. **Provide defaults**
   - Handle missing variables gracefully in code
   - Use fallback values in `setViewVars()`

### Migration Strategy

To migrate from file-based to database templates:

1. **Run Sync** - Creates inactive templates with file content
2. **Review and Edit** - Update content as needed in web interface
3. **Test** - Preview templates, test with actual emails
4. **Activate** - Enable templates one at a time
5. **Monitor** - Check logs for template usage
6. **Archive Files** - Keep file-based templates as backup

## Technical Details

### Variable Extraction

The system attempts to extract variables from mailer methods by:
1. Reading the source file
2. Finding `setViewVars()` calls
3. Parsing the array structure
4. Extracting variable names

This is done using regular expressions. While robust, it may miss:
- Variables set in loops
- Variables from complex expressions
- Variables set conditionally

**Solution**: Manually specify variables when creating templates if auto-detection misses any.

### Performance

- Template lookups are cached at the query level
- No performance impact when using file-based templates
- Minimal overhead for database template rendering
- Consider caching frequently-used templates for high-volume emails

### Logging

Template usage is logged at DEBUG level:
```php
Log::debug('Email rendered from database template', [
    'mailer_class' => 'App\Mailer\KMPMailer',
    'template_id' => 5,
    'action' => 'resetPassword',
]);
```

## Troubleshooting

### Template Not Being Used

**Check:**
1. Is template active? (`is_active = 1`)
2. Does mailer class use the trait?
3. Are class and method names exact matches?
4. Check logs for errors

### Variables Not Replacing

**Check:**
1. Variable names match exactly (case-sensitive)
2. Variables are set in `setViewVars()`
3. Syntax is correct: `{{variableName}}`
4. No typos in variable names

### Discovery Not Finding Mailers

**Check:**
1. Mailer class extends `Cake\Mailer\Mailer`
2. Class is not abstract
3. Methods are public
4. File is in correct location:
   - `src/Mailer/` for app
   - `plugins/{Plugin}/src/Mailer/` for plugins

## Future Enhancements

Potential improvements:
- Template versioning and history
- A/B testing support
- Template inheritance/snippets
- Rich HTML editor option (in addition to markdown)
- Email testing/sending test emails
- Import/export templates
- Template categories/tags
- Multi-language support

## Files Created/Modified

### New Files

**Database:**
- `config/Migrations/*_CreateEmailTemplates.php`

**Backend:**
- `src/Model/Entity/EmailTemplate.php`
- `src/Model/Table/EmailTemplatesTable.php`
- `src/Service/MailerDiscoveryService.php`
- `src/Service/EmailTemplateRendererService.php`
- `src/Mailer/TemplateAwareMailerTrait.php`
- `src/Controller/EmailTemplatesController.php`
- `src/Policy/EmailTemplatePolicy.php`

**Frontend:**
- `assets/js/controllers/email-template-editor-controller.js`
- `templates/EmailTemplates/index.php`
- `templates/EmailTemplates/form.php`
- `templates/EmailTemplates/add.php`
- `templates/EmailTemplates/edit.php`
- `templates/EmailTemplates/view.php`
- `templates/EmailTemplates/discover.php`

### Modified Files

**Mailer Classes (added trait):**
- `src/Mailer/KMPMailer.php`
- `plugins/Officers/src/Mailer/OfficersMailer.php`
- `plugins/Activities/src/Mailer/ActivitiesMailer.php`

## Summary

The Email Template Management System provides a powerful, user-friendly way to manage email templates in KMP. By combining automatic discovery, WYSIWYG editing, and seamless integration with the existing mailer pipeline, it empowers administrators to maintain email content without developer intervention while maintaining full backward compatibility with file-based templates.
