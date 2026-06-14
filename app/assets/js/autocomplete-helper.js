/**
 * Reusable autocomplete widget HTML generator.
 *
 * Produces markup matching the structure in templates/element/autoCompleteControl.php,
 * for use in JavaScript-rendered UI (e.g. workflow config panels).
 * All generated HTML is compatible with the "ac" Stimulus controller.
 *
 * @param {object} options
 * @param {string} options.url - AJAX endpoint URL for search
 * @param {string} [options.name] - name attribute for the hidden value input
 * @param {string} [options.value] - initial value for the hidden input
 * @param {string} [options.placeholder] - placeholder text for the text input
 * @param {number} [options.minLength=1] - minimum characters before search triggers
 * @param {boolean} [options.allowOther=false] - allow custom values not in results
 * @param {object} [options.initSelection] - {value, text} to pre-populate the widget
 * @param {string} [options.hiddenAttrs] - extra attribute string for the hidden value input
 * @param {string} [options.size] - Bootstrap size suffix (e.g. 'sm')
 * @param {string} [options.cssClass] - extra CSS classes on the wrapper div
 * @param {string} [options.inputId] - id for the visible text input
 * @param {string} [options.resultsId] - id for the results list
 * @param {string} [options.statusId] - id for the live status region
 * @returns {string} HTML string
 */
export function renderAutoComplete(options) {
    const url = options.url || '';
    const name = options.name ? ` name="${options.name}"` : '';
    const value = options.value || '';
    const placeholder = options.placeholder ? ` placeholder="${options.placeholder}"` : '';
    const minLength = options.minLength != null ? options.minLength : 1;
    const allowOther = options.allowOther ? 'true' : 'false';
    const hiddenAttrs = options.hiddenAttrs ? ' ' + options.hiddenAttrs : '';
    const size = options.size || '';
    const cssClass = options.cssClass ? ' ' + options.cssClass : '';

    const inputGroupClass = size ? `input-group input-group-${size}` : 'input-group';
    const inputClass = size ? `form-control form-control-${size}` : 'form-control';
    const safeName = (options.name || 'autocomplete').replace(/[^a-zA-Z0-9_-]/g, '-');
    const inputId = options.inputId || `${safeName}-disp`;
    const resultsId = options.resultsId || `${safeName}-results`;
    const statusId = options.statusId || `${safeName}-status`;

    let initAttr = '';
    if (options.initSelection) {
        const json = JSON.stringify(options.initSelection).replace(/'/g, '&#39;');
        initAttr = `\n     data-ac-init-selection-value='${json}'`;
    }

    return `<div data-controller="ac"
     data-ac-url-value="${url}"
     data-ac-min-length-value="${minLength}"
     data-ac-allow-other-value="${allowOther}"${initAttr}
     class="position-relative kmp_autoComplete${cssClass}">
  <input type="hidden"${name} value="${value}"
         data-ac-target="hidden"${hiddenAttrs}>
  <input type="hidden" data-ac-target="hiddenText">
  <div class="${inputGroupClass}">
    <input type="text" class="${inputClass}" id="${inputId}"
           role="combobox"
           aria-autocomplete="list"
           aria-expanded="false"
           aria-controls="${resultsId}"
           aria-describedby="${statusId}"
           data-ac-target="input"${placeholder}>
    <button type="button" class="btn btn-outline-secondary" data-ac-target="clearBtn" data-action="ac#clear" disabled>Clear</button>
  </div>
  <ul data-ac-target="results"
      id="${resultsId}"
      role="listbox"
      class="list-group z-3 col-12 position-absolute auto-complete-list"
      hidden="hidden"></ul>
  <div id="${statusId}" class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
       data-ac-target="status"></div>
</div>`;
}
