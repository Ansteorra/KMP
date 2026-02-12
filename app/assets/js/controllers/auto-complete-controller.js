import { Controller } from "@hotwired/stimulus"

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Auto Complete Controller
 * 
 * A comprehensive Stimulus controller providing advanced autocomplete functionality with
 * AJAX search capabilities, keyboard navigation, and flexible value management. Supports
 * remote data loading, local filtering, custom value support, and accessibility features.
 * 
 * Key Features:
 * - Remote AJAX data loading with configurable endpoints
 * - Local data filtering from pre-loaded datalists
 * - Keyboard navigation (arrow keys, enter, escape)
 * - Custom value support for "allow other" scenarios
 * - Hidden field integration for form submission
 * - Debounced search with configurable delay
 * - Accessibility support with ARIA attributes
 * - Clear button functionality with state management
 * - Real-time validation and selection feedback
 * - Bootstrap styling integration
 * 
 * @class AutoComplete
 * @extends Controller
 * 
 * Targets:
 * - input: The visible text input field
 * - hidden: Hidden field for storing selected value ID
 * - hiddenText: Hidden field for storing selected display text
 * - results: Container for autocomplete results dropdown
 * - dataList: Pre-loaded data options container
 * - clearBtn: Button to clear current selection
 * 
 * Values:
 * - ready: Boolean - Controller readiness state
 * - submitOnEnter: Boolean - Auto-submit form on Enter key
 * - url: String - AJAX endpoint for remote data
 * - minLength: Number - Minimum characters before search
 * - allowOther: Boolean - Allow custom values not in list
 * - required: Boolean - Field is required for form validation
 * - initSelection: Object - Initial selection value and text
 * - delay: Number - Debounce delay in milliseconds (default: 300)
 * - queryParam: String - Query parameter name (default: "q")
 * 
 * Classes:
 * - selected: Applied to selected option elements
 * 
 * HTML Structure Example:
 * ```html
 * <!-- Remote AJAX autocomplete for member search -->
 * <div data-controller="auto-complete"
 *      data-auto-complete-url-value="/members/search"
 *      data-auto-complete-min-length-value="2"
 *      data-auto-complete-allow-other-value="false"
 *      data-auto-complete-delay-value="300">
 *   <input type="text" 
 *          data-auto-complete-target="input"
 *          class="form-control"
 *          placeholder="Start typing member name...">
 *   <input type="hidden" data-auto-complete-target="hidden" name="member_id">
 *   <input type="hidden" data-auto-complete-target="hiddenText" name="member_name">
 *   <button type="button" 
 *           data-auto-complete-target="clearBtn"
 *           class="btn btn-outline-secondary"
 *           disabled>Clear</button>
 *   <div data-auto-complete-target="results" class="autocomplete-results"></div>
 * </div>
 * 
 * <!-- Local datalist autocomplete with pre-loaded options -->
 * <div data-controller="auto-complete"
 *      data-auto-complete-allow-other-value="true">
 *   <input type="text" 
 *          data-auto-complete-target="input"
 *          class="form-control">
 *   <div data-auto-complete-target="dataList" style="display: none;">
 *     <div data-value="1" data-text="Option 1">Option 1</div>
 *     <div data-value="2" data-text="Option 2">Option 2</div>
 *   </div>
 * </div>
 * ```
 */

const optionSelector = "[role='option']:not([aria-disabled='true'])"
const activeSelector = "[aria-selected='true']"

class AutoComplete extends Controller {
    static targets = ["input", "hidden", "hiddenText", "results", "dataList", "clearBtn"]
    static classes = ["selected"]
    static values = {
        ready: Boolean,
        submitOnEnter: Boolean,
        url: String,
        minLength: Number,
        allowOther: Boolean,
        required: Boolean,
        initSelection: Object,
        delay: { type: Number, default: 300 },
        queryParam: { type: String, default: "q" },
    }
    static uniqOptionId = 0

    initialize() {
        this._selectOptions = [];
        this._datalistLoaded = false;
    }

    // Getter for the value property
    get value() {
        // if there is a hidden value return that
        if (this.hasHiddenTarget.value != "") {
            return this.hiddenTarget.value;
        } else {
            //if we allow other values return the input value
            if (this.allowOtherValue) {
                return this.inputTarget.value;
            }
            return "";
        }
    }

