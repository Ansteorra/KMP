<?php

declare(strict_types=1);

/**
 * Static utility methods for KMP application.
 *
 * Provides centralized utilities for: file operations, image scaling, configuration
 * access (getAppSetting/setAppSetting), plugin status checking, data path extraction,
 * template processing, CSV generation, and security (token generation, HTML sanitization).
 *
 * @package App\KMP
 * @see \App\Model\Table\AppSettingsTable For database configuration storage
 */

namespace App\KMP;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use Exception;

class StaticHelpers
{
    /**
     * Ensure a directory exists, creating it recursively if necessary.
     *
     * @param string $dirname Path to directory to create
     * @param int $visibility Permission mask (e.g., 0755)
     * @return void
     * @throws \Exception When directory creation fails
     */
    static function ensureDirectoryExists(string $dirname, int $visibility): void
    {
        // Quick return if directory already exists
        if (is_dir($dirname)) {
            return;
        }

        // Clear any previous errors before attempting creation
        error_clear_last();

        // Attempt to create directory recursively with specified permissions
        if (!@mkdir($dirname, $visibility, true)) {
            // Capture mkdir error for exception message
            $mkdirError = error_get_last();
        }

        // Clear filesystem stat cache to ensure fresh is_dir() check
        clearstatcache(true, $dirname);

        // Verify directory was actually created
        if (!is_dir($dirname)) {
            $errorMessage = $mkdirError['message'] ?? '';

            throw new Exception($errorMessage);
        }
    }

    /**
     * Scale and save an image maintaining aspect ratio (fit-inside bounds).
     *
     * Supports PNG and JPEG. Original file deleted if output path differs.
     *
     * @param string $imageName Base filename without extension
     * @param int $newWidth Maximum width
     * @param int $newHeight Maximum height
     * @param string $uploadDir Source directory path
     * @param string $moveToDir Destination directory path
     * @return string Full path to scaled image, or empty string on failure
     */
    static function saveScaledImage(string $imageName, int $newWidth, int $newHeight, string $uploadDir, string $moveToDir): string
    {
        // Build source image path
        $path = $uploadDir . '/' . $imageName;

        // Detect image format and dimensions via MIME analysis
        $mime = getimagesize($path);

        // Create source image resource based on detected format
        switch ($mime['mime']) {
            case 'image/png':
                $src_img = imagecreatefrompng($path);
                break;
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $src_img = imagecreatefromjpeg($path);
                break;
        }

        // Get original image dimensions
        $old_x = imageSX($src_img);
        $old_y = imageSY($src_img);

        // Calculate the scaling factor to fit image inside specified bounds
        // Uses minimum scale to ensure entire image fits within bounds
        $scale = min($newWidth / $old_x, $newHeight / $old_y);

        // Calculate new dimensions maintaining aspect ratio
        $thumb_w = toInt(round($old_x * $scale));
        $thumb_h = toInt(round($old_y * $scale));

        // Create destination image canvas with calculated dimensions
        $dst_img = ImageCreateTrueColor($thumb_w, $thumb_h);

        // Perform high-quality resampling from source to destination
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);

        // Determine output path and save in appropriate format
        $new_thumb_loc = $moveToDir . $imageName;

        switch ($mime['mime']) {
            case 'image/png':
                $new_thumb_loc = $new_thumb_loc . '.png';
                // Save with compression level 8 (good balance of size/quality)
                $result = imagepng($dst_img, $new_thumb_loc, 8);
                break;
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $new_thumb_loc = $new_thumb_loc . '.jpg';
                // Save with 80% quality (good balance of size/quality)
                $result = imagejpeg($dst_img, $new_thumb_loc, 80);
                break;
        }

        // Clean up image resources to free memory
        imagedestroy($dst_img);
        imagedestroy($src_img);
        if (!$result) {
            return '';
        }
        if ($new_thumb_loc != $path) {
            unlink($path);
        }

