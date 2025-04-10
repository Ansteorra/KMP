// This file can be replaced by proper Stimulus registration
// If using import maps or a bundler, this file may not be necessary
// as controllers can be auto-registered

// Import and register the controller manually if needed
// import { Application } from '@hotwired/stimulus';
// import PermissionsMatrixController from './controllers/permissions_matrix_controller';
// 
// const application = Application.start();
// application.register('permissions-matrix', PermissionsMatrixController);

document.addEventListener('DOMContentLoaded', function () {
    // Find all policy checkboxes
    const policyCheckboxes = document.querySelectorAll('.policy-checkbox');

    // Add change event listeners to each checkbox
    policyCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function (event) {
            const checked = event.target.checked;
            const permissionId = this.dataset.permissionId;
            const policyClass = this.dataset.policyClass;
            const policyMethod = this.dataset.policyMethod;

            // Disable the checkbox during the AJAX request
            this.disabled = true;

            // Send AJAX request to update the permission
            updatePermissionPolicy(permissionId, policyClass, policyMethod, checked)
                .then(() => {
                    showSaveStatus('Saved', 'success');
                })
                .catch(error => {
                    console.error('Error updating permission:', error);
                    showSaveStatus('Error saving changes', 'error');
                    // Revert the checkbox state since the save failed
                    this.checked = !checked;
                })
                .finally(() => {
                    // Re-enable the checkbox after the request completes
                    this.disabled = false;
                });
        });
    });

    // Function to update permission policy via AJAX
    function updatePermissionPolicy(permissionId, policyClass, policyMethod, enabled) {
        return fetch('/permissions/update-policy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken()
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
    function getCsrfToken() {
        return document.querySelector('meta[name="csrfToken"]').getAttribute('content');
    }

    // Function to show save status
    function showSaveStatus(message, type) {
        const statusEl = document.getElementById('save-status');
        statusEl.textContent = message;
        statusEl.className = 'save-status ' + type;

        // Hide after 3 seconds
        setTimeout(() => {
            statusEl.className = 'save-status';
        }, 3000);
    }
});
