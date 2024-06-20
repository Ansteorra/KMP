import $ from 'jquery';
import jQuery from 'jquery';

const DEFAULTS = {
    treshold: 2,
    maximumItems: 5,
    highlightTyped: true,
    highlightClass: "text-primary",
};

class Autocomplete {
    DEFAULTS = {
        treshold: 2,
        maximumItems: 5,
        highlightTyped: true,
        highlightClass: "text-primary",
    }

    constructor(field, options) {
        this.field = field;
        this.options = Object.assign({}, this.DEFAULTS, options);
        this.dropdown = null;

        field.parentNode.classList.add("dropdown");
        field.setAttribute("data-toggle", "dropdown");
        field.classList.add("dropdown-toggle");

        const dropdown = this.ce(`<div class="dropdown-menu" ></div>`);
        if (this.options.dropdownClass)
            dropdown.classList.add(this.options.dropdownClass);

        this.insertAfter(dropdown, field);

        this.dropdown = new bootstrap.Dropdown(
            field,
            this.options.dropdownOptions,
        );

        field.addEventListener("click", (e) => {
            if (this.createItems() === 0) {
                e.stopPropagation();
                this.dropdown.hide();
            }
        });

        field.addEventListener("input", () => {
            const lookup = this.field.value;
            if (this.options.onInput && lookup.length >= this.options.treshold)
                this.options.onInput();
            this.renderIfNeeded();
        });

        field.addEventListener("keydown", (e) => {
            if (e.keyCode === 27) {
                this.dropdown.hide();
                return;
            }
        });
    }

    setData(data) {
        this.options.data = data;
        this.renderIfNeeded();
    }

    renderIfNeeded() {
        if (this.createItems() > 0) this.dropdown.show();
        else this.field.on('click');
    }

    createItem(lookup, item) {
        let label;
        if (this.options.highlightTyped) {
            const idx = item.label.toLowerCase().indexOf(lookup.toLowerCase());
            const className = Array.isArray(this.options.highlightClass)
                ? this.options.highlightClass.join(" ")
                : typeof this.options.highlightClass == "string"
                    ? this.options.highlightClass
                    : "";
            label =
                item.label.substring(0, idx) +
                `<span class="${className}">${item.label.substring(idx, idx + lookup.length)}</span>` +
                item.label.substring(idx + lookup.length, item.label.length);
        } else label = item.label;
        return this.ce(
            `<button type="button" class="dropdown-item" data-value="${item.value}">${label}</button>`,
        );
    }

    /**
 * @param html
 * @returns {Node}
 */
    ce(html) {
        let div = document.createElement("div");
        div.innerHTML = html;
        return div.firstChild;
    }

    /**
     * @param elem
     * @param refElem
     * @returns {*}
     */
    insertAfter(elem, refElem) {
        return refElem.parentNode.insertBefore(elem, refElem.nextSibling);
    }

    createItems() {
        const lookup = this.field.value;
        if (lookup.length < this.options.treshold) {
            this.dropdown.hide();
            return 0;
        }

        const items = this.field.nextSibling;
        items.innerHTML = "";

        let count = 0;

        for (let i = 0; i < this.options.data.length; i++) {
            const { label, value } = this.options.data[i];
            const item = { label, value };
            if (item.label.toLowerCase().indexOf(lookup.toLowerCase()) >= 0) {
                items.appendChild(this.createItem(lookup, item));
                if (
                    this.options.maximumItems > 0 &&
                    ++count >= this.options.maximumItems
                )
                    break;
            }
        }

        this.field.nextSibling
            .querySelectorAll(".dropdown-item")
            .forEach((item) => {
                item.addEventListener("click", (e) => {
                    let dataValue = e.target.getAttribute("data-value");
                    this.field.value = e.target.innerText;
                    if (this.options.onSelectItem)
                        this.options.onSelectItem({
                            value: dataValue,
                            label: e.target.innerText,
                        });
                    this.dropdown.hide();
                });
            });

        return items.childNodes.length;
    }
}
export default Autocomplete;