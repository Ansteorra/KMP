import { Controller } from "@hotwired/stimulus";

/**
 * MemberMobileCardMenu Stimulus Controller
 * 
 * Manages mobile-optimized menu interface for PWA member cards with floating
 * action button (FAB) style menu system. Provides plugin-based menu item
 * registration and navigation for mobile-optimized features.
 * 
 * Features:
 * - Floating action button (FAB) menu interface
 * - Plugin-registered menu items with icons and badges
 * - Mobile-optimized touch interactions
 * - Expandable/collapsible menu system
 * - Bootstrap icon integration
 * - Notification badge support
 * - Smooth animations and transitions
 * - Accessible ARIA attributes
 * 
 * Values:
 * - menuItems: String (JSON) - Array of menu item configurations from plugins
 * 
 * Targets:
 * - fab: Main floating action button
 * - menu: Menu items container
 * - menuItem: Individual menu item buttons
 * - badge: Notification badge elements
 * 
 * Menu Item Structure (from plugins):
 * {
 *   label: "Submit Waiver",
 *   icon: "bi-file-earmark-text",  // Bootstrap icon class
 *   url: "/waivers/mobile-submit",
 *   order: 10,
 *   badge: null | number,  // Optional notification count
 *   color: "primary" | "success" | "danger" | etc
 * }
 * 
 * Usage:
 * <div data-controller="member-mobile-card-menu"
 *      data-member-mobile-card-menu-menu-items-value='[...]'>
 *   <button data-member-mobile-card-menu-target="fab"
 *           data-action="click->member-mobile-card-menu#toggleMenu">
 *     <i class="bi bi-three-dots"></i>
 *   </button>
 *   <div data-member-mobile-card-menu-target="menu" hidden>
 *     <!-- Menu items rendered here -->
 *   </div>
 * </div>
 */
class MemberMobileCardMenu extends Controller {
    static targets = ["fab", "menu", "menuItem", "badge"]
    static values = {
        menuItems: String
    }

    /**
     * Initialize controller state
     * Sets up menu state tracking
     */
    initialize() {
        this.menuOpen = false;
        this.items = [];
    }

    /**
     * Connect controller to DOM
     * Initializes menu with plugin-registered items
     */
    connect() {
        console.log("MemberMobileCardMenu connected");
        this.loadMenuItems();
        this.renderMenu();
    }

    /**
     * Load menu items from JSON value
     * Parses and validates plugin-registered menu items
     */
    loadMenuItems() {
        try {
            if (this.menuItemsValue) {
                this.items = JSON.parse(this.menuItemsValue);
                // Sort by order
                this.items.sort((a, b) => (a.order || 999) - (b.order || 999));
                console.log("Loaded menu items:", this.items);
            }
        } catch (error) {
            console.error("Error parsing menu items:", error);
            this.items = [];
        }
    }

    /**
     * Render menu items into DOM
     * Creates button elements for each menu item with icons and badges
     */
    renderMenu() {
        if (!this.hasMenuTarget || this.items.length === 0) {
            return;
        }

        // Clear existing menu items
        this.menuTarget.innerHTML = '';

        // Create menu items
        this.items.forEach(item => {
            const menuItem = this.createMenuItem(item);
            this.menuTarget.appendChild(menuItem);
        });
    }

    /**
     * Create menu item DOM element
     * Generates button with icon, label, and optional badge
     * 
     * @param {Object} item Menu item configuration
     * @returns {HTMLElement} Menu item button element
     */
    createMenuItem(item) {
        const button = document.createElement('a');
        button.href = item.url;
        button.className = `btn btn-${item.color || 'primary'} btn-lg w-100 mb-2 d-flex align-items-center justify-content-between mobile-menu-item`;
        button.setAttribute('data-member-mobile-card-menu-target', 'menuItem');
        button.setAttribute('data-action', 'click->member-mobile-card-menu#closeMenu');
        button.setAttribute('role', 'button');
        button.setAttribute('aria-label', item.label);

        // Create content wrapper
        const content = document.createElement('span');
        content.className = 'd-flex align-items-center';

        // Add icon
        if (item.icon) {
            const icon = document.createElement('i');
            icon.className = `bi ${item.icon} me-2`;
            icon.setAttribute('aria-hidden', 'true');
            content.appendChild(icon);
        }

        // Add label
        const label = document.createElement('span');
        label.textContent = item.label;
        content.appendChild(label);

        button.appendChild(content);

        // Add badge if present
        if (item.badge !== null && item.badge !== undefined && item.badge > 0) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-danger rounded-pill';
            badge.setAttribute('data-member-mobile-card-menu-target', 'badge');
            badge.textContent = item.badge;
            button.appendChild(badge);
        }

        return button;
    }

    /**
     * Toggle menu open/closed state
     * Handles FAB button click to show/hide menu
     * 
     * @param {Event} event Click event
     */
    toggleMenu(event) {
        event.preventDefault();
        
        if (this.menuOpen) {
            this.closeMenu();
        } else {
            this.openMenu();
        }
    }

    /**
     * Open menu display
     * Shows menu items with animation
     */
    openMenu() {
        if (!this.hasMenuTarget) return;

        this.menuOpen = true;
        this.menuTarget.hidden = false;
        
        // Add animation class
        this.menuTarget.classList.add('menu-opening');
        
        // Update FAB appearance
        if (this.hasFabTarget) {
            this.fabTarget.classList.add('menu-active');
        }

        // Remove animation class after animation completes
        setTimeout(() => {
            this.menuTarget.classList.remove('menu-opening');
        }, 300);
    }

    /**
     * Close menu display
     * Hides menu items with animation
     */
    closeMenu() {
        if (!this.hasMenuTarget) return;

        this.menuOpen = false;
        
        // Add closing animation class
        this.menuTarget.classList.add('menu-closing');
        
        // Update FAB appearance
        if (this.hasFabTarget) {
            this.fabTarget.classList.remove('menu-active');
        }

        // Hide after animation completes
        setTimeout(() => {
            this.menuTarget.hidden = true;
            this.menuTarget.classList.remove('menu-closing');
        }, 300);
    }

    /**
     * Handle clicks outside menu to close it
     * 
     * @param {Event} event Click event
     */
    handleOutsideClick(event) {
        if (!this.menuOpen) return;

        // Check if click is outside menu and FAB
        const clickedOutside = !this.element.contains(event.target);
        if (clickedOutside) {
            this.closeMenu();
        }
    }

    /**
     * Disconnect controller from DOM
     * Cleans up event listeners
     */
    disconnect() {
        // Cleanup if needed
        console.log("MemberMobileCardMenu disconnected");
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["member-mobile-card-menu"] = MemberMobileCardMenu;
