import { Controller } from "@hotwired/stimulus"
class FilterGrid extends Controller {
    submitForm(event) {
        console.log("submitting form");
        this.element.submit();
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["filter-grid"] = FilterGrid;