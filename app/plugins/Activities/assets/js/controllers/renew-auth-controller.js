import { Controller } from "@hotwired/stimulus"



class ActivitiesRenewAuthorization extends Controller {
    static values = {
        url: String,
    }
    static targets = ["activity", "approvers", "submitBtn", "memberId", "id"]
    static outlets = ["outlet-btn"]

    setId(event) {
        this.idTarget.value = event.detail.id;
        this.activityTarget.value = event.detail.activity;
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
            let activityId = this.activityTarget.value;
            let url = this.urlValue + "/" + activityId + "/" + this.memberIdTarget.value;
            fetch(url, this.optionsForFetch())
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

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
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

window.Controllers["activities-renew-auth"] = ActivitiesRenewAuthorization;