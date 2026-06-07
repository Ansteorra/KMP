
import { Controller } from "@hotwired/stimulus";

/**
 * Awards Recommendation Quick Edit Form Controller
 *
 * Streamlined modal form for workflow-centric recommendation updates.
 */
class AwardsRecommendationQuickEditForm extends Controller {
    static targets = [
        "domain",
        "award",
        "currentAwardId",
        "currentApprovalProcessId",
        "approvalWorkflowRestartConfirmed",
        "reason",
        "specialty",
        "recId",
        "memberId",
        "turboFrame",
    ];
    static values = {
        awardListUrl: String,
        formUrl: String,
        turboFrameUrl: String,
    };
    static outlets = ['outlet-btn'];

    setId(event) {
        if (event.detail.id) {
            this.turboFrameTarget.setAttribute("src", this.turboFrameUrlValue + "/" + event.detail.id);
            const form = document.getElementById("recommendation_form");
            if (form) {
                form.action = this.formUrlValue + "/" + event.detail.id;
            }
        }
    }

    outletBtnOutletConnected(outlet, element) {
        outlet.addListener(this.setId.bind(this));
    }

    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setId.bind(this));
    }

    onTurboFrameLoad() {
        const locked = this.turboFrameTarget.querySelector('[data-recommendation-locked]');
        const submitBtn = document.getElementById('recommendation_submit');
        if (submitBtn) {
            submitBtn.disabled = Boolean(locked);
        }
        window.dispatchEvent(new CustomEvent('page-context:sync'));
    }

    async submit(event) {
        if (this.turboFrameTarget.querySelector('[data-recommendation-locked]')) {
            event.preventDefault?.();
            event.stopImmediatePropagation?.();
            return;
        }
        if (this.shouldConfirmApprovalWorkflowRestart() && await this.confirmApprovalWorkflowRestart(event)) {
            return;
        }
    }

    setAward(event) {
        this.awardTarget.value = event.target.dataset.awardId;
        this.resetApprovalWorkflowRestartConfirmation();
        if (this.awardTarget.value !== "") {
            this.populateSpecialties(event);
        }
    }

    populateAwardDescriptions(event) {
        let url = this.awardListUrlValue + "/" + event.target.value;
        if (this.hasCurrentAwardIdTarget && this.currentAwardIdTarget.value) {
            url += `?current_award_id=${encodeURIComponent(this.currentAwardIdTarget.value)}`;
        }
        fetch(url, this.optionsForFetch())
            .then(response => response.json())
            .then(data => {
                this.awardTarget.value = "";
                const awardList = [];
                if (data.length > 0) {
                    data.forEach(function (award) {
                        awardList.push({ value: award.id, text: award.name, data: award });
                    });
                    this.awardTarget.options = awardList;
                    this.awardTarget.disabled = false;
                    if (this.awardTarget.dataset.acInitSelectionValue) {
                        const val = JSON.parse(this.awardTarget.dataset.acInitSelectionValue);
                        this.awardTarget.value = val.value;
                        this.resetApprovalWorkflowRestartConfirmation();
                        if (this.awardTarget.value !== "") {
                            this.populateSpecialties({ target: { value: val.value } });
                        }
                    }
                } else {
                    this.awardTarget.options = [{ value: "No awards available", text: "No awards available" }];
                    this.awardTarget.value = "No awards available";
                    this.awardTarget.disabled = true;
                    this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
                    this.specialtyTarget.value = "No specialties available";
                    this.specialtyTarget.disabled = true;
                    this.specialtyTarget.hidden = true;
                }
            });
    }

    populateSpecialties(event) {
        const awardId = this.awardTarget.value;
        const award = this.awardTarget.options.find(award => award.value == awardId);
        const specialtyArray = [];
        if (award.data.specialties != null && award.data.specialties.length > 0) {
            award.data.specialties.forEach(function (specialty) {
                specialtyArray.push({ value: specialty, text: specialty });
            });
            this.specialtyTarget.options = specialtyArray;
            this.specialtyTarget.value = "";
            this.specialtyTarget.disabled = false;
            this.specialtyTarget.hidden = false;
            if (this.specialtyTarget.dataset.acInitSelectionValue) {
                const val = JSON.parse(this.specialtyTarget.dataset.acInitSelectionValue);
                this.specialtyTarget.value = val.value;
            }
        } else {
            this.specialtyTarget.options = [{ value: "No specialties available", text: "No specialties available" }];
            this.specialtyTarget.value = "No specialties available";
            this.specialtyTarget.disabled = true;
            this.specialtyTarget.hidden = true;
        }
    }

    shouldConfirmApprovalWorkflowRestart() {
        if (
            !this.hasCurrentApprovalProcessIdTarget ||
            !this.hasApprovalWorkflowRestartConfirmedTarget ||
            this.approvalWorkflowRestartConfirmedTarget.value === "1"
        ) {
            return false;
        }

        const currentProcessId = String(this.currentApprovalProcessIdTarget.value || "");
        if (currentProcessId === "") {
            return false;
        }

        const currentAwardId = this.hasCurrentAwardIdTarget ? String(this.currentAwardIdTarget.value || "") : "";
        const selectedAwardId = String(this.awardTarget.value || "");
        if (selectedAwardId === "" || selectedAwardId === currentAwardId) {
            return false;
        }

        const award = this.awardTarget.options?.find((option) => String(option.value) === selectedAwardId);
        const selectedProcessId = String(award?.data?.approval_process_id || "");

        return selectedProcessId !== currentProcessId;
    }

    async confirmApprovalWorkflowRestart(event) {
        if (!this.shouldConfirmApprovalWorkflowRestart()) {
            return false;
        }

        event.preventDefault?.();
        event.stopImmediatePropagation?.();
        const form = event.target;
        const confirmed = await window.KMP_accessibility.confirm(
            "Changing this award will cancel the current approval workflow. If the new award requires approval, a new workflow will be started after the recommendation is saved.",
            {
                title: "Restart approval workflow?",
                confirmLabel: "Save and restart workflow",
                cancelLabel: "Keep editing",
            },
        );
        if (!confirmed) {
            window.KMP_accessibility.announce("Award change was not saved.", { assertive: true });
            return true;
        }

        this.approvalWorkflowRestartConfirmedTarget.value = "1";
        form.requestSubmit();

        return true;
    }

    resetApprovalWorkflowRestartConfirmation() {
        if (this.hasApprovalWorkflowRestartConfirmedTarget) {
            this.approvalWorkflowRestartConfirmedTarget.value = "0";
        }
    }

    loadScaMemberInfo(event) {
    }

    optionsForFetch() {
        return {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }
    }

    recIdTargetConnected() {
        const recId = this.recIdTarget.value;
        let actionUrl = this.element.getAttribute("action");
        actionUrl = actionUrl.replace(/\/\d+$/, "");
        this.element.setAttribute("action", actionUrl + "/" + recId);
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-quick-edit"] = AwardsRecommendationQuickEditForm;