    // Setter for the value property
    set value(newValue) {
        //check if the new value is an object with a "value" property and "text" property
        if (typeof newValue === "object" && newValue.hasOwnProperty("value") && newValue.hasOwnProperty("text")) {
            this.inputTarget.value = newValue.text;
            this.hiddenTarget.value = newValue.value;
            this.hiddenTextTarget.value = newValue.text;
            this.clearBtnTarget.disabled = false;
            this.inputTarget.disabled = true;
            return;
        }
        //if the value matches an option set the input value to the option text
        if (newValue != "" && newValue != null) {
            let option = this._selectOptions.find(option => option.value == newValue && option.enabled != false);
            if (option) {
                this.inputTarget.value = option.text;
                this.hiddenTextTarget.value = option.text;
                this.hiddenTarget.value = option.value;
                this.clearBtnTarget.disabled = false;
                this.inputTarget.disabled = true;
                return;
            } else {
                if (this.allowOtherValue) {
                    if (this.hasDataListTarget) {
                        var newOptions = this.options;
                        newOptions.push({ value: newValue, text: newValue });
                        this.options = newOptions;
                    }
                    this.inputTarget.value = newValue;
                    this.hiddenTextTarget.value = newValue;
                    this.hiddenTarget.value = newValue;
                } else {
                    this.inputTarget.value = "";
                    this.hiddenTextTarget.value = "";
                    this.hiddenTarget.value = "";
                    newValue = "";
                }
                if (newValue != "") {
                    this.clearBtnTarget.disabled = false;
                    this.inputTarget.disabled = true;
                } else {
                    this.clearBtnTarget.disabled = true;
                    this.inputTarget.disabled = false;
                }
                return;
            }
        }
        this.inputTarget.value = "";
        this.hiddenTarget.value = "";
        this.hiddenTextTarget.value = "";
        this.clearBtnTarget.disabled = true;
        this.inputTarget.disabled = false;
    }
    get value() {
        return this.hiddenTarget.value;
    }

    get disabled() {
        return this.inputTarget.disabled;
    }
    set disabled(newValue) {
        this.hiddenTarget.disabled = newValue;
        this.hiddenTextTarget.disabled = newValue;
        if (this.inputTarget.value != "") {
            this.inputTarget.disabled = true;
            this.clearBtnTarget.disabled = newValue;
        } else {
            this.clearBtnTarget.disabled = true;
            this.inputTarget.disabled = newValue;
        }
    }

    get hidden() {
        return this.element.hidden;
    }
    set hidden(newValue) {
        this.element.hidden = newValue;
    }

    get options() {
        return this._selectOptions;
    }
    set options(newValue) {
        this._selectOptions = newValue;
        this.makeDataListItems();
    }

    connect() {
        this.close()

        if (!this.inputTarget.hasAttribute("autocomplete")) this.inputTarget.setAttribute("autocomplete", "off")
        this.inputTarget.setAttribute("spellcheck", "false")

        this.mouseDown = false

        this.onInputChange = debounce(this.onInputChange, this.delayValue)

        this.inputTarget.addEventListener("keydown", this.onKeydown)
        this.inputTarget.addEventListener("blur", this.onInputBlur)
        this.inputTarget.addEventListener("input", this.onInputChange)
        this.inputTarget.addEventListener("click", this.onInputClick)
        this.inputTarget.addEventListener("change", this.onInputChangeTriggered);
        this.resultsTarget.addEventListener("mousedown", this.onResultsMouseDown)
        this.resultsTarget.addEventListener("click", this.onResultsClick)

        if (this.inputTarget.hasAttribute("autofocus")) {
            this.inputTarget.focus()
        }

        this.shimElement();

        this.readyValue = true
        this.element.dispatchEvent(new CustomEvent("ready", { detail: this.element.dataset }));
    }

    disconnect() {
        if (this.hasInputTarget) {
            this.inputTarget.removeEventListener("keydown", this.onKeydown)
            this.inputTarget.removeEventListener("blur", this.onInputBlur)
            this.inputTarget.removeEventListener("input", this.onInputChange)
            this.inputTarget.removeEventListener("click", this.onInputClick)
            this.inputTarget.removeEventListener("change", this.onInputChangeTriggered);
        }

        if (this.hasResultsTarget) {
            this.resultsTarget.removeEventListener("mousedown", this.onResultsMouseDown)
            this.resultsTarget.removeEventListener("click", this.onResultsClick)
        }
    }

