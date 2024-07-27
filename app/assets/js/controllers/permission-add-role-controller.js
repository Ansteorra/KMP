import { Controller } from "@hotwired/stimulus"

class PermissionAddRole extends Controller {
    static targets = ["role", "form", "submitBtn"]

    checkSubmitEnable() {
        let role = this.roleTarget.value;
        let roleId = Number(role.replace(/_/g, ""));
        if (roleId > 0) {
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
window.Controllers["permission-add-role"] = PermissionAddRole;