<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use App\View\AppView;
use AssetMix\Mix;
use Cake\Event\Event;
use Cake\View\Helper;
use Cake\View\Helper\HtmlHelper;

/**
 * KMP Helper - Kingdom Management Portal View Helper
 * 
 * Custom view helper providing KMP-specific UI components, utilities,
 * and integration with the application's business logic. Extends CakePHP's
 * base Helper class with functionality tailored for the KMP system.
 * 
 * Key Features:
 * - Advanced form controls (auto-complete, combo boxes)
 * - Block management for dynamic template composition
 * - Data conversion utilities (CSV export)
 * - Boolean display with icons
 * - Application settings access
 * - Asset management integration
 * - Navigation cell rendering
 * 
 * Dependencies:
 * - StaticHelpers: Core KMP utility functions
 * - AssetMix: For asset URL generation with versioning
 * - AppView: Main view reference for block management
 * 
 * Template Integration:
 * This helper is automatically loaded in AppView and available
 * in all templates as $this->Kmp->methodName()
 * 
 * Security Features:
 * - Input sanitization for all form controls
 * - Safe HTML attribute generation
 * - XSS prevention in output
 * 
 * Usage Examples:
 * ```php
 * // Auto-complete member search
 * echo $this->Kmp->autoCompleteControl($this->Form, 'member_name', 'member_id', '/members/search');
 * 
 * // Boolean status with icons
 * echo $this->Kmp->bool($member->active, $this->Html);
 * 
 * // Application setting access
 * $siteName = $this->Kmp->getAppSetting('site.name', 'KMP');
 * ```
 * 
 * @see \App\KMP\StaticHelpers For core utility functions
 * @see \App\View\AppView For view integration
 * @see templates/element/ For template elements used by controls
 */
class KmpHelper extends Helper
{
    /**
     * Main view reference for block management and script inclusion.
     * 
     * Stores the primary AppView instance to enable block management
     * across view cells. View cells create their own view instances,
     * but we need a reference to the main view for consistent
     * block handling and script management.
     * 
     * @var AppView
     */
    private static AppView $mainView;

    /**
     * Tracks the currently open block name.
     * 
     * Used to manage nested blocks and ensure proper block closure.
     * Helps prevent block management errors and provides debugging
     * information for template developers.
     * 
     * @var string
     */
    private static string $currentOpenBlock = '';

    public function beforeRender(Event $event): void
    {
        // Each cell has its own view, but the first one created is the main
        // one that we want to add scripts to, so we'll store it here
        $view = $event->getSubject();
        assert($view instanceof AppView);

        // Only store the main view once, and ignore error views
        if (isset(self::$mainView) && $view->getTemplatePath() != 'Error') {
            return;
        }

        // Store reference to main view for block management
        self::$mainView = $view;
    }

    /**
     * Convert array data to CSV format for download/export.
     * 
     * Delegates to StaticHelpers for consistent CSV formatting across
     * the application. Useful for generating CSV exports from view data.
     * 
     * @param array $data Array of data to convert to CSV
     * @return string CSV formatted string
     * @see \App\KMP\StaticHelpers::arrayToCsv() For implementation details
     * 
     * @example
     * ```php
     * $csvData = $this->Kmp->makeCsv([
     *     ['Name', 'Email', 'Branch'],
     *     ['John Doe', 'john@example.com', 'East Kingdom'],
     *     ['Jane Smith', 'jane@example.com', 'Middle Kingdom']
     * ]);
     * ```
     */
    public function makeCsv(array $data): string
    {
        return StaticHelpers::arrayToCsv($data);
    }

    /**
     * Start a named view block for content organization.
     * 
     * Begins a content block that can be rendered in different parts
     * of the layout. Useful for organizing CSS, JavaScript, or content
     * that needs to be placed in specific layout sections.
     * 
     * Block Management:
     * - Uses main view reference for consistent block handling
     * - Tracks current open block to prevent nesting errors
     * - Returns current block content (if any)
     * 
     * @param string $block Name of the block to start
     * @return string Current content of the block (if any)
     * 
     * @example
     * ```php
     * // Start a CSS block
     * $this->Kmp->startBlock('css');
     * echo $this->Html->css('custom-page-styles');
     * $this->Kmp->endBlock();
     * 
     * // In layout: echo $this->fetch('css');
     * ```
     */
    public function startBlock(string $block): string
    {
        self::$mainView->start($block);
        self::$currentOpenBlock = $block;

        return self::$mainView->fetch($block);
    }

    /**
     * End the currently open view block.
     * 
     * Closes the current block and resets the tracking variable.
     * Must be called after startBlock() to properly close the block.
     * 
     * Safety Features:
     * - Resets current block tracking
     * - Prevents block nesting errors
     * - Ensures proper block closure
     * 
     * @return void
     * 
     * @example
     * ```php
     * $this->Kmp->startBlock('scripts');
     * echo $this->Html->script('page-specific');
     * $this->Kmp->endBlock(); // Must be called
     * ```
     */
    public function endBlock(): void
    {
        self::$mainView->end();
        self::$currentOpenBlock = '';
    }

