"use strict";
(function ($) {
    $.fn.laiImagePreview = function (options) {
        // setting default options.
        var settings = $.extend({
            // These are the defaults.
            columns: "col-sm-12 col-md-12",
            inputFileName: "lai-img-upload-file",
            imageCaption: false,
            imageLimit: 1,
            maxFileSize: 2000000,
        }, options);

        var setId = $(this).attr("id");

        //set options settings
        sessionStorage.setItem("columns", settings.columns);
        sessionStorage.setItem("inputFileName", settings.inputFileName);
        sessionStorage.setItem("imageCaption", settings.imageCaption);
        sessionStorage.setItem("imageLimit", settings.imageLimit);
        sessionStorage.setItem("imageLimit", settings.imageLimit);
        sessionStorage.setItem("maxFileSize", settings.maxFileSize);

        setTimeout(
            function () {
                //set image uploader div
                //const imgUploadHtml = '<div class="card p-0"><div class="card-header bg-white border-bottom"><p class="text-danger float-start" id="lai-img-upload-limit"></p><a href="#!" class="float-end lai-img-upload-plus"><i class="bi bi-plus-circle-fill lai-img-upload-plus-icon"></i></a></div><div class="card-body"><div class="row" id="lai-img-uploader-div" style="padding: 5px;">' + generateImageCard(true, settings.columns, settings.inputFileName, settings.imageCaption) + '</div></div></div>';
                const imgUploadHtml = '<div class="mb-3 form-group"><label class="form-label" for="' + settings.field + '">' + settings.label + '</label><div class="lai-img-uploader-div">' + generateImageCard(true, settings.columns, settings.inputFileName, settings.imageCaption) + '</div>';
                return $("#" + setId).html(imgUploadHtml);
            }, 500);

    };
}(jQuery));


//new image preview add
"use strict";
$('body').on('click', '.lai-img-upload-plus', function () {
    //set image caption from string to boolean
    var imageCaption = sessionStorage.getItem("imageCaption") == "true" ? true : false;

    var getLimit = !isEmpty(sessionStorage.getItem("imageLimit")) ? parseInt(sessionStorage.getItem("imageLimit")) : 6;
    var numItems = parseInt($('.lai-img-div').length);

    if (numItems < (getLimit)) {
        $(".lai-img-div").last().after(generateImageCard(false, sessionStorage.getItem("columns"), sessionStorage.getItem("inputFileName"), imageCaption));
    }
    else {
        $("#lai-img-upload-limit").html("Limit of " + getLimit + " image(s) has been reached.");
    }

});

//image preview reload
"use strict";
$('body').on('click', '.lai-img-upload-reload', function () {
    //get data id for image file
    const inputId = $(this).data("input-id");

    //clear image file name
    $(".lai-img-file-name-" + inputId).text("");

    //clear seleted file name
    $("#" + inputId).val("");

    //set image src
    $(".lai-img-file-src-" + inputId).attr('src', 'data:image/jpeg;base64,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');
});

//image preview close
"use strict";
$('body').on('click', '.lai-img-upload-close', function () {
    //clear limit message if existing
    $("#lai-img-upload-limit").html("");

    //get data id for image file
    const inputId = $(this).data("input-id");

    //remove image div
    $('#lai-img-div-' + inputId).remove();
});

//set image preview on change
"use strict";
$('body').on('change', '.lai-img-file-input', function (e) {
    var selectId = $(this).attr('id');
    var fileName = e.target.files[0].name;

    $(".lai-img-file-name-" + selectId).text(fileName);
    // check the file size
    var maxFileSize = sessionStorage.getItem("maxFileSize");
    if (e.target.files[0].size > maxFileSize) {
        alert("File is too big!");
        $(this).val('');
        $('.lai-img-upload-reload').click();
    }
    var reader = new FileReader();
    reader.onload = function (e) {
        // get loaded data and render image thumbnail.
        $(".lai-img-file-src-" + selectId).attr('src', e.target.result);
    };

    // read the image file as a data URL.
    reader.readAsDataURL(this.files[0]);
});

//trigger select image file on image click
"use strict";
$('body').on('click', 'img', function (e) {

    var fileInputId = $(this).data('input-id');
    console.log('clicked! ' + fileInputId);

    // Trigger a click event on the found file input
    $("#" + fileInputId).click();
});

//generate image card
"use strict";
function generateImageCard(initialgeneration, columns, inputName, imageCaption) {
    const elementId = generateGuid();
    var closeBtn = initialgeneration ? '' : '<a href="#!" class="float-end lai-img-upload-close" data-input-id="' + elementId + '"><i class="bi bi-x-lg"></i></a>'; //remove close on initial load
    var colFormat = !isEmpty(columns) && validColumn(columns) ? columns : "col-sm-4 col-md-3";
    var inputName = !isEmpty(inputName) ? inputName : "lai-img-upload-file";
    var imageCaptionInput = imageCaption == true ? '<input class="form-control form-control-sm" type="text" placeholder="image caption" name="' + inputName + '" id="lai-image-caption-' + elementId + '">' : "";
    return '<div class="' + colFormat + ' mb-2 lai-img-div" id="lai-img-div-' + elementId + '"><div class="card"><div class="card-header"><a href="#!" class="float-start lai-img-upload-reload" data-input-id="' + elementId + '"><i class="bi bi-arrow-clockwise"></i></a>' + closeBtn + '</div><div class="card-body p-0 text-center"><img class="lai-img-file-src-' + elementId + '" data-input-id=' + elementId + ' height="180" src="' + getDefaultImageSrc() + '" alt="Card image"></div><div class="card-footer">' + imageCaptionInput + '<label for="File" class="form-label fst-italic text-danger small text-wrap lai-img-file-name-' + elementId + '"></label><input class="form-control form-control-sm lai-img-file-input" id="' + elementId + '" name="' + inputName + '" type="file" accept="image/*"></div></div></div>';
}

//gets default image src
"use strict";
function getDefaultImageSrc() {
    return "data:image/jpeg;base64,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";
}

//generates unique guid for input file
"use strict";
function generateGuid() {
    return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
        (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
    );
}

//Check if column is valid
"use strict";
function validColumn(value) {
    return ((value.includes("col") || value.includes("col-")) && !isEmpty(value));
}

//Check variable for empty
"use strict";
function isEmpty(value) {
    return (value == null || value.length === 0);
}


$(document).ready(function () {
    "use strict";
    // set style by appending to body
    const cssStyle = ".lai-img-upload-plus-icon{font-size: 2em;}.lai-img-file-name{overflow: hidden;white-space: nowrap;}";
    var styleSheet = document.createElement("style");
    styleSheet.innerText = cssStyle;
    document.head.appendChild(styleSheet);
});