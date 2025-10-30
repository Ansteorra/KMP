import { BaseGatheringFormController } from "./base-gathering-form-controller";

/**
 * Gathering Clone Controller
 * 
 * Handles the clone gathering modal form interactions with date validation and defaulting.
 * Extends BaseGatheringFormController for shared date validation logic.
 * 
 * Features:
 * - Automatically defaults end date to start date when start date changes
 * - Validates that end date is not before start date
 * - Provides real-time feedback to users
 */
class GatheringCloneController extends BaseGatheringFormController {
    // Define additional targets specific to clone form
    static targets = ["nameInput", "startDate", "endDate", "submitButton"]
    
    // All date validation functionality inherited from BaseGatheringFormController
}

// Add to global controllers registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gathering-clone"] = GatheringCloneController;
