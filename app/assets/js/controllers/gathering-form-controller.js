import { BaseGatheringFormController } from "./base-gathering-form-controller";

/**
 * Gathering Form Controller
 *
 * Manages client-side validation and UX improvements for gathering forms.
 * Extends BaseGatheringFormController for shared date validation logic.
 *
 * Features:
 * - Automatically defaults end date to start date when start date changes
 * - Validates that end date is not before start date
 * - Provides real-time feedback to users
 * - Disables the Event Website field while the public landing page is
 *   enabled (the public page becomes the event's website)
 */
class GatheringFormController extends BaseGatheringFormController {
    static targets = [
        ...BaseGatheringFormController.targets,
        "publicPageToggle",
        "websiteUrl",
    ]

    connect() {
        super.connect();
        this.syncWebsiteUrlState();
    }

    /**
     * Handle public-page checkbox changes
     */
    publicPageToggled() {
        this.syncWebsiteUrlState();
    }

    /**
     * Disable the Event Website input while the public landing page is
     * enabled - the public page takes precedence as the event's web link.
     */
    syncWebsiteUrlState() {
        if (!this.hasPublicPageToggleTarget || !this.hasWebsiteUrlTarget) {
            return;
        }
        const publicPageEnabled = this.publicPageToggleTarget.checked;
        this.websiteUrlTarget.disabled = publicPageEnabled;
        this.websiteUrlTarget.classList.toggle("text-muted", publicPageEnabled);
    }
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-form"] = GatheringFormController;
