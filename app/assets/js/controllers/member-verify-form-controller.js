import { Controller } from "@hotwired/stimulus"

class MemberVerifyForm extends Controller {
    static targets = ['scaMember',
        'membershipNumber',
        'membershipExpDate',
    ]
    toggleParent(event) {
        var checked = event.target.checked;
        this.scaMemberTarget.disabled = !checked;
    }
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