import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';

/** Configure the dialog on every visit; release listeners and delayed work on exit. */
class GatheringStaffDialogController extends Controller {
    static values = {
        bindings: Object
    };
    connect() {
        this.lifecycle = createControllerScope();
        const lifecycle = this.lifecycle;
        const { listen, fetch } = lifecycle;
        const bindings = this.bindingsValue;
        // Add Staff Modal Logic
        const addModal = document.getElementById('addStaffModal');
        if (addModal) {
            const isStewardCheckbox = document.getElementById('add-is-steward');
            const memberPublicIdInput = addModal.querySelector('[data-ac-target="hidden"]');
            const memberAutocomplete = document.getElementById('add-member-autocomplete'); // Text field from autocomplete
            const emailInput = document.getElementById('add-email');
            const phoneInput = document.getElementById('add-phone');
            const stewardNotice = document.getElementById('add-steward-notice');
            const autoFillNotice = document.getElementById('add-autofill-notice');
            const showOnPublicPageCheckbox = document.getElementById('add-show-on-public-page');
            function updateAddStewardFields() {
                if (isStewardCheckbox && isStewardCheckbox.checked) {
                    if (stewardNotice) stewardNotice.style.display = 'block';

                    // Stewards must always show on public page
                    if (showOnPublicPageCheckbox) {
                        showOnPublicPageCheckbox.checked = true;
                        showOnPublicPageCheckbox.disabled = true;
                    }

                    // If a member is already selected, fetch their contact info
                    if (memberPublicIdInput && memberPublicIdInput.value) {
                        fetchMemberContactInfo(memberPublicIdInput.value);
                    }
                } else {
                    if (stewardNotice) stewardNotice.style.display = 'none';

                    // Non-stewards can choose
                    if (showOnPublicPageCheckbox) {
                        showOnPublicPageCheckbox.disabled = false;
                    }
                }
            }
            function fetchMemberContactInfo(memberPublicId) {
                if (!memberPublicId) return;
                if (autoFillNotice) autoFillNotice.style.display = 'block';

                // The server authorizes both the gathering and target member.
                const gatheringPublicId = bindings.gatheringPublicId;
                fetch(bindings.contactUrl + "?member_public_id=" + memberPublicId + '&gathering_public_id=' + gatheringPublicId).then(response => response.json()).then(data => {
                    if (lifecycle.signal.aborted || memberPublicIdInput?.value !== memberPublicId) return;
                    if (data.error) {
                        if (autoFillNotice) {
                            autoFillNotice.textContent = 'Enter contact details provided for this role.';
                            autoFillNotice.setAttribute('role', 'status');
                        }
                        return;
                    }

                    // Always populate email and phone from AMP member if available
                    // Only fill if fields are currently empty to avoid overwriting user edits
                    if (data.email && emailInput && !emailInput.value) {
                        emailInput.value = data.email;
                    }
                    if (data.phone && phoneInput && !phoneInput.value) {
                        phoneInput.value = data.phone;
                    }
                }).catch(error => console.error('Error fetching member info:', error));
            }

            // Also listen on the parent element (the div with data-controller)
            const autocompleteContainer = memberAutocomplete?.closest('[data-controller="ac"]');
            if (autocompleteContainer) {
                listen(autocompleteContainer, 'autocomplete.change', function (event) {
                    if (event.detail && event.detail.value) {
                        fetchMemberContactInfo(event.detail.value);
                    }
                });
            }
            if (isStewardCheckbox) {
                listen(isStewardCheckbox, 'change', updateAddStewardFields);
            }

            // Reset form when modal is closed
            listen(addModal, 'hidden.bs.modal', function () {
                document.getElementById('addStaffForm').reset();
                if (stewardNotice) stewardNotice.style.display = 'none';
                if (autoFillNotice) autoFillNotice.style.display = 'none';

                // Reset autocomplete
                if (memberPublicIdInput) memberPublicIdInput.value = '';
                if (memberAutocomplete) memberAutocomplete.value = '';
            });

            // Initialize on modal show
            listen(addModal, 'shown.bs.modal', function () {
                updateAddStewardFields();
            });
        }

        // Edit Staff Modal Logic
        const editModal = document.getElementById('editStaffModal');
        if (editModal) {
            const editIsStewardCheckbox = document.getElementById('edit-is-steward');
            const editShowOnPublicPageCheckbox = document.getElementById('edit-show-on-public-page');

            // Update show_on_public_page based on is_steward changes
            if (editIsStewardCheckbox) {
                listen(editIsStewardCheckbox, 'change', function () {
                    if (this.checked) {
                        // Stewards must always show on public page
                        if (editShowOnPublicPageCheckbox) {
                            editShowOnPublicPageCheckbox.checked = true;
                            editShowOnPublicPageCheckbox.disabled = true;
                        }
                    } else {
                        // Non-stewards can choose
                        if (editShowOnPublicPageCheckbox) {
                            editShowOnPublicPageCheckbox.disabled = false;
                        }
                    }
                });
            }
            listen(editModal, 'show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;
                const staffId = button.getAttribute('data-staff-id');
                const staffName = button.getAttribute('data-staff-name');
                const staffRole = button.getAttribute('data-staff-role');
                const staffEmail = button.getAttribute('data-staff-email');
                const staffPhone = button.getAttribute('data-staff-phone');
                const staffNotes = button.getAttribute('data-staff-notes');
                const isSteward = button.getAttribute('data-is-steward') === '1';
                const showOnPublicPage = button.getAttribute('data-show-on-public-page') === '1';

                // Update form action
                const form = document.getElementById('editStaffForm');
                form.action = bindings.editUrl.replace('XXX', staffId);

                // Populate fields
                document.getElementById('edit-staff-name-display').textContent = staffName;
                document.getElementById('edit-is-steward').checked = isSteward;
                document.getElementById('edit-role').value = staffRole;
                document.getElementById('edit-email').value = staffEmail;
                document.getElementById('edit-phone').value = staffPhone;
                document.getElementById('edit-contact-notes').value = staffNotes;
                document.getElementById('edit-show-on-public-page').checked = showOnPublicPage;

                // Handle show_on_public_page checkbox state
                if (isSteward) {
                    // Stewards must always show on public page
                    document.getElementById('edit-show-on-public-page').disabled = true;
                } else {
                    // Non-stewards can choose
                    document.getElementById('edit-show-on-public-page').disabled = false;
                }

                // Show/hide steward notices
                const stewardInfo = document.getElementById('edit-steward-info');
                const stewardNotice = document.getElementById('edit-steward-notice');
                if (isSteward) {
                    if (stewardInfo) stewardInfo.style.display = 'block';
                    if (stewardNotice) stewardNotice.style.display = 'block';
                } else {
                    if (stewardInfo) stewardInfo.style.display = 'none';
                    if (stewardNotice) stewardNotice.style.display = 'none';
                }
            });
        }
    }
    disconnect() {
        this.lifecycle?.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['gathering-staff-dialog'] = GatheringStaffDialogController;
export default GatheringStaffDialogController;
