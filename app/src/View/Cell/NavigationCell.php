<?php

declare(strict_types=1);

namespace App\View\Cell;

use App\KMP\StaticHelpers;
use App\Services\NavigationRegistry;
use Cake\View\Cell;

/**
 * Navigation cell
 * 
 * View Cell responsible for building and organizing the complete navigation menu structure
 * for the KMP application. This cell handles the complex logic of organizing navigation
 * items into hierarchical menus with proper active state detection and permission-based filtering.
 * 
 * The cell works with the NavigationRegistry service to collect navigation items from
 * various plugins and core modules, then organizes them into a structured menu hierarchy
 * with parent categories, main links, and sublinks.
 * 
 * Key Features:
 * - Dynamic menu generation based on user permissions
 * - Hierarchical organization (Parent > Child > Sublink)
 * - Active state detection for current page highlighting
 * - Plugin-extensible navigation system
 * - Responsive menu structure support
 * 
 * Template: templates/cell/Navigation/display.php
 * 
 * @package App\View\Cell
 * @see \App\Services\NavigationRegistry Navigation item collection service
 */
class NavigationCell extends Cell
{
    /**
     * List of valid options that can be passed into this cell's constructor.
     * 
     * Currently empty as this cell doesn't accept configuration options,
     * but maintained for future extensibility.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     * 
     * Currently no initialization required, but maintained for
     * potential future setup needs.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Default display method for building navigation menu
     * 
     * Collects navigation items from the NavigationRegistry based on the current user
     * and request context, then organizes them into a hierarchical menu structure.
     * The resulting menu includes proper active state detection and permission filtering.
     * 
     * Process Flow:
     * 1. Extract current user and request parameters
     * 2. Collect navigation items from registry (filtered by permissions)
     * 3. Organize items into hierarchical structure
     * 4. Set organized menu for template rendering
     * 
     * The menu structure returned includes:
     * - Parent categories with children
     * - Main navigation links
     * - Sublinks under main categories
     * - Active state flags for current page
     * 
     * @return void Menu data is set via $this->set() for template access
     * 
     * @see \App\Services\NavigationRegistry::getNavigationItems() Item collection
     * @see organizeMenu() Menu hierarchy organization
     */
    public function display(): void
    {
        $user = $this->request->getAttribute('identity');
        $params = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
            $this->request->getParam('pass'),
        ];

        // Get navigation items from the registry instead of dispatching events
        $menuItems = NavigationRegistry::getNavigationItems($user, $params);
        $menu = $this->organizeMenu($menuItems);