    initSelectionValueChanged() {
        if (this._datalistLoaded) {
            if (this.initSelectionValue == null || !this.initSelectionValue.hasOwnProperty("value")) {
                return;
            }
            let newOption = this.initSelectionValue;
            if (!newOption.value && (!newOption.text || newOption.text == "")) {
                return;
            }
            if (this.allowOtherValue) {
                if (newOption.value == null) {
                    newOption.value = newOption.text;
                }
            }
            let option = this._selectOptions.find(option => option.value == newOption.value);
            if (option) {
                this.value = option.value;
            } else {
                this.addOption(newOption);
                this.value = newOption.value;
            }
        } else {
            //check if there is a value key in the initSelectionValue object
            if (this.initSelectionValue.hasOwnProperty("value")) {
                this.hiddenTarget.value = this.initSelectionValue.value;
                this.hiddenTextTarget.value = this.initSelectionValue.text;
                this.inputTarget.value = this.initSelectionValue.text;
                if (this.initSelectionValue.value) {
                    this.inputTarget.disabled = true;
                    this.clearBtnTarget.disabled = false;
                }
            }
        }
    }

    addOption(option) {
        if (option.hasOwnProperty("value") && option.hasOwnProperty("text")) {
            this._selectOptions.push(option);
            this.makeDataListItems();
        }
    }

    makeDataListItems() {
        if (this.hasDataListTarget) {
            this.dataListTarget.textContent = "";
            var items = JSON.stringify(this._selectOptions);
            this.dataListTarget.textContent = items;
        }
    }

    dataListTargetConnected() {
        this._selectOptions = JSON.parse(this.dataListTarget.textContent);
        this._datalistLoaded = true;
        if (this.hasInitSelectionValue) {
            this.initSelectionValueChanged();
        }
    }

    shimElement() {
        Object.defineProperty(this.element, 'value', {
            get: () => {
                return this.value;
            },
            set: (newValue) => {
                this.value = newValue;
            }
        });
        this.element.focus = () => {
            this.inputTarget.focus();
        }
        let proto = this.element;
        while (proto && !Object.getOwnPropertyDescriptor(proto, 'hidden')) {
            proto = Object.getPrototypeOf(proto);
        }

        if (proto) {
            this.baseHidden = Object.getOwnPropertyDescriptor(proto, 'hidden');
            Object.defineProperty(this.element, 'hidden', {
                get: () => {
                    return this.baseHidden.get.call(this.element);
                },
                set: (newValue) => {
                    this.baseHidden.set.call(this.element, newValue);
                    if (newValue) {
                        this.hiddenTarget.disabled = true;
                        this.hiddenTextTarget.disabled = true;
                        this.inputTarget.disabled = true;
                        this.close();
                    } else {
                        this.hiddenTarget.disabled = false;
                        this.hiddenTextTarget.disabled = false;
                        this.inputTarget.disabled = false;
                    }
                }
            });
        }
        Object.defineProperty(this.element, 'disabled', {
            get: () => {
                return this.disabled;
            },
            set: (newValue) => {
                this.disabled = newValue;
            }
        });

        Object.defineProperty(this.element, 'options', {
            get: () => {
                return this.options;
            },
            set: (newValue) => {
                this.options = newValue;
            }
        });
    }

    sibling(next) {
        const options = this.options
        const selected = this.selectedOption
        const index = options.indexOf(selected)
        const sibling = next ? options[index + 1] : options[index - 1]
        const def = next ? options[0] : options[options.length - 1]
        return sibling || def
    }

    select(target) {
        const previouslySelected = this.selectedOption
        if (previouslySelected) {
            previouslySelected.removeAttribute("aria-selected")
            previouslySelected.classList.remove(...this.selectedClassesOrDefault)
        }
        target.setAttribute("aria-selected", "true")
        target.classList.add(...this.selectedClassesOrDefault)
        this.inputTarget.setAttribute("aria-activedescendant", target.id)
        target.scrollIntoView({ behavior: "auto", block: "nearest" })
    }

    onInputChangeTriggered = (event) => {
        event.stopPropagation();
        this.hiddenTextTarget.value = this.inputTarget.value;
    }

    onInputClick = (event) => {
        this.state = "start";
        if (this.hasDataListTarget) {
            const query = this.inputTarget.value.trim()
            this.fetchResults(query);
        }
        this.hiddenTextTarget.value = this.inputTarget.value;
    }


