# Template Plugin - Complete Overview

## What is this?

The Template plugin is a **fully-functional, production-ready boilerplate** for creating new KMP plugins. It includes all the components, patterns, and best practices used throughout the Kingdom Management Portal system.

## What's Included

### Core Components ✅

- **Main Plugin Class** (`TemplatePlugin.php`)
  - KMP integration with `KMPPluginInterface`
  - Navigation registry integration
  - View cell registry setup
  - Configuration management
  - Service registration

- **Controller** (`HelloWorldController.php`)
  - Complete CRUD operations (Index, View, Add, Edit, Delete)
  - Authorization integration
  - Flash messaging
  - Proper documentation
  - Example patterns for real implementation

- **Authorization Policy** (`HelloWorldPolicy.php`)
  - Policy-based access control
  - Examples of all permission types
  - Helper methods for common checks
  - Resource-level authorization
  - Query scoping

- **Navigation Provider** (`TemplateNavigationProvider.php`)
  - Dynamic menu generation
  - Conditional menu items
  - Badge notifications
  - Member/branch context support
  - Icon integration

### Data Layer ✅

- **Table Class** (`HelloWorldItemsTable.php`)
  - Validation rules
  - Association examples
  - Custom finder methods
  - Behavior configuration
  - Application rules

- **Entity Class** (`HelloWorldItem.php`)
  - Virtual fields
  - Accessor/mutator methods
  - Business logic methods
  - Mass assignment protection
  - Type safety

- **Migration** (`CreateHelloWorldItems.php`)
  - Table creation
  - Indexes for performance
  - Foreign key examples
  - Proper field types
  - Comments for documentation

- **Seed Data** (`HelloWorldItemsSeed.php`)
  - Sample data for development
  - Testing examples
  - Data patterns

### Frontend ✅

- **View Templates** (4 templates)
  - `index.php` - List view with Bootstrap table
  - `view.php` - Detail view
  - `add.php` - Create form
  - `edit.php` - Update form

- **Stimulus Controller** (`hello-world-controller.js`)
  - Targets and values
  - Event handling
  - Async operations
  - Value change callbacks
  - Documentation and examples

- **CSS Styles** (`template.css`)
  - Component-specific styles
  - Responsive design
  - Dark mode support
  - Utility classes
  - Bootstrap integration

### Testing ✅

- **Controller Tests** (`HelloWorldControllerTest.php`)
  - Integration test examples
  - Authorization testing
  - CRUD operation tests
  - Form validation tests
  - Response assertions

### Documentation ✅

- **README.md** - Plugin overview and features
- **USAGE_GUIDE.md** - Step-by-step customization guide
- **Inline Documentation** - Every class and method documented
- **Code Comments** - Explanatory comments throughout

### Configuration ✅

- **composer.json** - Proper autoloading and dependencies
- **phpunit.xml.dist** - Test configuration
- **.gitignore** - Standard ignore patterns

## Directory Structure

```
Template/
├── assets/
│   ├── css/
│   │   └── template.css                      # Plugin styles
│   └── js/
│       └── controllers/
│           └── hello-world-controller.js     # Stimulus controller
├── config/
│   ├── Migrations/
│   │   └── 20250107000000_CreateHelloWorldItems.php
│   └── Seeds/
│       └── HelloWorldItemsSeed.php
├── src/
│   ├── Controller/
│   │   └── HelloWorldController.php          # CRUD controller
│   ├── Model/
│   │   ├── Entity/
│   │   │   └── HelloWorldItem.php            # Entity class
│   │   └── Table/
│   │       └── HelloWorldItemsTable.php      # Table class
│   ├── Policy/
│   │   └── HelloWorldPolicy.php              # Authorization
│   ├── Services/
│   │   └── TemplateNavigationProvider.php    # Navigation
│   ├── View/
│   │   └── Cell/                             # (empty, ready for cells)
│   └── TemplatePlugin.php                    # Main plugin class
├── templates/
│   ├── HelloWorld/
│   │   ├── index.php                         # List view
│   │   ├── view.php                          # Detail view
│   │   ├── add.php                           # Create form
│   │   └── edit.php                          # Update form
│   ├── cell/                                 # Cell templates
│   └── element/                              # Reusable elements
├── tests/
│   └── TestCase/
│       └── Controller/
│           └── HelloWorldControllerTest.php  # Controller tests
├── webroot/                                  # Public assets
├── .gitignore                                # Git ignore
├── composer.json                             # Dependencies
├── phpunit.xml.dist                          # Test config
├── README.md                                 # Plugin overview
└── USAGE_GUIDE.md                            # Customization guide
```

