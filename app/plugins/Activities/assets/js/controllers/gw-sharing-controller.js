import { Controller } from "@hotwired/stimulus"



class GWSharingController extends Controller {
    static targets = ["form"]


    //when the switch is changed then submit the form
    submit() {
        this.formTarget.submit();
    }

}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}

window.Controllers["gw_sharing"] = GWSharingController;