    /**
     * Render a combo box control with predefined options.
     * 
     * Creates a sophisticated combo box control that combines a dropdown
     * with text input, allowing users to either select from predefined
     * options or enter custom values (if allowed).
     * 
     * Features:
     * - Dropdown selection from predefined data
     * - Optional custom value entry
     * - Integration with CakePHP forms
     * - Bootstrap styling support
     * - Automatic form validation
     * 
     * Parameters:
     * @param mixed $Form The CakePHP Form helper instance
     * @param string $inputField Name of the display input field
     * @param string $resultField Name of the hidden field for selected value
     * @param array $data Array of options [value => label] or [value => ['text' => label, 'data' => extra]]
     * @param string|null $label Label text for the control
     * @param bool $required Whether the field is required
     * @param bool $allowOtherValues Whether to allow custom values not in the list
     * @param array $additionalAttrs Additional HTML attributes for the control
     * @return string Rendered combo box HTML
     * 
     * @example
     * ```php
     * echo $this->Kmp->comboBoxControl(
     *     $this->Form,
     *     'branch_name',        // Display field
     *     'branch_id',          // Value field
     *     $branchOptions,       // Options array
     *     'Select Branch',      // Label
     *     true,                 // Required
     *     true,                 // Allow other values
     *     ['class' => 'custom'] // Additional attributes
     * );
     * ```
     */
    public function comboBoxControl(
        $Form,
        string $inputField,
        string $resultField,
        array $data,
        ?string $label = null,
        bool $required = false,
        bool $allowOtherValues = false,
        array $additionalAttrs = []
    ): string {
        return $this->_View->element('comboBoxControl', compact(
            'Form',
            'inputField',
            'resultField',
            'data',
            'label',
            'required',
            'allowOtherValues',
            'additionalAttrs'
        ));
    }

    /**
     * Render auto complete control using element
     * 
     * Creates an autocomplete input field that dynamically loads suggestions from a URL endpoint.
     * The control includes both a display field for user input and a hidden field for the selected value.
     * This uses the Stimulus.js autocomplete controller for JavaScript functionality.
     * 
     * @param mixed $Form The CakePHP Form helper instance
     * @param string $inputField Name of the display input field (shows user-friendly text)
     * @param string $resultField Name of the hidden field for storing selected value (usually ID)
     * @param string $url URL endpoint that provides autocomplete suggestions (JSON response expected)
     * @param string|null $label Label text for the control (null for no label)
     * @param bool $required Whether the field is required for form validation
     * @param bool $allowOtherValues Whether to allow custom values not from suggestions
     * @param int $minLength Minimum characters before triggering autocomplete (default: 1)
     * @param array $additionalAttrs Additional HTML attributes for the input element
     * @return string Rendered autocomplete control HTML
     * 
     * @example
     * ```php
     * echo $this->Kmp->autoCompleteControl(
     *     $this->Form,
     *     'member_name',            // Display field
     *     'member_id',              // Hidden value field  
     *     '/members/search.json',   // Search endpoint
     *     'Select Member',          // Label
     *     true,                     // Required
     *     false,                    // Don't allow other values
     *     2,                        // Min 2 characters
     *     ['placeholder' => 'Type to search...']
     * );
     * ```
     */
    public function autoCompleteControl(
        $Form,
        string $inputField,
        string $resultField,
        string $url,
        ?string $label = null,
        bool $required = false,
        bool $allowOtherValues = false,
        int $minLength = 1,
        array $additionalAttrs = []
    ): string {
        return $this->_View->element('autoCompleteControl', compact(
            'Form',
            'inputField',
            'resultField',
            'url',
            'label',
            'required',
            'allowOtherValues',
            'minLength',
            'additionalAttrs'
        ));
    }

    /**
     * Returns a boolean icon for visual representation of true/false values
     * 
     * Renders Bootstrap icons to visually represent boolean states in the UI.
     * Uses green check-circle-fill for true values and red x-circle for false values.
     * This provides a consistent visual language for boolean data throughout the KMP application.
     * 
     * @param bool $value The boolean value to represent
     * @param \Cake\View\Helper\HtmlHelper $Html CakePHP HTML helper instance for icon rendering
     * @param array $options Additional HTML attributes/options for the icon
     * @return string HTML string containing the appropriate Bootstrap icon
     * 
     * @example
     * ```php
     * // In a table cell showing member status
     * echo $this->Kmp->bool($member->is_active, $this->Html);
     * // Outputs: <i class="bi bi-check-circle-fill"></i> for active
     * //          <i class="bi bi-x-circle"></i> for inactive
     * 
     * // With custom CSS classes
     * echo $this->Kmp->bool($permission->granted, $this->Html, ['class' => 'large-icon']);
     * ```
     * 
     * @see \BootstrapUI\View\Helper\HtmlHelper::icon() Bootstrap icon helper
     */
    public function bool(bool $value, HtmlHelper $Html, array $options = []): string
    {
        return $value
            ? $Html->icon('check-circle-fill', $options)
            : $Html->icon('x-circle', $options);
    }

