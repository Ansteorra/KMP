import { Controller } from "@hotwired/stimulus"

class MemberUniqueEmail extends Controller {
    static values = { url: String }

    connect() {
        this.element.removeAttribute('oninput');
        this.element.removeAttribute('oninvalid');
        this.element.addEventListener('change', this.checkEmail.bind(this));
    }
    disconnect(event) {
        this.element.removeEventListener('change', this.checkEmail.bind(this));
    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    checkEmail(event) {
        var email = this.element.value;
        if (email == '') {
            this.element.classList.remove('is-invalid');
            this.element.classList.remove('is-valid');
            this.element.setCustomValidity('');
            return;
        }
        var originalEmail = this.element.dataset.originalValue.toLowerCase();
        if (email.toLowerCase() == originalEmail) {
            this.element.classList.add('is-valid');
            this.element.classList.remove('is-invalid');
            return;
        }
        var checkEmailUrl = this.urlValue + '?nostack=yes&email=' + encodeURIComponent(email);
        fetch(checkEmailUrl, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                if (data) {
                    this.element.classList.add('is-invalid');
                    this.element.classList.remove('is-valid');
                    this.element.setCustomValidity('This email address is already taken.');
                } else {
                    this.element.classList.add('is-valid');
                    this.element.classList.remove('is-invalid');
                    this.element.setCustomValidity('');
                }
            });
    }

}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["member-unique-email"] = MemberUniqueEmail;