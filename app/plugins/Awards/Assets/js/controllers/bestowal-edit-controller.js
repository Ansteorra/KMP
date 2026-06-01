
import { Controller } from "@hotwired/stimulus";

/**
 * Awards Bestowal Edit Form Controller
 *
 * Modal form for editing a single bestowal with state-driven field rules,
 * court slot loading, and recommendation link/unlink controls.
 */
class AwardsBestowalEditForm extends Controller {
    static targets = [
        "state",
        "planToGiveBlock",
        "planToGiveGathering",
        "courtSlotBlock",
        "courtSlot",
        "courtSlotHelp",
        "courtSlotNoSchedule",
        "courtSlotSelectWrap",
        "givenBlock",
        "givenDate",
        "closeReason",
        "closeReasonBlock",
        "stateRulesBlock",
        "bestowalId",
        "memberId",
        "turboFrame",
        "statusDisplay",
        "nobleNotes",
        "heraldNotes",
        "unlinkRecommendation",
        "linkRecommendation",
        "unlinkRecommendationsBlock",
        "linkRecommendationsBlock",
        "domain",
        "award",
        "currentAwardId",
        "submitButton",
    ];
    static values = {
        formUrl: String,
        turboFrameUrl: String,
        courtSlotsUrl: String,
        gatheringsLookupUrl: String,
        linkedRecommendationCount: Number,
        awardListUrl: String,
        modalId: { type: String, default: "editBestowalModal" },
    };
    static outlets = ["outlet-btn"];

    /** Whether the selected gathering has Event Schedule entries for court sessions. */
    _courtSlotsAvailable = false;

    /** First day of the selected gathering (Y-m-d). */
    _gatheringStartDate = "";

    /** Court session activity ID => bestowal date (Y-m-d). */
    _courtOptionDates = {};

    /** Initialize submit button state and modal listeners when the form connects. */
    connect() {
        this.boundSetId = this.setId.bind(this);
        this.boundHandleEditTriggerClick = this.handleEditTriggerClick.bind(this);
        this.boundHandleAutocompleteChange = this.handleAutocompleteChange.bind(this);
        document.addEventListener("click", this.boundHandleEditTriggerClick, true);
        this.element.addEventListener("autocomplete.change", this.boundHandleAutocompleteChange);
        this.bindModalEvents();
        this.observeTurboFrame();
        window.requestAnimationFrame(() => this.refreshAfterTurboLoad());
    }

    /** Clean up modal listeners when the form disconnects. */
    disconnect() {
        document.removeEventListener("click", this.boundHandleEditTriggerClick, true);
        this.element.removeEventListener("autocomplete.change", this.boundHandleAutocompleteChange);
        this.unbindModalEvents();
        if (this.turboObserver) {
            this.turboObserver.disconnect();
            this.turboObserver = null;
        }
    }

    /** Route autocomplete.change from domain/award combos to paired-field handlers. */
    handleAutocompleteChange(event) {
        const combo = event.target?.closest?.("[data-awards-bestowal-edit-target]");
        if (!combo) {
            return;
        }

        const root = combo.closest("#bestowal_edit_root");
        if (!root || root !== this.element) {
            return;
        }

        const role = combo.getAttribute("data-awards-bestowal-edit-target");
        if (role === "domain") {
            this.onDomainChange(event);
        } else if (role === "award") {
            this.onAwardChange(event);
        }
    }

    observeTurboFrame() {
        const frame = this.element.querySelector("#editBestowal");
        if (!frame || frame.dataset.bestowalEditObserveBound === "true") {
            return;
        }

        frame.dataset.bestowalEditObserveBound = "true";
        this.turboObserver = new MutationObserver(() => {
            this.refreshAfterTurboLoad();
        });
        this.turboObserver.observe(frame, { childList: true, subtree: true });
    }

    /** @return {HTMLElement|null} */
    getModalElement() {
        const modalId = this.modalIdValue || "editBestowalModal";
        return this.element.querySelector(`#${modalId}`)
            || document.getElementById(modalId);
    }

    bindModalEvents() {
        const modal = this.getModalElement();
        if (!modal || modal.dataset.bestowalEditModalBound === "true") {
            return;
        }

        modal.dataset.bestowalEditModalBound = "true";
        this.boundHandleModalShow = this.handleModalShow.bind(this);
        this.boundHandleModalShown = this.handleModalShown.bind(this);
        modal.addEventListener("show.bs.modal", this.boundHandleModalShow);
        modal.addEventListener("shown.bs.modal", this.boundHandleModalShown);
    }

