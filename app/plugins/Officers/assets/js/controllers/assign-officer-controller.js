import { Controller } from "@hotwired/stimulus"

class OfficersAssignOfficer extends Controller {
    static values = {
        url: String,
    }
    static targets = ["assignee", "submitBtn", "deputyDescBlock", "deputyDesc", "office", "endDateBlock", "endDate"]
    static outlets = ["outlet-btn", "member-serach"]

    setOfficeQuestions() {
        this.deputyDescBlockTarget.classList.add('d-none');
        this.endDateBlockTarget.classList.add('d-none');
        this.endDateTarget.disabled = true;
        this.deputyDescTarget.disabled = true;
        var officeVal = this.officeTarget.value;
        // set the member search url by taking the current url and removing the last part (if it is a number) and replacing it with the officeVal
        var url = this.assigneeTarget.getAttribute('data-ac-url-value');
        var urlParts = url.split('/');
        var lastPart = urlParts[urlParts.length - 1];
        if (parseInt(lastPart)) {
            urlParts.pop();
        }
        urlParts.push(officeVal);
        var newUrl = urlParts.join('/');
        this.assigneeTarget.setAttribute('data-ac-url-value', newUrl);
        var office = this.officeTarget.options.find(option => option.value == officeVal);
        if (office) {
            if (office.data.is_deputy) {
                this.deputyDescBlockTarget.classList.remove('d-none');
                this.endDateBlockTarget.classList.remove('d-none');
                this.endDateTarget.disabled = false;
                this.deputyDescTarget.disabled = false;
            }
            this.checkReadyToSubmit();
            return;
        }
    }

    checkReadyToSubmit() {
        var assigneeVal = this.assigneeTarget.value;
        var officeVal = this.officeTarget.value;
        var assignId = parseInt(assigneeVal);
        var officeId = parseInt(officeVal);
        if (assignId > 0 && officeId > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }
    endDateTargetConnected() {
        this.endDateTarget.disabled = true;
    }
    deputyDescTargetConnected() {
        this.deputyDescTarget.disabled = true;
    }
    connect() {
        this.deputyDescBlockTarget.classList.add('d-none');
        this.endDateBlockTarget.classList.add('d-none');
    }


}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}

window.Controllers["officers-assign-officer"] = OfficersAssignOfficer;