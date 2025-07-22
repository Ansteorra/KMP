<?php

declare(strict_types=1);

/**
 * KMP Plugin Registry Configuration
 *
 * This file defines the plugins used by the Kingdom Management Portal (KMP) and their
 * configuration settings. Plugins extend CakePHP's core functionality and add specialized
 * features for managing kingdom/organizational activities, awards, officers, and more.
 *
 * Plugin Architecture in KMP:
 * The plugin system allows modular development and deployment, enabling:
 * - Feature separation and maintainability
 * - Independent versioning and updates
 * - Conditional loading based on environment
 * - Migration ordering for database dependencies
 * - Optional plugin availability
 *
 * Plugin Categories:
 * 1. Development Tools (DebugKit, Bake, Tools)
 * 2. Database Management (Migrations, Muffin/Trash, Muffin/Footprint)
 * 3. UI Framework (Bootstrap, BootstrapUI, AssetMix)
 * 4. Security & Auth (Authentication, Authorization)
 * 5. Core KMP Features (Activities, Awards, Officers)
 * 6. Utility Plugins (Queue, CsvView, ADmad/Glide, GitHubIssueSubmitter)
 *
 * Migration Order:
 * Critical for database schema dependencies between plugins:
 * - Activities (1): Base activity system
 * - Officers (2): Depends on activities for officer assignments
 * - Awards (3): Depends on both activities and officers for award criteria
 *
 * Environment Considerations:
 * - onlyDebug: Loaded only when debug mode is enabled
 * - onlyCli: Available only in command-line interface
 * - optional: Plugin loading failures won't stop application startup
 *
 * Security Implications:
 * - DebugKit provides sensitive debugging information (debug only)
 * - Authentication/Authorization handle security policies
 * - Muffin/Footprint tracks data modification for auditing
 *
 * @see src/Application.php::bootstrap() Plugin loading implementation
 * @see https://book.cakephp.org/5/en/plugins.html CakePHP Plugin Guide
 */