        return $new_thumb_loc;
    }

    /**
     * Generate cryptographically secure random alphanumeric token.
     *
     * @param int $length Number of characters (default: 32)
     * @return string Random alphanumeric token
     * @see \Cake\Utility\Security::randomString()
     */
    static function generateToken(int $length = 32): string
    {
        return Security::randomString($length);
    }

    /**
     * Safely delete a file. Idempotent: returns true if file doesn't exist.
     *
     * @param string $path Full path to file
     * @return bool True if deleted or doesn't exist
     * @throws \Exception If deletion fails
     */
    static function deleteFile(string $path): bool
    {
        // If file doesn't exist, consider deletion successful (idempotent)
        if (!file_exists($path)) {
            return true;
        }

        // Clear any previous errors to get accurate error information
        error_clear_last();

        // Attempt to delete file using @ to suppress warnings
        if (!@unlink($path)) {
            // Get the last error that occurred during unlink
            $error = error_get_last();
            throw new Exception($error['message']);
        }

        return true;
    }

    /**
     * Extract values from nested arrays using arrow-notation paths.
     *
     * Supports path syntax like "user->profile->name" and conditional
     * formatting with "prefix(path)suffix".
     *
     * @param string $path Navigation path using -> separator
     * @param mixed $array Source array to navigate
     * @param int $minLength Minimum string length required (default: 0)
     * @param mixed $fallback Value if path not found or validation fails
     * @return mixed Extracted value or fallback
     */
    static function getValue(string $path, mixed $array, $minLength = 0, $fallback = null): mixed
    {
        // Split path into navigation segments
        $path = explode('->', $path);
        $temp = &$array;
        $prepend = '';
        $postpend = '';

        // Navigate through each segment of the path
        foreach ($path as $key) {
            // Check for conditional formatting syntax: prefix(key)suffix
            if (strpos($key, '(') !== false) {
                $key = explode('(', $key);
                $prepend = $key[0];  // Text before parentheses
                $key = explode(')', $key[1]);
                $postpend = $key[1]; // Text after parentheses
                $key = $key[0];      // Key inside parentheses
            }
            $temp = &$temp[$key];
        }

        // Apply conditional formatting if specified and value exists/non-empty
        if ($prepend != '' && $postpend != '' && $temp != '') {
            $temp = $prepend . $temp . $postpend;
        }

        // Return fallback for null values
        if ($temp === null) {
            return $fallback;
        }

        // Return arrays without length validation
        if (is_array($temp)) {
            return $temp;
        }

        // Apply minimum length validation to strings
        if (is_string($temp) && (strlen($temp) < $minLength)) {
            return $fallback;
        }

        return $temp;
    }

    /**
     * Process template strings with {{variable}} placeholders.
     *
     * Uses getValue() path syntax for data extraction.
     *
     * @param string $string Template with {{path}} placeholders
     * @param mixed $data Source data for variable resolution
     * @param int $minLength Minimum length for string values (default: 0)
     * @param mixed $missingValue Default for undefined variables (default: '')
     * @return string Processed template
     * @see getValue()
     */
    static function processTemplate(string $string, mixed $data, $minLength = 0, $missingValue = ''): string
    {
        $matches = [];

        // Find all template variables using regex pattern {{variable}}
        preg_match_all('/{{(.*?)}}/', $string, $matches);

        // Process each found variable placeholder
        foreach ($matches[1] as $match) {
            // Resolve the variable path using getValue() and convert to string
            $value = toString(self::getValue($match, $data, $minLength, $missingValue));

            // Replace the placeholder with the resolved value
            $string = str_replace('{{' . $match . '}}', $value, $string);
        }

        return $string;
    }

    /**
     * Check if a plugin is enabled via `Plugin.{PluginName}.Active` setting.
     *
     * @param string $pluginName Plugin name (case-sensitive)
     * @return bool True if setting equals 'yes'
     * @see getAppSetting()
     */
    static function pluginEnabled($pluginName): bool
    {
        return self::getAppSetting('Plugin.' . $pluginName . '.Active', 'no') == 'yes';
    }

    /**
     * Retrieve app setting with multi-layer fallback (Configure -> DB -> fallback).
     *
     * @param string $key Configuration key (dot notation)
     * @param string|null $fallback Default value if not found
     * @param mixed $type Optional type for validation/conversion
     * @param bool $required Whether setting is mandatory
     * @return mixed Configuration value
     * @throws \Exception Re-throws non-database exceptions
     * @see \App\Model\Table\AppSettingsTable::getAppSetting()
     */
    static function getAppSetting(string $key, ?string $fallback = null, $type = null, $required = false): mixed
    {
        try {
            // First priority: Check CakePHP Configure system
            // This includes static config files and runtime overrides
            $value = Configure::read($key);
            if ($value !== null) {
                return $value;
            }

            // Second priority: Check database AppSettings table
            // This provides dynamic, user-configurable settings
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');
            $value = $AppSettings->getAppSetting($key, $fallback, $type, $required);

            return $value;
        } catch (Exception $e) {
            // Handle database connectivity issues gracefully
            if (get_class($e) == "Cake\Database\Exception\DatabaseException") {
                return $fallback;
            }

            // Re-throw other exceptions to preserve error context
            throw $e;
        }
    }

    /**
     * Retrieve all settings with keys starting with given prefix.
     *
     * @param string $key Prefix to match (case-sensitive)
     * @return array Matching settings (key => value), empty on error
     * @see \App\Model\Table\AppSettingsTable::getAllAppSettingsStartWith()
     */
    static function getAppSettingsStartWith(string $key): array
    {
        try {
            // Access AppSettings table for bulk retrieval
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');
            $return = $AppSettings->getAllAppSettingsStartWith($key);

            return $return;
        } catch (Exception $e) {
            // Return empty array on any error to prevent application failure
            // This allows the application to continue with reduced functionality
            return [];
        }
    }

    /**
     * Delete an application setting from database.
     *
     * @param string $key Setting key (dot notation)
     * @param bool $forceDelete Bypass protection mechanisms (default: false)
     * @return bool True if deleted/didn't exist, false on constraint failure
     * @throws \Exception Re-throws non-database exceptions
     * @see \App\Model\Table\AppSettingsTable::deleteAppSetting()
     */
    static function deleteAppSetting(string $key, bool $forceDelete = false): bool
    {
        try {
            // Access AppSettings table for deletion operation
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');

            return $AppSettings->deleteAppSetting($key, $forceDelete);
        } catch (Exception $e) {
            // Handle database connectivity issues gracefully
            if (get_class($e) == "Cake\Database\Exception\DatabaseException") {
                // Consider database unavailability as successful deletion
                // This prevents application errors when database is down
                return true;
            }

            // Re-throw other exceptions to preserve error context
            throw $e;
        }
    }

    /**
     * Create or update an application setting in database.
     *
     * @param string $key Setting key (dot notation)
     * @param mixed $value Value to store
     * @param mixed $type Optional type for validation
     * @param bool $required Mark as required setting (default: false)
     * @return bool True if saved successfully
     * @see \App\Model\Table\AppSettingsTable::setAppSetting()
     */
    static function setAppSetting(string $key, $value, $type = null, $required = false): bool
    {
        try {
            // Access AppSettings table for persistence operation
            $AppSettings = TableRegistry::getTableLocator()->get('AppSettings');

            return $AppSettings->setAppSetting($key, $value, $type, $required);
        } catch (Exception $e) {
            // Return false on any error to indicate failure
            // This provides consistent error handling for calling code
            return false;
        }
    }

    /**
     * Convert a CakePHP route array to a normalized lowercase path string.
     *
     * Format: `[plugin/]controller/action[/param]`
     *
     * @param array $path Route array with controller, action, optional plugin/params
     * @return string Normalized lowercase path (e.g., "awards/awards/index")
     */
    static function makePathString($path): string
    {
        // Build base path from controller and action
        $pathString = $path['controller'] . '/' . $path['action'];

        // Prepend plugin if present
        if (isset($path['plugin'])) {
            $pathString = $path['plugin'] . '/' . $pathString;
        }

        // Append first positional parameter if present
        if (isset($path[0])) {
            $pathString .= '/' . $path[0];
        }

        // Return normalized lowercase path
        return strtolower($pathString);
    }

    /**
     * Convert 2D array to CSV formatted string using fputcsv.
     *
     * @param array $data 2D array where each sub-array is a CSV row
     * @param string $delimiter Field separator (default: ',')
     * @param string $enclosure Quote character (default: '"')
     * @param string $escapeChar Escape character (default: '\\')
     * @return string CSV formatted string
     */
    static function arrayToCsv(array $data, $delimiter = ',', $enclosure = '"', $escapeChar = '\\'): string
    {
        $csvString = '';

        // Create memory stream for efficient CSV processing
        $f = fopen('php://memory', 'r+');

        // Process each row using PHP's built-in CSV formatting
        foreach ($data as $row) {
            fputcsv($f, $row, $delimiter, $enclosure, $escapeChar);
        }

        // Read back the formatted CSV data
        rewind($f);
        while (($line = fgets($f)) !== false) {
            $csvString .= $line;
        }

        // Clean up stream resource
        fclose($f);

        return $csvString;
    }

    /**
     * Sanitize string for safe HTML attribute use (XSS prevention).
     *
     * Escapes quotes and special characters using htmlspecialchars.
     *
     * @param string $string Input string
     * @return string HTML-safe string for attribute values
     */
    static function makeSafeForHtmlAttribute($string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
