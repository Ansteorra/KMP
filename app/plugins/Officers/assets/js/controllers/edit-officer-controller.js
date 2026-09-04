/**
 * Officer Edit Controller - Populates edit form from officer selection events
 *
 * Targets: deputyDescBlock, deputyDesc, id, emailAddress, startOn, expiresOn,
 * termDatesError, termNote, termNoteRequired, termNotesList, termNotesEmpty, status
 * Outlets: outlet-btn
 */

import { Controller } from "@hotwired/stimulus"

class EditOfficer extends Controller {
    static targets = [
        "deputyDescBlock",
        "deputyDesc",
        "id",
        "emailAddress",
        "startOn",
        "expiresOn",
        "termDatesError",
        "termNote",
        "termNoteRequired",
        "termNotesList",
        "termNotesEmpty",
        "status",
    ]
    static outlets = ["outlet-btn"]

    initialize() {
        this.setIdListener = this.setId.bind(this);
        this.modalShownListener = this.announcePendingTermNotes.bind(this);
        this.originalStartOn = "";
        this.originalExpiresOn = "";
        this.pendingTermNotesAnnouncement = "";
        this.modalElement = null;
    }

    connect() {
        this.modalElement = this.element.querySelector(".modal")
            ?? this.element.closest(".modal");
        this.modalElement?.addEventListener("shown.bs.modal", this.modalShownListener);
    }

    disconnect() {
        this.modalElement?.removeEventListener("shown.bs.modal", this.modalShownListener);
        this.modalElement = null;
    }

    /**
     * Populate edit form with officer data from selection event.
     * @param {Event} event - Event containing the selected officer assignment data.
     */
    setId(event) {
        const detail = event && event.detail && typeof event.detail === "object"
            ? event.detail
            : {};
        const deputyDescription = this.normalizeText(detail.deputy_description);
        const isDeputy = detail.is_deputy === true
            || detail.is_deputy === 1
            || detail.is_deputy === "1";

        this.idTarget.value = this.normalizeText(detail.id);
        this.emailAddressTarget.value = this.normalizeText(detail.email_address);

        if (isDeputy) {
            this.deputyDescBlockTarget.classList.remove("d-none");
            this.deputyDescTarget.value = deputyDescription.replace(/:/g, "").trim();
        } else {
            this.deputyDescBlockTarget.classList.add("d-none");
            this.deputyDescTarget.value = deputyDescription;
        }

        this.originalStartOn = this.normalizeDate(detail.start_on);
        this.originalExpiresOn = this.normalizeDate(detail.expires_on);
        this.startOnTarget.value = this.originalStartOn;
        this.expiresOnTarget.value = this.originalExpiresOn;
        this.termNoteTarget.value = "";
        this.statusTarget.textContent = "";
        this.clearDateRangeValidation();

        this.renderTermNotes(detail.term_notes_payload);
        this.updateTermNoteRequirement(false);
    }

    /** Update note requirements after either term date changes. */
    termDatesChanged() {
        this.updateTermNoteRequirement(true);
        this.validateDateRange(false);
    }

