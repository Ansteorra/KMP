import { Controller } from "@hotwired/stimulus"

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Role Add Permission Controller
 * 
 * A specialized Stimulus controller for managing role permission assignments with real-time
 * validation and submit control. Provides seamless permission selection validation for RBAC
 * role configuration workflows.
 * 
 * Key Features:
 * - Permission ID validation from autocomplete selection
 * - Real-time form validation with submit control
 * - Focus management for improved user experience
 * - ID parsing from underscore-separated values
 * - Integration with autocomplete permission selection
 * - Bootstrap form integration with disabled state management
 * 
 * @class RoleAddPermission
 * @extends Controller
 * 
 * HTML Structure Example:
 * ```html
 * <form data-controller="role-add-permission">
 *   <div class="mb-3">
 *     <label for="permission" class="form-label">Select Permission</label>
 *     <input type="text" 
 *            data-role-add-permission-target="permission"
 *            data-action="change->role-add-permission#checkSubmitEnable"
 *            data-controller="auto-complete"
 *            data-auto-complete-url-value="/permissions/autocomplete"
 *            class="form-control"
 *            placeholder="Start typing permission name...">
 *   </div>
 *   
 *   <button type="submit" 
 *           data-role-add-permission-target="submitBtn"
 *           class="btn btn-primary">
 *     Add Permission to Role
 *   </button>
 * </form>
 * ```
 */
class RoleAddPermission extends Controller {
    static targets = ["permission", "form", "submitBtn"]

    /**
     * Validate permission selection and control submit button state
     * Parses permission ID from underscore-separated value format
     * Enables submission only when valid permission is selected
     * Provides focus management for improved user workflow
     */
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