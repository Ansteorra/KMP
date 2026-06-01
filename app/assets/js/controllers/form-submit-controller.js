import { Controller } from "@hotwired/stimulus"

class FormSubmitController extends Controller {
    submit(event) {
        const form = event.currentTarget.form || event.currentTarget.closest("form")
        if (form instanceof HTMLFormElement) {
            form.requestSubmit()
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["form-submit"] = FormSubmitController