    unbindModalEvents() {
        const modal = this.getModalElement();
        if (!modal || modal.dataset.bestowalEditModalBound !== "true") {
            return;
        }

        modal.removeEventListener("show.bs.modal", this.boundHandleModalShow);
        modal.removeEventListener("shown.bs.modal", this.boundHandleModalShown);
        delete modal.dataset.bestowalEditModalBound;
    }

    /** Load the edit form when an edit trigger is clicked (grid or detail page). */
    handleEditTriggerClick(event) {
        const trigger = event.target.closest(".edit-bestowal");
        if (!trigger) {
            return;
        }

        const modalId = this.modalIdValue || "editBestowalModal";
        const modalTarget = trigger.getAttribute("data-bs-target");
        if (modalTarget && modalTarget !== `#${modalId}`) {
            return;
        }

        const bestowalId = this.extractBestowalIdFromTrigger(trigger);
        if (bestowalId) {
            this.loadBestowalForm(bestowalId);
        }
    }

    /** Load the edit form when the modal opens from a grid or detail trigger. */
    handleModalShow(event) {
        const bestowalId = this.extractBestowalIdFromTrigger(event.relatedTarget);
        if (bestowalId) {
            this.loadBestowalForm(bestowalId);
        }
    }

    /** Refresh field state after the Bootstrap modal becomes visible. */
    handleModalShown() {
        this.refreshAfterTurboLoad();
    }

    /** Stimulus action handler for turbo frame load events. */
    onTurboFrameLoad() {
        this.refreshAfterTurboLoad();
    }

    refreshAfterTurboLoad() {
        window.requestAnimationFrame(() => {
            this.readBestowedDateHints();
            this.setFieldRules();
            this.updateUnlinkAvailability();
            this.syncAwardFieldState();
            this.updateSubmitState();
        });
    }

    /** @param {HTMLElement|null} trigger */
    extractBestowalIdFromTrigger(trigger) {
        if (!trigger) {
            return null;
        }

        const dataAttr = trigger.getAttribute("data-outlet-btn-btn-data-value");
        if (dataAttr) {
            try {
                const parsed = JSON.parse(dataAttr);
                if (parsed?.id) {
                    return parsed.id;
                }
            } catch (error) {
                // Ignore malformed trigger payloads.
            }
        }

        const row = trigger.closest("tr[data-id]");
        return row?.dataset?.id ?? null;
    }

