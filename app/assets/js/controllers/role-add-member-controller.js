import { Controller } from "@hotwired/stimulus"


class RoleAddMember extends Controller {
    static targets = ["scaMember", "form", "submitBtn", "branch"]

    checkSubmitEnable() {
        let scaMember = this.scaMemberTarget.value;
        let memberId = Number(scaMember.replace(/_/g, ""));
        let require_branch = this.hasBranchTarget;

        if (memberId > 0) {
            if (require_branch && this.branchTarget.value == "") {
                this.submitBtnTarget.disabled = true;
                return;
            }
            this.submitBtnTarget.disabled = false;
            this.submitBtnTarget.focus();
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["role-add-member"] = RoleAddMember;