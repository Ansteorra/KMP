import { Controller } from "@hotwired/stimulus"

/**
 * GitHub Submitter Controller - AJAX feedback submission to GitHub Issues
 *
 * Targets: success, formBlock, submitBtn, issueLink, form, modal
 * Values: url (String) - API endpoint for issue submission
 */
class GitHubSubmitter extends Controller {
    static targets = ["success", "formBlock", "submitBtn", "issueLink", "form", "modal"];


    // Define configurable values from HTML data attributes
    static values = { url: String };

    /**
     * Handle form submission via AJAX to GitHub Issues API.
     * @param {Event} event - Form submission event
     */
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

    /** Reset UI state when modal is hidden. */
    modalTargetConnected() {
        this.modalTarget.addEventListener('hidden.bs.modal', () => {
            this.formBlockTarget.style.display = 'block';
            this.successTarget.style.display = 'none';
            this.submitBtnTarget.style.display = 'block';
        });
    }

    /** Clean up modal event listeners. */
    modalTargetDisconnected() {
        this.modalTarget.removeEventListener('hidden.bs.modal', () => {
            this.formBlockTarget.style.display = 'block';
            this.successTarget.style.display = 'none';
            this.submitBtnTarget.style.display = 'block';
        });
    }

    /** Initialize UI state on controller connect. */
    connect() {
        this.formBlockTarget.style.display = 'block';
        this.successTarget.style.display = 'none';
        this.submitBtnTarget.style.display = 'block';
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["github-submitter"] = GitHubSubmitter;