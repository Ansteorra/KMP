import { Controller } from "@hotwired/stimulus"


class RoleAddMember extends Controller {
    static targets = ["scaMember", "form", "submitBtn"]

    checkSubmitEnable() {
        let scaMember = this.scaMemberTarget.value;
        let memberId = Number(scaMember.replace(/_/g, ""));
        if (memberId > 0) {
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