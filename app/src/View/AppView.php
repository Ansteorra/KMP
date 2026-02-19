<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\View;

use BootstrapUI\View\UIViewTrait;
use Cake\View\View;

/**
 * Application View
 * 
 * Base view class for the Kingdom Management Portal (KMP) application.
 * Extends CakePHP's View class with Bootstrap UI integration and 
 * KMP-specific helper loading.
 * 
 * This class serves as the foundation for all views in the KMP system,
 * providing consistent initialization of:
 * - UI framework integration (Bootstrap)
 * - Asset management helpers
 * - Authentication helpers
 * - KMP-specific utilities
 * - Image processing capabilities
 * 
 * Key Features:
 * - Automatic Bootstrap UI integration via UIViewTrait
 * - Asset versioning through AssetMix
 * - User authentication context via Identity helper
 * - Image processing with Glide
 * - Custom KMP helper for application-specific components
 * 
 * Usage:
 * This view is automatically used by all controllers unless explicitly overridden.
 * All helpers loaded here are available in templates without additional loading.
 * 
 * Helper Dependencies:
 * - AssetMix.AssetMix: Asset compilation and versioning
 * - Authentication.Identity: User authentication context
 * - Bootstrap.Modal: Modal dialog components  
 * - Bootstrap.Navbar: Navigation components
 * - Kmp: Custom KMP-specific helper
 * - ADmad/Glide.Glide: Image processing and optimization
 * - Tools.Format: Text formatting utilities
 * - Tools.Time: Time formatting utilities
 * - Templating.Icon: Icon rendering utilities
 * - Templating.IconSnippet: Icon snippet utilities
 * 
 * Configuration Notes:
 * - Glide is configured for image processing with secure URLs
 * - Bootstrap UI is initialized without layout override
 * - All helpers are loaded during initialization
 *
 * @link https://book.cakephp.org/4/en/views.html#the-app-view
 * @see \BootstrapUI\View\UIViewTrait For Bootstrap integration
 * @see \App\View\Helper\KmpHelper For KMP-specific functionality
 */
class AppView extends View
{
    use UIViewTrait;

    /**
     * Initialization hook method.
     * 
     * Automatically called when the view is instantiated. Sets up all
     * the helpers and UI components needed for KMP templates.
     * 
     * Initialization Process:
     * 1. Calls parent initialization
     * 2. Initializes Bootstrap UI framework integration
     * 3. Loads core CakePHP helpers for assets and authentication
     * 4. Loads Bootstrap-specific helpers for UI components
     * 5. Configures Glide for image processing
     * 6. Loads additional utility helpers
     * 
     * Helper Loading Order:
     * - AssetMix: Must be loaded early for asset management
     * - Identity: Required for permission checks in templates
     * - Bootstrap components: For consistent UI rendering
     * - KMP helper: For application-specific functionality
     * - Glide: For responsive image processing
     * - Utility helpers: For formatting and display
     * 
     * Configuration Details:
     * - UIViewTrait is initialized without layout override to maintain flexibility
     * - Glide is configured with secure URLs and image base path
     * - All helpers are immediately available in templates after initialization
     * 
     * @return void
     * @throws \Exception If helper loading fails
     */
    public function initialize(): void
    {
        parent::initialize();

        // Initialize Bootstrap UI integration without layout override
        // This allows controllers to still choose their own layouts while
        // getting Bootstrap form helpers and components
        $this->initializeUI(['layout' => false]);

        // Load asset management helper for versioned CSS/JS files
        // This integrates with Laravel Mix for development and production builds
        $this->loadHelper('AssetMix.AssetMix');

        // Load authentication helper for user context in templates
        // Provides $this->Identity->can() and user information access
        $this->loadHelper('Authentication.Identity');

        // Load Bootstrap component helpers for consistent UI
        $this->loadHelper('Bootstrap.Modal');
        $this->loadHelper('Bootstrap.Navbar');

        // Load URL helper for route generation
        $this->loadHelper('Url');

        // Legacy asset compression helper (currently disabled)
        //$this->loadHelper("AssetCompress.AssetCompress");

        // Load KMP-specific helper for application functionality
        // This provides custom form controls and KMP utilities
        $this->loadHelper('Kmp');

        // Load Markdown helper for rendering markdown content
        $this->loadHelper('Markdown');

        // Configure Glide for responsive image processing
        // All option values should match the corresponding options for `GlideFilter`.
        $this->loadHelper('ADmad/Glide.Glide', [
            // Base URL path for images
            'baseUrl' => '/images/',
            // Whether to generate secure URLs (prevents direct manipulation)
            'secureUrls' => true,
            // Signing key to use when generating secure URLs (null = auto-generated)
            'signKey' => null,
        ]);

        // Load additional utility helpers for formatting and display
        $helpers = [
            'Tools.Format',        // Text formatting utilities
            'Tools.Time',          // Time/date formatting utilities
            'Timezone',            // Timezone conversion and formatting
            'SecurityDebug',       // Security debugging helper (only active in debug mode)
        ];

        // Templating helpers require cakephp-templating (require dependency).
        // Guard with class_exists so a missing package degrades gracefully.
        if (class_exists(\Templating\View\Helper\IconHelper::class)) {
            $helpers[] = 'Templating.Icon';
            $helpers[] = 'Templating.IconSnippet';
        }

        // Add all utility helpers to the view
        foreach ($helpers as $helper) {
            $this->addHelper($helper);
        }
    }
}
