import { Controller } from "@hotwired/stimulus"
class OutletButton extends Controller {
    static values = {
        btnData: Object,
        requireData: Boolean,
    }
    btnDataValueChanged() {
        console.log(this.btnDataValue);
        if (this.btnDataValue === null) {
            this.btnDataValue = {};
        }
        if (this.requireDataValue && Object.keys(this.btnDataValue).length === 0) {
            this.element.disabled = true;
        } else {
            this.element.disabled = false;
        }
    }
    addBtnData(data) {
        this.btnDataValue = data;
    }
    fireNotice(event) {
        let btnData = this.btnDataValue;
        this.dispatch("outlet-button-clicked", { detail: btnData });
    }
    addListener(callback) {
        this.element.addEventListener("outlet-btn:outlet-button-clicked", callback);
    }
    removeListener(callback) {
        this.element.removeEventListener("outlet-btn:outlet-button-clicked", callback);
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["outlet-btn"] = OutletButton;