
// export for others scripts to use
import { Application } from "@hotwired/stimulus";
import * as Turbo from "@hotwired/turbo";
import 'bootstrap';
import KMP_utils from './KMP_utils.js';
import './timezone-utils.js';

// Import controllers
import './controllers/qrcode-controller.js';
import './controllers/timezone-input-controller.js';

// Disable Turbo Drive (automatic navigation) but keep Turbo Frames working
Turbo.session.drive = false;

//window.$ = $;
//window.jQuery = jQuery;
window.KMP_utils = KMP_utils;
const stimulusApp = Application.start();
window.Stimulus = stimulusApp;

// load all the controllers that have registered in the window.Controllers object
for (const controller in window.Controllers) {
    stimulusApp.register(controller, window.Controllers[controller]);
}

//activate boostrap tooltips
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))