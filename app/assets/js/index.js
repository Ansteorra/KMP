
// export for others scripts to use
import { Application } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";
import 'bootstrap';
import KMP_utils from './KMP_utils.js';

// Import Stimulus controllers
import ActivityWaiverManagerController from './controllers/activity-waiver-manager-controller';
import DeleteConfirmationController from './controllers/delete-confirmation-controller';
import GatheringTypeFormController from './controllers/gathering-type-form-controller';

// Disable Turbo Drive (automatic navigation) but keep Turbo Frames working
Turbo.session.drive = false;

//window.$ = $;
//window.jQuery = jQuery;
window.KMP_utils = KMP_utils;
window.Stimulus = Application.start();

// Register imported controllers
Stimulus.register("activity-waiver-manager", ActivityWaiverManagerController);
Stimulus.register("gathering-type-form", GatheringTypeFormController);
Stimulus.register("delete-confirmation", DeleteConfirmationController);

// load all the controllers that have registered in the window.Controllers object
for (var controller in window.Controllers) {
    Stimulus.register(controller, window.Controllers[controller]);
}

//activate boostrap tooltips
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))