import { Controller } from "@hotwired/stimulus";

/**
 * Awards Bestowal Quick To-Dos Controller
 *
 * Loads a single bestowal's preparation-checks checklist into the grid To-Dos
 * modal turbo-frame when the modal opens (or its outlet-btn trigger fires),
 * mirroring the bestowal edit modal load pattern.
 */
class AwardsBestowalTodos extends Controller {
    static targets = ["turboFrame"];
    static values = {
        turboFrameUrl: String,
        modalId: { type: String, default: "bestowalTodosModal" },
    };
    static outlets = ["outlet-btn"];

    connect() {
        if (this.hasTurboFrameUrlValue) {
            this.boundHandleOutletClick = this.handleOutletClick.bind(this);
            document.addEventListener("outlet-btn:outlet-button-clicked", this.boundHandleOutletClick);
            this.bindModalEvents();
        }
    }

    disconnect() {
        if (this.boundHandleOutletClick) {
            document.removeEventListener("outlet-btn:outlet-button-clicked", this.boundHandleOutletClick);
        }
        if (this.boundHandleModalShow) {
            this.unbindModalEvents();
        }
    }

    /** @return {HTMLElement|null} */
    getModalElement() {
        const modalId = this.modalIdValue || "bestowalTodosModal";
        return this.element.querySelector(`#${modalId}`)
            || document.getElementById(modalId);
    }

    bindModalEvents() {
        const modal = this.getModalElement();
        if (!modal || modal.dataset.bestowalTodosModalBound === "true") {
            return;
        }

        modal.dataset.bestowalTodosModalBound = "true";
        this.boundHandleModalShow = this.handleModalShow.bind(this);
        modal.addEventListener("show.bs.modal", this.boundHandleModalShow);
    }

    unbindModalEvents() {
        const modal = this.getModalElement();
        if (!modal || modal.dataset.bestowalTodosModalBound !== "true") {
            return;
        }

        modal.removeEventListener("show.bs.modal", this.boundHandleModalShow);
        delete modal.dataset.bestowalTodosModalBound;
    }

    /** Load the checklist when the grid outlet-btn dispatches row data. */
    handleOutletClick(event) {
        const trigger = event.target;
        if (!trigger?.closest?.(".todos-bestowal")) {
            return;
        }

        const modalId = this.modalIdValue || "bestowalTodosModal";
        const modalTarget = trigger.getAttribute("data-bs-target");
        if (modalTarget && modalTarget !== `#${modalId}`) {
            return;
        }

        const bestowalId = event.detail?.id ?? this.extractBestowalIdFromTrigger(trigger);
        if (bestowalId) {
            this.loadTodos(bestowalId);
        }
    }

    /** Load the checklist when the modal opens from a grid or detail trigger. */
    handleModalShow(event) {
        const bestowalId = this.extractBestowalIdFromTrigger(event.relatedTarget);
        if (bestowalId) {
            this.loadTodos(bestowalId);
        }
    }

    /** @param {HTMLElement|null} trigger */
    extractBestowalIdFromTrigger(trigger) {
        if (!trigger) {
            return null;
        }

        const dataAttr = trigger.getAttribute?.("data-outlet-btn-btn-data-value");
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

        const row = trigger.closest?.("tr[data-id]");
        return row?.dataset?.id ?? null;
    }

    /** @return {HTMLElement|null} */
    getTurboFrame() {
        if (this.hasTurboFrameTarget) {
            return this.turboFrameTarget;
        }

        return this.element.querySelector("#bestowalTodosQuick");
    }

    /** @param {string|number} bestowalId */
    loadTodos(bestowalId) {
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
        if (frame.dataset.bestowalTodosLoadingId === loadingId && currentFrameUrl === url) {
            return;
        }

        frame.dataset.bestowalTodosLoadingId = loadingId;
        if (!frame.querySelector(".list-group")) {
            frame.replaceChildren();
            const loading = document.createElement("div");
            loading.className = "text-center p-4 text-muted";
            loading.textContent = "Loading...";
            frame.appendChild(loading);
        }
        frame.src = url;
    }

    /** Include or exclude past gatherings for a required-field to-do picker. */
    handleIncludePastChange(event) {
        const checkbox = event.target;
        const section = checkbox?.closest?.("[data-bestowal-gathering-requirement]");
        const control = section?.querySelector?.("[data-bestowal-gathering-control]");
        if (!control?.dataset?.baseUrl) {
            return;
        }

        const url = new URL(control.dataset.baseUrl, window.location.href);
        if (checkbox.checked) {
            url.searchParams.set("include_past", "1");
        } else {
            url.searchParams.delete("include_past");
        }
        control.dataset.acUrlValue = url.toString();
        this.clearGatheringControl(control);
    }

    /** @param {HTMLElement} control */
    clearGatheringControl(control) {
        const input = control.querySelector("[data-ac-target='input']");
        const hidden = control.querySelector("[data-ac-target='hidden']");
        const hiddenText = control.querySelector("[data-ac-target='hiddenText']");
        const clearBtn = control.querySelector("[data-ac-target='clearBtn']");
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
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-bestowal-todos"] = AwardsBestowalTodos;

export default AwardsBestowalTodos;