## How to Use

### Quick Start (5 minutes)

1. **Copy the plugin**
   ```bash
   cp -r plugins/Template plugins/MyPlugin
   ```

2. **Search and replace**
   - Replace "Template" with "MyPlugin"
   - Replace "template" with "my-plugin"
   - Update composer.json

3. **Register the plugin**
   - Add to `config/plugins.php`

4. **Run migrations**
   ```bash
   bin/cake migrations migrate -p MyPlugin
   ```

5. **Access the plugin**
   - Navigate to `/my-plugin/hello-world`

### Detailed Customization

See `USAGE_GUIDE.md` for complete instructions on:
- Renaming components
- Customizing controllers
- Updating policies
- Modifying navigation
- Creating new actions
- Adding associations
- Frontend customization
- Testing your plugin

## Key Features

### 🎯 Production Ready
- All components are functional
- Follows KMP best practices
- Comprehensive documentation
- Ready to customize

### 🔒 Security Built-In
- Authorization policies
- Mass assignment protection
- Input validation
- XSS protection
- CSRF handling

### 🎨 Modern Frontend
- Bootstrap 5 styling
- Stimulus.js controllers
- Responsive design
- Accessible markup
- Clean UI patterns

### 📊 Database Integration
- Migrations for versioning
- Seeds for test data
- Proper indexes
- Foreign key support
- Soft delete ready

### ✅ Well Tested
- Integration test examples
- Authorization tests
- CRUD operation tests
- Response assertions

### 📚 Fully Documented
- Every class documented
- Method-level documentation
- Usage examples
- Inline comments

## What Makes This Different

Unlike basic boilerplates, this template:

1. **Actually Works** - It's a functioning plugin, not just empty files
2. **Complete Examples** - Every component has real, working code
3. **Best Practices** - Demonstrates KMP patterns and conventions
4. **Educational** - Learn by example from working code
5. **Copy & Customize** - Start with something that works, modify as needed

## Common Use Cases

### Create a Simple Plugin
1. Copy template
2. Rename components
3. Update navigation
4. Customize views
5. Deploy

### Create a Complex Plugin with Database
1. Copy template
2. Update migration with your schema
3. Modify Table/Entity classes
4. Update controller for your logic
5. Customize authorization
6. Add custom finders
7. Create advanced views

### Create a Utility Plugin (No Database)
1. Copy template
2. Remove model classes
3. Remove migration/seed
4. Keep controller for logic
5. Simplify views
6. Focus on services

## Tips for Success

### ✅ Do's
- Follow the existing patterns
- Keep the structure consistent
- Document your changes
- Write tests as you go
- Use type declarations
- Follow PSR-12 standards

### ❌ Don'ts
- Don't skip authorization
- Don't ignore validation
- Don't bypass KMP patterns
- Don't forget to update navigation
- Don't skip documentation
- Don't ignore errors

## Support Files

This template includes:

- **Composer Integration** - Proper autoloading
- **PHPUnit Setup** - Ready for testing
- **Git Configuration** - Sensible .gitignore
- **Code Documentation** - PHPDoc blocks throughout
- **Usage Guide** - Step-by-step instructions
- **Migration/Seed Examples** - Database patterns

## Next Steps

1. **Read** the USAGE_GUIDE.md
2. **Explore** the existing code
3. **Copy** the template
4. **Customize** for your needs
5. **Test** your changes
6. **Deploy** with confidence

## Questions?

- Check the USAGE_GUIDE.md for detailed instructions
- Review the plugin boilerplate documentation
- Look at existing plugins (Activities, Awards, Officers)
- Consult the KMP development team

---

**This template represents the culmination of KMP's plugin architecture and best practices. Use it to jumpstart your plugin development!**
