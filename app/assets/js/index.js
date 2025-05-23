
// export for others scripts to use
import 'bootstrap';
import * as Turbo from "@hotwired/turbo"
import { Application, Controller } from "@hotwired/stimulus"
import KMP_utils from './KMP_utils.js';

//window.$ = $;
//window.jQuery = jQuery;
window.KMP_utils = KMP_utils;
window.Stimulus = Application.start();
// load all the controllers that have registered in the window.Controllers object
for (var controller in window.Controllers) {
    Stimulus.register(controller, window.Controllers[controller]);
}

//activate boostrap tooltips
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))