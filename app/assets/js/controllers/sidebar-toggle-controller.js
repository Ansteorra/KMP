import { setIcon } from '../services/icon-service.js';
import { Controller } from "@hotwired/stimulus";

/**
 * Sidebar Toggle Stimulus Controller
 *
 * Manages collapsing/expanding the navigation sidebar on desktop.
 * Persists state in localStorage so the preference survives page loads.
 *
 * Targets:
 * - icon: The <kmp-icon> element whose name selects the chevron direction
 */
class SidebarToggleController extends Controller {
    static targets = ["icon"]

    connect() {
        const root = document.documentElement;
        const header = this.element.closest('header');
        root.kmpHeaderLayoutOwner = this;
        const measure = () => {
            if (root.kmpHeaderLayoutOwner !== this || !header?.isConnected) return;
            root.style.setProperty('--kmp-navbar-height', `${Math.ceil(header.getBoundingClientRect().height)}px`);
        };
        if (header && typeof ResizeObserver === 'function') {
            this.headerObserver = new ResizeObserver(measure);
            this.headerObserver.observe(header);
        }
        measure();
        if (localStorage.getItem("kmp-sidebar-collapsed") === "true") {
            // Apply collapsed state immediately without transition on page load
            document.body.classList.add("sidebar-no-transition", "sidebar-collapsed");
            this.firstFrame = requestAnimationFrame(() => {
                this.secondFrame = requestAnimationFrame(() => {
                    document.body.classList.remove("sidebar-no-transition");
                });
            });
        }
        this._updateIcon();
    }

    disconnect() {
        this.headerObserver?.disconnect();
        cancelAnimationFrame(this.firstFrame);
        cancelAnimationFrame(this.secondFrame);
        if (document.documentElement.kmpHeaderLayoutOwner === this) {
            delete document.documentElement.kmpHeaderLayoutOwner;
            document.documentElement.style.removeProperty('--kmp-navbar-height');
        }
    }

    toggle() {
        document.body.classList.toggle("sidebar-collapsed");
        const isCollapsed = document.body.classList.contains("sidebar-collapsed");
        localStorage.setItem("kmp-sidebar-collapsed", isCollapsed);
        this._updateIcon();
    }

    _updateIcon() {
        if (!this.hasIconTarget) return;
        const collapsed = document.body.classList.contains("sidebar-collapsed");
        setIcon(this.iconTarget, collapsed ? "chevron-right-pipe" : "chevron-left-pipe");
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["sidebar-toggle"] = SidebarToggleController;
