<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.4
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\View;

/**
 * AJAX Response View Class
 * 
 * Specialized view class for handling AJAX requests in the KMP application.
 * Extends AppView to inherit all helper functionality while providing
 * AJAX-specific response handling.
 * 
 * Key Features:
 * - Automatic JSON response content type
 * - Uses minimal 'ajax' layout for clean responses
 * - Inherits all AppView helpers and functionality
 * - Optimized for AJAX endpoint responses
 * 
 * Usage:
 * Controllers automatically use this view when:
 * - Request is made via AJAX
 * - Response format is set to JSON
 * - Controller explicitly sets view class to AjaxView
 * 
 * Layout Behavior:
 * - Uses 'ajax.php' layout instead of 'default.php'
 * - Provides minimal HTML wrapper for JSON responses
 * - Maintains helper availability for partial template rendering
 * 
 * Response Handling:
 * - Sets response content type to 'application/json'
 * - Compatible with Stimulus.js fetch requests
 * - Supports both JSON data and partial HTML responses
 * 
 * Integration:
 * - Works seamlessly with KMP's Stimulus controllers
 * - Supports Turbo frame updates
 * - Compatible with CakePHP's request handling
 * 
 * @see \App\View\AppView For base functionality
 * @see templates/layout/ajax.php For AJAX layout template
 */
class AjaxView extends AppView
{
    /**
     * The name of the layout file to render the view inside of.
     * 
     * Uses the 'ajax' layout which provides minimal HTML structure
     * optimized for AJAX responses. The layout file should be located
     * at templates/Layout/ajax.php
     * 
     * The AJAX layout typically:
     * - Omits full HTML document structure
     * - Excludes navigation and footer elements
     * - Focuses on content delivery
     * - May include minimal styling for partial updates
     * 
     * @var string
     */
    protected string $layout = 'ajax';

    /**
     * Initialization hook method for AJAX view setup.
     * 
     * Extends AppView initialization to configure AJAX-specific behavior:
     * 1. Inherits all helper loading from AppView
     * 2. Sets response content type to application/json
     * 3. Prepares view for AJAX response handling
     * 
     * Response Type Configuration:
     * - Sets 'application/json' content type for proper browser handling
     * - Ensures AJAX requests receive appropriate response headers
     * - Compatible with fetch() API and XMLHttpRequest
     * 
     * Helper Availability:
     * - All AppView helpers remain available (Identity, Kmp, AssetMix, etc.)
     * - Bootstrap helpers can be used for partial rendering
     * - Image processing helpers work for dynamic content
     * 
     * Usage Notes:
     * - Called automatically when view is instantiated
     * - No additional configuration needed in controllers
     * - Works with both JSON data and partial HTML responses
     * 
     * @return void
     * @throws \Exception If parent initialization fails
     */
    public function initialize(): void
    {
        // Initialize parent AppView functionality
        // This loads all helpers and sets up Bootstrap UI integration
        parent::initialize();

        // Set response content type to JSON for proper AJAX handling
        // This ensures browsers and JavaScript frameworks receive the correct
        // content type header for processing AJAX responses
        $this->response = $this->response->withType('application/json');
    }
}
