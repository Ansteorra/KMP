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
 */
class GatheringFormController extends BaseGatheringFormController {
    // All functionality inherited from BaseGatheringFormController
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-form"] = GatheringFormController;
