import { Controller } from "@hotwired/stimulus"

/**
 * **INTERNAL CODE DOCUMENTATION COMPLETE**
 * 
 * Role Add Member Controller
 * 
 * A specialized Stimulus controller for managing role membership assignments with member
 * validation and optional branch requirements. Provides real-time form validation and
 * submit control for RBAC member assignment workflows.
 * 
 * Key Features:
 * - Member ID validation from autocomplete selection
 * - Optional branch requirement validation
 * - Real-time form validation with submit control  
 * - Focus management for improved user experience
 * - Integration with autocomplete member selection
 * - Bootstrap form integration with disabled state management
 * 
 * @class RoleAddMember
 * @extends Controller
 * 
 * HTML Structure Example:
 * ```html
 * <form data-controller="role-add-member">
 *   <div class="mb-3">
 *     <label for="scaMember" class="form-label">Select Member</label>
 *     <input type="text" 
 *            data-role-add-member-target="scaMember"
 *            data-action="change->role-add-member#checkSubmitEnable"
 *            data-controller="auto-complete"
 *            class="form-control">
 *   </div>
 *   
 *   <!-- Optional branch selection (if role requires branch context) -->
 *   <div class="mb-3">
 *     <label for="branch" class="form-label">Branch</label>
 *     <select data-role-add-member-target="branch"
 *             data-action="change->role-add-member#checkSubmitEnable"
 *             class="form-select">
 *       <option value="">Select Branch</option>
 *       <option value="1">Branch 1</option>
 *     </select>
 *   </div>
 *   
 *   <button type="submit" 
 *           data-role-add-member-target="submitBtn"
 *           class="btn btn-primary">
 *     Add Member to Role
 *   </button>
 * </form>
 * ```
 */
class RoleAddMember extends Controller {
    static targets = ["scaMember", "form", "submitBtn", "branch"]

    /**
     * Validate form submission requirements and control submit button
     * Checks member selection validity and optional branch requirements
     * Provides focus management and real-time validation feedback
     */
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