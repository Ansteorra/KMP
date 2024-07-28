import { Controller } from "@hotwired/stimulus"

class GitHubSubmitter extends Controller {
    static targets = ["success", "formBlock", "submitBtn", "issueLink", "form", "modal"];
    static values = { url: String };

    submit(event) {
        event.preventDefault();
        let url = this.urlValue;
        let form = this.formTarget;
        let formData = new FormData(form);

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (response.ok) {
                    return response.json();
                } else {
                    throw new Error('An error occurred while creating the issue.');
                }
            })
            .then(data => {
                if (data.message) {
                    alert("Error: " + data.message);
                    return;
                }
                form.reset();
                this.formBlockTarget.style.display = 'none';
                this.submitBtnTarget.style.display = 'none';
                this.issueLinkTarget.href = data.url;
                this.successTarget.style.display = 'block';
            })
            .catch(error => {
                console.error(error);
                alert('An error occurred while creating the issue.');
            });
    }

    modalTargetConnected() {
        this.modalTarget.addEventListener('hidden.bs.modal', () => {
            this.formBlockTarget.style.display = 'block';
            this.successTarget.style.display = 'none';
            this.submitBtnTarget.style.display = 'block';
        });
    }
    modalTargetDisconnected() {
        this.modalTarget.removeEventListener('hidden.bs.modal', () => {
            this.formBlockTarget.style.display = 'block';
            this.successTarget.style.display = 'none';
            this.submitBtnTarget.style.display = 'block';
        });
    }
    connect() {
        this.formBlockTarget.style.display = 'block';
        this.successTarget.style.display = 'none';
        this.submitBtnTarget.style.display = 'block';
    }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["github-submitter"] = GitHubSubmitter;