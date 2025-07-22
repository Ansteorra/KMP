import { Controller } from "@hotwired/stimulus"

/**
 * MemberVerifyForm Stimulus Controller
 * 
 * Manages member verification form with conditional field management and
 * dependent form controls. Provides dynamic field enabling/disabling based
 * on checkbox selections for parent/guardian and membership information.
 * 
 * Features:
 * - Conditional field management based on checkbox states
 * - Parent/guardian information toggle
 * - Membership information toggle
 * - Dynamic form field enabling/disabling
 * - Form workflow support for verification processes
 * 
 * Targets:
 * - scaMember: SCA member information field
 * - membershipNumber: Membership number input field
 * - membershipExpDate: Membership expiration date field
 * 
 * Usage:
 * <form data-controller="member-verify-form">
 *   <input type="checkbox" data-action="change->member-verify-form#toggleParent">
 *   <input data-member-verify-form-target="scaMember" disabled>
 *   
 *   <input type="checkbox" data-action="change->member-verify-form#toggleMembership">
 *   <input data-member-verify-form-target="membershipNumber" disabled>
 *   <input data-member-verify-form-target="membershipExpDate" disabled>
 * </form>
 */
class MemberVerifyForm extends Controller {
    static targets = ['scaMember',
        'membershipNumber',
        'membershipExpDate',
    ]

    /**
     * Toggle parent/guardian information field
     * Enables or disables SCA member field based on checkbox state
     * 
     * @param {Event} event - Change event from parent checkbox
     */
    toggleParent(event) {
        var checked = event.target.checked;
        this.scaMemberTarget.disabled = !checked;
    }

    /**
     * Toggle membership information fields
     * Enables or disables membership-related fields based on checkbox state
     * 
     * @param {Event} event - Change event from membership checkbox
     */
    toggleMembership(event) {
        var checked = event.target.checked;
        this.membershipNumberTarget.disabled = !checked;
        this.membershipExpDateTarget.disabled = !checked;
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-verify-form"] = MemberVerifyForm;