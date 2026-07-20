
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
        "includePastGatherings",
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
        "specialtyBlock",
        "specialty",
        "member",
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
        this.boundHandleEditTriggerClick = this.handleEditTriggerClick.bind(this);
        this.boundHandleOutletClick = this.handleOutletClick.bind(this);
        this.boundHandleAutocompleteChange = this.handleAutocompleteChange.bind(this);
        document.addEventListener("click", this.boundHandleEditTriggerClick, true);
        document.addEventListener("outlet-btn:outlet-button-clicked", this.boundHandleOutletClick);
        this.element.addEventListener("autocomplete.change", this.boundHandleAutocompleteChange);
        this.bindModalEvents();
        this.observeTurboFrame();
        window.requestAnimationFrame(() => this.refreshAfterTurboLoad());
    }

    /** Clean up modal listeners when the form disconnects. */
    disconnect() {
        document.removeEventListener("click", this.boundHandleEditTriggerClick, true);
        document.removeEventListener("outlet-btn:outlet-button-clicked", this.boundHandleOutletClick);
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

        const root = combo.closest("[data-controller~=\"awards-bestowal-edit\"]");
        if (!root || root !== this.element) {
            return;
        }

        const role = combo.getAttribute("data-awards-bestowal-edit-target");
        if (role === "domain") {
            this.onDomainChange(event);
        } else if (role === "award") {
            this.onAwardChange(event);
        } else if (role === "member") {
            this.onMemberChange(event);
        }
    }

    observeTurboFrame() {
        const frame = this.element.querySelector("#editBestowalQuick");
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

    /** @return {HTMLElement|null} */
    getTurboFrame() {
        if (this.hasTurboFrameTarget) {
            return this.turboFrameTarget;
        }

        return this.element.querySelector("#editBestowalQuick");
    }

    /** Load form when grid outlet-btn dispatches row data (same pattern as app settings). */
    handleOutletClick(event) {
        const trigger = event.target;
        if (!trigger?.closest?.(".edit-bestowal")) {
            return;
        }

        const modalId = this.modalIdValue || "editBestowalModal";
        const modalTarget = trigger.getAttribute("data-bs-target");
        if (modalTarget && modalTarget !== `#${modalId}`) {
            return;
        }

        const bestowalId = event.detail?.id ?? this.extractBestowalIdFromTrigger(trigger);
        if (bestowalId) {
            this.loadBestowalForm(bestowalId);
        }
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
        this.markTurboFrameLoaded();
        this.refreshAfterTurboLoad();
    }

    refreshAfterTurboLoad() {
        window.requestAnimationFrame(() => {
            this.readBestowedDateHints();
            this.setFieldRules();
            this.updateUnlinkAvailability();
            this.syncAwardFieldState();
            this.syncSpecialtyOptions();
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
        if (!bestowalId || !this.turboFrameUrlValue) {
            return;
        }

        const frame = this.getTurboFrame();
        if (!frame) {
            return;
        }

        const url = `${this.turboFrameUrlValue}/${bestowalId}`;
        const loadingId = String(bestowalId);
        const currentFrameUrl = frame.getAttribute("src") || frame.src;
        if (
            frame.dataset.bestowalEditLoadingId === loadingId
            && currentFrameUrl === url
        ) {
            return;
        }

        frame.dataset.bestowalEditLoadingId = loadingId;
        if (!frame.querySelector("#bestowal_form")) {
            frame.replaceChildren();
            const loading = document.createElement("div");
            loading.className = "text-center p-4 text-muted";
            loading.textContent = "Loading...";
            frame.appendChild(loading);
        }
        frame.src = url;

        const form = document.getElementById("bestowal_form");
        if (form) {
            form.action = `${this.formUrlValue}/${bestowalId}`;
        }
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
        this.markTurboFrameLoaded();
        this.refreshAfterTurboLoad();
    };

    markTurboFrameLoaded() {
        const frame = this.getTurboFrame();
        if (frame) {
            delete frame.dataset.bestowalEditLoadingId;
        }
    }

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
        this.syncMemberTextValue();
        this.setFieldRules();

        const form = event?.target?.closest?.("form")
            || this.element.querySelector("#bestowal_form")
            || this.element.querySelector("form")
            || this.element;
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
        }
    }

    /** Ensure custom typed recipient names are copied into the submitted hidden text field. */
    syncMemberTextValue() {
        if (!this.hasMemberTarget) {
            return;
        }

        const hiddenValue = this.getHiddenValue(this.memberTarget);
        if (hiddenValue !== "") {
            return;
        }

        const hiddenText = this.memberTarget.querySelector("[data-ac-target='hiddenText']");
        const input = this.memberTarget.querySelector("[data-ac-target='input']");
        if (hiddenText && input && typeof input.value === "string") {
            hiddenText.value = input.value.trim();
        }
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

    /** Sync lookup and submit state when member autocomplete connects. */
    memberTargetConnected() {
        this.bindAutocompleteClear(this.memberTarget, () => this.onMemberChange());
        const memberHidden = this.memberTarget.querySelector("[data-ac-target='hidden']");
        const memberInput = this.memberTarget.querySelector("[data-ac-target='input']");
        if (memberHidden) {
            memberHidden.required = false;
        }
        if (memberInput) {
            memberInput.required = true;
        }
        this.updateSubmitState();
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
            this.clearSpecialtyOptions();
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
            this.clearSpecialtyOptions();
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
        this.syncSpecialtyOptions();
        this.updateGatherings();
        this.updateSubmitState();
    }

    /** Handle member selection changes for ad-hoc bestowal creation. */
    onMemberChange() {
        this.updateGatherings();
        this.updateSubmitState();
    }

    /** Fetch awards for domain and populate award selection. */
    populateAwardDescriptions(event) {
        const awardCombo = this.getAwardComboElement();
        if (!this.hasAwardListUrlValue || !awardCombo) {
            return null;
        }

        const domainId = event?.target?.value ?? this.getDomainId();
        if (!domainId) {
            this.onDomainChange(event);
            return null;
        }

        let url = `${this.awardListUrlValue}/${domainId}`;
        if (this.hasCurrentAwardIdTarget && this.currentAwardIdTarget.value) {
            url += `?current_award_id=${encodeURIComponent(this.currentAwardIdTarget.value)}`;
        }

        const requestDomainId = String(domainId);
        this.setAwardFieldEnabled(true);

        return fetch(url, {
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
                this.setAutocompleteValue(awardCombo, "");
                const awardList = [];
                if (data.length > 0) {
                    data.forEach((award) => {
                        awardList.push({ value: award.id, text: award.name, data: award });
                    });
                    this.setAutocompleteOptions(awardCombo, awardList);
                    this.setAwardFieldEnabled(true);
                    if (
                        preservedAwardId
                        && awardList.some((award) => String(award.value) === String(preservedAwardId))
                    ) {
                        this.setAutocompleteValue(awardCombo, preservedAwardId);
                        if (this.hasCurrentAwardIdTarget) {
                            this.currentAwardIdTarget.value = preservedAwardId;
                        }
                    } else if (awardCombo.dataset.acInitSelectionValue) {
                        const val = JSON.parse(awardCombo.dataset.acInitSelectionValue);
                        this.setAutocompleteValue(awardCombo, val.value);
                        delete awardCombo.dataset.acInitSelectionValue;
                        if (this.hasCurrentAwardIdTarget) {
                            this.currentAwardIdTarget.value = val.value;
                        }
                    }
                } else {
                    this.setAutocompleteOptions(awardCombo, [{ value: "", text: "No awards available" }]);
                    this.setAutocompleteValue(awardCombo, "");
                    awardCombo.disabled = true;
                }
                this.syncSpecialtyOptions();
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
        this.clearSpecialtyOptions();
    }

    /** Normalize award specialty metadata from endpoint and embedded combo options. */
    normalizeSpecialties(rawSpecialties) {
        if (rawSpecialties === null || rawSpecialties === undefined || rawSpecialties === "") {
            return [];
        }

        let specialties = rawSpecialties;
        if (typeof specialties === "string") {
            try {
                specialties = JSON.parse(specialties);
            } catch (_error) {
                specialties = [specialties];
            }
        }

        if (!Array.isArray(specialties)) {
            return [];
        }

        return specialties
            .map((specialty) => String(specialty).trim())
            .filter((specialty) => specialty !== "");
    }

    /** Return the selected award option including custom data payloads. */
    getSelectedAwardOption() {
        const awardCombo = this.getAwardComboElement();
        const awardId = this.getAwardId();
        const options = this.getAutocompleteOptions(awardCombo);
        if (!awardCombo || !awardId || options.length === 0) {
            return null;
        }

        return options.find((option) => String(option.value) === String(awardId)) ?? null;
    }

    /** Return the Stimulus autocomplete controller for a wrapper when available. */
    getAutocompleteController(target) {
        if (!target) {
            return null;
        }

        const getController = window.Stimulus?.getControllerForElementAndIdentifier;
        return typeof getController === "function"
            ? getController.call(window.Stimulus, target, "ac")
            : null;
    }

    /** Read autocomplete options from the live controller, falling back to the wrapper. */
    getAutocompleteOptions(target) {
        const autocomplete = this.getAutocompleteController(target);
        if (Array.isArray(autocomplete?.options)) {
            return autocomplete.options;
        }

        return Array.isArray(target?.options) ? target.options : [];
    }

    /** Update autocomplete options on both the live controller and wrapper fallback. */
    setAutocompleteOptions(target, options) {
        if (!target) {
            return;
        }

        const normalizedOptions = Array.isArray(options) ? options : [];
        const autocomplete = this.getAutocompleteController(target);
        if (autocomplete) {
            autocomplete.options = normalizedOptions;
        }
        target.options = normalizedOptions;
    }

    /** Set an autocomplete value on both the live controller and hidden fallback fields. */
    setAutocompleteValue(target, value) {
        if (!target) {
            return;
        }

        const normalizedValue = value === null || value === undefined ? "" : String(value);
        const autocomplete = this.getAutocompleteController(target);
        if (autocomplete) {
            autocomplete.value = normalizedValue;
        }
        target.value = normalizedValue;

        const hidden = target.querySelector("[data-ac-target='hidden']");
        if (hidden) {
            hidden.value = normalizedValue;
        }

        const selected = this.getAutocompleteOptions(target)
            .find((option) => String(option.value) === normalizedValue);
        const hiddenText = target.querySelector("[data-ac-target='hiddenText']");
        const input = target.querySelector("[data-ac-target='input']");
        const textValue = selected?.text ?? "";
        if (hiddenText) {
            hiddenText.value = textValue;
        }
        if (input) {
            input.value = textValue;
            input.disabled = normalizedValue !== "" && target.dataset.acAllowOtherValue !== "true";
        }
    }

    /** Hide, disable, and clear the specialty selector when the selected award does not use specialties. */
    clearSpecialtyOptions() {
        if (this.hasSpecialtyBlockTarget) {
            this.specialtyBlockTarget.classList.add("d-none");
        }
        if (!this.hasSpecialtyTarget) {
            return;
        }

        this.setSpecialtyRequired(false);
        this.replaceSpecialtyOptions([], { preserveCurrent: false });
        this.setAutocompleteValue(this.specialtyTarget, "");
        this.setAutocompleteDisabled(this.specialtyTarget, true);
    }

    /** Populate specialty selector from the selected award's configured specialties. */
    syncSpecialtyOptions() {
        if (!this.hasSpecialtyTarget) {
            return;
        }

        const selectedAward = this.getSelectedAwardOption();
        const specialties = this.normalizeSpecialties(
            selectedAward?.data?.specialties ?? selectedAward?.specialties ?? null,
        );
        if (specialties.length === 0) {
            this.clearSpecialtyOptions();
            return;
        }

        this.replaceSpecialtyOptions(specialties);
        this.setAutocompleteDisabled(this.specialtyTarget, false);
        this.setSpecialtyRequired(true);
        if (this.hasSpecialtyBlockTarget) {
            this.specialtyBlockTarget.classList.remove("d-none");
        }
    }

    /** Set disabled state on an autocomplete wrapper or native field. */
    setAutocompleteDisabled(target, disabled) {
        if (!target) {
            return;
        }

        target.disabled = disabled;
        const autocomplete = this.getAutocompleteController(target);
        if (autocomplete) {
            autocomplete.disabled = disabled;
        }
        target.querySelectorAll("[data-ac-target='hidden'], [data-ac-target='hiddenText'], [data-ac-target='input']")
            .forEach((field) => {
                field.disabled = disabled;
            });
    }

    /** Set required state on the specialty combo text field or select. */
    setSpecialtyRequired(required) {
        if (!this.hasSpecialtyTarget) {
            return;
        }

        this.specialtyTarget.required = required;
        if (required) {
            this.specialtyTarget.setAttribute("aria-required", "true");
        } else {
            this.specialtyTarget.removeAttribute("aria-required");
        }

        const hiddenText = this.specialtyTarget.querySelector("[data-ac-target='hiddenText']");
        const input = this.specialtyTarget.querySelector("[data-ac-target='input']");
        if (hiddenText) {
            hiddenText.required = required;
        }
        if (input) {
            input.required = required;
            input.setAttribute("aria-required", required ? "true" : "false");
        }
    }

    /** Read specialty value from a combo-box wrapper or native field. */
    getSpecialtyValue() {
        if (!this.hasSpecialtyTarget) {
            return "";
        }

        const hiddenText = this.specialtyTarget.querySelector("[data-ac-target='hiddenText']");
        if (hiddenText && typeof hiddenText.value === "string" && hiddenText.value.trim() !== "") {
            return hiddenText.value.trim();
        }

        const input = this.specialtyTarget.querySelector("[data-ac-target='input']");
        if (input && typeof input.value === "string" && input.value.trim() !== "") {
            return input.value.trim();
        }

        return typeof this.specialtyTarget.value === "string" ? this.specialtyTarget.value.trim() : "";
    }

    /** Replace specialty options safely without innerHTML. */
    replaceSpecialtyOptions(specialties, { preserveCurrent = true } = {}) {
        if (!this.hasSpecialtyTarget) {
            return;
        }

        const currentValue = this.getSpecialtyValue();
        const options = specialties.map((specialty) => ({ value: specialty, text: specialty }));
        if (preserveCurrent && currentValue !== "" && !specialties.includes(currentValue)) {
            options.push({ value: currentValue, text: currentValue });
        }
        if (this.specialtyTarget.querySelector("[data-ac-target='input']")) {
            this.setAutocompleteOptions(this.specialtyTarget, options);
            if (currentValue === "") {
                this.setAutocompleteValue(this.specialtyTarget, "");
            }

            return;
        }

        while (this.specialtyTarget.firstChild) {
            this.specialtyTarget.removeChild(this.specialtyTarget.firstChild);
        }

        const emptyOption = document.createElement("option");
        emptyOption.value = "";
        emptyOption.textContent = "Select a specialty";
        this.specialtyTarget.appendChild(emptyOption);

        specialties.forEach((specialty) => {
            const option = document.createElement("option");
            option.value = specialty;
            option.textContent = specialty;
            if (specialty === currentValue) {
                option.selected = true;
            }
            this.specialtyTarget.appendChild(option);
        });

        if (!specialties.includes(currentValue)) {
            this.specialtyTarget.value = "";
        }
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

    /** Read selected member ID or public ID from add/edit form fields. */
    getMemberValue() {
        if (this.hasMemberTarget) {
            const memberValue = this.getHiddenValue(this.memberTarget);
            if (memberValue !== "") {
                return memberValue;
            }
            const hiddenText = this.memberTarget.querySelector("[data-ac-target='hiddenText']");
            if (hiddenText && typeof hiddenText.value === "string" && hiddenText.value.trim() !== "") {
                return hiddenText.value.trim();
            }
            const input = this.memberTarget.querySelector("[data-ac-target='input']");
            if (input && typeof input.value === "string") {
                return input.value.trim();
            }
        }
        if (this.hasMemberIdTarget) {
            return this.memberIdTarget.value ?? "";
        }

        return this.element.querySelector("input[name=\"member_id\"]")?.value
            || this.element.querySelector("input[name=\"member_public_id\"]")?.value
            || "";
    }

    /** Read only selected member ID/public ID values, excluding custom typed names. */
    getSelectedMemberValue() {
        if (this.hasMemberTarget) {
            const memberValue = this.getHiddenValue(this.memberTarget);
            if (memberValue !== "") {
                return memberValue;
            }
        }
        if (this.hasMemberIdTarget) {
            return this.memberIdTarget.value ?? "";
        }

        return this.element.querySelector("input[name=\"member_id\"]")?.value
            || this.element.querySelector("input[name=\"member_public_id\"]")?.value
            || "";
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
    hasValidMemberSelection() {
        if (!this.hasMemberTarget && !this.hasMemberIdTarget) {
            return true;
        }

        return this.getMemberValue() !== "";
    }

    /** @return {boolean} */
    isFormSubmittable() {
        return this.hasValidAwardSelection()
            && this.hasValidMemberSelection()
            && this.hasValidSpecialtySelection();
    }

    /** @return {boolean} */
    hasValidSpecialtySelection() {
        if (!this.hasSpecialtyTarget || this.specialtyTarget.disabled || !this.specialtyTarget.required) {
            return true;
        }

        return this.getSpecialtyValue() !== "";
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
        if (this.hasSpecialtyTarget) {
            fields.push(this.specialtyTarget);
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
        const memberValue = this.getSelectedMemberValue();
        if (memberValue) {
            const memberParam = /^\d+$/.test(String(memberValue)) ? "member_id" : "member_public_id";
            params.append(memberParam, memberValue);
        }
        if (currentSelection) {
            params.append("selected_id", currentSelection);
            this.planToGiveGatheringTarget.dataset.initialValue = currentSelection;
        }
        if (this.hasIncludePastGatheringsTarget && this.includePastGatheringsTarget.checked) {
            params.append("include_past", "1");
        }

        let lookupUrl = this.gatheringsLookupUrlValue;
        if (bestowalId) {
            lookupUrl += `/${bestowalId}`;
        }
        if (params.toString()) {
            lookupUrl += `?${params.toString()}`;
        }
        this.planToGiveGatheringTarget.setAttribute("data-ac-url-value", lookupUrl);
    }

    /** Include or exclude past gatherings in the gathering picker. */
    onIncludePastGatheringsChange() {
        if (!this.hasPlanToGiveGatheringTarget) {
            return;
        }

        const input = this.planToGiveGatheringTarget.querySelector("[data-ac-target='input']");
        const hidden = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hidden']");
        const hiddenText = this.planToGiveGatheringTarget.querySelector("[data-ac-target='hiddenText']");
        const clearBtn = this.planToGiveGatheringTarget.querySelector("[data-ac-target='clearBtn']");
        if (input) {
            input.value = "";
            input.disabled = false;
        }
        if (hidden) {
            hidden.value = "";
        }
        if (hiddenText) {
            hiddenText.value = "";
        }
        if (clearBtn) {
            clearBtn.disabled = true;
        }
        this.planToGiveGatheringTarget.dataset.initialValue = "";
        this.updateGatherings();
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