    /** @param {string|number} bestowalId */
    loadBestowalForm(bestowalId) {
        if (!bestowalId) {
            return;
        }

        const form = this.element.querySelector("#bestowal_form") || this.element;
        const frame = this.element.querySelector("#editBestowal");
        if (frame) {
            frame.setAttribute(
                "src",
                `${this.turboFrameUrlValue}/${bestowalId}`,
            );
        }
        form.setAttribute("action", `${this.formUrlValue}/${bestowalId}`);
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
        }
    }

    /** Read linked recommendation count and re-bind handlers when turbo frame loads. */
    turboFrameTargetConnected() {
        const count = this.turboFrameTarget.dataset.awardsBestowalEditLinkedRecommendationCountValue;
        if (count !== undefined && count !== "") {
            this.linkedRecommendationCountValue = parseInt(count, 10);
        }
        this.updateUnlinkAvailability();
        this.turboFrameTarget.addEventListener("turbo:frame-load", this.handleTurboFrameLoad);
        this.bindAutocompleteClear(this.getDomainComboElement(), () => this.onDomainChange());
        this.bindAutocompleteClear(this.getAwardComboElement(), () => this.onAwardChange());
        this.refreshAfterTurboLoad();
    }

    /** Re-evaluate submit state after turbo frame content loads. */
    turboFrameTargetDisconnected() {
        this.turboFrameTarget.removeEventListener("turbo:frame-load", this.handleTurboFrameLoad);
    }

    handleTurboFrameLoad = () => {
        this.refreshAfterTurboLoad();
    };

    /** Keep at least one recommendation linked when choosing unlink targets. */
    updateUnlinkAvailability(event) {
        if (!this.hasUnlinkRecommendationTarget) {
            return;
        }

        const linkedCount = this.linkedRecommendationCountValue || this.unlinkRecommendationTargets.length;
        const checkedLinkCount = this.hasLinkRecommendationTarget
            ? this.linkRecommendationTargets.filter((input) => input.checked).length
            : 0;
        const maxUnlink = Math.min(
            this.unlinkRecommendationTargets.length,
            Math.max(0, linkedCount - 1 + checkedLinkCount),
        );

        if (event?.target?.type === "checkbox" && event.target.checked) {
            const checkedUnlinkCount = this.unlinkRecommendationTargets.filter((input) => input.checked).length;
            if (checkedUnlinkCount > maxUnlink) {
                event.target.checked = false;
                return;
            }
        }

        const checkedUnlinkCount = this.unlinkRecommendationTargets.filter((input) => input.checked).length;

        if (this.hasUnlinkRecommendationsBlockTarget) {
            if (linkedCount === 1) {
                const showUnlink = checkedLinkCount > 0;
                this.unlinkRecommendationsBlockTarget.classList.toggle("d-none", !showUnlink);
            } else {
                this.unlinkRecommendationsBlockTarget.classList.remove("d-none");
            }
        }

        this.unlinkRecommendationTargets.forEach((input) => {
            if (input.checked) {
                input.disabled = false;
                return;
            }

            input.disabled = checkedUnlinkCount >= maxUnlink;
        });
    }

    /** Initialize unlink guard when unlink checkbox connects. */
    unlinkRecommendationTargetConnected() {
        this.updateUnlinkAvailability();
    }

    /** Re-evaluate unlink guard when link checkbox connects. */
    linkRecommendationTargetConnected() {
        this.updateUnlinkAvailability();
    }

    /** Set bestowal ID and update Turbo Frame source and form action. */
    setId(event) {
        const bestowalId = event?.detail?.id ?? event?.detail?.ids?.[0] ?? null;
        if (bestowalId) {
            this.loadBestowalForm(bestowalId);
        }
    }

    /** Register listener when outlet-btn connects. */
    outletBtnOutletConnected(outlet) {
        outlet.addListener(this.boundSetId);
    }

    /** Remove listener when outlet-btn disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.boundSetId);
    }

    /** Block submit and close modal when the form is submittable. */
    submit(event) {
        this.setFieldRules();

        const form = this.element.querySelector("#bestowal_form") || this.element;
        if (!this.hasValidAwardSelection()) {
            event.preventDefault();
            event.stopPropagation();
            this.reportAwardValidation();
            form.reportValidity?.();
            return;
        }

        if (typeof form.checkValidity === "function" && !form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.reportValidity?.();
            return;
        }

        document.getElementById("bestowal_edit_close").click();
    }

    /** Apply field rules when state target connects. */
    stateTargetConnected() {
        this.readStatusMap();
        this.setFieldRules();
        this.updateStatusDisplay();
    }

    /** Apply field rules once state rules JSON is available in the turbo frame. */
    stateRulesBlockTargetConnected() {
        this.setFieldRules();
    }

    /** Sync award field enablement when domain autocomplete connects. */
    domainTargetConnected() {
        this.bindAutocompleteClear(this.domainTarget, () => this.onDomainChange());
        this.syncAwardFieldState();
    }

    /** Sync award field enablement when award autocomplete connects. */
    awardTargetConnected() {
        this.bindAutocompleteClear(this.awardTarget, () => this.onAwardChange());
        this.syncAwardFieldState();
    }

    /** Read status map embedded in turbo frame when available. */
    readStatusMap() {
        const mapBlock = this.element.querySelector("[data-awards-bestowal-edit-status-map-json]");
        if (!mapBlock) {
            return;
        }
        try {
            this.statusMapValue = JSON.parse(mapBlock.textContent.trim());
        } catch (error) {
            console.warn("Bestowal edit: could not parse status map JSON.", error);
            this.statusMapValue = {};
        }
    }

    /** Update read-only status label when state changes. */
    updateStatusDisplay() {
        if (!this.hasStatusDisplayTarget || !this.hasStateTarget) {
            return;
        }
        const status = this.statusMapValue[this.stateTarget.value];
        if (status) {
            this.statusDisplayTarget.textContent = status;
        }
    }

    /** Parse JSON state rules and apply Visible/Required/Disabled field states. */
    setFieldRules() {
        if (!this.hasStateRulesBlockTarget) {
            return;
        }

        let rules = {};
        try {
            rules = JSON.parse(this.stateRulesBlockTarget.textContent.trim());
        } catch (error) {
            console.warn("Bestowal edit: could not parse state rules JSON.", error);
            this.updateGatherings();
            this.syncCourtSlotVisibility();
            this.updateSubmitState();
            return;
        }
        if (this.hasPlanToGiveBlockTarget) {
            this.planToGiveBlockTarget.style.display = "none";
        }
        if (this.hasCourtSlotBlockTarget) {
            this.courtSlotBlockTarget.style.display = "none";
        }
        if (this.hasGivenBlockTarget) {
            this.givenBlockTarget.style.display = "none";
        }
        if (this.hasCloseReasonBlockTarget) {
            this.closeReasonBlockTarget.style.display = "none";
        }

        this.setGatheringRequired(false);
        this.courtSlotRequiredByState = false;
        if (this.hasCourtSlotTarget) {
            this.courtSlotTarget.required = false;
        }
        if (this.hasGivenDateTarget) {
            this.givenDateTarget.required = false;
        }
        if (this.hasCloseReasonTarget) {
            this.closeReasonTarget.required = false;
        }

        const state = this.stateTarget.value;
        const statusRules = rules[state];
        if (!statusRules) {
            this.updateGatherings();
            this.syncCourtSlotVisibility();
            this.updateSubmitState();
            return;
        }

        const visibleFields = [
            ...(statusRules.Visible ?? []),
            ...(statusRules.Optional ?? []),
        ];
        visibleFields.forEach((field) => {
            if (this[field]) {
                this[field].style.display = "block";
            }
        });
        if (statusRules.Disabled) {
            statusRules.Disabled.forEach((field) => {
                if (this[field]) {
                    this[field].disabled = true;
                }
            });
        }
        if (statusRules.Required) {
            statusRules.Required.forEach((field) => {
                if (this[field]) {
                    this[field].required = true;
                }
                if (field === "courtSlotTarget") {
                    this.courtSlotRequiredByState = true;
                }
            });
        }

        this.setGatheringRequired(!!this.planToGiveGatheringTarget?.required);
        this.updateGatherings();
        this.syncCourtSlotVisibility();
        if (this.isGivenState()) {
            this.applyDefaultGivenDate();
        }
        this.updateSubmitState();
    }

    /** @return {boolean} */
    isGivenState() {
        return this.hasStateTarget && this.stateTarget.value === "Given";
    }

    /** Load server-provided gathering / court session date hints from the turbo frame. */
    readBestowedDateHints() {
        const block = this.element.querySelector(
            "[data-awards-bestowal-edit-target=\"bestowedDateHints\"]",
        );
        if (!block) {
            return;
        }

        try {
            const hints = JSON.parse(block.textContent.trim());
            if (hints.gatheringStartDate) {
                this._gatheringStartDate = hints.gatheringStartDate;
            }
            if (hints.courtSessionDates && typeof hints.courtSessionDates === "object") {
                this._courtOptionDates = hints.courtSessionDates;
            }
            if (
                this.isGivenState()
                && this.hasGivenDateTarget
                && !this.givenDateTarget.value
                && hints.suggestedBestowedDate
            ) {
                this.givenDateTarget.value = hints.suggestedBestowedDate;
                this.givenDateTarget.dataset.autoValue = hints.suggestedBestowedDate;
            }
        } catch (error) {
            console.warn("Bestowal edit: could not parse bestowed date hints JSON.", error);
        }
    }

    /** @return {string} */
    resolveDefaultBestowedDate() {
        const courtId = this.hasCourtSlotTarget ? this.courtSlotTarget.value : "";
        if (courtId && this._courtOptionDates[courtId]) {
            return this._courtOptionDates[courtId];
        }

        return this._gatheringStartDate || "";
    }

    /** Default bestowed date for Given state; respects manual edits. */
    applyDefaultGivenDate() {
        if (!this.isGivenState() || !this.hasGivenDateTarget) {
            return;
        }

        if (this.givenDateTarget.dataset.userEdited === "true") {
            const current = this.givenDateTarget.value;
            const auto = this.givenDateTarget.dataset.autoValue ?? "";
            if (current && current !== auto) {
                return;
            }
        }

        const date = this.resolveDefaultBestowedDate();
        if (date) {
            this.givenDateTarget.value = date;
            this.givenDateTarget.dataset.autoValue = date;
            if (!this.givenDateTarget.dataset.initialValue) {
                this.givenDateTarget.dataset.initialValue = date;
            }
        }
    }

    /** Re-default bestowed date when gathering changes in Given state. */
    onGatheringChange() {
        if (!this.isGivenState()) {
            return;
        }

        window.requestAnimationFrame(() => this.applyDefaultGivenDate());
    }

    /** Re-default bestowed date when court session selection changes. */
    onCourtSlotChange() {
        if (!this.isGivenState()) {
            return;
        }

        this.applyDefaultGivenDate();
    }

    /** Handle award type changes, including clear. */
    onDomainChange(event) {
        if (this._syncingAwardPair) {
            return;
        }

        const domainId = this.getDomainId();
        if (!domainId) {
            this._syncingAwardPair = true;
            this.clearCurrentAwardId();
            this.clearPairedAwardFields();
            this._syncingAwardPair = false;
            this.updateGatherings();
            this.updateSubmitState();
            return;
        }

        this.populateAwardDescriptions(event ?? { target: { value: domainId } });
    }

    /** Handle award selection changes, including clear. */
    onAwardChange(event) {
        if (this._syncingAwardPair) {
            return;
        }

        const awardId = this.getAwardId();
        if (!awardId) {
            this._syncingAwardPair = true;
            this.clearAutocomplete(this.getDomainComboElement());
            this.clearCurrentAwardId();
            this.clearPairedAwardFields();
            this._syncingAwardPair = false;
            this.updateGatherings();
            this.updateSubmitState();
            return;
        }

        if (this.hasCurrentAwardIdTarget) {
            this.currentAwardIdTarget.value = awardId;
        } else {
            const currentAwardInput = this.element.querySelector("input[name=\"current_award_id\"]");
            if (currentAwardInput) {
                currentAwardInput.value = awardId;
            }
        }
        this.updateGatherings();
        this.updateSubmitState();
    }

    /** Fetch awards for domain and populate award selection. */
    populateAwardDescriptions(event) {
        const awardCombo = this.getAwardComboElement();
        if (!this.hasAwardListUrlValue || !awardCombo) {
            return;
        }

        const domainId = event?.target?.value ?? this.getDomainId();
        if (!domainId) {
            this.onDomainChange(event);
            return;
        }

        let url = `${this.awardListUrlValue}/${domainId}`;
        if (this.hasCurrentAwardIdTarget && this.currentAwardIdTarget.value) {
            url += `?current_award_id=${encodeURIComponent(this.currentAwardIdTarget.value)}`;
        }

        const requestDomainId = String(domainId);
        this.setAwardFieldEnabled(true);

        fetch(url, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (String(this.getDomainId()) !== requestDomainId) {
                    return;
                }

                const preservedAwardId = this.getAwardId();
                awardCombo.value = "";
                const awardList = [];
                if (data.length > 0) {
                    data.forEach((award) => {
                        awardList.push({ value: award.id, text: award.name, data: award });
                    });
                    awardCombo.options = awardList;
                    this.setAwardFieldEnabled(true);
                    if (
                        preservedAwardId
                        && awardList.some((award) => String(award.value) === String(preservedAwardId))
                    ) {
                        awardCombo.value = preservedAwardId;
                        if (this.hasCurrentAwardIdTarget) {
                            this.currentAwardIdTarget.value = preservedAwardId;
                        }
                    } else if (awardCombo.dataset.acInitSelectionValue) {
                        const val = JSON.parse(awardCombo.dataset.acInitSelectionValue);
                        awardCombo.value = val.value;
                        delete awardCombo.dataset.acInitSelectionValue;
                        if (this.hasCurrentAwardIdTarget) {
                            this.currentAwardIdTarget.value = val.value;
                        }
                    }
                } else {
                    awardCombo.options = [{ value: "", text: "No awards available" }];
                    awardCombo.value = "";
                    awardCombo.disabled = true;
                }
                this.updateGatherings();
                this.updateSubmitState();
            });
    }

    /** @return {HTMLElement|null} */
    getDomainComboElement() {
        return this.hasDomainTarget
            ? this.domainTarget
            : this.element.querySelector("[data-awards-bestowal-edit-target=\"domain\"]");
    }

    /** @return {HTMLElement|null} */
    getAwardComboElement() {
        return this.hasAwardTarget
            ? this.awardTarget
            : this.element.querySelector("[data-awards-bestowal-edit-target=\"award\"]");
    }

    clearCurrentAwardId() {
        if (this.hasCurrentAwardIdTarget) {
            this.currentAwardIdTarget.value = "";
            return;
        }

        const currentAwardInput = this.element.querySelector("input[name=\"current_award_id\"]");
        if (currentAwardInput) {
            currentAwardInput.value = "";
        }
    }

    /** Clear award autocomplete when domain is cleared (works before targets connect). */
    clearPairedAwardFields() {
        this.setAwardFieldEnabled(false);
    }

    /** Read hidden value from an autocomplete wrapper. */
    getHiddenValue(target) {
        if (!target) {
            return "";
        }
        const hiddenInput = target.querySelector("[data-ac-target='hidden']");
        return hiddenInput ? hiddenInput.value : "";
    }

    /** Read selected domain ID from autocomplete hidden field. */
    getDomainId() {
        const domainCombo = this.getDomainComboElement();
        const hiddenValue = this.getHiddenValue(domainCombo);
        if (hiddenValue !== "") {
            return hiddenValue;
        }

        return this.element.querySelector("input[name=\"domain_id\"]")?.value ?? "";
    }

    /** Read selected award ID from autocomplete hidden field. */
    getAwardId() {
        const awardCombo = this.getAwardComboElement();
        const hiddenValue = this.getHiddenValue(awardCombo);
        if (hiddenValue !== "") {
            return hiddenValue;
        }

        return this.element.querySelector("input[name=\"award_id\"]")?.value ?? "";
    }

    /** Reset an autocomplete control to empty. */
    clearAutocomplete(target, { enableInput = true } = {}) {
        if (!target) {
            return;
        }

        target.value = "";
        const hiddenInput = target.querySelector("[data-ac-target='hidden']");
        const hiddenText = target.querySelector("[data-ac-target='hiddenText']");
        const input = target.querySelector("[data-ac-target='input']");
        const clearBtn = target.querySelector("[data-ac-target='clearBtn']");

        if (hiddenInput) {
            hiddenInput.value = "";
        }
        if (hiddenText) {
            hiddenText.value = "";
        }
        if (input) {
            input.value = "";
            input.disabled = !enableInput;
        }
        if (clearBtn) {
            clearBtn.disabled = true;
        }
        delete target.dataset.acInitSelectionValue;
    }

    /** Enable or disable the award autocomplete based on domain selection. */
    setAwardFieldEnabled(enabled) {
        const awardCombo = this.getAwardComboElement();
        if (!awardCombo) {
            const awardHidden = this.element.querySelector("input[name=\"award_id\"]");
            if (awardHidden && !enabled) {
                awardHidden.value = "";
                awardHidden.disabled = true;
            }
            return;
        }

        const hiddenInput = awardCombo.querySelector("[data-ac-target='hidden']");
        const input = awardCombo.querySelector("[data-ac-target='input']");
        if (hiddenInput) {
            hiddenInput.required = enabled;
            hiddenInput.disabled = !enabled;
        }
        if (input) {
            input.required = enabled;
        }

        if (!enabled) {
            this.clearAutocomplete(awardCombo, { enableInput: false });
            awardCombo.disabled = true;
            return;
        }

        awardCombo.disabled = false;
    }

    /** Keep award disabled until a domain is selected. */
    syncAwardFieldState() {
        const domainId = this.getDomainId();
        this.setAwardFieldEnabled(!!domainId);

        const domainCombo = this.getDomainComboElement();
        if (domainCombo) {
            const domainHidden = domainCombo.querySelector("[data-ac-target='hidden']");
            const domainInput = domainCombo.querySelector("[data-ac-target='input']");
            if (domainHidden) {
                domainHidden.required = true;
            }
            if (domainInput) {
                domainInput.required = true;
            }
        }

        this.updateSubmitState();
    }

    /** Wire clear button to paired-field handlers (ac#clear does not emit change). */
    bindAutocompleteClear(target, handler) {
        const clearBtn = target?.querySelector("[data-ac-target='clearBtn']");
        if (!clearBtn || clearBtn.dataset.bestowalEditClearBound === "true") {
            return;
        }

        clearBtn.dataset.bestowalEditClearBound = "true";
        clearBtn.addEventListener("click", () => {
            window.requestAnimationFrame(handler);
        });
    }

    /** @return {boolean} */
    hasValidAwardSelection() {
        const domainId = this.getDomainId()
            || this.element.querySelector("input[name=\"domain_id\"]")?.value
            || "";
        const awardId = this.getAwardId()
            || this.element.querySelector("input[name=\"award_id\"]")?.value
            || "";

        return domainId !== "" && awardId !== "";
    }

    /** @return {boolean} */
    isFormSubmittable() {
        return this.hasValidAwardSelection();
    }

    /** Enable submit only when required fields are satisfied. */
    updateSubmitState() {
        if (!this.hasSubmitButtonTarget) {
            return;
        }

        this.submitButtonTarget.disabled = !this.isFormSubmittable();
    }

    /** Surface native validation for required award fields. */
    reportAwardValidation() {
        const fields = [];
        if (this.hasDomainTarget) {
            fields.push(this.domainTarget.querySelector("[data-ac-target='input']"));
            fields.push(this.domainTarget.querySelector("[data-ac-target='hidden']"));
        }
        if (this.hasAwardTarget) {
            fields.push(this.awardTarget.querySelector("[data-ac-target='input']"));
            fields.push(this.awardTarget.querySelector("[data-ac-target='hidden']"));
        }

        fields.filter(Boolean).some((field) => {
            if (typeof field.reportValidity === "function") {
                field.reportValidity();
            }
            return !field.checkValidity();
        });
    }

    /** Update backend lookup URL for gathering autocomplete. */
    updateGatherings() {
        if (!this.hasPlanToGiveGatheringTarget || !this.hasGatheringsLookupUrlValue) {
            return;
        }

        const bestowalId = this.hasBestowalIdTarget ? this.bestowalIdTarget.value : "";
        if (!bestowalId) {
            return;
        }

        const bestowalKey = String(bestowalId);
        const awardId = this.getAwardId();
        const awardKey = String(awardId || "");
        if (
            this.planToGiveGatheringTarget.dataset.lookupAwardId !== undefined &&
            this.planToGiveGatheringTarget.dataset.lookupAwardId !== awardKey
        ) {
            const gatheringHidden = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hidden']");
            if (gatheringHidden) {
                gatheringHidden.value = "";
            }
            this.planToGiveGatheringTarget.dataset.initialValue = "";
        }
        this.planToGiveGatheringTarget.dataset.lookupAwardId = awardKey;

        if (
            this.planToGiveGatheringTarget.dataset.lookupBestowalId &&
            this.planToGiveGatheringTarget.dataset.lookupBestowalId !== bestowalKey
        ) {
            this.planToGiveGatheringTarget.value = "";
            this.planToGiveGatheringTarget.dataset.initialValue = "";
        }
        this.planToGiveGatheringTarget.dataset.lookupBestowalId = bestowalKey;

        const hiddenInput = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hidden']");
        const currentSelection = (hiddenInput ? hiddenInput.value : "")
            || this.planToGiveGatheringTarget.dataset.initialValue
            || "";

        const params = new URLSearchParams();
        if (this.hasStateTarget && this.stateTarget.value) {
            params.append("status", this.stateTarget.value);
        }
        if (awardId) {
            params.append("award_id", awardId);
        }
        if (currentSelection) {
            params.append("selected_id", currentSelection);
            this.planToGiveGatheringTarget.dataset.initialValue = currentSelection;
        }

        let lookupUrl = `${this.gatheringsLookupUrlValue}/${bestowalId}`;
        if (params.toString()) {
            lookupUrl += `?${params.toString()}`;
        }
        this.planToGiveGatheringTarget.setAttribute("data-ac-url-value", lookupUrl);
    }

    /** Sync required state to autocomplete text input. */
    setGatheringRequired(required) {
        if (!this.hasPlanToGiveGatheringTarget) {
            return;
        }
        this.planToGiveGatheringTarget.required = required;
        const input = this.planToGiveGatheringTarget.querySelector("[data-ac-target='input']");
        if (input) {
            input.required = required;
        }
    }

    /** Store initial gathering value on target connect. */
    planToGiveGatheringTargetConnected() {
        const hiddenInput = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hidden']");
        if (hiddenInput && hiddenInput.value) {
            this.planToGiveGatheringTarget.dataset.initialValue = hiddenInput.value;
        }
        this.updateGatherings();
    }

    /** Read selected gathering ID from autocomplete hidden field. */
    getGatheringId() {
        if (!this.hasPlanToGiveGatheringTarget) {
            return "";
        }
        const hiddenInput = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hidden']");
        return hiddenInput ? hiddenInput.value : "";
    }

    /** Replace select options safely without innerHTML. */
    replaceSelectOptions(select, entries, currentValue) {
        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }

        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = "Select a court session (optional)";
        select.appendChild(emptyOption);

        entries.forEach(([id, label]) => {
            const option = document.createElement("option");
            option.value = id;
            option.textContent = label;
            if (id === currentValue) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    /** Show help, select, or no-schedule message based on gathering Event Schedule. */
    syncCourtSlotVisibility() {
        if (!this.hasCourtSlotBlockTarget) {
            return;
        }

        const blockVisible = this.courtSlotBlockTarget.style.display !== "none";
        const gatheringId = this.getGatheringId();
        const available = this._courtSlotsAvailable && gatheringId !== "";

        if (this.hasCourtSlotHelpTarget) {
            this.courtSlotHelpTarget.classList.toggle("d-none", !available || !blockVisible);
        }
        if (this.hasCourtSlotNoScheduleTarget) {
            const showNoSchedule = blockVisible && gatheringId !== "" && !available;
            this.courtSlotNoScheduleTarget.classList.toggle("d-none", !showNoSchedule);
        }
        if (this.hasCourtSlotSelectWrapTarget) {
            this.courtSlotSelectWrapTarget.classList.toggle("d-none", !available);
        }
        if (this.hasCourtSlotTarget) {
            const stateRequiresSlot = !!this.courtSlotRequiredByState;
            this.courtSlotTarget.required = stateRequiresSlot && available && blockVisible;
            this.courtSlotTarget.disabled = !available;
        }
    }

    /** Load court session options when gathering changes. */
    updateCourtSlots() {
        if (!this.hasPlanToGiveGatheringTarget) {
            return;
        }

        const gatheringId = this.getGatheringId();
        const currentValue = this.hasCourtSlotTarget ? this.courtSlotTarget.value : "";

        if (!gatheringId) {
            this._courtSlotsAvailable = false;
            this._gatheringStartDate = "";
            this._courtOptionDates = {};
            if (this.hasCourtSlotTarget) {
                this.replaceSelectOptions(this.courtSlotTarget, [], "");
            }
            this.syncCourtSlotVisibility();
            if (this.isGivenState()) {
                this.applyDefaultGivenDate();
            }
            this.updateSubmitState();
            return;
        }

        if (!this.hasCourtSlotsUrlValue) {
            this.syncCourtSlotVisibility();
            return;
        }

        fetch(`${this.courtSlotsUrlValue}/${gatheringId}`, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        })
            .then((response) => response.json())
            .then((data) => {
                const enabled = data?.enabled === true;
                const options = data?.options ?? {};
                this._courtSlotsAvailable = enabled;
                if (this.hasCourtSlotNoScheduleTarget) {
                    const hasScheduled = data?.hasScheduledSessions === true;
                    const gatheringId = this.getGatheringId();
                    const showNoSchedule = enabled && gatheringId !== "" && !hasScheduled;
                    this.courtSlotNoScheduleTarget.classList.toggle("d-none", !showNoSchedule);
                }
                if (data?.gatheringStartDate) {
                    this._gatheringStartDate = data.gatheringStartDate;
                } else if (!gatheringId) {
                    this._gatheringStartDate = "";
                }
                if (data?.optionDates && typeof data.optionDates === "object") {
                    this._courtOptionDates = data.optionDates;
                }

                if (this.hasCourtSlotTarget) {
                    this.replaceSelectOptions(
                        this.courtSlotTarget,
                        Object.entries(options),
                        currentValue,
                    );
                }
                this.syncCourtSlotVisibility();
                if (this.isGivenState()) {
                    this.applyDefaultGivenDate();
                }
                this.updateSubmitState();
            });
    }

    /** Store initial bestowed date and track manual edits. */
    givenDateTargetConnected() {
        if (this.givenDateTarget.value) {
            this.givenDateTarget.dataset.initialValue = this.givenDateTarget.value;
            this.givenDateTarget.dataset.autoValue = this.givenDateTarget.value;
        }

        if (this.givenDateTarget.dataset.givenDateInputBound === "true") {
            return;
        }

        this.givenDateTarget.dataset.givenDateInputBound = "true";
        this.givenDateTarget.addEventListener("input", () => {
            this.givenDateTarget.dataset.userEdited = "true";
        });
    }

    /** Apply server-provided court slot availability when the block connects. */
    courtSlotBlockTargetConnected() {
        const initial = this.courtSlotBlockTarget.getAttribute(
            "data-awards-bestowal-edit-initial-court-slots-available",
        );
        this._courtSlotsAvailable = initial === "true" || initial === "1";
        this.syncCourtSlotVisibility();
        if (this.getGatheringId()) {
            this.updateCourtSlots();
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-edit"] = AwardsBestowalEditForm;