    onKeydown = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        const handler = this[`on${event.key}Keydown`]
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (handler) handler(event)

    }

    onEscapeKeydown = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (!this.resultsShown) return
        this.hideAndRemoveOptions()
        event.stopPropagation()
        event.preventDefault()
    }

    onArrowDownKeydown = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        const item = this.sibling(true)
        if (item) this.select(item)
        event.preventDefault()
    }

    onArrowUpKeydown = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        const item = this.sibling(false)
        if (item) this.select(item)
        event.preventDefault()
    }

    onTabKeydown = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (this.allowOtherValue) {
            this.fireChangeEvent(this.inputTarget.value, this.inputTarget.value, null);
        } else {
            if (this.inputTarget.value != "") {
                let newValue = this.inputTarget.value;
                let option = this._selectOptions.find(option => option.text == newValue && option.enabled != false);
                this.value = option ? option.value : "";
            } else {
                this.clear();
            }
        }
    }

    onEnterKeydown = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        const selected = this.selectedOption
        if (selected && this.resultsShown) {
            this.commit(selected)
            if (!this.hasSubmitOnEnterValue) {
                event.preventDefault()
            }
        }
    }

    onInputBlur = () => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (this.mouseDown) {
            return;
        }
        if (this.state == "open") {
            if (this.allowOtherValue) {
                this.fireChangeEvent(this.inputTarget.value, this.inputTarget.value, null);
            } else {
                if (this.inputTarget.value != "") {
                    let newValue = this.inputTarget.value;
                    let option = this._selectOptions.find(option => option.text == newValue && option.enabled != false);
                    this.value = option ? option.value : "";
                } else {
                    this.clear();
                }
            }
        }
        this.close();
        console.log("leaving");
    }

    commit(selected) {
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (selected.getAttribute("aria-disabled") === "true") return

        if (selected instanceof HTMLAnchorElement) {
            selected.click()
            this.close()
            return
        }

        const textValue = selected.getAttribute("data-ac-label") || selected.textContent.trim()
        const value = selected.getAttribute("data-ac-value") || textValue
        this.inputTarget.value = textValue

        if (this.hasHiddenTarget) {
            this.hiddenTarget.value = value
            this.hiddenTarget.dispatchEvent(new Event("input"))
            this.hiddenTarget.dispatchEvent(new Event("change"))
        } else {
            this.inputTarget.value = value
        }

        if (this.hasHiddenTextTarget) {
            this.hiddenTextTarget.value = textValue;
        }

        this.inputTarget.focus()
        this.state = "finished";
        this.fireChangeEvent(value, textValue, selected);
        this.hideAndRemoveOptions();
    }

    fireChangeEvent(value, textValue, selected) {
        this.hiddenTextTarget.value = this.inputTarget.value;
        this.element.dispatchEvent(
            new CustomEvent("autocomplete.change", {
                bubbles: true,
                detail: { value: value, textValue: textValue, selected: selected }
            })
        )
        if (this.inputTarget.value == "") {
            this.clearBtnTarget.disabled = true;
            this.inputTarget.disabled = false;
        } else {
            this.clearBtnTarget.disabled = false;
            this.inputTarget.disabled = true;
        }
        this.element.dispatchEvent(new CustomEvent("change"), { bubbles: true });
        this.state = "finished";
    }

    clear() {
        this.inputTarget.value = "";
        if (this.hasHiddenTarget) this.hiddenTarget.value = "";
        if (this.hasHiddenTextTarget) this.hiddenTextTarget.value = "";
        this.clearBtnTarget.disabled = true;
        this.inputTarget.disabled = false;
        this.close();
    }

    onResultsClick = (event) => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (!(event.target instanceof Element)) return
        const selected = event.target.closest(optionSelector)
        if (selected) this.commit(selected)
    }

    onResultsMouseDown = () => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        this.mouseDown = true
        this.resultsTarget.addEventListener("mouseup", () => {
            this.mouseDown = false
        }, { once: true })
    }
    onInputChange = () => {
        this.hiddenTextTarget.value = this.inputTarget.value;
        if (this.hasHiddenTarget) this.hiddenTarget.value = "";
        if (this.hasHiddenTextTarget) this.hiddenTextTarget.value = "";

        const query = this.inputTarget.value.trim();
        if ((query && query.length >= this.minLengthValue) || this.hasDataListTarget) {
            this.fetchResults(query);
        } else {
            this.hideAndRemoveOptions();
        }
    }

    identifyOptions() {
        const prefix = this.resultsTarget.id || "stimulus-autocomplete"
        const optionsWithoutId = this.resultsTarget.querySelectorAll(`${optionSelector}:not([id])`)
        optionsWithoutId.forEach(el => el.id = `${prefix}-option-${AutoComplete.uniqOptionId++}`)
    }

    hideAndRemoveOptions() {
        this.close()
        this.resultsTarget.innerHTML = null
    }

    fetchResults = async (query) => {
        if (!this.hasUrlValue) {
            if (!this.hasDataListTarget) {
                throw new Error("You must provide a URL or a DataList target")
            } else {
                this.resultsTarget.innerHTML = null;
                let allItems = this._selectOptions;
                for (let item of allItems) {
                    if (item.text.toLowerCase().includes(query.toLowerCase()) && (item.enabled != false || query == "")) {
                        let itemHtml = document.createElement("li");
                        itemHtml.setAttribute("data-ac-value", item.value);
                        itemHtml.classList.add("list-group-item");
                        if (item.enabled == false) {
                            itemHtml.setAttribute("aria-disabled", "true");
                            itemHtml.classList.add("disabled");
                        } else {
                            itemHtml.setAttribute("aria-disabled", "false");
                        }
                        itemHtml.setAttribute("role", "option")
                        itemHtml.setAttribute("aria-selected", "false")

                        //add a span around matching string to highlight it
                        if (query != "") {
                            let filteredOptions = item.text;
                            itemHtml.innerHTML = filteredOptions.replace(new RegExp(query, 'gi'), match => `<span class="text-primary">${match}</span>`);
                        } else {
                            itemHtml.innerHTML = item.text;
                        }
                        this.resultsTarget.appendChild(itemHtml);
                    }
                }
                if (this.state != "finished") {
                    this.identifyOptions();
                    this.open();
                    this.state = "open";
                }

                return
            }
        }

        const url = this.buildURL(query)
        try {
            this.element.dispatchEvent(new CustomEvent("loadstart"))
            const html = await this.doFetch(url);
            if (this.state != "finished") {
                this.replaceResults(html)
                this.state = "open";
            }
            this.element.dispatchEvent(new CustomEvent("load"))
            this.element.dispatchEvent(new CustomEvent("loadend"))
        } catch (error) {
            this.element.dispatchEvent(new CustomEvent("error"))
            this.element.dispatchEvent(new CustomEvent("loadend"))
            throw error
        }
    }

    buildURL(query) {
        const url = new URL(this.urlValue, window.location.href)
        const params = new URLSearchParams(url.search.slice(1))
        params.append(this.queryParamValue, query)
        url.search = params.toString()

        return url.toString()
    }

    doFetch = async (url) => {
        const response = await fetch(url, this.optionsForFetch())

        if (!response.ok) {
            throw new Error(`Server responded with status ${response.status}`)
        }

        const html = await response.text()
        return html
    }

    replaceResults(html) {
        this.hiddenTextTarget.value = this.inputTarget.value;
        this.resultsTarget.innerHTML = html
        this.identifyOptions()
        if (!!this.options) {
            this.state = "results";
            this.open()
        } else {
            this.state = "empty list";
            this.close()
        }
    }

    open() {
        if (this.resultsShown) return

        this.resultsShown = true
        this.element.setAttribute("aria-expanded", "true")
        this.hiddenTextTarget.value = this.inputTarget.value;
        this.element.dispatchEvent(
            new CustomEvent("toggle", {
                detail: { action: "open", inputTarget: this.inputTarget, resultsTarget: this.resultsTarget }
            })
        )
    }

    close() {
        if (!this.resultsShown) {

            return
        }
        this.state = "finished";
        this.resultsShown = false
        this.inputTarget.removeAttribute("aria-activedescendant")
        this.element.setAttribute("aria-expanded", "false")
        this.hiddenTextTarget.value = this.inputTarget.value;
        this.element.dispatchEvent(
            new CustomEvent("toggle", {
                detail: { action: "close", inputTarget: this.inputTarget, resultsTarget: this.resultsTarget }
            })
        )
    }

    get resultsShown() {
        return !this.resultsTarget.hidden
    }

    set resultsShown(value) {
        this.resultsTarget.hidden = !value
    }

    get selectedClassesOrDefault() {
        return this.hasSelectedClass ? this.selectedClasses : ["active"]
    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }
}

const debounce = (fn, delay = 10) => {
    let timeoutId = null

    return (...args) => {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(fn, delay)
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["ac"] = AutoComplete;