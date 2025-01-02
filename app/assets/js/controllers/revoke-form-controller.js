import { Controller } from "@hotwired/stimulus"

class RevokeForm extends Controller {
    static values = {
        url: String,
    }
    static targets = ["submitBtn", "reason", "id"]

    static outlets = ["outlet-btn"]

    setId(event) {
        this.idTarget.value = event.detail.id;
    }
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    checkReadyToSubmit() {
        let reasonValue = this.reasonTarget.value;
        if (reasonValue.length > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }


    connect() {
        this.submitBtnTarget.disabled = true;
    }

}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["revoke-form"] = RevokeForm;