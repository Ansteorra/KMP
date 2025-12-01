<?php

declare(strict_types=1);

namespace App\KMP;

/**
 * KMPPluginInterface - Plugin Architecture Contract for Kingdom Management Portal
 *
 * This interface defines the core contract that all KMP plugins must implement to integrate
 * properly with the Kingdom Management Portal system. It establishes the foundation for
 * plugin architecture and ensures proper initialization order through migration ordering.
 *
 * ## Plugin Architecture Overview
 *
 * The KMP plugin system extends CakePHP's BasePlugin architecture with additional features
 * specific to organizational management systems. Plugins provide modular functionality for:
 *
 * - **Activities Management**: Authorization tracking, event planning, activity reporting
 * - **Awards System**: Recommendation workflows, approval processes, ceremony management
 * - **Officer Management**: Officer rosters, reporting chains, appointment tracking
 * - **Queue Processing**: Background job management for bulk operations
 * - **GitHub Integration**: Issue submission and feedback collection
 * - **Bootstrap Framework**: UI consistency and responsive design components
 *
 * ## Migration Order System
 *
 * The migration order system ensures plugins are initialized in the correct sequence to
 * handle database dependencies and service registrations. Critical ordering considerations:
 *
 * ### Database Dependencies
 * - Base KMP tables must exist before plugin tables that reference them
 * - Junction tables require both parent tables to exist first
 * - Foreign key constraints enforce proper initialization order
 *
 * ### Service Registration Dependencies
 * - Navigation services must be available before plugins register navigation items
 * - View cell registry must be initialized before plugin cell registration
 * - Authorization services must be ready before plugin authorization handlers
 *
 * ## Plugin Registration Pattern
 *
 * All KMP plugins follow a standardized registration pattern in `config/plugins.php`:
 *
 * ```php
 * 'PluginName' => [
 *     'migrationOrder' => 1,   // Database initialization order
 *     'dependencies' => [],     // Optional: explicit dependencies
 *     'conditional' => true,    // Optional: conditional loading
 * ],
 * ```
 *
 * ## Implementation Requirements
 *
 * ### Migration Order Implementation
 * All KMP plugins must implement `getMigrationOrder()` to specify their initialization
 * priority. Lower numbers indicate earlier initialization:
 *
 * - **Order 1**: Activities (base activity system)
 * - **Order 2**: Awards (depends on activities for some workflows)
 * - **Order 3**: Officers (depends on activities for event reporting)
 * - **Order 10+**: Utility plugins (minimal dependencies)
 *
 * ### Plugin Class Structure
 * KMP plugins extend CakePHP's BasePlugin and implement KMPPluginInterface:
 *
 * ```php
 * class MyPlugin extends BasePlugin implements KMPPluginInterface
 * {
 *     protected int $_migrationOrder = 1;
 *
 *     public function getMigrationOrder(): int
 *     {
 *         return $this->_migrationOrder;
 *     }
 *
 *     public function bootstrap(PluginApplicationInterface $app): void
 *     {
 *         // Plugin initialization logic
 *         // Navigation registration
 *         // Service registration
 *         // Configuration setup
 *     }
 * }
 * ```
 *
 * ## Integration Points
 *
 * ### Navigation System Integration
 * Plugins register navigation items through the NavigationRegistry service:
 *
 * ```php
 * NavigationRegistry::register(
 *     'PluginName',
 *     [], // Static navigation items
 *     function ($user, $params) {
 *         return PluginNavigationProvider::getNavigationItems($user, $params);
 *     }
 * );
 * ```
 *
 * ### View Cell Integration
 * Plugins register view cells for dashboard and page integration:
 *
 * ```php
 * ViewCellRegistry::register(
 *     'PluginName',
 *     [], // Static view cells
 *     function ($urlParams, $user) {
 *         return PluginViewCellProvider::getViewCells($urlParams, $user);
 *     }
 * );
 * ```
 *
 * ### Configuration Management Integration
 * Plugins use StaticHelpers for configuration management with versioning:
 *
 * ```php
 * $currentConfigVersion = "25.01.11.a";
 * $configVersion = StaticHelpers::getAppSetting("Plugin.configVersion", "0.0.0", null, true);
 * if ($configVersion != $currentConfigVersion) {
 *     StaticHelpers::setAppSetting("Plugin.configVersion", $currentConfigVersion, null, true);
 *     // Update plugin configuration
 * }
 * ```
 *
 * ## Security Considerations
 *
 * ### Authorization Integration
 * Plugins must integrate with KMP's RBAC system through:
 * - Policy classes for resource authorization
 * - Permission requirements for sensitive operations
 * - Branch-scoped access control for organizational data
 *
 * ### Data Protection
 * Plugin implementations must follow KMP security patterns:
 * - Input validation and sanitization
 * - SQL injection prevention through ORM usage
 * - XSS protection in view output
 * - Proper session and authentication handling
 *
 * ## Performance Considerations
 *
 * ### Lazy Loading
 * Plugins should implement lazy loading patterns to minimize startup overhead:
 * - Register navigation and cell providers without immediate execution
 * - Load heavy dependencies only when needed
 * - Cache expensive operations appropriately
 *
 * ### Database Optimization
 * Plugin database operations should follow KMP performance patterns:
 * - Use proper indexing for foreign keys and search fields
 * - Implement query caching for frequently accessed data
 * - Follow BaseTable caching patterns for entity operations
 *
 * ## Development Best Practices
 *
 * ### Plugin Structure
 * Follow consistent directory structure for maintainability:
 * ```
 * plugins/PluginName/
 * ├── assets/              # CSS, JS, images
 * │   ├── css/
 * │   └── js/controllers/  # Stimulus.js controllers
 * ├── config/              # Plugin-specific configuration
 * ├── src/                 # Plugin source code
 * │   ├── Controller/
 * │   ├── Model/
 * │   ├── Services/
 * │   └── PluginNamePlugin.php
 * ├── templates/           # Plugin templates
 * ├── tests/               # Plugin tests
 * └── webroot/             # Public plugin assets
 * ```
 *
 * ### Testing Integration
 * Plugins should include comprehensive testing:
 * - Unit tests for models and services
 * - Integration tests for controller workflows
 * - UI tests for user interface components
 * - Mock implementations for external dependencies
 *
 * @see \App\Services\NavigationRegistry Navigation system integration
 * @see \App\Services\ViewCellRegistry View cell registration system
 * @see \App\KMP\StaticHelpers Configuration management utilities
 * @see \Cake\Core\BasePlugin CakePHP plugin foundation
 * @since KMP 1.0
 * @package App\KMP
 */