        $this->set(compact('menu'));
    }

    /**
     * Organize navigation items into hierarchical menu structure
     * 
     * Takes the flat array of navigation items from the registry and organizes them
     * into a hierarchical structure with parents, children, and sublinks. Also handles
     * active state detection based on the current request URL.
     * 
     * Menu Organization:
     * - Parent items: Top-level categories (type='parent')
     * - Main links: Direct children of parents (mergePath length = 1)  
     * - Sublinks: Third-level items under main links (mergePath length > 1)
     * 
     * Active State Logic:
     * - Marks current page and its parent hierarchy as active
     * - Uses exact URL matching and wildcard pattern matching
     * - Only one active path is marked to prevent conflicts
     * 
     * @param array $menuItems Flat array of navigation items from NavigationRegistry
     * @return array Hierarchically organized menu structure with active states
     * 
     * @example
     * ```php
     * // Input: Flat navigation items
     * [
     *   ['type' => 'parent', 'label' => 'Members', 'order' => 1],
     *   ['type' => 'link', 'label' => 'View All', 'mergePath' => ['Members'], 'url' => '/members'],
     *   ['type' => 'link', 'label' => 'Add Member', 'mergePath' => ['Members', 'View All'], 'url' => '/members/add']
     * ]
     * 
     * // Output: Hierarchical structure  
     * [
     *   'Members' => [
     *     'label' => 'Members',
     *     'active' => true,
     *     'children' => [
     *       'View All' => ['label' => 'View All', 'active' => true, 'sublinks' => [...]]
     *     ]
     *   ]
     * ]
     * ```
     */
    protected function organizeMenu($menuItems)
    {
        $currentRequestString = $this->request->getParam('controller') . '/' . $this->request->getParam('action');
        if ($this->request->getParam('plugin')) {
            $currentRequestString = $this->request->getParam('plugin') . '/' . $currentRequestString;
        }
        if ($this->request->getParam('pass')) {
            $currentRequestString .= '/' . $this->request->getParam('pass')[0];
        }
        $currentRequestString = strtolower($currentRequestString);
        $parents = [];
        $mainLinks = [];
        $sublinks = [];
        foreach ($menuItems as &$item) {
            if (!$item) {
                continue;
            }
            $item['active'] = false;
            if ($item['type'] === 'parent') {
                $parents[$item['label']] = $item;
            } elseif ($item['type'] === 'link' && count($item['mergePath']) == 1) {
                $mainLinks[] = $item;
            } elseif ($item['type'] === 'link' && count($item['mergePath']) > 1) {
                $sublinks[] = $item;
            }
        }
        $activeFound = false;
        // add mainlinks to parents
        foreach ($mainLinks as &$mainlink) {
            if (!$activeFound && $this->isActive($mainlink, $currentRequestString)) {
                $parents[$mainlink['mergePath'][0]]['active'] = true;
                $mainlink['active'] = true;
                $activeFound = true;
            }
            $parents[$mainlink['mergePath'][0]]['children'][$mainlink['label']] = $mainlink;
        }
        //foreach sublink to mainlink
        foreach ($sublinks as &$sublink) {
            if (!$activeFound && $this->isActive($sublink, $currentRequestString)) {
                $parents[$mainlink['mergePath'][0]]['active'] = true;
                $parents[$sublink['mergePath'][0]]['children'][$sublink['mergePath'][1]]['active'] = true;
                $sublink['active'] = true;
                $activeFound = true;
            }
            $parents[$sublink['mergePath'][0]]['children'][$sublink['mergePath'][1]]['sublinks'][$sublink['label']] = $sublink;
        }
        //sort parents by order
        uasort($parents, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        //foreach parent sort children by order
        foreach ($parents as &$parent) {
            if (!isset($parent['children'])) {
                continue;
            }
            uasort($parent['children'], function ($a, $b) {
                $returnval = $a['order'] <=> $b['order'];

                return $returnval;
            });
            //foreach child sort sublinks by order
            foreach ($parent['children'] as &$child) {
                if (!isset($child['sublinks'])) {
                    continue;
                }
                uasort($child['sublinks'], function ($a, $b) {
                    return $a['order'] <=> $b['order'];
                });
            }
        }

        return $parents;
    }

    /**
     * Determine if a navigation link is active based on current request
     * 
     * Checks if the given navigation link should be marked as active by comparing
     * its URL and active paths against the current request string. Supports both
     * exact matching and wildcard pattern matching for flexible active state detection.
     * 
     * Active State Matching:
     * 1. Exact URL match: Link URL exactly matches current request
     * 2. Active paths: Link defines specific paths that should mark it active
     * 3. Wildcard matching: Paths ending with '*' match URL prefixes
     * 
     * @param array $link Navigation link item with 'url' and optional 'activePaths'
     * @param string $currentRequestString Normalized current request path (controller/action/params)
     * @return bool True if the link should be marked as active
     * 
     * @example
     * ```php
     * // Exact match
     * $link = ['url' => '/members/index'];
     * $current = 'members/index';
     * // Returns: true
     * 
     * // Wildcard match
     * $link = ['url' => '/members', 'activePaths' => ['members/*']];
     * $current = 'members/view/123';
     * // Returns: true (matches wildcard pattern)
     * 
     * // No match
     * $link = ['url' => '/branches'];
     * $current = 'members/index';
     * // Returns: false
     * ```
     * 
     * @see \App\KMP\StaticHelpers::makePathString() URL normalization helper
     */
    protected function isActive($link, $currentRequestString): bool
    {
        $itemPath = StaticHelpers::makePathString($link['url']);
        if ($itemPath === $currentRequestString) {
            return true;
        }
        if (isset($link['activePaths'])) {
            foreach ($link['activePaths'] as $activePath) {
                if ($activePath === $currentRequestString) {
                    return true;
                }
                //if $activepath ends with * then we are looking for a partial match
                if (substr($activePath, -1) === '*') {
                    $activePath = substr($activePath, 0, -2);
                    $testPath = strtolower(substr($currentRequestString, 0, strlen($activePath)));
                    if ($testPath == strtolower($activePath)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
