import { Controller } from "@hotwired/stimulus"


class RoleAddPermission extends Controller {
    static targets = ["permission", "form", "submitBtn"]

    checkSubmitEnable() {
        let permission = this.permissionTarget.value;
        let permissionId = Number(permission.replace(/_/g, ""));
        if (permissionId > 0) {
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
window.Controllers["role-add-permission"] = RoleAddPermission;