    /** Stop submission and focus the end date when the selected term range is invalid. */
    validateForm(event) {
        if (this.validateDateRange(true)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
    }

    /**
     * Normalize payload values before assigning them to form controls.
     *
     * @param {*} value Payload value.
     * @returns {string} A safe string value.
     */
    normalizeText(value) {
        return value === null || value === undefined ? "" : String(value);
    }

    /**
     * Normalize CakePHP date payloads for an HTML date input.
     *
     * @param {*} value Payload date value.
     * @returns {string} An ISO calendar date or an empty string.
     */
    normalizeDate(value) {
        const dateText = this.normalizeText(value).trim();
        const match = dateText.match(/^(\d{4}-\d{2}-\d{2})/);

        return match ? match[1] : "";
    }

    /**
     * Require a note only when the selected officer's term dates changed.
     *
     * @param {boolean} shouldAnnounce Whether to announce a requirement change.
     */
    updateTermNoteRequirement(shouldAnnounce) {
        const isRequired = this.startOnTarget.value !== this.originalStartOn
            || this.expiresOnTarget.value !== this.originalExpiresOn;
        const requirementChanged = this.termNoteTarget.required !== isRequired;

        this.termNoteTarget.required = isRequired;
        this.termNoteTarget.setAttribute("aria-required", String(isRequired));
        this.termNoteRequiredTarget.classList.toggle("d-none", !isRequired);

        if (shouldAnnounce && requirementChanged) {
            this.announce(isRequired
                ? "A term change note is now required."
                : "A term change note is no longer required.");
        }
    }

    /**
     * Validate that an optional end date is not earlier than the required start date.
     *
     * @param {boolean} shouldFocus Whether to focus the invalid end-date control.
     * @returns {boolean} Whether the current date range is valid.
     */
    validateDateRange(shouldFocus) {
        const startOn = this.startOnTarget.value;
        const expiresOn = this.expiresOnTarget.value;
        const isInvalid = startOn !== "" && expiresOn !== "" && expiresOn < startOn;

        this.expiresOnTarget.setCustomValidity(
            isInvalid ? this.termDatesErrorTarget.textContent.trim() : "",
        );
        this.expiresOnTarget.classList.toggle("is-invalid", isInvalid);
        this.termDatesErrorTarget.classList.toggle("d-none", !isInvalid);
        if (isInvalid) {
            this.expiresOnTarget.setAttribute("aria-invalid", "true");
            if (shouldFocus) {
                this.expiresOnTarget.focus();
            }
        } else {
            this.expiresOnTarget.removeAttribute("aria-invalid");
        }

        return !isInvalid;
    }

    /** Reset date-range validation when a different officer assignment is selected. */
    clearDateRangeValidation() {
        this.expiresOnTarget.setCustomValidity("");
        this.expiresOnTarget.classList.remove("is-invalid");
        this.expiresOnTarget.removeAttribute("aria-invalid");
        this.termDatesErrorTarget.classList.add("d-none");
    }

    /**
     * Render existing term notes using text content so payload text is never interpreted as markup.
     *
     * @param {*} payload Existing term-note payload.
     */
    renderTermNotes(payload) {
        const notes = this.normalizeNotes(payload);
        this.termNotesListTarget.replaceChildren();

        if (notes.length === 0) {
            this.termNotesListTarget.classList.add("d-none");
            this.termNotesEmptyTarget.classList.remove("d-none");
            this.pendingTermNotesAnnouncement = "No existing term notes.";

            return;
        }

        const fragment = document.createDocumentFragment();
        notes.forEach((note) => {
            const item = document.createElement("li");
            item.className = "list-group-item";

            const subject = document.createElement("h4");
            subject.className = "h6 mb-1";
            subject.textContent = this.normalizeText(note.subject).trim() || "Term change";
            item.appendChild(subject);

            const author = this.noteAuthor(note);
            const createdOn = this.normalizeText(note.created_on ?? note.created).trim();
            if (author || createdOn) {
                const metadata = document.createElement("p");
                metadata.className = "small text-body-secondary mb-1";
                metadata.textContent = [createdOn, author ? `by ${author}` : ""]
                    .filter(Boolean)
                    .join(" — ");
                item.appendChild(metadata);
            }

            const body = document.createElement("p");
            body.className = "mb-0 text-break";
            body.textContent = this.normalizeText(note.body);
            item.appendChild(body);
            fragment.appendChild(item);
        });

        this.termNotesListTarget.appendChild(fragment);
        this.termNotesListTarget.classList.remove("d-none");
        this.termNotesEmptyTarget.classList.add("d-none");
        this.pendingTermNotesAnnouncement = `${notes.length} existing term ${notes.length === 1 ? "note" : "notes"} loaded.`;
    }

    /**
     * Normalize either an array payload or a JSON-encoded payload.
     *
     * @param {*} payload Existing term-note payload.
     * @returns {Array<object>} Renderable note objects.
     */
    normalizeNotes(payload) {
        let notes = payload;

        if (typeof notes === "string") {
            if (notes.trim() === "") {
                return [];
            }
            try {
                notes = JSON.parse(notes);
            } catch {
                return [];
            }
        }

        if (!Array.isArray(notes) && notes && Array.isArray(notes.notes)) {
            notes = notes.notes;
        }

        return Array.isArray(notes)
            ? notes.filter((note) => note && typeof note === "object")
            : [];
    }

    /**
     * Read an author label from the supported note payload shapes.
     *
     * @param {object} note Note payload.
     * @returns {string} Author label.
     */
    noteAuthor(note) {
        if (note.author && typeof note.author === "object") {
            return this.normalizeText(note.author.sca_name ?? note.author.name).trim();
        }

        return this.normalizeText(note.author_name ?? note.author).trim();
    }

    /** Announce rendered term-note context only after the modal becomes visible. */
    announcePendingTermNotes() {
        if (this.pendingTermNotesAnnouncement === "") {
            return;
        }

        this.announce(this.pendingTermNotesAnnouncement);
        this.pendingTermNotesAnnouncement = "";
    }

    /** Announce an edit-form state change to assistive technology. */
    announce(message) {
        this.statusTarget.textContent = message;
    }

    /** Register setId listener when outlet button connects. */
    outletBtnOutletConnected(outlet) {
        outlet.addListener(this.setIdListener);
    }

    /** Remove setId listener when outlet button disconnects. */
    outletBtnOutletDisconnected(outlet) {
        outlet.removeListener(this.setIdListener);
    }
}

// Register controller with global registry
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["officers-edit-officer"] = EditOfficer;
