import { Controller } from "@hotwired/stimulus"

class EditOfficer extends Controller {
    static targets = ["deputyDescBlock", "deputyDesc", "id", "emailAddress", "emailAddressBlock"]

    static outlets = ["outlet-btn"]

    setId(event) {

        this.idTarget.value = event.detail.id;
        this.deputyDescTarget.value = event.detail.deputy_description;
        this.emailAddressTarget.value = event.detail.email_address;
        if (event.detail.is_deputy == '1') {
            this.deputyDescBlockTarget.classList.remove('d-none');
            //remove : from the deputy_description and trim
            this.deputyDescTarget.value = event.detail.deputy_description.replace(/:/g, '').trim();
        } else {
            this.deputyDescBlockTarget.classList.add('d-none');
        }
        if (event.detail.email_address != '') {
            this.emailAddressBlockTarget.classList.remove('d-none');
        } else {
            this.emailAddressBlockTarget.classList.add('d-none');
        }
    }
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officers-edit-officer"] = EditOfficer;