<?php

declare(strict_types=1);

namespace App\View\Cell;

use App\Model\Entity\Member;
use Cake\View\Cell;

/**
 * App Navigation Cell
 * 
 * View Cell responsible for rendering the main application navigation bar.
 * Handles complex navigation logic including user permissions, active states,
 * responsive mobile navigation, and user menu functionality.
 * 
 * This cell is the primary navigation component for the KMP application,
 * displaying different menu items based on user roles and permissions.
 * The navigation structure is dynamically built based on the current user's
 * access rights and the application's RBAC (Role-Based Access Control) system.
 * 
 * Template: templates/cell/AppNav/display.php
 * 
 * @package App\View\Cell
 * @see \App\View\Helper\KmpHelper::appNav() Helper method that invokes this cell
 */
class AppNavCell extends Cell
{
    /**
     * Display the application navigation
     * 
     * Sets up variables for the navigation template rendering. The template
     * uses these variables to construct the Bootstrap navigation bar with
     * dropdown menus, user profile access, and responsive mobile toggle.
     * 
     * The navigation structure includes:
     * - Primary navigation items (Members, Branches, etc.)
     * - User-specific menu with profile and settings
     * - Administrative sections based on permissions
     * - Mobile-responsive hamburger menu
     * 
     * @param array $appNav Navigation structure array containing menu hierarchy
     *                     Format: [['label' => 'Menu Item', 'url' => '/path', 'children' => [...]]]
     * @param \App\Model\Entity\Member $user Current authenticated user entity
     *                                      Used for permission checks and user menu
     * @param array $navBarState Navigation bar state for highlighting active items
     *                          Contains current controller/action for active state detection
     * @return void Variables are set for template via $this->set()
     * 
     * @example
     * ```php
     * // Called from KmpHelper in layout template
     * echo $this->cell('AppNav', [
     *     $navigationStructure,  // Built by controller
     *     $this->Identity->get(), // Current user
     *     ['controller' => 'Members', 'action' => 'index'] // For active states
     * ]);
     * ```
     * 
     * @see templates/cell/AppNav/display.php Template that renders the navigation
     * @see \App\Controller\AppController::beforeRender() Where navigation structure is built
     */
    public function display(array $appNav, Member $user, array $navBarState = []): void
    {
        $this->set(compact('appNav', 'user', 'navBarState'));
    }
}
