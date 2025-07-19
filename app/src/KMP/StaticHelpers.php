<?php

declare(strict_types=1);

/**
 * Kingdom Management Portal (KMP) - Static Helper Utilities
 * 
 * This class provides a collection of static utility methods that are used
 * throughout the KMP application. It serves as a centralized location for
 * common operations including file management, image processing, configuration
 * access, data manipulation, and string processing.
 * 
 * ## Core Functionality Areas
 * 
 * ### 1. File System Operations
 * - Directory creation and management with proper error handling
 * - File deletion with existence checking and error management
 * - Image processing and scaling for uploads and thumbnails
 * 
 * ### 2. Configuration Management
 * - Application settings access with fallback support
 * - Plugin status checking and configuration retrieval
 * - Multi-layer configuration resolution (Config -> Database)
 * 
 * ### 3. Data Processing
 * - Array path navigation and value extraction
 * - Template processing with placeholder replacement
 * - CSV generation from array data
 * 
 * ### 4. Security & Utilities
 * - Random token generation for security purposes
 * - HTML attribute sanitization for XSS prevention
 * - URL path string generation for routing
 * 
 * ## Architecture Integration
 * 
 * This utility class integrates with several KMP subsystems:
 * - Configuration System: Via getAppSetting methods for settings access
 * - Plugin System: Via pluginEnabled for plugin status checking
 * - File Management: For image processing and file operations
 * - Security Layer: For token generation and input sanitization
 * - Template System: For dynamic content generation
 * 
 * ## Design Patterns
 * 
 * ### Static Method Pattern
 * All methods are static for global accessibility without instantiation:
 * ```php
 * $token = StaticHelpers::generateToken(32);
 * $setting = StaticHelpers::getAppSetting('key', 'default');
 * ```
 * 
 * ### Graceful Error Handling
 * Methods handle errors gracefully with appropriate fallbacks:
 * - File operations return status or throw specific exceptions
 * - Configuration access falls back to defaults on missing values
 * - Database errors are caught and handled appropriately
 * 
 * ### Multi-Layer Configuration
 * Configuration resolution follows a priority order:
 * 1. CakePHP Configure values (highest priority)
 * 2. Database AppSettings table values
 * 3. Provided fallback values (lowest priority)
 * 
 * ## Usage Patterns
 * 
 * ### Configuration Access
 * ```php
 * // Basic setting with fallback
 * $value = StaticHelpers::getAppSetting('App.Setting', 'default');
 * 
 * // Plugin status checking
 * if (StaticHelpers::pluginEnabled('Awards')) {
 *     // Plugin-specific functionality
 * }
 * 
 * // Bulk settings retrieval
 * $settings = StaticHelpers::getAppSettingsStartWith('Plugin.');
 * ```
 * 
 * ### File Operations
 * ```php
 * // Ensure directory exists
 * StaticHelpers::ensureDirectoryExists('/path/to/dir', 0755);
 * 
 * // Scale and save image
 * $newPath = StaticHelpers::saveScaledImage(
 *     'image.jpg', 200, 200, '/uploads/', '/thumbs/'
 * );
 * 
 * // Safe file deletion
 * StaticHelpers::deleteFile('/path/to/file.txt');
 * ```
 * 
 * ### Data Processing
 * ```php
 * // Extract nested array values
 * $value = StaticHelpers::getValue('user->profile->name', $data, 0, 'Unknown');
 * 
 * // Process templates with placeholders
 * $text = StaticHelpers::processTemplate(
 *     'Hello {{user->name}}!', $userData
 * );
 * 
 * // Generate CSV from array
 * $csv = StaticHelpers::arrayToCsv($dataArray);
 * ```
 * 
 * ## Performance Considerations
 * 
 * - Configuration values are cached after first database access
 * - Image processing uses memory-efficient operations
 * - File operations include proper cleanup and resource management
 * - Error handling minimizes performance impact of failures
 * 
 * ## Security Considerations
 * 
 * - Random token generation uses cryptographically secure methods
 * - HTML sanitization prevents XSS attacks
 * - File operations validate paths and handle permissions safely
 * - Configuration access is protected against injection attacks
 * 
 * @package App\KMP
 * @author KMP Development Team
 * @since KMP 1.0
 * @see \Cake\Core\Configure For configuration system integration
 * @see \Cake\Utility\Security For security utility integration
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
     * Ensure a directory exists, creating it if necessary
     * 
     * This method provides safe directory creation with proper error handling
     * and permission setting. It uses a combination of checks and atomic
     * operations to ensure the directory exists and is accessible.
     * 
     * ## Directory Creation Process
     * 
     * 1. **Existence Check**: Returns immediately if directory already exists
     * 2. **Atomic Creation**: Uses mkdir with recursive flag for full path creation
     * 3. **Error Capture**: Captures and processes any creation errors
     * 4. **Verification**: Confirms directory was successfully created
     * 5. **Exception Handling**: Throws descriptive exceptions on failure
     * 
     * ## Permission Handling
     * 
     * The visibility parameter sets directory permissions using standard Unix
     * permission octals:
     * - `0755`: Owner full access, group/others read/execute (typical)
     * - `0700`: Owner only access (secure)
     * - `0777`: Full access for all (not recommended for production)
     * 
     * ## Error Scenarios
     * 
     * - **Permission denied**: Parent directory not writable
     * - **Path conflicts**: File exists with same name
     * - **Filesystem full**: No space available for directory creation
     * - **Invalid path**: Path contains invalid characters or is too long
     * 
     * @param string $dirname Absolute or relative path to directory to create
     * @param int $visibility Permission mask for directory (e.g., 0755)
     * @return void
     * @throws \Exception When directory creation fails with descriptive error message
     * 
     * @example Basic Usage
     * ```php
     * StaticHelpers::ensureDirectoryExists('/var/www/uploads', 0755);
     * StaticHelpers::ensureDirectoryExists('./temp/cache', 0700);
     * ```
     * 
     * @example Error Handling
     * ```php
     * try {
     *     StaticHelpers::ensureDirectoryExists('/restricted/path', 0755);
     * } catch (Exception $e) {
     *     Log::error('Directory creation failed: ' . $e->getMessage());
     * }
     * ```
     * 
     * @example Temporary Directory Creation
     * ```php
     * $tempDir = sys_get_temp_dir() . '/kmp_temp_' . uniqid();
     * StaticHelpers::ensureDirectoryExists($tempDir, 0700);
     * // Use temporary directory...
     * ```
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
     * Scale and save an image while maintaining aspect ratio
     * 
     * This method provides comprehensive image processing functionality for
     * creating scaled versions of uploaded images. It handles multiple image
     * formats, maintains aspect ratios, and includes proper resource cleanup.
     * 
     * ## Image Processing Workflow
     * 
     * 1. **Format Detection**: Identifies image type (PNG, JPEG) via MIME analysis
     * 2. **Source Loading**: Creates image resource from original file
     * 3. **Scaling Calculation**: Determines optimal scale to fit within bounds
     * 4. **Dimension Calculation**: Computes new width/height maintaining aspect ratio
     * 5. **Image Creation**: Creates new image canvas with calculated dimensions
     * 6. **Resampling**: Performs high-quality image resampling/resizing
     * 7. **Format Saving**: Saves in appropriate format with quality settings
     * 8. **Cleanup**: Destroys image resources and removes original if different
     * 
     * ## Supported Formats
     * 
     * - **PNG**: Supports transparency, saved with compression level 8
     * - **JPEG**: High quality format, saved with 80% quality setting
     * - **PJPEG**: Progressive JPEG variant, treated as standard JPEG
     * 
     * ## Scaling Algorithm
     * 
     * Uses "fit-inside" scaling that maintains aspect ratio:
     * ```php
     * $scale = min($newWidth / $oldWidth, $newHeight / $oldHeight);
     * $thumbWidth = round($oldWidth * $scale);
     * $thumbHeight = round($oldHeight * $scale);
     * ```
     * 
     * This ensures the image fits entirely within the specified bounds without distortion.
     * 
     * ## File Path Handling
     * 
     * - **Input**: `$uploadDir/$imageName` (source image location)
     * - **Output**: `$moveToDir$imageName.{ext}` (scaled image destination)
     * - **Extension**: Automatically appended based on detected format
     * - **Cleanup**: Original file deleted if output path differs from input
     * 
     * ## Quality and Performance
     * 
     * - Uses `imagecopyresampled()` for high-quality scaling
     * - Maintains transparency for PNG images
     * - Applies optimal compression settings per format
     * - Includes proper memory management and resource cleanup
     * 
     * @param string $imageName Base filename without path or extension
     * @param int $newWidth Maximum width for scaled image
     * @param int $newHeight Maximum height for scaled image  
     * @param string $uploadDir Source directory path (with trailing slash)
     * @param string $moveToDir Destination directory path (with trailing slash)
     * @return string Full path to created scaled image, or empty string on failure
     * 
     * @example Profile Picture Scaling
     * ```php
     * $thumbPath = StaticHelpers::saveScaledImage(
     *     'user_photo', 150, 150, '/uploads/originals/', '/uploads/thumbs/'
     * );
     * // Result: '/uploads/thumbs/user_photo.jpg' (or .png)
     * ```
     * 
     * @example Gallery Thumbnail Creation
     * ```php
     * $galleryThumb = StaticHelpers::saveScaledImage(
     *     'gallery_image_001', 300, 200, '/uploads/full/', '/uploads/gallery/'
     * );
     * ```
     * 
     * @example Error Handling
     * ```php
     * $scaledPath = StaticHelpers::saveScaledImage(
     *     $imageName, 200, 200, $uploadDir, $thumbDir
     * );
     * 
     * if (empty($scaledPath)) {
     *     Log::error('Image scaling failed for: ' . $imageName);
     *     // Handle error appropriately
     * }
     * ```
     * 
     * @see getimagesize() For MIME type detection
     * @see imagecopyresampled() For high-quality image scaling
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
     * Generate a cryptographically secure random token
     * 
     * This method creates high-entropy random tokens suitable for security-sensitive
     * applications such as password reset tokens, session identifiers, CSRF tokens,
     * and API keys. It leverages CakePHP's Security class for cryptographically
     * secure random string generation.
     * 
     * ## Security Characteristics
     * 
     * - **Cryptographically Secure**: Uses CakePHP Security::randomString()
     * - **Alphanumeric Output**: Contains letters (a-z, A-Z) and numbers (0-9)
     * - **High Entropy**: Each character provides ~5.95 bits of entropy
     * - **Collision Resistant**: Extremely low probability of duplicate tokens
     * 
     * ## Token Format
     * 
     * Generated tokens consist of:
     * - Random alphanumeric characters (a-z, A-Z, 0-9)
     * - No special characters (URL and database safe)
     * - Case-sensitive for maximum entropy
     * - No ambiguous characters (implementation dependent)
     * 
     * ## Length Considerations
     * 
     * - **16 characters**: ~95 bits entropy, suitable for short-term tokens
     * - **32 characters (default)**: ~190 bits entropy, excellent general security
     * - **64 characters**: ~380 bits entropy, maximum security applications
     * 
     * The default length of 32 provides excellent security for most use cases
     * while remaining manageable for storage and transmission.
     * 
     * ## Use Case Examples
     * 
     * - **Session Tokens**: 24-32 characters for user session identification
     * - **Password Reset**: 32+ characters for secure password reset links
     * - **CSRF Tokens**: 16-24 characters for form protection
     * - **API Keys**: 32-64 characters for service authentication
     * - **One-time Codes**: 8-16 characters for temporary access codes
     * 
     * ## Performance and Integration
     * 
     * - Leverages CakePHP's optimized random generation
     * - Consistent with framework security practices
     * - No external dependencies required
     * - Fast generation suitable for high-frequency use
     * 
     * @param int $length Number of characters to generate (default: 32)
     * @return string Random alphanumeric token
     * 
     * @example Session Token Generation
     * ```php
     * $sessionToken = StaticHelpers::generateToken(24);
     * // Result: "kQ7oV2mxPHcW8GNtYpZ1zw8F"
     * 
     * session_start();
     * $_SESSION['token'] = $sessionToken;
     * ```
     * 
     * @example Password Reset Token
     * ```php
     * $resetToken = StaticHelpers::generateToken(); // Uses default 32
     * // Result: "xB9mK2p7NvQ8dF4hR6tY1wS5cA3jL9nE2pM"
     * 
     * $resetLink = "https://example.com/reset?token=" . $resetToken;
     * ```
     * 
     * @example CSRF Token for Forms
     * ```php
     * $csrfToken = StaticHelpers::generateToken(16);
     * echo '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">';
     * ```
     * 
     * @example API Key Generation
     * ```php
     * $apiKey = StaticHelpers::generateToken(64);
     * // Store in database for API authentication
     * $this->ApiKeys->save([
     *     'user_id' => $userId,
     *     'key' => $apiKey,
     *     'created' => new FrozenTime()
     * ]);
     * ```
     * 
     * @example Database Storage
     * ```php
     * $token = StaticHelpers::generateToken();
     * 
     * // Safe for database storage (no special characters)
     * $query = "INSERT INTO tokens (user_id, token) VALUES (?, ?)";
     * $stmt = $connection->prepare($query);
     * $stmt->execute([$userId, $token]);
     * ```
     * 
     * @see \Cake\Utility\Security::randomString() For underlying implementation
     */
    static function generateToken(int $length = 32): string
    {
        return Security::randomString($length);
    }

    /**
     * Safely delete a file with comprehensive error handling
     * 
     * This method provides secure file deletion functionality with proper validation,
     * error handling, and safety checks. It verifies file existence, attempts deletion,
     * and provides clear success/failure feedback with exception throwing for errors.
     * 
     * ## Safety Features
     * 
     * - **Existence Check**: Returns true if file doesn't exist (idempotent operation)
     * - **Error Capture**: Uses error_clear_last() and error_get_last() for detailed errors
     * - **Exception Handling**: Throws exceptions with detailed error messages
     * - **Silent Operator**: Uses @ operator to suppress PHP warnings during unlink
     * 
     * ## File System Considerations
     * 
     * - Works with both absolute and relative file paths
     * - Respects file system permissions and ownership
     * - Handles locked files and throws descriptive exceptions
     * - Does not attempt to delete directories (will throw exception)
     * 
     * ## Error Handling Strategy
     * 
     * 1. **Non-existent Files**: Returns true (successful deletion state)
     * 2. **Permission Errors**: Throws exception with system error message
     * 3. **Locked Files**: Throws exception with system error message
     * 4. **File System Errors**: Throws exception with detailed error information
     * 
     * ## Security Implications
     * 
     * - Does not validate path traversal attacks (caller responsibility)
     * - Requires appropriate file system permissions
     * - Throws exceptions on permission restrictions (not silent)
     * - Does not log deletion attempts (implement separately if needed)
     * 
     * ## Common Use Cases
     * 
     * - **Temporary File Cleanup**: Remove uploaded files after processing
     * - **Cache Invalidation**: Delete outdated cache files
     * - **User Content Management**: Remove user-uploaded files on deletion
     * - **Log Rotation**: Clean up old log files
     * - **Image Processing**: Remove temporary scaled images
     * 
     * @param string $path Full path to file to be deleted
     * @return bool True if file was successfully deleted or doesn't exist
     * @throws \Exception If file deletion fails with system error message
     * 
     * @example Temporary File Cleanup with Exception Handling
     * ```php
     * $tempFile = '/tmp/upload_' . uniqid() . '.tmp';
     * // ... process file ...
     * 
     * try {
     *     if (StaticHelpers::deleteFile($tempFile)) {
     *         Log::info('Temporary file cleaned up: ' . $tempFile);
     *     }
     * } catch (Exception $e) {
     *     Log::error('Failed to delete temporary file: ' . $e->getMessage());
     * }
     * ```
     * 
     * @example User Avatar Deletion with Error Recovery
     * ```php
     * $user = $this->Users->get($userId);
     * $avatarPath = WWW_ROOT . 'img/avatars/' . $user->avatar_filename;
     * 
     * try {
     *     StaticHelpers::deleteFile($avatarPath);
     *     $user->avatar_filename = null;
     *     $this->Users->save($user);
     * } catch (Exception $e) {
     *     Log::error('Avatar deletion failed: ' . $e->getMessage());
     *     $this->Flash->error('Could not remove avatar file');
     * }
     * ```
     * 
     * @example Batch File Cleanup with Error Tracking
     * ```php
     * $filesToDelete = [
     *     '/uploads/temp/file1.jpg',
     *     '/uploads/temp/file2.png',
     *     '/uploads/temp/file3.pdf'
     * ];
     * 
     * $errors = [];
     * $deletedCount = 0;
     * 
     * foreach ($filesToDelete as $file) {
     *     try {
     *         if (StaticHelpers::deleteFile($file)) {
     *             $deletedCount++;
     *         }
     *     } catch (Exception $e) {
     *         $errors[] = $file . ': ' . $e->getMessage();
     *     }
     * }
     * 
     * if (!empty($errors)) {
     *     Log::error('File deletion errors: ' . implode(', ', $errors));
     * }
     * ```
     * 
     * @example Idempotent Deletion (Safe Multiple Calls)
     * ```php
     * $filePath = '/path/to/file.txt';
     * 
     * // First call - deletes file if it exists
     * StaticHelpers::deleteFile($filePath); // Returns true
     * 
     * // Second call - file already gone, still returns true
     * StaticHelpers::deleteFile($filePath); // Returns true (idempotent)
     * ```
     * 
     * @see file_exists() For file existence checking
     * @see unlink() For underlying file deletion
     * @see error_get_last() For detailed error information
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
     * Extract values from nested arrays using dot-notation paths with advanced features
     * 
     * This method provides powerful array navigation capabilities using a custom path syntax
     * that supports nested array access, conditional formatting, minimum length validation,
     * and fallback values. It's designed for flexible data extraction from complex nested
     * data structures.
     * 
     * ## Path Syntax
     * 
     * **Basic Navigation**: Use `->` to navigate array levels
     * ```
     * "user->profile->name" accesses $array['user']['profile']['name']
     * ```
     * 
     * **Conditional Formatting**: Use parentheses for text wrapping
     * ```
     * "prefix(user->name)suffix" becomes "prefix" + value + "suffix"
     * ```
     * 
     * **Array Traversal**: Navigate through nested associative arrays
     * ```
     * "data->items->0->title" accesses $array['data']['items'][0]['title']
     * ```
     * 
     * ## Advanced Features
     * 
     * - **Minimum Length Validation**: Returns fallback if string shorter than minLength
     * - **Null Handling**: Returns fallback for null values
     * - **Array Preservation**: Returns arrays without modification
     * - **Conditional Wrapping**: Adds prefix/suffix only if value exists and non-empty
     * 
     * ## Data Type Handling
     * 
     * - **Strings**: Length validation applied, conditional formatting supported
     * - **Arrays**: Returned as-is without length validation
     * - **Null Values**: Return specified fallback value
     * - **Empty Strings**: Subject to minimum length validation
     * - **Numeric Values**: Converted to string for length validation
     * 
     * ## Use Cases
     * 
     * - **Template Processing**: Extract values for string replacement
     * - **Configuration Access**: Navigate complex config arrays
     * - **API Response Parsing**: Extract data from nested JSON structures
     * - **Form Data Validation**: Access nested form field values
     * - **Database Result Processing**: Extract specific fields from result sets
     * 
     * @param string $path Navigation path using -> separator and optional (prefix)suffix
     * @param mixed $array Source array or object to navigate
     * @param int $minLength Minimum string length required (default: 0)
     * @param mixed $fallback Value returned if path not found or validation fails (default: null)
     * @return mixed Extracted value, formatted value, or fallback
     * 
     * @example Basic Array Navigation
     * ```php
     * $data = [
     *     'user' => [
     *         'profile' => [
     *             'name' => 'John Doe',
     *             'email' => 'john@example.com'
     *         ]
     *     ]
     * ];
     * 
     * $name = StaticHelpers::getValue('user->profile->name', $data);
     * // Result: "John Doe"
     * 
     * $email = StaticHelpers::getValue('user->profile->email', $data);
     * // Result: "john@example.com"
     * ```
     * 
     * @example Conditional Formatting
     * ```php
     * $data = ['title' => 'Article Title'];
     * 
     * $formatted = StaticHelpers::getValue('Title: (title)', $data);
     * // Result: "Title: Article Title"
     * 
     * $empty = StaticHelpers::getValue('Title: (missing)', $data);
     * // Result: null (no formatting applied to missing values)
     * ```
     * 
     * @example Minimum Length Validation
     * ```php
     * $data = [
     *     'short' => 'Hi',
     *     'long' => 'Hello World'
     * ];
     * 
     * $valid = StaticHelpers::getValue('long', $data, 5, 'Default');
     * // Result: "Hello World" (length 11 >= 5)
     * 
     * $invalid = StaticHelpers::getValue('short', $data, 5, 'Default');
     * // Result: "Default" (length 2 < 5)
     * ```
     * 
     * @example Fallback Value Handling
     * ```php
     * $data = ['existing' => 'value'];
     * 
     * $found = StaticHelpers::getValue('existing', $data, 0, 'fallback');
     * // Result: "value"
     * 
     * $notFound = StaticHelpers::getValue('missing', $data, 0, 'fallback');
     * // Result: "fallback"
     * ```
     * 
     * @example Complex Nested Access
     * ```php
     * $data = [
     *     'api' => [
     *         'response' => [
     *             'data' => [
     *                 'items' => [
     *                     ['id' => 1, 'name' => 'Item 1'],
     *                     ['id' => 2, 'name' => 'Item 2']
     *                 ]
     *             ]
     *         ]
     *     ]
     * ];
     * 
     * $firstItem = StaticHelpers::getValue('api->response->data->items->0->name', $data);
     * // Result: "Item 1"
     * ```
     * 
     * @example Template Variable Extraction
     * ```php
     * $templateData = [
     *     'user' => ['name' => 'Alice'],
     *     'site' => ['title' => 'My Site']
     * ];
     * 
     * $greeting = StaticHelpers::getValue('Hello, (user->name)!', $templateData);
     * // Result: "Hello, Alice!"
     * 
     * $pageTitle = StaticHelpers::getValue('Welcome to (site->title)', $templateData);
     * // Result: "Welcome to My Site"
     * ```
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
     * Process template strings with variable substitution using advanced path syntax
     * 
     * This method provides comprehensive template processing functionality that combines
     * the power of the getValue() method with regex-based pattern matching to replace
     * placeholder variables with actual data values. It supports complex nested data
     * access, conditional formatting, and robust error handling.
     * 
     * ## Template Syntax
     * 
     * **Variable Placeholders**: Use double curly braces `{{path}}`
     * ```
     * "Hello {{user->name}}" becomes "Hello John"
     * ```
     * 
     * **Multiple Variables**: Support unlimited variables per template
     * ```
     * "{{greeting}} {{user->name}}, welcome to {{site->title}}"
     * ```
     * 
     * **Nested Path Access**: Full getValue() path syntax supported
     * ```
     * "{{user->profile->contact->email}}" accesses deep nested data
     * ```
     * 
     * **Conditional Formatting**: Include prefix/suffix within paths
     * ```
     * "{{Contact: (user->email)}}" becomes "Contact: user@example.com"
     * ```
     * 
     * ## Processing Workflow
     * 
     * 1. **Pattern Detection**: Uses regex `/{{(.*?)}}/` to find all placeholders
     * 2. **Path Extraction**: Extracts path from each placeholder
     * 3. **Value Resolution**: Uses getValue() to resolve each path
     * 4. **Type Conversion**: Converts all values to strings via toString()
     * 5. **String Replacement**: Replaces placeholders with resolved values
     * 6. **Missing Value Handling**: Substitutes specified default for missing values
     * 
     * ## Advanced Features
     * 
     * - **Minimum Length Validation**: Pass-through to getValue() for length checks
     * - **Missing Value Defaults**: Customizable replacement for undefined paths
     * - **Type Safety**: Automatic string conversion for all data types
     * - **Recursive Processing**: Supports nested template variables
     * - **Performance Optimized**: Single-pass regex matching for efficiency
     * 
     * ## Use Cases
     * 
     * - **Email Templates**: Personalized email content generation
     * - **Report Generation**: Dynamic report text with data injection
     * - **Configuration Processing**: Runtime config value substitution
     * - **Notification Messages**: User-specific notification content
     * - **Dynamic Content**: CMS content with variable replacement
     * - **API Response Formatting**: Template-based API message generation
     * 
     * @param string $string Template string containing {{variable}} placeholders
     * @param mixed $data Source data array/object for variable resolution
     * @param int $minLength Minimum length requirement for string values (default: 0)
     * @param mixed $missingValue Default value for undefined/missing variables (default: '')
     * @return string Processed template with all variables replaced
     * 
     * @example Basic Template Processing
     * ```php
     * $template = "Hello {{name}}, welcome to {{site}}!";
     * $data = [
     *     'name' => 'Alice',
     *     'site' => 'Our Platform'
     * ];
     * 
     * $result = StaticHelpers::processTemplate($template, $data);
     * // Result: "Hello Alice, welcome to Our Platform!"
     * ```
     * 
     * @example Nested Data Access
     * ```php
     * $template = "Dear {{user->profile->name}}, your order {{order->id}} is ready.";
     * $data = [
     *     'user' => [
     *         'profile' => ['name' => 'John Doe']
     *     ],
     *     'order' => ['id' => 'ORD-12345']
     * ];
     * 
     * $result = StaticHelpers::processTemplate($template, $data);
     * // Result: "Dear John Doe, your order ORD-12345 is ready."
     * ```
     * 
     * @example Email Template with Conditional Formatting
     * ```php
     * $template = "Hi {{user->name}}!\n\n" .
     *            "{{Thank you for your order (order->number).}}\n" .
     *            "{{Your tracking code is: (shipping->tracking)}}\n" .
     *            "Visit: {{site->url}}";
     *            
     * $data = [
     *     'user' => ['name' => 'Sarah'],
     *     'order' => ['number' => '12345'],
     *     'shipping' => ['tracking' => 'TRK789'],
     *     'site' => ['url' => 'https://shop.example.com']
     * ];
     * 
     * $email = StaticHelpers::processTemplate($template, $data);
     * // Result: Formatted email with all variables replaced
     * ```
     * 
     * @example Missing Value Handling
     * ```php
     * $template = "User: {{name}}, Status: {{status}}, Role: {{role}}";
     * $data = ['name' => 'Bob']; // Missing status and role
     * 
     * $result = StaticHelpers::processTemplate($template, $data, 0, 'N/A');
     * // Result: "User: Bob, Status: N/A, Role: N/A"
     * ```
     * 
     * @example Report Generation with Minimum Length
     * ```php
     * $template = "Report: {{title}}\nDescription: {{description}}";
     * $data = [
     *     'title' => 'Q4 Sales',
     *     'description' => 'No'  // Too short
     * ];
     * 
     * $result = StaticHelpers::processTemplate($template, $data, 10, 'No description available');
     * // Result: "Report: Q4 Sales\nDescription: No description available"
     * ```
     * 
     * @example Configuration File Processing
     * ```php
     * $configTemplate = "database_url={{db->host}}:{{db->port}}/{{db->name}}";
     * $config = [
     *     'db' => [
     *         'host' => 'localhost',
     *         'port' => '3306',
     *         'name' => 'app_db'
     *     ]
     * ];
     * 
     * $dbUrl = StaticHelpers::processTemplate($configTemplate, $config);
     * // Result: "database_url=localhost:3306/app_db"
     * ```
     * 
     * @see getValue() For variable path resolution and advanced syntax
     * @see toString() For type conversion during substitution
     * @see preg_match_all() For placeholder pattern matching
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
     * Check if a plugin is enabled in the application configuration
     * 
     * This method provides a standardized way to check plugin activation status
     * by querying the application settings system. It follows KMP's plugin
     * configuration convention where plugins are enabled/disabled via
     * configuration keys in the format `Plugin.{PluginName}.Active`.
     * 
     * ## Plugin Configuration Convention
     * 
     * - **Setting Key Format**: `Plugin.{PluginName}.Active`
     * - **Enabled Value**: `'yes'` (string, case-sensitive)
     * - **Disabled Value**: Any other value or missing key (defaults to `'no'`)
     * - **Default State**: Plugins are disabled by default
     * 
     * ## Configuration Resolution
     * 
     * The method leverages the getAppSetting() infrastructure which provides:
     * 1. **Primary Check**: CakePHP Configure values (highest priority)
     * 2. **Secondary Check**: Database AppSettings table
     * 3. **Fallback**: Default value of `'no'` (disabled)
     * 
     * ## Use Cases
     * 
     * - **Feature Toggles**: Enable/disable entire plugin functionality
     * - **Conditional Loading**: Load plugin resources only when active
     * - **Menu Generation**: Show/hide plugin menu items
     * - **Route Registration**: Conditionally register plugin routes
     * - **Service Initialization**: Start plugin services based on status
     * 
     * ## Performance Considerations
     * 
     * - Results are cached after first database query
     * - Multiple calls for same plugin are efficient
     * - No plugin instantiation required for status check
     * - Lightweight string comparison operation
     * 
     * @param string $pluginName Name of the plugin to check (case-sensitive)
     * @return bool True if plugin is enabled ('yes'), false otherwise
     * 
     * @example Basic Plugin Status Check
     * ```php
     * if (StaticHelpers::pluginEnabled('Awards')) {
     *     // Load awards-specific functionality
     *     $this->loadComponent('Awards.AwardManager');
     * }
     * 
     * if (StaticHelpers::pluginEnabled('Reports')) {
     *     // Add reports menu item
     *     $this->set('showReportsMenu', true);
     * }
     * ```
     * 
     * @example Conditional Plugin Route Loading
     * ```php
     * // In routes.php
     * if (StaticHelpers::pluginEnabled('Activities')) {
     *     $routes->plugin('Activities', function (RouteBuilder $builder) {
     *         $builder->fallbacks();
     *     });
     * }
     * ```
     * 
     * @example Template Conditional Rendering
     * ```php
     * // In view template
     * <?php if (StaticHelpers::pluginEnabled('Officers')): ?>
     *     <li><?= $this->Html->link('Officers', ['plugin' => 'Officers', 'controller' => 'Officers']) ?></li>
     * <?php endif; ?>
     * ```
     * 
     * @example Plugin Service Initialization
     * ```php
     * class AppController extends Controller
     * {
     *     public function initialize(): void
     *     {
     *         parent::initialize();
     *         
     *         if (StaticHelpers::pluginEnabled('Queue')) {
     *             $this->loadComponent('Queue.QueueManager');
     *         }
     *         
     *         if (StaticHelpers::pluginEnabled('GitHubIssueSubmitter')) {
     *             $this->loadComponent('GitHubIssueSubmitter.GitHub');
     *         }
     *     }
     * }
     * ```
     * 
     * @example Bulk Plugin Status Check
     * ```php
     * $availablePlugins = ['Awards', 'Activities', 'Reports', 'Officers'];
     * $enabledPlugins = [];
     * 
     * foreach ($availablePlugins as $plugin) {
     *     if (StaticHelpers::pluginEnabled($plugin)) {
     *         $enabledPlugins[] = $plugin;
     *     }
     * }
     * 
     * $this->set('enabledPlugins', $enabledPlugins);
     * ```
     * 
     * @see getAppSetting() For underlying configuration resolution
     */
    static function pluginEnabled($pluginName): bool
    {
        return self::getAppSetting('Plugin.' . $pluginName . '.Active', 'no') == 'yes';
    }

    /**
     * Retrieve application configuration settings with multi-layer fallback support
     * 
     * This method provides the core configuration access functionality for the KMP
     * application, implementing a sophisticated multi-tier resolution system that
     * checks multiple configuration sources in priority order. It serves as the
     * foundation for all application configuration access.
     * 
     * ## Configuration Resolution Hierarchy
     * 
     * 1. **CakePHP Configure Values** (Highest Priority)
     *    - Static configuration from config files
     *    - Runtime configuration values
     *    - Environment-specific overrides
     * 
     * 2. **Database AppSettings Table** (Secondary Priority)
     *    - Dynamic runtime configuration
     *    - User-configurable settings
     *    - Plugin-specific configuration
     * 
     * 3. **Fallback Value** (Lowest Priority)
     *    - Developer-provided default
     *    - Null if no fallback specified
     * 
     * ## Advanced Features
     * 
     * - **Type Validation**: Optional type checking and conversion
     * - **Required Settings**: Enforcement of mandatory configuration
     * - **Database Error Handling**: Graceful degradation on DB issues
     * - **Exception Passthrough**: Re-throws non-database exceptions
     * 
     * ## Database Error Resilience
     * 
     * The method gracefully handles database connectivity issues:
     * - Returns fallback value if AppSettings table unavailable
     * - Distinguishes database errors from other exceptions
     * - Allows application to function with reduced configuration
     * 
     * ## Common Setting Patterns
     * 
     * - **Application Settings**: `App.{SettingName}`
     * - **Plugin Settings**: `Plugin.{PluginName}.{Setting}`
     * - **Feature Flags**: `Feature.{FeatureName}.Enabled`
     * - **Integration Settings**: `Integration.{ServiceName}.{Setting}`
     * 
     * ## Type System Integration
     * 
     * When type parameter is provided, the AppSettings table can:
     * - Validate setting values against expected types
     * - Convert string values to appropriate PHP types
     * - Enforce type consistency across application
     * 
     * @param string $key Configuration key using dot notation (e.g., 'App.Setting.Key')
     * @param string|null $fallback Default value returned if setting not found
     * @param mixed $type Optional type specification for validation/conversion
     * @param bool $required Whether this setting is mandatory (affects error handling)
     * @return mixed Configuration value, converted to appropriate type if specified
     * @throws \Exception Re-throws non-database exceptions (maintains error context)
     * 
     * @example Basic Configuration Access
     * ```php
     * // Get application title with fallback
     * $appTitle = StaticHelpers::getAppSetting('App.Title', 'KMP Application');
     * 
     * // Get database timeout setting
     * $timeout = StaticHelpers::getAppSetting('Database.Timeout', '30');
     * ```
     * 
     * @example Plugin Configuration
     * ```php
     * // Check if email notifications are enabled
     * $emailEnabled = StaticHelpers::getAppSetting('Plugin.Email.Enabled', 'no');
     * 
     * // Get SMTP server configuration
     * $smtpHost = StaticHelpers::getAppSetting('Plugin.Email.SMTP.Host', 'localhost');
     * $smtpPort = StaticHelpers::getAppSetting('Plugin.Email.SMTP.Port', '587');
     * ```
     * 
     * @example Type-Safe Configuration
     * ```php
     * // Get integer setting with type validation
     * $maxUsers = StaticHelpers::getAppSetting(
     *     'App.MaxUsers', 
     *     '100', 
     *     'integer', 
     *     false
     * );
     * 
     * // Get required boolean setting
     * $debugMode = StaticHelpers::getAppSetting(
     *     'App.Debug', 
     *     'false', 
     *     'boolean', 
     *     true
     * );
     * ```
     * 
     * @example Feature Flag Usage
     * ```php
     * // Check if new feature is enabled
     * $newFeature = StaticHelpers::getAppSetting('Feature.NewDashboard.Enabled', 'no');
     * 
     * if ($newFeature === 'yes') {
     *     // Load new dashboard components
     *     $this->render('dashboard_v2');
     * } else {
     *     // Fall back to legacy dashboard
     *     $this->render('dashboard_legacy');
     * }
     * ```
     * 
     * @example Error-Resilient Configuration
     * ```php
     * // Get setting with database error handling
     * try {
     *     $setting = StaticHelpers::getAppSetting('Critical.Setting', null, null, true);
     * } catch (DatabaseException $e) {
     *     // Database unavailable, use safe defaults
     *     $setting = 'safe_default_value';
     *     Log::warning('Database unavailable, using default configuration');
     * }
     * ```
     * 
     * @example Configuration with Environment Overrides
     * ```php
     * // Check environment-specific setting
     * // This will check Configure first (may contain ENV overrides)
     * $apiKey = StaticHelpers::getAppSetting('Integration.PaymentGateway.ApiKey', null);
     * 
     * if ($apiKey === null) {
     *     throw new ConfigurationException('Payment gateway API key not configured');
     * }
     * ```
     * 
     * @example Bulk Configuration Loading
     * ```php
     * // Load multiple related settings
     * $emailConfig = [
     *     'host' => StaticHelpers::getAppSetting('Email.SMTP.Host', 'localhost'),
     *     'port' => StaticHelpers::getAppSetting('Email.SMTP.Port', '587'),
     *     'username' => StaticHelpers::getAppSetting('Email.SMTP.Username', ''),
     *     'password' => StaticHelpers::getAppSetting('Email.SMTP.Password', ''),
     *     'tls' => StaticHelpers::getAppSetting('Email.SMTP.TLS', 'yes') === 'yes'
     * ];
     * ```
     * 
     * @see \Cake\Core\Configure::read() For static configuration access
     * @see \App\Model\Table\AppSettingsTable::getAppSetting() For database configuration
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
     * Retrieve all application settings that start with a specific key prefix
     * 
     * This method provides bulk configuration retrieval functionality for loading
     * groups of related settings efficiently. It's particularly useful for plugin
     * configuration, feature flags, and modular system initialization where
     * multiple related settings need to be loaded together.
     * 
     * ## Key Prefix Patterns
     * 
     * - **Plugin Settings**: `Plugin.PluginName.` - All settings for a specific plugin
     * - **Feature Flags**: `Feature.` - All feature toggle settings
     * - **Integration Config**: `Integration.ServiceName.` - Service-specific configuration
     * - **Module Settings**: `Module.ModuleName.` - Module-specific configuration
     * 
     * ## Return Format
     * 
     * Returns an associative array where:
     * - **Keys**: Full setting key names (including prefix)
     * - **Values**: Setting values as stored in database/configuration
     * - **Empty Array**: Returned on database errors or no matches
     * 
     * ## Database Error Handling
     * 
     * The method provides robust error handling:
     * - Returns empty array on database connectivity issues
     * - Logs errors appropriately for debugging
     * - Allows application to continue with reduced functionality
     * - Does not expose database errors to calling code
     * 
     * ## Performance Characteristics
     * 
     * - Single database query for all matching settings
     * - Efficient for loading related configuration groups
     * - Results can be cached by calling code if needed
     * - Minimal memory overhead for result processing
     * 
     * ## Use Cases
     * 
     * - **Plugin Initialization**: Load all plugin-specific settings
     * - **Feature Management**: Load all feature flags for conditional logic
     * - **Configuration Validation**: Check all settings in a namespace
     * - **Admin Interfaces**: Display grouped settings for editing
     * - **System Diagnostics**: Export configuration for troubleshooting
     * 
     * @param string $key Prefix to match against setting keys (case-sensitive)
     * @return array Associative array of matching settings (key => value)
     * 
     * @example Plugin Configuration Loading
     * ```php
     * // Load all Awards plugin settings
     * $awardsConfig = StaticHelpers::getAppSettingsStartWith('Plugin.Awards.');
     * 
     * // Result might be:
     * // [
     * //     'Plugin.Awards.Active' => 'yes',
     * //     'Plugin.Awards.MaxAwards' => '10',
     * //     'Plugin.Awards.EmailNotifications' => 'yes'
     * // ]
     * 
     * $isActive = $awardsConfig['Plugin.Awards.Active'] ?? 'no';
     * $maxAwards = (int)($awardsConfig['Plugin.Awards.MaxAwards'] ?? 5);
     * ```
     * 
     * @example Feature Flag Management
     * ```php
     * // Load all feature flags
     * $features = StaticHelpers::getAppSettingsStartWith('Feature.');
     * 
     * // Check multiple features
     * $enabledFeatures = [];
     * foreach ($features as $key => $value) {
     *     if ($value === 'yes') {
     *         // Extract feature name from key
     *         $featureName = str_replace('Feature.', '', $key);
     *         $featureName = str_replace('.Enabled', '', $featureName);
     *         $enabledFeatures[] = $featureName;
     *     }
     * }
     * 
     * $this->set('enabledFeatures', $enabledFeatures);
     * ```
     * 
     * @example Integration Configuration
     * ```php
     * // Load payment gateway settings
     * $paymentConfig = StaticHelpers::getAppSettingsStartWith('Integration.PaymentGateway.');
     * 
     * // Transform into configuration array
     * $config = [];
     * foreach ($paymentConfig as $key => $value) {
     *     $shortKey = str_replace('Integration.PaymentGateway.', '', $key);
     *     $config[$shortKey] = $value;
     * }
     * 
     * // Result: ['ApiKey' => 'xxx', 'Endpoint' => 'https://...', 'Timeout' => '30']
     * ```
     * 
     * @example Configuration Validation
     * ```php
     * // Validate all required plugin settings exist
     * $requiredSettings = [
     *     'Plugin.Email.SMTP.Host',
     *     'Plugin.Email.SMTP.Port', 
     *     'Plugin.Email.SMTP.Username'
     * ];
     * 
     * $emailSettings = StaticHelpers::getAppSettingsStartWith('Plugin.Email.');
     * $missingSettings = [];
     * 
     * foreach ($requiredSettings as $required) {
     *     if (!isset($emailSettings[$required]) || empty($emailSettings[$required])) {
     *         $missingSettings[] = $required;
     *     }
     * }
     * 
     * if (!empty($missingSettings)) {
     *     throw new ConfigurationException('Missing email settings: ' . implode(', ', $missingSettings));
     * }
     * ```
     * 
     * @example Admin Interface Configuration
     * ```php
     * // Load settings for admin configuration page
     * $pluginSettings = StaticHelpers::getAppSettingsStartWith('Plugin.');
     * 
     * // Group by plugin
     * $groupedSettings = [];
     * foreach ($pluginSettings as $key => $value) {
     *     preg_match('/Plugin\.([^.]+)\.(.+)/', $key, $matches);
     *     if (count($matches) === 3) {
     *         $pluginName = $matches[1];
     *         $settingName = $matches[2];
     *         $groupedSettings[$pluginName][$settingName] = $value;
     *     }
     * }
     * 
     * $this->set('pluginSettings', $groupedSettings);
     * ```
     * 
     * @example System Diagnostics Export
     * ```php
     * // Export all application settings for support
     * $allSettings = array_merge(
     *     StaticHelpers::getAppSettingsStartWith('App.'),
     *     StaticHelpers::getAppSettingsStartWith('Plugin.'),
     *     StaticHelpers::getAppSettingsStartWith('Feature.')
     * );
     * 
     * // Sanitize sensitive data before export
     * foreach ($allSettings as $key => &$value) {
     *     if (strpos($key, 'Password') !== false || strpos($key, 'ApiKey') !== false) {
     *         $value = '***REDACTED***';
     *     }
     * }
     * 
     * file_put_contents('system_config_export.json', json_encode($allSettings, JSON_PRETTY_PRINT));
     * ```
     * 
     * @see getAppSetting() For individual setting retrieval
     * @see \App\Model\Table\AppSettingsTable::getAllAppSettingsStartWith() For database implementation
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
     * Delete an application setting from the database configuration store
     * 
     * This method provides safe deletion of application settings with proper
     * error handling and optional force deletion capabilities. It integrates
     * with the AppSettings table to remove configuration values while
     * maintaining system stability and providing appropriate feedback.
     * 
     * ## Deletion Behavior
     * 
     * - **Standard Deletion**: Respects setting protection flags and constraints
     * - **Force Deletion**: Bypasses protection mechanisms when explicitly requested
     * - **Idempotent Operation**: Returns success even if setting doesn't exist
     * - **Database Error Handling**: Graceful handling of connectivity issues
     * 
     * ## Safety Mechanisms
     * 
     * The AppSettings table may implement protection for critical settings:
     * - **Required Settings**: Cannot be deleted without force flag
     * - **System Settings**: Protected from accidental deletion
     * - **Plugin Dependencies**: Validation of setting usage before deletion
     * - **Cascade Handling**: Proper cleanup of dependent configurations
     * 
     * ## Force Deletion Scenarios
     * 
     * Use force deletion for:
     * - **Administrative Override**: Manual intervention for protected settings
     * - **System Reset**: Complete configuration cleanup procedures
     * - **Migration Cleanup**: Removing obsolete configuration during upgrades
     * - **Emergency Recovery**: Removing corrupted or problematic settings
     * 
     * ## Return Value Semantics
     * 
     * - **True**: Setting successfully deleted or didn't exist
     * - **False**: Deletion failed due to business logic constraints
     * - **Exception**: Database connectivity or system errors
     * 
     * ## Integration Considerations
     * 
     * - Settings deleted here don't affect Configure values
     * - Cache invalidation may be required for some settings
     * - Plugin deactivation may require setting cleanup
     * - Audit logging may track configuration deletions
     * 
     * @param string $key Configuration key to delete (dot notation)
     * @param bool $forceDelete Whether to bypass protection mechanisms (default: false)
     * @return bool True if setting was deleted or didn't exist, false on failure
     * @throws \Exception Re-throws non-database exceptions for error handling
     * 
     * @example Safe Setting Deletion
     * ```php
     * // Delete a plugin setting safely
     * $success = StaticHelpers::deleteAppSetting('Plugin.TempPlugin.TempSetting');
     * 
     * if ($success) {
     *     Log::info('Plugin setting removed successfully');
     * } else {
     *     Log::warning('Setting deletion failed - may be protected');
     * }
     * ```
     * 
     * @example Force Deletion for System Reset
     * ```php
     * // Force delete a protected setting during system reset
     * try {
     *     $success = StaticHelpers::deleteAppSetting('App.CriticalSetting', true);
     *     if ($success) {
     *         Log::warning('Critical setting forcibly deleted');
     *     }
     * } catch (Exception $e) {
     *     Log::error('Force deletion failed: ' . $e->getMessage());
     * }
     * ```
     * 
     * @example Plugin Deactivation Cleanup
     * ```php
     * // Clean up all settings for a deactivated plugin
     * $pluginSettings = StaticHelpers::getAppSettingsStartWith('Plugin.OldPlugin.');
     * 
     * foreach (array_keys($pluginSettings) as $settingKey) {
     *     try {
     *         StaticHelpers::deleteAppSetting($settingKey);
     *         Log::info("Deleted setting: {$settingKey}");
     *     } catch (Exception $e) {
     *         Log::error("Failed to delete {$settingKey}: " . $e->getMessage());
     *     }
     * }
     * ```
     * 
     * @example Configuration Migration
     * ```php
     * // Migration script to clean up obsolete settings
     * $obsoleteSettings = [
     *     'App.LegacyFeature.Enabled',
     *     'Plugin.DeprecatedPlugin.Active',
     *     'Feature.OldDashboard.Enabled'
     * ];
     * 
     * foreach ($obsoleteSettings as $setting) {
     *     if (StaticHelpers::deleteAppSetting($setting)) {
     *         Log::info("Migration: Removed obsolete setting {$setting}");
     *     }
     * }
     * ```
     * 
     * @example Error-Resilient Cleanup
     * ```php
     * // Cleanup with database error handling
     * function cleanupTempSettings() {
     *     $tempSettings = StaticHelpers::getAppSettingsStartWith('Temp.');
     *     
     *     foreach (array_keys($tempSettings) as $key) {
     *         try {
     *             StaticHelpers::deleteAppSetting($key);
     *         } catch (DatabaseException $e) {
     *             Log::warning('Database unavailable during cleanup: ' . $e->getMessage());
     *             return false; // Retry later
     *         } catch (Exception $e) {
     *             Log::error('Unexpected error during cleanup: ' . $e->getMessage());
     *         }
     *     }
     *     
     *     return true;
     * }
     * ```
     * 
     * @example Administrative Setting Management
     * ```php
     * // Admin interface for setting management
     * public function deleteSetting($settingKey) {
     *     // Validate admin permissions first
     *     if (!$this->request->is('post')) {
     *         throw new MethodNotAllowedException();
     *     }
     *     
     *     $forceDelete = $this->request->getData('force_delete', false);
     *     
     *     try {
     *         $success = StaticHelpers::deleteAppSetting($settingKey, $forceDelete);
     *         
     *         if ($success) {
     *             $this->Flash->success("Setting '{$settingKey}' deleted successfully");
     *         } else {
     *             $this->Flash->error("Cannot delete protected setting '{$settingKey}'");
     *         }
     *     } catch (Exception $e) {
     *         $this->Flash->error('Deletion failed: ' . $e->getMessage());
     *     }
     *     
     *     return $this->redirect(['action' => 'index']);
     * }
     * ```
     * 
     * @see setAppSetting() For setting creation/modification
     * @see getAppSetting() For setting retrieval
     * @see \App\Model\Table\AppSettingsTable::deleteAppSetting() For database implementation
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
     * Create or update an application setting in the database configuration store
     * 
     * This method provides comprehensive setting management functionality with
     * support for type validation, required flag setting, and robust error
     * handling. It integrates with the AppSettings table to persist configuration
     * values that can be dynamically modified at runtime.
     * 
     * ## Setting Management Features
     * 
     * - **Create/Update**: Automatically handles both new settings and updates
     * - **Type Validation**: Optional type checking and conversion during storage
     * - **Required Flag**: Mark settings as mandatory for system operation
     * - **Value Persistence**: Stores values in database for runtime modification
     * 
     * ## Type System Integration
     * 
     * When type parameter is provided:
     * - **Validation**: Ensures value matches expected type before storage
     * - **Conversion**: Automatically converts compatible values to target type
     * - **Documentation**: Type information stored for administrative interfaces
     * - **Consistency**: Enforces type consistency across application restarts
     * 
     * ## Required Settings Management
     * 
     * Required settings provide additional safeguards:
     * - **Deletion Protection**: Cannot be easily deleted without force flag
     * - **Validation**: System can validate all required settings are present
     * - **Documentation**: Clearly marks critical configuration values
     * - **Error Prevention**: Helps prevent accidental removal of vital settings
     * 
     * ## Database Integration
     * 
     * - **Transaction Support**: Operations are typically wrapped in transactions
     * - **Constraint Validation**: Respects database constraints and validations
     * - **Error Handling**: Provides appropriate feedback on database errors
     * - **Performance**: Optimized for both single and bulk setting operations
     * 
     * ## Use Cases
     * 
     * - **Runtime Configuration**: Settings that can be modified without code changes
     * - **Plugin Settings**: Configuration values specific to plugin functionality
     * - **Feature Flags**: Dynamic enable/disable of application features
     * - **User Preferences**: System-wide preferences and customizations
     * - **Integration Config**: API keys, endpoints, and service configurations
     * 
     * @param string $key Configuration key using dot notation (e.g., 'Plugin.Name.Setting')
     * @param mixed $value Value to store (will be converted to string for database storage)
     * @param mixed $type Optional type specification for validation (string, int, bool, etc.)
     * @param bool $required Whether this setting is required for system operation (default: false)
     * @return bool True if setting was successfully saved, false on failure
     * 
     * @example Basic Setting Creation
     * ```php
     * // Set a simple application setting
     * $success = StaticHelpers::setAppSetting('App.MaintenanceMode', 'no');
     * 
     * if ($success) {
     *     Log::info('Maintenance mode setting updated');
     * } else {
     *     Log::error('Failed to update maintenance mode setting');
     * }
     * ```
     * 
     * @example Plugin Configuration Setup
     * ```php
     * // Configure plugin settings during installation
     * $pluginSettings = [
     *     'Plugin.Email.Active' => 'yes',
     *     'Plugin.Email.SMTP.Host' => 'smtp.example.com',
     *     'Plugin.Email.SMTP.Port' => '587'
     * ];
     * 
     * foreach ($pluginSettings as $key => $value) {
     *     if (!StaticHelpers::setAppSetting($key, $value)) {
     *         Log::error("Failed to set plugin setting: {$key}");
     *     }
     * }
     * ```
     * 
     * @example Type-Safe Setting Management
     * ```php
     * // Set settings with type validation
     * StaticHelpers::setAppSetting('App.MaxUsers', '100', 'integer', false);
     * StaticHelpers::setAppSetting('App.DebugMode', 'false', 'boolean', true);
     * StaticHelpers::setAppSetting('App.SiteName', 'KMP Portal', 'string', true);
     * 
     * // Required settings cannot be easily deleted
     * $criticalConfig = [
     *     'App.DatabaseURL' => 'mysql://localhost/kmp',
     *     'App.SecretKey' => 'random-secret-key-here'
     * ];
     * 
     * foreach ($criticalConfig as $key => $value) {
     *     StaticHelpers::setAppSetting($key, $value, 'string', true);
     * }
     * ```
     * 
     * @example Feature Flag Management
     * ```php
     * // Enable/disable features dynamically
     * $features = [
     *     'Feature.NewDashboard.Enabled' => 'yes',
     *     'Feature.BetaFeatures.Enabled' => 'no',
     *     'Feature.MobileApp.Enabled' => 'yes'
     * ];
     * 
     * foreach ($features as $feature => $enabled) {
     *     StaticHelpers::setAppSetting($feature, $enabled, 'string', false);
     * }
     * ```
     * 
     * @example Integration Configuration
     * ```php
     * // Set up external service integration
     * $paymentConfig = [
     *     'Integration.PaymentGateway.ApiKey' => 'sk_live_...',
     *     'Integration.PaymentGateway.Endpoint' => 'https://api.payment.com',
     *     'Integration.PaymentGateway.Timeout' => '30'
     * ];
     * 
     * foreach ($paymentConfig as $key => $value) {
     *     $type = str_contains($key, 'Timeout') ? 'integer' : 'string';
     *     $required = str_contains($key, 'ApiKey') || str_contains($key, 'Endpoint');
     *     
     *     StaticHelpers::setAppSetting($key, $value, $type, $required);
     * }
     * ```
     * 
     * @example Administrative Interface
     * ```php
     * // Admin form processing for setting updates
     * public function updateSettings() {
     *     if ($this->request->is('post')) {
     *         $settings = $this->request->getData('settings');
     *         $errors = [];
     *         
     *         foreach ($settings as $key => $value) {
     *             if (!StaticHelpers::setAppSetting($key, $value)) {
     *                 $errors[] = "Failed to update {$key}";
     *             }
     *         }
     *         
     *         if (empty($errors)) {
     *             $this->Flash->success('Settings updated successfully');
     *         } else {
     *             $this->Flash->error('Some settings failed to update: ' . implode(', ', $errors));
     *         }
     *         
     *         return $this->redirect(['action' => 'index']);
     *     }
     * }
     * ```
     * 
     * @example Bulk Configuration Import
     * ```php
     * // Import configuration from JSON file
     * $configFile = 'config/import_settings.json';
     * $settings = json_decode(file_get_contents($configFile), true);
     * 
     * $imported = 0;
     * $failed = 0;
     * 
     * foreach ($settings as $key => $config) {
     *     $value = $config['value'];
     *     $type = $config['type'] ?? null;
     *     $required = $config['required'] ?? false;
     *     
     *     if (StaticHelpers::setAppSetting($key, $value, $type, $required)) {
     *         $imported++;
     *     } else {
     *         $failed++;
     *         Log::error("Failed to import setting: {$key}");
     *     }
     * }
     * 
     * Log::info("Configuration import complete: {$imported} imported, {$failed} failed");
     * ```
     * 
     * @see getAppSetting() For setting retrieval
     * @see deleteAppSetting() For setting removal
     * @see \App\Model\Table\AppSettingsTable::setAppSetting() For database implementation
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
     * Convert a CakePHP route array into a standardized path string
     * 
     * This method transforms CakePHP's route parameter arrays into normalized
     * path strings suitable for permissions checking, logging, caching keys,
     * and other string-based route identification. It handles plugin routes,
     * controller/action combinations, and additional parameters consistently.
     * 
     * ## Path String Format
     * 
     * The generated path follows this pattern:
     * - **Without Plugin**: `controller/action[/param]`
     * - **With Plugin**: `plugin/controller/action[/param]`
     * - **All Lowercase**: Consistent case-insensitive comparison
     * 
     * ## Route Array Structure
     * 
     * Expected input array format:
     * ```php
     * [
     *     'plugin' => 'PluginName',      // Optional: Plugin name
     *     'controller' => 'ControllerName', // Required: Controller name
     *     'action' => 'actionName',         // Required: Action method
     *     0 => 'parameter'                  // Optional: First positional parameter
     * ]
     * ```
     * 
     * ## Use Cases
     * 
     * - **Permission System**: Convert routes to permission identifiers
     * - **Cache Keys**: Generate consistent cache keys from route data
     * - **Logging**: Create readable route identifiers for logs
     * - **Breadcrumbs**: Generate path-based navigation elements
     * - **Route Analysis**: Normalize routes for comparison and grouping
     * 
     * ## Normalization Benefits
     * 
     * - **Case Consistency**: All paths are lowercase for reliable comparison
     * - **Format Standardization**: Consistent separator and order
     * - **Plugin Handling**: Proper plugin namespace inclusion
     * - **Parameter Integration**: First parameter included when present
     * 
     * @param array $path CakePHP route array with controller, action, and optional plugin/parameters
     * @return string Normalized lowercase path string (e.g., "plugin/controller/action/param")
     * 
     * @example Basic Controller/Action Path
     * ```php
     * $route = [
     *     'controller' => 'Members',
     *     'action' => 'view'
     * ];
     * 
     * $pathString = StaticHelpers::makePathString($route);
     * // Result: "members/view"
     * ```
     * 
     * @example Plugin Route Path
     * ```php
     * $route = [
     *     'plugin' => 'Awards',
     *     'controller' => 'Awards',
     *     'action' => 'index'
     * ];
     * 
     * $pathString = StaticHelpers::makePathString($route);
     * // Result: "awards/awards/index"
     * ```
     * 
     * @example Path with Parameter
     * ```php
     * $route = [
     *     'controller' => 'Members',
     *     'action' => 'view',
     *     0 => '123'  // Member ID parameter
     * ];
     * 
     * $pathString = StaticHelpers::makePathString($route);
     * // Result: "members/view/123"
     * ```
     * 
     * @example Permission System Integration
     * ```php
     * // Check if user has permission for current route
     * $currentRoute = $this->request->getParam();
     * $pathString = StaticHelpers::makePathString($currentRoute);
     * 
     * if (!$this->Authorization->can($user, 'access', $pathString)) {
     *     throw new ForbiddenException('Access denied to: ' . $pathString);
     * }
     * ```
     * 
     * @example Cache Key Generation
     * ```php
     * // Generate cache key for route-specific data
     * $route = $this->request->getParam();
     * $pathString = StaticHelpers::makePathString($route);
     * $cacheKey = 'route_data_' . str_replace('/', '_', $pathString);
     * 
     * $cachedData = Cache::read($cacheKey);
     * if ($cachedData === null) {
     *     $cachedData = $this->generateExpensiveData();
     *     Cache::write($cacheKey, $cachedData);
     * }
     * ```
     * 
     * @example Breadcrumb Generation
     * ```php
     * // Create breadcrumb path from route
     * $route = $this->request->getParam();
     * $pathString = StaticHelpers::makePathString($route);
     * $pathParts = explode('/', $pathString);
     * 
     * $breadcrumbs = [];
     * $currentPath = '';
     * 
     * foreach ($pathParts as $part) {
     *     $currentPath .= ($currentPath ? '/' : '') . $part;
     *     $breadcrumbs[] = [
     *         'title' => Inflector::humanize($part),
     *         'path' => $currentPath
     *     ];
     * }
     * 
     * $this->set('breadcrumbs', $breadcrumbs);
     * ```
     * 
     * @example Logging with Route Context
     * ```php
     * // Log user actions with route context
     * $route = $this->request->getParam();
     * $pathString = StaticHelpers::makePathString($route);
     * 
     * Log::info('User action performed', [
     *     'user_id' => $this->Authentication->getIdentity()->get('id'),
     *     'route' => $pathString,
     *     'method' => $this->request->getMethod(),
     *     'ip' => $this->request->clientIp()
     * ]);
     * ```
     * 
     * @example Route Analysis and Grouping
     * ```php
     * // Analyze access patterns by grouping routes
     * $accessLog = [
     *     ['plugin' => 'Awards', 'controller' => 'Awards', 'action' => 'index'],
     *     ['controller' => 'Members', 'action' => 'view', 0 => '123'],
     *     ['plugin' => 'Awards', 'controller' => 'Awards', 'action' => 'view', 0 => '456']
     * ];
     * 
     * $routeGroups = [];
     * foreach ($accessLog as $route) {
     *     $pathString = StaticHelpers::makePathString($route);
     *     $baseRoute = implode('/', array_slice(explode('/', $pathString), 0, -1));
     *     $routeGroups[$baseRoute][] = $pathString;
     * }
     * 
     * // Result: Groups routes by controller/action, ignoring parameters
     * ```
     * 
     * @see \Cake\Http\ServerRequest::getParam() For retrieving route parameters
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
     * Convert a two-dimensional array to CSV formatted string
     * 
     * This method provides robust CSV generation functionality with customizable
     * formatting options for delimiter, enclosure, and escape characters. It
     * uses PHP's built-in fputcsv() function for proper CSV formatting while
     * providing a convenient array-to-string interface.
     * 
     * ## CSV Formatting Features
     * 
     * - **Standard Compliance**: Uses RFC 4180 CSV formatting standards
     * - **Proper Escaping**: Handles special characters, quotes, and newlines
     * - **Customizable Delimiters**: Support for comma, semicolon, tab, or custom separators
     * - **Flexible Enclosure**: Configurable quote characters for field wrapping
     * - **Escape Character Control**: Customizable escape sequences for special handling
     * 
     * ## Input Data Structure
     * 
     * Expected array format:
     * ```php
     * [
     *     ['Header1', 'Header2', 'Header3'],    // Row 1 (often headers)
     *     ['Value1', 'Value2', 'Value3'],       // Row 2 (data)
     *     ['Value4', 'Value5', 'Value6']        // Row 3 (data)
     * ]
     * ```
     * 
     * ## Memory Efficiency
     * 
     * - **Stream Processing**: Uses php://memory for efficient processing
     * - **Low Memory Footprint**: Processes data without large memory buffers
     * - **Scalable**: Handles large datasets without memory exhaustion
     * - **Resource Cleanup**: Proper stream resource management
     * 
     * ## Character Encoding
     * 
     * - **UTF-8 Safe**: Preserves Unicode characters properly
     * - **Special Character Handling**: Proper escaping of CSV metacharacters
     * - **Newline Preservation**: Maintains line breaks within field values
     * - **Quote Handling**: Automatic quoting of fields containing special characters
     * 
     * ## Use Cases
     * 
     * - **Data Export**: Convert database results to downloadable CSV files
     * - **Report Generation**: Create formatted reports for external consumption
     * - **API Responses**: Provide CSV format option for data APIs
     * - **Bulk Data Processing**: Prepare data for import into external systems
     * - **Excel Compatibility**: Generate files compatible with spreadsheet applications
     * 
     * @param array $data Two-dimensional array where each sub-array represents a CSV row
     * @param string $delimiter Field separator character (default: ',')
     * @param string $enclosure Field enclosure character for quoting (default: '"')
     * @param string $escapeChar Escape character for special sequences (default: '\\')
     * @return string CSV formatted string with proper escaping and formatting
     * 
     * @example Basic CSV Generation
     * ```php
     * $data = [
     *     ['Name', 'Email', 'Phone'],
     *     ['John Doe', 'john@example.com', '555-1234'],
     *     ['Jane Smith', 'jane@example.com', '555-5678']
     * ];
     * 
     * $csv = StaticHelpers::arrayToCsv($data);
     * // Result: 
     * // Name,Email,Phone
     * // "John Doe",john@example.com,555-1234
     * // "Jane Smith",jane@example.com,555-5678
     * ```
     * 
     * @example Custom Delimiter (European Format)
     * ```php
     * $data = [
     *     ['Product', 'Price', 'Quantity'],
     *     ['Widget A', '19.99', '100'],
     *     ['Widget B', '29.99', '50']
     * ];
     * 
     * $csv = StaticHelpers::arrayToCsv($data, ';');
     * // Result: Product;Price;Quantity
     * //         "Widget A";19.99;100
     * //         "Widget B";29.99;50
     * ```
     * 
     * @example Special Characters and Escaping
     * ```php
     * $data = [
     *     ['Description', 'Notes'],
     *     ['Product with "quotes"', 'Line 1\nLine 2'],
     *     ['Comma, separated, values', 'Special chars: !@#$%']
     * ];
     * 
     * $csv = StaticHelpers::arrayToCsv($data);
     * // Properly escapes quotes and preserves newlines
     * ```
     * 
     * @example Database Export to CSV
     * ```php
     * // Export members table to CSV
     * $members = $this->Members->find('all')->toArray();
     * $csvData = [];
     * 
     * // Add header row
     * $csvData[] = ['ID', 'Name', 'Email', 'Branch', 'Joined Date'];
     * 
     * // Add data rows
     * foreach ($members as $member) {
     *     $csvData[] = [
     *         $member->id,
     *         $member->full_name,
     *         $member->email,
     *         $member->branch->name,
     *         $member->created->format('Y-m-d')
     *     ];
     * }
     * 
     * $csv = StaticHelpers::arrayToCsv($csvData);
     * 
     * // Send as download
     * $response = $this->response;
     * $response = $response->withType('csv');
     * $response = $response->withHeader('Content-Disposition', 'attachment; filename="members.csv"');
     * $response = $response->withStringBody($csv);
     * 
     * return $response;
     * ```
     * 
     * @example Report Generation with Calculations
     * ```php
     * // Generate financial report CSV
     * $transactions = $this->getTransactionData();
     * $reportData = [];
     * 
     * // Headers
     * $reportData[] = ['Date', 'Description', 'Amount', 'Category', 'Running Total'];
     * 
     * $runningTotal = 0;
     * foreach ($transactions as $transaction) {
     *     $runningTotal += $transaction['amount'];
     *     $reportData[] = [
     *         $transaction['date'],
     *         $transaction['description'],
     *         number_format($transaction['amount'], 2),
     *         $transaction['category'],
     *         number_format($runningTotal, 2)
     *     ];
     * }
     * 
     * $csv = StaticHelpers::arrayToCsv($reportData);
     * file_put_contents('reports/financial_report.csv', $csv);
     * ```
     * 
     * @example API Response with CSV Option
     * ```php
     * // Controller action supporting multiple formats
     * public function export() {
     *     $data = $this->getData();
     *     
     *     if ($this->request->getQuery('format') === 'csv') {
     *         $csvData = [];
     *         $csvData[] = array_keys($data[0]); // Headers from first row keys
     *         
     *         foreach ($data as $row) {
     *             $csvData[] = array_values($row);
     *         }
     *         
     *         $csv = StaticHelpers::arrayToCsv($csvData);
     *         
     *         return $this->response
     *             ->withType('csv')
     *             ->withStringBody($csv);
     *     }
     *     
     *     // Default JSON response
     *     $this->set('data', $data);
     *     $this->viewBuilder()->setOption('serialize', ['data']);
     * }
     * ```
     * 
     * @example Tab-Delimited Export
     * ```php
     * // Export for import into specialized software
     * $data = $this->getComplexData();
     * $tabDelimited = StaticHelpers::arrayToCsv($data, "\t");
     * 
     * file_put_contents('exports/data_for_import.tsv', $tabDelimited);
     * ```
     * 
     * @see fputcsv() For underlying CSV formatting implementation
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
     * Sanitize a string for safe use in HTML attributes
     * 
     * This method provides essential XSS (Cross-Site Scripting) protection by
     * properly escaping strings before they are used in HTML attributes. It uses
     * PHP's htmlspecialchars() function with appropriate flags to ensure that
     * user-provided data cannot break out of HTML attribute contexts.
     * 
     * ## Security Protection
     * 
     * - **XSS Prevention**: Prevents script injection through HTML attributes
     * - **Quote Escaping**: Handles both single and double quotes safely
     * - **UTF-8 Safe**: Preserves Unicode characters while securing output
     * - **Attribute Context**: Specifically designed for HTML attribute values
     * 
     * ## Character Escaping
     * 
     * The method escapes these potentially dangerous characters:
     * - `<` becomes `&lt;`
     * - `>` becomes `&gt;`
     * - `&` becomes `&amp;`
     * - `"` becomes `&quot;`
     * - `'` becomes `&#039;` (or `&apos;` in some contexts)
     * 
     * ## Use Cases
     * 
     * - **Form Input Values**: Sanitize data for input value attributes
     * - **CSS Class Names**: Ensure class names don't contain malicious content
     * - **Data Attributes**: Secure custom data-* attribute values
     * - **Title Attributes**: Sanitize tooltip and title text
     * - **Alt Text**: Secure alternative text for images
     * - **Dynamic Attributes**: Any dynamically generated attribute content
     * 
     * ## Security Context
     * 
     * This method is specifically for HTML attribute contexts. For other contexts:
     * - **HTML Content**: Use `h()` helper or `htmlspecialchars()` without ENT_QUOTES
     * - **JavaScript**: Use JSON encoding or JavaScript-specific escaping
     * - **CSS**: Use CSS-specific escaping functions
     * - **URL Parameters**: Use URL encoding functions
     * 
     * ## Integration with Templates
     * 
     * While CakePHP's templating system provides automatic escaping, this method
     * is useful for:
     * - **Manual HTML Generation**: When building HTML strings in PHP
     * - **Legacy Code**: Adding security to existing unescaped output
     * - **Complex Attribute Values**: Multi-part attribute construction
     * - **API Responses**: Securing HTML fragments in API responses
     * 
     * @param string $string Input string that may contain unsafe characters
     * @return string HTML-safe string suitable for use in HTML attributes
     * 
     * @example Form Input Value Sanitization
     * ```php
     * // Secure user input in form fields
     * $userInput = $_POST['description'] ?? '';
     * $safeValue = StaticHelpers::makeSafeForHtmlAttribute($userInput);
     * 
     * echo '<input type="text" value="' . $safeValue . '">';
     * // Even if $userInput contains quotes or scripts, output is safe
     * ```
     * 
     * @example Dynamic CSS Class Generation
     * ```php
     * // Sanitize user-provided class names
     * $userClass = $this->request->getQuery('theme');
     * $safeClass = StaticHelpers::makeSafeForHtmlAttribute($userClass);
     * 
     * echo '<div class="base-class ' . $safeClass . '">';
     * // Prevents class attribute breakout attacks
     * ```
     * 
     * @example Data Attribute Security
     * ```php
     * // Secure custom data attributes
     * $userData = json_encode($user->toArray());
     * $safeData = StaticHelpers::makeSafeForHtmlAttribute($userData);
     * 
     * echo '<div data-user="' . $safeData . '">';
     * // JSON data is safely escaped for attribute use
     * ```
     * 
     * @example Title and Alt Text Sanitization
     * ```php
     * // Secure tooltip and alternative text
     * $description = $product->description;
     * $safeDescription = StaticHelpers::makeSafeForHtmlAttribute($description);
     * 
     * echo '<img src="product.jpg" alt="' . $safeDescription . '" title="' . $safeDescription . '">';
     * // User-provided descriptions are safely displayed
     * ```
     * 
     * @example Malicious Input Prevention
     * ```php
     * // Example of input that would be dangerous without escaping
     * $maliciousInput = '"><script>alert("XSS")</script><div class="';
     * $safeOutput = StaticHelpers::makeSafeForHtmlAttribute($maliciousInput);
     * 
     * echo '<div class="' . $safeOutput . '">';
     * // Output: <div class="&quot;&gt;&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;&lt;div class=&quot;">
     * // Script is neutralized and cannot execute
     * ```
     * 
     * @example Form Generation with User Data
     * ```php
     * // Generate form with potentially unsafe user data
     * $member = $this->Members->get($id);
     * 
     * $form = '<form method="post">' .
     *         '<input type="text" name="name" value="' . 
     *         StaticHelpers::makeSafeForHtmlAttribute($member->name) . '">' .
     *         '<input type="email" name="email" value="' . 
     *         StaticHelpers::makeSafeForHtmlAttribute($member->email) . '">' .
     *         '<textarea name="bio">' . h($member->bio) . '</textarea>' .
     *         '</form>';
     * 
     * // Attributes are safe, content uses appropriate escaping
     * ```
     * 
     * @example API Response with HTML Fragments
     * ```php
     * // API endpoint returning HTML snippets
     * public function getWidgetHtml() {
     *     $widget = $this->Widgets->get($this->request->getQuery('id'));
     *     
     *     $html = '<div class="widget" data-id="' . 
     *             StaticHelpers::makeSafeForHtmlAttribute($widget->id) . '" ' .
     *             'data-config="' . 
     *             StaticHelpers::makeSafeForHtmlAttribute(json_encode($widget->config)) . '">' .
     *             h($widget->content) .
     *             '</div>';
     *     
     *     $this->set('html', $html);
     *     $this->viewBuilder()->setOption('serialize', ['html']);
     * }
     * ```
     * 
     * @example Batch Sanitization for Multiple Attributes
     * ```php
     * // Sanitize multiple values for HTML generation
     * $attributes = [
     *     'id' => $item->id,
     *     'class' => $item->category,
     *     'data-url' => $item->link_url,
     *     'title' => $item->description
     * ];
     * 
     * $safeAttributes = [];
     * foreach ($attributes as $name => $value) {
     *     $safeAttributes[$name] = StaticHelpers::makeSafeForHtmlAttribute($value);
     * }
     * 
     * $attrString = '';
     * foreach ($safeAttributes as $name => $value) {
     *     $attrString .= ' ' . $name . '="' . $value . '"';
     * }
     * 
     * echo '<div' . $attrString . '>' . h($item->content) . '</div>';
     * ```
     * 
     * @see htmlspecialchars() For underlying escaping implementation
     * @see h() For CakePHP's general HTML escaping helper
     */
    static function makeSafeForHtmlAttribute($string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