interface KMPPluginInterface
{
    /**
     * Get Migration Order for Plugin Initialization
     *
     * Returns the migration order value that determines when this plugin should be
     * initialized relative to other plugins. This is critical for ensuring proper
     * database schema dependencies and service registration order.
     *
     * The migration order system ensures:
     * - Database tables are created in dependency order
     * - Foreign key constraints can be properly established
     * - Service dependencies are available when needed
     * - Plugin configurations are applied in logical sequence
     *
     * ## Order Guidelines
     *
     * ### Critical System Plugins (1-5)
     * - **1**: Activities - Base activity and authorization system
     * - **2**: Awards - Award recommendation and ceremony management
     * - **3**: Officers - Officer management and reporting chains
     * - **4**: Reports - Reporting and analytics framework
     * - **5**: OfficerEventReporting - Officer-specific event reporting
     *
     * ### Utility Plugins (10+)
     * - **10**: Queue - Background job processing
     * - **11**: GitHubIssueSubmitter - External service integration
     * - **12**: Bootstrap - UI framework components
     *
     * ## Implementation Example
     *
     * ```php
     * class MyPlugin extends BasePlugin implements KMPPluginInterface
     * {
     *     protected int $_migrationOrder = 1;
     *
     *     public function __construct($config = [])
     *     {
     *         if (!isset($config['migrationOrder'])) {
     *             $config['migrationOrder'] = 1;
     *         }
     *         $this->_migrationOrder = $config['migrationOrder'];
     *     }
     *
     *     public function getMigrationOrder(): int
     *     {
     *         return $this->_migrationOrder;
     *     }
     * }
     * ```
     *
     * ## Configuration Integration
     *
     * The migration order is typically set through plugin configuration in `config/plugins.php`:
     *
     * ```php
     * 'MyPlugin' => [
     *     'migrationOrder' => 1,
     * ],
     * ```
     *
     * This allows for deployment-specific order adjustments without code changes.
     *
     * ## Dependencies and Conflicts
     *
     * When determining migration order, consider:
     *
     * ### Database Dependencies
     * - Tables that will be referenced by foreign keys must exist first
     * - Junction tables require both parent tables to exist
     * - Index creation may depend on data population order
     *
     * ### Service Dependencies
     * - Navigation providers require NavigationRegistry to be initialized
     * - View cells require ViewCellRegistry to be available
     * - Authorization handlers need authorization service setup
     *
     * ### Configuration Dependencies
     * - Some plugins may depend on configuration set by other plugins
     * - Shared configuration keys should be managed carefully
     * - Version-specific configuration updates must be ordered properly
     *
     * @return int Migration order (lower numbers = earlier initialization)
     * @throws \RuntimeException If migration order cannot be determined
     * @since KMP 1.0
     */
    public function getMigrationOrder(): int;
}