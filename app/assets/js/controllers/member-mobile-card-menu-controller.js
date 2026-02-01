import MobileControllerBase from "./mobile-controller-base.js";

/**
 * MemberMobileCardMenu Stimulus Controller
 * 
 * Manages mobile-optimized menu interface for PWA member cards.
 * Extends MobileControllerBase for centralized connection handling.
 * 
 * Features:
 * - Floating action button (FAB) menu interface
 * - Plugin-registered menu items with icons and badges
 * - Online/offline state management
 * - Expandable/collapsible menu system
 */
class MemberMobileCardMenu extends MobileControllerBase {
    static targets = ["fab", "menu", "menuItem", "badge"]
    static values = {
        menuItems: String
    }

    initialize() {
        super.initialize();
        this.menuOpen = false;
        this.items = [];
        this.authCardUrl = null;
    }

    /**
     * Called after base class connect
     */
    onConnect() {
        console.log("MemberMobileCardMenu connected");
        this.loadMenuItems();
        this.renderMenu();
        
        // Register outside click handler
        this._handleOutsideClick = this.bindHandler('outsideClick', this.handleOutsideClick);
        document.addEventListener('click', this._handleOutsideClick);
        document.addEventListener('touchstart', this._handleOutsideClick);
        
        // Register connection status handler for auth card URL
        this._handleConnectionStatus = this.bindHandler('connectionStatus', this.handleConnectionStatusEvent);
        document.addEventListener('connection-status-changed', this._handleConnectionStatus);
        
        // Update initial offline state
        this.updateOfflineState();
    }

    /**
     * Called when connection state changes (from base class)
     */
    onConnectionStateChanged(isOnline) {
        this.updateOfflineState();
    }

    /**
     * Called after base class disconnect
     */
    onDisconnect() {
        document.removeEventListener('click', this._handleOutsideClick);
        document.removeEventListener('touchstart', this._handleOutsideClick);
        document.removeEventListener('connection-status-changed', this._handleConnectionStatus);
        console.log("MemberMobileCardMenu disconnected");
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
        
        // Store the item data for offline state management
        button.dataset.itemLabel = item.label;
        button.dataset.itemUrl = item.url;

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
     * Handle connection status event from PWA controller (for auth card URL)
     */
    handleConnectionStatusEvent(event) {
        this.authCardUrl = event.detail.authCardUrl;
        this.updateOfflineState();
    }

    /**
     * Update menu items based on offline state
     * Uses base class online property instead of duplicate state
     */
    updateOfflineState() {
        if (!this.hasMenuItemTarget) return;

        // Items that should remain enabled when offline
        const offlineAllowedLabels = ['Auth Card', 'My RSVPs'];

        this.menuItemTargets.forEach(item => {
            const itemUrl = item.dataset.itemUrl;
            const itemLabel = item.dataset.itemLabel;
            
            // Check if this item should be allowed offline
            const isAllowedOffline = offlineAllowedLabels.includes(itemLabel) || 
                              (this.authCardUrl && itemUrl && itemUrl.includes('viewMobileCard'));
            
            if (!this.online && !isAllowedOffline) {
                // Offline and not allowed - disable
                item.classList.add('disabled');
                item.style.opacity = '0.5';
                item.style.pointerEvents = 'none';
                item.setAttribute('aria-disabled', 'true');
            } else {
                // Online or allowed offline - enable
                item.classList.remove('disabled');
                item.style.opacity = '1';
                item.style.pointerEvents = 'auto';
                item.removeAttribute('aria-disabled');
            }
        });
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["member-mobile-card-menu"] = MemberMobileCardMenu;
