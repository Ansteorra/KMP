<?php

/**
 * KMP Turbo Active Tabs Element
 * 
 * Dynamic tabbed interface element that integrates Turbo Frames with Bootstrap tabs
 * for efficient content loading and state management. This element provides a complete
 * tabbed interface solution with lazy loading, URL state management, and badge support.
 * 
 * Features:
 * - Bootstrap tabs integration with Turbo Frame content loading
 * - Lazy loading of tab content via Turbo Frames
 * - URL state management and browser history integration
 * - Badge support for tab notifications and counters
 * - Automatic tab selection logic with fallback handling
 * - Stimulus.js controller integration for enhanced functionality
 * 
 * Tab Selection Logic:
 * 1. Check for explicitly selected tab (selected: true)
 * 2. If no tab is selected, automatically select the first tab
 * 3. Support for dynamic tab activation via URL parameters
 * 4. Maintains tab state across page navigation
 * 
 * Turbo Frame Integration:
 * - Each tab content is loaded via separate Turbo Frame
 * - Lazy loading ensures content loads only when tab is activated
 * - Frame boundaries prevent full page reloads during navigation
 * - Independent content updates without affecting other tabs
 * 
 * URL State Management:
 * - Optional URL updating when tabs are switched
 * - Browser history integration for back/forward navigation
 * - Deep linking support for specific tab states
 * - Query parameter management for tab persistence
 * 
 * Badge System:
 * - Numeric badges for counters and notifications
 * - Customizable badge styling with CSS classes
 * - Conditional badge display (hidden when empty or zero)
 * - Dynamic badge updates via Turbo Frame responses
 * 
 * Tab Configuration:
 * Each tab in the $tabs array should contain:
 * - id: Unique identifier for tab and frame elements
 * - label: Display text for the tab button
 * - turboUrl: URL for Turbo Frame content loading
 * - selected: Boolean indicating initial selection state
 * - badge: Optional numeric value for badge display
 * - badgeClass: CSS classes for badge styling
 * 
 * Usage Examples:
 * ```php
 * // Basic tabbed interface
 * echo $this->element('turboActiveTabs', [
 *     'tabs' => [
 *         ['id' => 'overview', 'label' => 'Overview', 'turboUrl' => '/member/123/overview'],
 *         ['id' => 'details', 'label' => 'Details', 'turboUrl' => '/member/123/details'],
 *         ['id' => 'history', 'label' => 'History', 'turboUrl' => '/member/123/history']
 *     ],
 *     'tabGroupName' => 'memberTabs',
 *     'updateUrl' => true
 * ]);
 * 
 * // Tabs with badges and selection
 * echo $this->element('turboActiveTabs', [
 *     'tabs' => [
 *         [
 *             'id' => 'pending',
 *             'label' => 'Pending',
 *             'turboUrl' => '/tasks/pending',
 *             'badge' => 5,
 *             'badgeClass' => 'badge-warning',
 *             'selected' => true
 *         ],
 *         [
 *             'id' => 'completed',
 *             'label' => 'Completed',
 *             'turboUrl' => '/tasks/completed',
 *             'badge' => 12,
 *             'badgeClass' => 'badge-success'
 *         ]
 *     ],
 *     'tabGroupName' => 'taskTabs'
 * ]);
 * ```
 * 
 * JavaScript Integration:
 * - detail-tabs Stimulus controller manages tab behavior
 * - URL state management with configurable updates
 * - Tab button and content area targeting
 * - Event handling for tab switching and frame loading
 * 
 * Accessibility Features:
 * - Proper ARIA roles and attributes for screen readers
 * - Semantic tab structure with role="tab" and role="tabpanel"
 * - Keyboard navigation support for tab switching
 * - Clear focus indicators and tab order management
 * 
 * Performance Considerations:
 * - Lazy loading reduces initial page load time
 * - Content loads only when tabs are accessed
 * - Turbo Frame caching improves subsequent tab switches
 * - Efficient DOM updates without full page refreshes
 * 
 * @var \App\View\AppView $this The view instance
 * @var array $tabs Array of tab configurations with id, label, turboUrl, and optional badge/selected properties
 * @var string $tabGroupName Unique identifier for the tab group (used in DOM IDs)
 * @var bool $updateUrl Whether to update browser URL when tabs are switched (default: true)
 * 
 * @see /assets/js/controllers/detail-tabs-controller.js For JavaScript implementation
 * @see https://turbo.hotwired.dev/reference/frames For Turbo Frame documentation
 * @see /templates/element/activeWindowTabs.php For alternative tab implementation
 */

use App\KMP\StaticHelpers;
//find out which tab should be selected by first seeing if any tab has selected true.. then check if that tab data is empty.. if it is select the next one.
$selected = false;
foreach ($tabs as $tab) {
    if ($tab["selected"]) {
        $selected = true;
    }
    if ($selected) {
        break;
    }
}
if (!$selected) {
    foreach ($tabs as &$tab) {
        $tabs[0]["selected"] = true;
    }
}
if (!isset($updateUrl)) {
    $updateUrl = true;
}
//if no tab is selected, select the first tab with data
?>
<div class="row" data-controller="detail-tabs" data-detail-tabs-update-url-value="<?= $updateUrl ? 'true' : 'false' ?>">
    <nav>
        <div class="nav nav-tabs" id="nav-<?= $tabGroupName ?>" role="tablist">
            <?php foreach ($tabs as &$tab) { ?>
                <button class="nav-link" id="nav-<?= $tab["id"] ?>-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-<?= $tab["id"] ?>" type="button" role="tab" data-level="activityWindow"
                    aria-controls="nav-<?= $tab["id"] ?>" aria-selected="false"
                    data-detail-tabs-target='tabBtn'><?= $tab["label"] ?>
                    <?php if (isset($tab["badge"]) && $tab["badge"] != "" && $tab["badge"] > 0) { ?>
                        <span class="badge <?= $tab["badgeClass"] ?>"><?= $tab["badge"] ?></span>
                    <?php } ?>
                </button>
            <?php } ?>
        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <?php foreach ($tabs as &$tab) { ?>
            <div class="tab-pane fade" id="nav-<?= $tab["id"] ?>" role="tabpanel"
                aria-labelledby="nav-<?= $tab["id"] ?>-tab" data-detail-tabs-target="tabContent">
                <turbo-frame id="<?= $tab["id"] ?>-frame" loading="lazy" src="<?= $tab["turboUrl"] ?>" data-turbo='true'>
                </turbo-frame>
            </div>
        <?php } ?>
    </div>
</div>