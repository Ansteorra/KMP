/**
 * Officer Edit Controller - Populates edit form from officer selection events
 *
 * Targets: deputyDescBlock, deputyDesc, id, emailAddress, emailAddressBlock
 * Outlets: outlet-btn
 */

import { Controller } from "@hotwired/stimulus"

class EditOfficer extends Controller {
    static targets = ["deputyDescBlock", "deputyDesc", "id", "emailAddress", "emailAddressBlock"]
    static outlets = ["outlet-btn"]

    /**
     * Populate edit form with officer data from selection event.
     * @param {Event} event - Event containing officer data (id, deputy_description, email_address, is_deputy)
     */
    setId(event) {
        this.idTarget.value = event.detail.id;
        this.deputyDescTarget.value = event.detail.deputy_description;
        this.emailAddressTarget.value = event.detail.email_address;
        if (event.detail.is_deputy == '1') {
            this.deputyDescBlockTarget.classList.remove('d-none');
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

    /** Register setId listener when outlet button connects. */
    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    /** Remove setId listener when outlet button disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officers-edit-officer"] = EditOfficer;