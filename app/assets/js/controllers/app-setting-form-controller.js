const { Controller } = require("@hotwired/stimulus");

class AppSettingForm extends Controller {
    static targets = ["submitBtn", "form"]

    submit(event) {
        event.preventDefault()
        this.formTarget.submit()
    }

    enableSubmit() {
        this.submitBtnTarget.disabled = false;
        this.submitBtnTarget.focus();
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["app-setting-form"] = AppSettingForm;