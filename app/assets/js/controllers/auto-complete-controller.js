import { Controller } from "@hotwired/stimulus"

const optionSelector = "[role='option']:not([aria-disabled])"
const activeSelector = "[aria-selected='true']"

class AutoComplete extends Controller {
    static targets = ["input", "hidden", "results", "dataList"]
    static classes = ["selected"]
    static values = {
        ready: Boolean,
        submitOnEnter: Boolean,
        url: String,
        minLength: Number,
        allowOther: Boolean,
        required: Boolean,
        delay: { type: Number, default: 300 },
        queryParam: { type: String, default: "q" },
    }
    static uniqOptionId = 0

    initialize() {
        this._selectOptions = [];
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
            return;
        }
        //if the value matches an option set the input value to the option text
        if (newValue != "" && newValue != null) {
            let option = this._selectOptions.find(option => option.value == newValue);
            if (!option) {
                if (this.hasDataListTarget) {
                    var newOptions = this.options;
                    newOptions.push({ value: newValue, text: newValue });
                    this.options = newOptions;
                }
            }
            if (option) {
                this.inputTarget.value = option.text;
                this.hiddenTarget.value = option.value;
                return;
            } else {
                if (this.allowOtherValue) {
                    this.inputTarget.value = newValue;
                    this.hiddenTarget.value = newValue;
                } else {
                    this.inputTarget.value = "";
                    this.hiddenTarget.value = "";
                }
                return;
            }
        }
        this.inputTarget.value = "";
        this.hiddenTarget.value = "";
    }

    get disabled() {
        return this.inputTarget.disabled;
    }
    set disabled(newValue) {
        this.inputTarget.disabled = newValue;
        this.hiddenTarget.disabled = newValue;
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

    makeDataListItems() {
        if (this.hasDataListTarget) {
            this.dataListTarget.textContent = "";
            var items = JSON.stringify(this._selectOptions);
            this.dataListTarget.textContent = items;
        }
    }

    dataListTargetConnected() {
        console.log("DataList Target Connected");
        this._selectOptions = JSON.parse(this.dataListTarget.textContent);
    }

    connect() {
        this.state = "start";
        this.close()
        this.state = "ready";

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
                        this.inputTarget.disabled = true;
                        this.close();
                    } else {
                        this.hiddenTarget.disabled = false;
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
    }

    onInputClick = (event) => {
        if (this.hasDataListTarget) {
            const query = this.inputTarget.value.trim()
            this.fetchResults(query);
        }
    }


    onKeydown = (event) => {
        const handler = this[`on${event.key}Keydown`]
        if (handler) handler(event)
    }

    onEscapeKeydown = (event) => {
        if (!this.resultsShown) return
        this.hideAndRemoveOptions()
        event.stopPropagation()
        event.preventDefault()
    }

    onArrowDownKeydown = (event) => {
        const item = this.sibling(true)
        if (item) this.select(item)
        event.preventDefault()
    }

    onArrowUpKeydown = (event) => {
        const item = this.sibling(false)
        if (item) this.select(item)
        event.preventDefault()
    }

    onTabKeydown = (event) => {
        const selected = this.selectedOption
        if (selected) this.commit(selected)
    }

    onEnterKeydown = (event) => {
        const selected = this.selectedOption
        if (selected && this.resultsShown) {
            this.commit(selected)
            if (!this.hasSubmitOnEnterValue) {
                event.preventDefault()
            }
        }
    }

    onInputBlur = () => {
        if (this.mouseDown) return;
        if (this.state !== "finished" && this.state !== "start") {
            if (this.allowOtherValue) {
                this.fireChangeEvent(this.inputTarget.value, this.inputTarget.value, null);
            } else {
                this.clear();
            }
        }
        this.close();
    }

    commit(selected) {
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

        this.inputTarget.focus()
        this.state = "finished";
        this.fireChangeEvent(value, textValue, selected);
        this.hideAndRemoveOptions();
    }

    fireChangeEvent(value, textValue, selected) {
        this.element.dispatchEvent(
            new CustomEvent("autocomplete.change", {
                bubbles: true,
                detail: { value: value, textValue: textValue, selected: selected }
            })
        )
        this.element.dispatchEvent(new CustomEvent("change"), { bubbles: true });
    }

    clear() {
        this.inputTarget.value = ""
        if (this.hasHiddenTarget) this.hiddenTarget.value = ""
    }

    onResultsClick = (event) => {
        if (!(event.target instanceof Element)) return
        const selected = event.target.closest(optionSelector)
        if (selected) this.commit(selected)
    }

    onResultsMouseDown = () => {
        this.mouseDown = true
        this.resultsTarget.addEventListener("mouseup", () => {
            this.mouseDown = false
        }, { once: true })
    }
    onInputChange = () => {
        if (this.hasHiddenTarget) this.hiddenTarget.value = ""

        const query = this.inputTarget.value.trim()
        if ((query && query.length >= this.minLengthValue) || this.hasDataListTarget) {
            this.fetchResults(query)
        } else {
            this.hideAndRemoveOptions()
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
                    if (item.text.toLowerCase().includes(query.toLowerCase())) {
                        let itemHtml = document.createElement("li");
                        itemHtml.setAttribute("data-ac-value", item.value);
                        itemHtml.classList.add("list-group-item");
                        itemHtml.setAttribute("role", "option")
                        itemHtml.setAttribute("aria-selected", "false")
                        itemHtml.textContent = item.text;
                        //add a span around matching string to highlight it
                        itemHtml.innerHTML = itemHtml.innerHTML.replace(new RegExp(query, 'gi'), match => `<span class="text-primary">${match}</span>`);
                        this.resultsTarget.appendChild(itemHtml);
                    }
                }
                this.identifyOptions();
                this.open();
                return
            }
        }

        const url = this.buildURL(query)
        try {
            this.element.dispatchEvent(new CustomEvent("loadstart"))
            const html = await this.doFetch(url)
            this.replaceResults(html)
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

        this.resultsShown = false
        this.inputTarget.removeAttribute("aria-activedescendant")
        this.element.setAttribute("aria-expanded", "false")
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
        return { headers: { "X-Requested-With": "XMLHttpRequest" } } // override if you need
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