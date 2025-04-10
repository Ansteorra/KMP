import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'status'];

    connect() {
        // Controller initialization when it connects to the DOM
        console.log('Permissions matrix controller connected');
    }

    togglePermission(event) {
        const checkbox = event.target;
        const checked = checkbox.checked;
        const permissionId = checkbox.dataset.permissionId;
        const policyClass = checkbox.dataset.policyClass;
        const policyMethod = checkbox.dataset.policyMethod;

        // Disable the checkbox during the AJAX request
        checkbox.disabled = true;

        // Send AJAX request to update the permission
        this.updatePermissionPolicy(permissionId, policyClass, policyMethod, checked)
            .then(() => {
                this.showSaveStatus('Saved', 'success');
            })
            .catch(error => {
                console.error('Error updating permission:', error);
                this.showSaveStatus('Error saving changes', 'error');
                // Revert the checkbox state since the save failed
                checkbox.checked = !checked;
            })
            .finally(() => {
                // Re-enable the checkbox after the request completes
                checkbox.disabled = false;
            });
    }

    // Function to update permission policy via AJAX
    updatePermissionPolicy(permissionId, policyClass, policyMethod, enabled) {
        return fetch('/permissions/update-policy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken()
            },
            body: JSON.stringify({
                permission_id: permissionId,
                policy_class: policyClass,
                policy_method: policyMethod,
                enabled: enabled
            })
        }).then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        });
    }

    // Function to get CSRF token
    getCsrfToken() {
        return document.querySelector('meta[name="csrfToken"]').getAttribute('content');
    }

    // Function to show save status
    showSaveStatus(message, type) {
        const statusEl = this.statusTarget;
        statusEl.textContent = message;
        statusEl.className = 'save-status ' + type;

        // Hide after 3 seconds
        setTimeout(() => {
            statusEl.className = 'save-status';
        }, 3000);
    }
}