    /**
     * Render application navigation using cell
     * 
     * Delegates to the AppNavCell to render the main navigation bar for the KMP application.
     * This includes primary navigation items, user menu, and responsive mobile navigation.
     * The cell handles complex navigation logic including permissions and active states.
     * 
     * @param array $appNav Navigation configuration array containing menu structure
     * @param Member $user Current authenticated user for permission checks
     * @param array $navBarState Current navigation state for highlighting active items
     * @return string Rendered navigation HTML from AppNavCell
     * 
     * @example
     * ```php
     * // In main layout template
     * echo $this->Kmp->appNav($this->navigationData, $this->Identity->get(), $this->navState);
     * ```
     * 
     * @see \App\View\Cell\AppNavCell View cell that handles navigation rendering
     */
    public function appNav(array $appNav, Member $user, array $navBarState = []): string
    {
        return (string)$this->_View->cell('AppNav', [$appNav, $user, $navBarState]);
    }

    /**
     * Get application setting from the database configuration
     * 
     * Provides view-layer access to application settings stored in the database.
     * This is a wrapper around StaticHelpers::getAppSetting() that allows templates
     * to retrieve configuration values without direct service access.
     * 
     * @param string $key The setting key to retrieve
     * @param string|null $fallback Default value if setting is not found
     * @return mixed The setting value or fallback if not found
     * 
     * @example
     * ```php
     * // In a template
     * $siteName = $this->Kmp->getAppSetting('site_name', 'KMP Application');
     * $maxUpload = $this->Kmp->getAppSetting('max_upload_size', '10MB');
     * ```
     * 
     * @see \App\Services\StaticHelpers::getAppSetting() Static helper that performs the lookup
     */
    public function getAppSetting(string $key, ?string $fallback = null): mixed
    {
        return StaticHelpers::getAppSetting($key, $fallback);
    }

    /**
     * Get application settings that start with a specific key prefix
     * 
     * Retrieves multiple application settings that begin with the specified key.
     * Useful for getting related configuration options or grouped settings.
     * This is commonly used for plugin configurations or feature-specific settings.
     * 
     * @param string $key The prefix to search for in setting keys
     * @return array Array of settings where keys start with the prefix
     * 
     * @example
     * ```php
     * // Get all email-related settings
     * $emailSettings = $this->Kmp->getAppSettingsStartWith('email_');
     * // Returns: ['email_smtp_host' => 'smtp.example.com', 'email_from_address' => 'noreply@example.com']
     * 
     * // Get plugin-specific settings
     * $pluginSettings = $this->Kmp->getAppSettingsStartWith('plugin_awards_');
     * ```
     * 
     * @see \App\Services\StaticHelpers::getAppSettingsStartWith() Static helper that performs the lookup
     */
    public function getAppSettingsStartWith(string $key): array
    {
        return StaticHelpers::getAppSettingsStartWith($key);
    }

    /**
     * Get Mix script URL with versioning for cache busting
     * 
     * Integrates Laravel Mix asset versioning with CakePHP URL generation.
     * Appends version hashes to script URLs for cache busting when assets change.
     * This ensures browsers load the latest version of JavaScript files after deployment.
     * 
     * @param string $script The script filename/path relative to webroot/js
     * @param mixed $Url CakePHP URL helper instance for generating base URLs
     * @return string Versioned script URL with hash parameter
     * 
     * @example
     * ```php
     * // In layout template
     * echo $this->Html->script($this->Kmp->getMixScriptUrl('app.js', $this->Url));
     * // Outputs: <script src="/js/app.js?id=abc123hash"></script>
     * 
     * // For chunked/split JavaScript files  
     * echo $this->Html->script($this->Kmp->getMixScriptUrl('manifest.js', $this->Url));
     * echo $this->Html->script($this->Kmp->getMixScriptUrl('vendor.js', $this->Url));
     * ```
     * 
     * @see \App\Services\AssetMix Laravel Mix integration service
     */
    public function getMixScriptUrl(string $script, $Url): string
    {
        $url = $Url->script($script);
        return (new Mix())($url);
    }

    /**
     * Get Mix style URL with versioning for cache busting
     * 
     * Integrates Laravel Mix asset versioning with CakePHP URL generation for CSS files.
     * Appends version hashes to stylesheet URLs for cache busting when styles change.
     * This ensures browsers load the latest version of CSS files after deployment.
     * 
     * @param string $css The CSS filename/path relative to webroot/css  
     * @param mixed $Url CakePHP URL helper instance for generating base URLs
     * @return string Versioned CSS URL with hash parameter
     * 
     * @example
     * ```php
     * // In layout template
     * echo $this->Html->css($this->Kmp->getMixStyleUrl('app.css', $this->Url));
     * // Outputs: <link href="/css/app.css?id=xyz789hash" rel="stylesheet">
     * 
     * // For vendor/third-party stylesheets
     * echo $this->Html->css($this->Kmp->getMixStyleUrl('vendor.css', $this->Url));
     * ```
     * 
     * @see \App\Services\AssetMix Laravel Mix integration service
     */
    public function getMixStyleUrl(string $css, $Url): string
    {
        $url = $Url->css($css);
        return (new Mix())($url);
    }
}
