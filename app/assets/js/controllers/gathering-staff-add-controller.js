import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';

/** Configure the dialog on every visit; release listeners and delayed work on exit. */
class GatheringStaffAddController extends Controller {
    static values = {
        bindings: Object
    };
    connect() {
        this.lifecycle = createControllerScope();
        const lifecycle = this.lifecycle;
        const { listen, fetch } = lifecycle;
        const bindings = this.bindingsValue;
        const isStewardCheckbox = document.getElementById('is-steward-checkbox');
        const memberSelect = document.getElementById('member-select');
        const scaNameInput = document.getElementById('sca-name-input');
        const emailInput = document.getElementById('email-input');
        const phoneInput = document.getElementById('phone-input');
        const stewardNotice = document.getElementById('steward-contact-notice');
        const autoFillNotice = document.getElementById('contact-auto-fill-notice');
        const showOnPublicPageCheckbox = document.getElementById('show-on-public-page-checkbox');

        // Update UI based on steward status
        function updateStewardFields() {
            if (isStewardCheckbox.checked) {
                stewardNotice.style.display = 'block';
                // Stewards must be AMP members
                scaNameInput.disabled = true;
                scaNameInput.value = '';
                // Stewards must always show on public page
                showOnPublicPageCheckbox.checked = true;
                showOnPublicPageCheckbox.disabled = true;
            } else {
                stewardNotice.style.display = 'none';
                scaNameInput.disabled = false;
                showOnPublicPageCheckbox.disabled = false;
            }
        }

        // Update UI based on member selection
        function updateMemberFields() {
            if (memberSelect.value) {
                // AMP member selected - disable SCA name field
                scaNameInput.disabled = true;
                scaNameInput.value = '';
                autoFillNotice.style.display = 'block';

                // Fetch member contact info
                fetchMemberContactInfo(memberSelect.value);
            } else {
                // No member selected
                if (!isStewardCheckbox.checked) {
                    scaNameInput.disabled = false;
                }
                autoFillNotice.style.display = 'none';
            }
        }

        // Fetch member contact info via AJAX
        function fetchMemberContactInfo(memberId) {
            fetch(bindings.contactUrl + "?member_id=" + memberId).then(response => response.json()).then(data => {
                if (lifecycle.signal.aborted || memberSelect.value !== memberId) return;
                if (!data.error) {
                    if (data.email && !emailInput.value) {
                        emailInput.value = data.email;
                    }
                    if (data.phone && !phoneInput.value) {
                        phoneInput.value = data.phone;
                    }
                }
            }).catch(error => console.error('Error fetching member info:', error));
        }

        // Event listeners
        listen(isStewardCheckbox, 'change', updateStewardFields);
        listen(memberSelect, 'change', updateMemberFields);

        // Initial state
        updateStewardFields();
        updateMemberFields();
    }
    disconnect() {
        this.lifecycle?.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['gathering-staff-add'] = GatheringStaffAddController;
export default GatheringStaffAddController;
