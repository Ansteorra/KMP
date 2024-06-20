
import $ from 'jquery';
import jQuery from 'jquery';
// export for others scripts to use
import 'bootstrap';
import * as Turbo from "@hotwired/turbo"
import KMP_utils from './KMP_utils.js';
import imagePreview from './imagePreviewer.js';
window.$ = $;
window.jQuery = jQuery;
window.KMP_utils = KMP_utils;

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
});