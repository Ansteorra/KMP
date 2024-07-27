
// export for others scripts to use
import 'bootstrap';
import * as Turbo from "@hotwired/turbo"
import imagePreview from './imagePreviewer.js';
import { Application, Controller } from "@hotwired/stimulus"
import { definitionsFromContext } from "@hotwired/stimulus-webpack-helpers"
import KMP_utils from './KMP_utils.js';

window.$ = $;
window.jQuery = jQuery;
window.KMP_utils = KMP_utils;
window.Stimulus = Application.start();
// load all the controllers that have registered in the window.Controllers object
for (var controller in window.Controllers) {
    Stimulus.register(controller, window.Controllers[controller]);
}


$(function () {
    //if the querystring has a tab parameter, show that tab
    var tab = KMP_utils.urlParam('tab');
    if (tab) {
        $('#nav-' + tab + '-tab').trigger('click');
    } else {
        $('.nav-link[role=tab]:not([data-level])').first().trigger('click');
    };
    $('.nav-link[role=tab]:not([data-level])').on('click', function () {
        var tab = $(this).attr('id').replace('nav-', '').replace('-tab', '');
        window.history.pushState({}, '', '?tab=' + tab);
    });
    $('.navheader').on('click', function () {
        var state = $(this).attr('aria-expanded');

        if (state == 'true') {
            var recordExpandUrl = $(this).attr('data-expand-url');
            $.get(recordExpandUrl);
        } else {
            var recordCollapseUrl = $(this).attr('data-collapse-url');
            $.get(recordCollapseUrl);
        }
    });
});