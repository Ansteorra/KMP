import { Controller } from "@hotwired/stimulus"

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Permission Add Role Controller
 * 
 * A specialized Stimulus controller for managing role assignments to permissions with real-time
 * validation and submit control. Provides seamless role selection validation for RBAC
 * permission configuration workflows.
 * 
 * Key Features:
 * - Role ID validation from autocomplete selection
 * - Real-time form validation with submit control
 * - Focus management for improved user experience
 * - ID parsing from underscore-separated values
 * - Integration with autocomplete role selection
 * - Bootstrap form integration with disabled state management
 * 
 * @class PermissionAddRole
 * @extends Controller
 * 
 * HTML Structure Example:
 * ```html
 * <form data-controller="permission-add-role">
 *   <div class="mb-3">
 *     <label for="role" class="form-label">Select Role</label>
 *     <input type="text" 
 *            data-permission-add-role-target="role"
 *            data-action="change->permission-add-role#checkSubmitEnable"
 *            data-controller="auto-complete"
 *            data-auto-complete-url-value="/roles/autocomplete"
 *            class="form-control"
 *            placeholder="Start typing role name...">
 *   </div>
 *   
 *   <button type="submit" 
 *           data-permission-add-role-target="submitBtn"
 *           class="btn btn-primary">
 *     Add Role to Permission
 *   </button>
 * </form>
 * ```
 */
class PermissionAddRole extends Controller {
    static targets = ["role", "form", "submitBtn"]

    /**
     * Validate role selection and control submit button state
     * Parses role ID from underscore-separated value format
     * Enables submission only when valid role is selected
     * Provides focus management for improved user workflow
     */
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