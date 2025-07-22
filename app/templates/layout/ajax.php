<?php

/**
 * KMP AJAX Layout Template
 * 
 * Minimal layout template for AJAX responses and partial content delivery.
 * This layout provides a bare-bones structure that only renders the content
 * without any HTML wrapper elements, headers, or footers.
 * 
 * Purpose:
 * - AJAX endpoint responses that return HTML fragments
 * - Partial page updates and dynamic content loading
 * - API responses that need HTML formatting
 * - Turbo frame content delivery
 * - Modal dialog content loading
 * - Dynamic form field updates
 * 
 * Features:
 * - Minimal overhead with no wrapper elements
 * - Direct content rendering without layout scaffolding
 * - Compatible with CakePHP's AJAX detection
 * - Suitable for JSON responses containing HTML
 * - Optimized for partial page updates
 * 
 * Usage Scenarios:
 * 1. Controller methods that return HTML fragments via AJAX
 * 2. Dynamic form field updates (autocomplete, dependent dropdowns)
 * 3. Modal dialog content loading
 * 4. Turbo frame partial updates
 * 5. Search result fragments
 * 6. Table row updates and additions
 * 
 * Integration with KMP:
 * - Automatically selected when request is detected as AJAX
 * - Works with CakePHP's RequestHandler component
 * - Compatible with KMP's JavaScript controllers
 * - Supports Stimulus.js controller initialization in fragments
 * 
 * Example Controller Usage:
 * ```php
 * public function ajaxSearch()
 * {
 *     $this->viewBuilder()->setLayout('ajax');
 *     $results = $this->Members->find('search', ['term' => $this->request->getQuery('term')]);
 *     $this->set('results', $results);
 * }
 * ```
 * 
 * JavaScript Integration:
 * ```javascript
 * fetch('/members/ajax-search?term=john')
 *     .then(response => response.text())
 *     .then(html => {
 *         document.getElementById('results').innerHTML = html;
 *         // Stimulus controllers in the HTML fragment will auto-initialize
 *     });
 * ```
 * 
 * @var \App\View\AppView $this The view instance
 * @var string $content The rendered content from the view template
 * 
 * @see \App\Controller\Component\RequestHandlerComponent For AJAX detection
 * @see /templates/layout/turbo_frame.php For Turbo-specific minimal layout
 * @see \App\View\AjaxView For AJAX-specific view class
 */

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
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */

echo $this->fetch("content");