return [
    /**
     * Development Tools Section
     *
     * Plugins that enhance development workflow and debugging capabilities.
     * These tools should not be available in production environments.
     */

    /**
     * DebugKit Plugin
     *
     * Provides comprehensive debugging tools including:
     * - Debug toolbar with request/response information
     * - SQL query profiling and analysis
     * - Memory usage and performance metrics
     * - Session and configuration inspection
     * - Log message viewer
     *
     * Security: Only loaded in debug mode to prevent information disclosure
     * Performance: Adds overhead, disabled in production
     *
     * @see https://github.com/cakephp/debug_kit DebugKit Documentation
     */
    'DebugKit' => [
        'onlyDebug' => true,
    ],

    /**
     * Bake Plugin
     *
     * Code generation tool for rapid development:
     * - Generates controllers, models, views, and tests
     * - Creates plugin scaffolding
     * - Database introspection and code generation
     * - Custom templates for KMP-specific patterns
     *
     * Usage: Command-line only tool for development
     * Examples: bin/cake bake controller Members
     *
     * @see https://book.cakephp.org/5/en/bake.html Bake Documentation
     */
    'Bake' => [
        'onlyCli' => true,
        'optional' => true,
    ],

    /**
     * Tools Plugin
     *
     * Extended development utilities and helpers:
     * - Additional CLI commands and utilities
     * - Development workflow enhancements
     * - Code quality and analysis tools
     *
     * @see https://github.com/dereuromark/cakephp-tools Tools Plugin
     */
    'Tools' => [],

    /**
     * Database Management Section
     *
     * Plugins that handle database operations, schema management, and data integrity.
     */

    /**
     * Migrations Plugin
     *
     * Database schema versioning and management:
     * - Version-controlled database schema changes
     * - Migration rollback and rollforward capabilities
     * - Schema synchronization across environments
     * - Seed data management
     *
     * KMP Usage:
     * - Core schema migrations in config/Migrations/
     * - Plugin-specific migrations with ordered execution
     * - Production deployment schema updates
     *
     * @see https://book.cakephp.org/migrations/4/en/ Migrations Documentation
     */
    'Migrations' => [
        'onlyCli' => true,
    ],

    /**
     * Muffin/Footprint Plugin
     *
     * Automatic auditing and change tracking:
     * - Records who created/modified each record
     * - Tracks modification timestamps
     * - Provides audit trail for sensitive data
     * - Supports custom user identification
     *
     * KMP Security Benefits:
     * - Member data modification tracking
     * - Award and activity change auditing
     * - Compliance with data governance requirements
     *
     * @see https://github.com/UseMuffin/Footprint Footprint Documentation
     */
    'Muffin/Footprint' => [],

    /**
     * Muffin/Trash Plugin
     *
     * Soft delete functionality:
     * - Marks records as deleted without permanent removal
     * - Maintains referential integrity
     * - Provides data recovery capabilities
     * - Supports cascade soft deletes
     *
     * KMP Applications:
     * - Member account deactivation (not deletion)
     * - Activity and award record retention
     * - Data compliance and recovery procedures
     *
     * @see https://github.com/UseMuffin/Trash Trash Documentation
     */
    'Muffin/Trash' => [],

    /**
     * User Interface and Asset Management Section
     *
     * Plugins that handle UI frameworks, styling, and asset compilation.
     */

    /**
     * BootstrapUI Plugin
     *
     * Bootstrap-integrated form helpers and UI components:
     * - Bootstrap-styled form controls
     * - Responsive layout helpers
     * - Consistent styling across the application
     * - Accessibility-compliant markup
     *
     * @see https://github.com/friendsofcake/bootstrap-ui BootstrapUI Documentation
     */
    'BootstrapUI' => [],

    /**
     * Bootstrap Plugin (KMP Custom)
     *
     * KMP-specific Bootstrap integration and customizations:
     * - Custom Bootstrap themes and variables
     * - KMP-specific UI components
     * - Kingdom-themed styling and branding
     * - Responsive design patterns
     *
     * Features:
     * - Member card layouts
     * - Activity and award displays
     * - Navigation and dashboard components
     */
    'Bootstrap' => [],

    /**
     * AssetMix Plugin
     *
     * Laravel Mix integration for CakePHP:
     * - Asset compilation and optimization
     * - SCSS/Sass preprocessing
     * - JavaScript bundling and minification
     * - Hot module reloading for development
     *
     * KMP Build Process:
     * - Compiles Stimulus.js controllers
     * - Processes Bootstrap customizations
     * - Optimizes images and fonts
     * - Generates versioned assets for caching
     *
     * @see webpack.mix.js Asset compilation configuration
     */
    'AssetMix' => [],

    /**
     * Security and Authentication Section
     *
     * Plugins that handle user authentication, authorization, and security policies.
     */

    /**
     * Authentication Plugin
     *
     * User authentication and session management:
     * - Multiple authentication adapters (Form, JWT, etc.)
     * - Session-based authentication for web interface
     * - API token authentication for programmatic access
     * - Brute force protection and security measures
     *
     * KMP Security Features:
     * - Member login with email/password
     * - Session timeout and keep-alive
     * - Remember me functionality
     * - Failed login attempt tracking
     *
     * @see src/Application.php::getAuthenticationService() Configuration
     * @see https://book.cakephp.org/authentication/2/en/ Authentication Documentation
     */
    'Authentication' => [],

    /**
     * Authorization Plugin
     *
     * Role-based access control and permissions:
     * - Policy-based authorization system
     * - Resource-level permission checking
     * - Role and permission management
     * - Hierarchical authorization rules
     *
     * KMP Authorization Model:
     * - Member roles (Admin, Officer, Member, etc.)
     * - Resource-specific permissions
     * - Branch-based access control
     * - Activity and award authorization
     *
     * @see src/Policy/ Authorization policy classes
     * @see https://book.cakephp.org/authorization/2/en/ Authorization Documentation
     */
    'Authorization' => [],

    /**
     * Utility and Integration Plugins Section
     *
     * Specialized plugins for specific functionality and external integrations.
     */

    /**
     * ADmad/Glide Plugin
     *
     * On-demand image manipulation and optimization:
     * - Dynamic image resizing and cropping
     * - Format conversion and optimization
     * - Secure URL signing for image requests
     * - Efficient caching system
     *
     * KMP Image Processing:
     * - Member profile photo thumbnails
     * - Award badge and insignia resizing
     * - Activity photo optimization
     * - Responsive image serving
     *
     * @see config/routes.php::images Glide routing configuration
     * @see https://glide.thephpleague.com/ Glide Documentation
     */
    'ADmad/Glide' => [],

    /**
     * Queue Plugin
     *
     * Background job processing and task management:
     * - Asynchronous task execution
     * - Email sending queue
     * - Batch processing capabilities
     * - Job failure handling and retry logic
     *
     * KMP Background Jobs:
     * - Email notifications for activities and awards
     * - Bulk data processing and exports
     * - Report generation and caching
     * - System maintenance tasks
     *
     * @see config/app_queue.php Queue-specific configuration
     */
    'Queue' => [],

    /**
     * CsvView Plugin
     *
     * CSV export functionality:
     * - Automatic CSV response generation
     * - Customizable field mapping
     * - Large dataset streaming
     * - Excel-compatible formatting
     *
     * KMP Export Features:
     * - Member roster exports
     * - Activity participation reports
     * - Award recipient lists
     * - Branch membership data
     *
     * @example /members.csv → Download member data as CSV
     */
    'CsvView' => [],

    /**
     * GitHubIssueSubmitter Plugin (KMP Custom)
     *
     * Integrated feedback and issue reporting system:
     * - Direct GitHub issue creation from application
     * - User feedback collection and forwarding
     * - Bug report automation
     * - Feature request management
     *
     * Features:
     * - In-app feedback forms
     * - Automatic issue categorization
     * - User context and environment data
     * - Privacy-aware data collection
     */
    'GitHubIssueSubmitter' => [],

    /**
     * Core KMP Business Logic Plugins Section
     *
     * Custom plugins that implement KMP's core business functionality.
     * These plugins have specific migration ordering due to dependencies.
     */

    /**
     * Activities Plugin
     *
     * Core activity management system for the kingdom:
     * - Activity creation and scheduling
     * - Member participation tracking
     * - Authorization and approval workflows
     * - Activity reporting and analytics
     *
     * Database Dependencies: Base tables (members, branches, roles)
     * Migration Order: 1 (foundational plugin)
     *
     * Features:
     * - Activity types and categories
     * - Participation requirements and limits
     * - Marshal and officer assignment
     * - Activity authorization workflow
     * - Participant check-in/out systems
     *
     * @see plugins/Activities/ Plugin implementation
     */
    'Activities' => [
        'migrationOrder' => 1,
    ],

    /**
     * Officers Plugin
     *
     * Officer management and reporting system:
     * - Officer position definitions and assignments
     * - Reporting hierarchy and relationships
     * - Officer duty and responsibility tracking
     * - Performance and accountability systems
     *
     * Database Dependencies: Activities plugin (officer activity assignments)
     * Migration Order: 2 (depends on Activities)
     *
     * Features:
     * - Officer position management
     * - Reporting structure definition
     * - Officer assignment to activities
     * - Performance tracking and reporting
     * - Duty roster and scheduling
     *
     * @see plugins/Officers/ Plugin implementation
     */
    'Officers' => [
        'migrationOrder' => 2,
    ],

    /**
     * Awards Plugin
     *
     * Comprehensive award and recognition system:
     * - Award type definitions and criteria
     * - Award recommendation workflow
     * - Merit tracking and verification
     * - Award ceremony and presentation management
     *
     * Database Dependencies: Activities and Officers plugins (award criteria)
     * Migration Order: 3 (depends on Activities and Officers)
     *
     * Features:
     * - Award categories and types
     * - Recommendation submission and approval
     * - Merit verification and documentation
     * - Award presentation scheduling
     * - Recipient tracking and history
     *
     * @see plugins/Awards/ Plugin implementation
     */
    'Awards' => [
        'migrationOrder' => 3,
    ],
    'ActionItems' => [
        'migrationOrder' => 4,
    ],
    'CsvView' => [],
    'Queue' => [],
    'Tools' => [],
];