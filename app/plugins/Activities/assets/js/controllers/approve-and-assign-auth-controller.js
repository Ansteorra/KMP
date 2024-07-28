import { Controller } from "@hotwired/stimulus"



class ActivitiesApproveAndAssignAuthorization extends Controller {
    static values = {
        url: String,
    }
    static targets = ["approvers", "submitBtn", "id"]
    static outlets = ["grid-btn"]

    setId(event) {
        this.idTarget.value = event.detail.id;
        this.getApprovers();
    }
    gridBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }
    gridBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    getApprovers() {
        if (this.hasApproversTarget) {
            this.approversTarget.value = "";
            let activityId = this.idTarget.value;
            let url = this.urlValue + "/" + activityId;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    let list = [];
                    data.forEach((item) => {
                        list.push({
                            value: item.id,
                            text: item.sca_name
                        });
                    });
                    this.approversTarget.options = list;
                    this.submitBtnTarget.disabled = true;
                    this.approversTarget.disabled = false;
                });
        }
    }

    checkReadyToSubmit() {
        let approverValue = this.approversTarget.value;
        let approverNum = parseInt(approverValue);
        if (approverNum > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }


}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}

window.Controllers["activities-approve-and-assign-auth"] = ActivitiesApproveAndAssignAuthorization;