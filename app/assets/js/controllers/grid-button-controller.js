import { Controller } from "@hotwired/stimulus"
class GridButton extends Controller {
    static values = {
        rowData: Object,
    }
    fireNotice(event) {
        let rowData = this.rowDataValue;
        this.dispatch("grid-button-clicked", { detail: rowData });
    }
    addListener(callback) {
        this.element.addEventListener("grid-btn:grid-button-clicked", callback);
    }
    removeListener(callback) {
        this.element.removeEventListener("grid-btn:grid-button-clicked", callback);
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["grid-btn"] = GridButton;