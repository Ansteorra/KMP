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
 * KMP View Helper providing custom UI components and template utilities.
 * 
 * Features: auto-complete controls, block management, data conversion (CSV),
 * boolean display, application settings access, and navigation cell rendering.
 * Available in templates as $this->Kmp->methodName().
 * 
 * @see \App\KMP\StaticHelpers For core utility functions
 * @see \App\View\AppView For view integration
 */
class KmpHelper extends Helper
{
    /**
     * Main view reference for block management across view cells.
     * 
     * @var AppView|null
     */
    private static ?AppView $mainView = null;

    /**
     * Tracks the currently open block name.
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

        // Ignore error views
        if ($view->getTemplatePath() == 'Error') {
            return;
        }

        // Update mainView when a new request context begins (new HTTP request
        // or new test run). Cell views share the same request as their parent,
        // so they won't overwrite the main view within a single request.
        if (self::$mainView === null || self::$mainView->getRequest() !== $view->getRequest()) {
            self::$mainView = $view;
        }
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
     * @param string $block Name of the block to start
     * @return string Current content of the block (if any)
     */
    public function startBlock(string $block): string
    {
        $view = self::$mainView ?? $this->getView();
        $view->start($block);
        self::$currentOpenBlock = $block;

        return $view->fetch($block);
    }

    /**
     * End the currently open view block.
     * 
     * @return void
     */
    public function endBlock(): void
    {
        $view = self::$mainView ?? $this->getView();
        $view->end();
        self::$currentOpenBlock = '';
    }

    /**
     * Render a combo box control with predefined options.
     * 
     * Creates a dropdown with optional custom value entry.
     * 
     * @param mixed $Form The CakePHP Form helper instance
     * @param string $inputField Name of the display input field
     * @param string $resultField Name of the hidden field for selected value
     * @param array $data Options array [value => label]
     * @param string|null $label Label text for the control
     * @param bool $required Whether the field is required
     * @param bool $allowOtherValues Whether to allow custom values
     * @param array $additionalAttrs Additional HTML attributes
     * @return string Rendered combo box HTML
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
     * Render auto complete control using element.
     * 
     * Creates an autocomplete input that loads suggestions from a URL endpoint.
     * Uses Stimulus.js autocomplete controller for JavaScript functionality.
     * 
     * @param mixed $Form The CakePHP Form helper instance
     * @param string $inputField Name of the display input field
     * @param string $resultField Name of the hidden field for selected value
     * @param string $url URL endpoint providing autocomplete suggestions (JSON)
     * @param string|null $label Label text for the control
     * @param bool $required Whether the field is required
     * @param bool $allowOtherValues Whether to allow custom values
     * @param int $minLength Minimum characters before triggering autocomplete
     * @param array $additionalAttrs Additional HTML attributes
     * @return string Rendered autocomplete control HTML
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
     * Returns a boolean icon for visual representation of true/false values.
     * 
     * Uses Bootstrap icons: green check-circle-fill for true, red x-circle for false.
     * 
     * @param bool $value The boolean value to represent
     * @param \Cake\View\Helper\HtmlHelper $Html CakePHP HTML helper instance
     * @param array $options Additional HTML attributes for the icon
     * @return string HTML string containing the Bootstrap icon
     */
    public function bool(bool $value, HtmlHelper $Html, array $options = []): string
    {
        return $value
            ? $Html->icon('check-circle-fill', $options)
            : $Html->icon('x-circle', $options);
    }

    /**
     * Render application navigation using cell.
     * 
     * Delegates to AppNavCell for main navigation rendering.
     * 
     * @param array $appNav Navigation configuration array
     * @param Member $user Current authenticated user
     * @param array $navBarState Current navigation state
     * @return string Rendered navigation HTML
     */
    public function appNav(array $appNav, Member $user, array $navBarState = []): string
    {
        return (string)$this->_View->cell('AppNav', [$appNav, $user, $navBarState]);
    }

    /**
     * Get application setting from the database configuration.
     * 
     * @param string $key The setting key to retrieve
     * @param string|null $fallback Default value if setting not found
     * @return mixed The setting value or fallback
     */
    public function getAppSetting(string $key, ?string $fallback = null): mixed
    {
        return StaticHelpers::getAppSetting($key, $fallback);
    }

    /**
     * Get application settings that start with a specific key prefix.
     * 
     * @param string $key The prefix to search for in setting keys
     * @return array Array of settings where keys start with the prefix
     */
    public function getAppSettingsStartWith(string $key): array
    {
        return StaticHelpers::getAppSettingsStartWith($key);
    }

    /**
     * Get Mix script URL with versioning for cache busting.
     * 
     * @param string $script The script filename/path relative to webroot/js
     * @param mixed $Url CakePHP URL helper instance
     * @return string Versioned script URL with hash parameter
     */
    public function getMixScriptUrl(string $script, $Url): string
    {
        $url = $Url->script($script);
        return (new Mix())($url);
    }

    /**
     * Get Mix style URL with versioning for cache busting.
     * 
     * @param string $css The CSS filename/path relative to webroot/css  
     * @param mixed $Url CakePHP URL helper instance
     * @return string Versioned CSS URL with hash parameter
     */
    public function getMixStyleUrl(string $css, $Url): string
    {
        $url = $Url->css($css);
        return (new Mix())($url);
    }

    /**
     * Get PHP upload configuration limits in bytes.
     * 
     * Returns the smaller of upload_max_filesize and post_max_size.
     * 
     * @return array Array with 'maxFileSize' in bytes and 'formatted' human-readable string
     */
    public function getUploadLimits(): array
    {
        // Parse upload_max_filesize
        $uploadMax = $this->parsePhpSize(ini_get('upload_max_filesize'));

        // Parse post_max_size
        $postMax = $this->parsePhpSize(ini_get('post_max_size'));

        // The effective limit is the smaller of the two
        $maxFileSize = min($uploadMax, $postMax);

        return [
            'maxFileSize' => $maxFileSize,
            'formatted' => $this->formatBytes($maxFileSize),
            'uploadMaxFilesize' => $uploadMax,
            'postMaxSize' => $postMax,
        ];
    }

    /**
     * Parse PHP size notation to bytes
     * 
     * Converts PHP ini size notation (e.g., '25M', '2G', '512K') to bytes.
     * Handles various formats used in php.ini configuration.
     * 
     * @param string $size Size string from PHP ini setting
     * @return int Size in bytes
     */
    private function parsePhpSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $value = (int)$size;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // Fall through
            case 'm':
                $value *= 1024;
                // Fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human-readable string
     * 
     * @param int $bytes Size in bytes
     * @param int $precision Decimal precision
     * @return string Formatted size string (e.g., '25MB', '1.5GB')
     */
    private function formatBytes(int $bytes, int $precision = 0): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . $units[$i];
    }

    /**
     * Return the possessive form of a name.
     *
     * Trims input and appends an apostrophe or apostrophe-s depending
     * on whether the name ends with an "s" (case-insensitive).
     *
     * @param string $name Name to convert
     * @return string Possessive form (empty string for blank input)
     */
    public function makePossessive(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $last = strtolower(substr($name, -1));
        if ($last === 's') {
            return $name . "'";
        }

        return $name . "'s";
    }
}
