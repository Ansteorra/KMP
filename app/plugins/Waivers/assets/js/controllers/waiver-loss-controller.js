import { Controller } from "@hotwired/stimulus"

/** Bind loss reports to the grid row and return focus to its action. */
class WaiverLossController extends Controller {
    static targets = ["type", "typeName", "notes"]

    selectRow(event) {
        this.trigger = event.relatedTarget
        this.typeTarget.value = this.trigger.dataset.waiverLossTypeId
        this.typeNameTarget.textContent = this.trigger.dataset.waiverLossTypeName
        this.notesTarget.value = ""
    }

    focusNotes() {
        this.notesTarget.focus()
    }

    restoreFocus() {
        if (this.trigger?.isConnected) this.trigger.focus()
    }
}

window.Controllers ??= {}
window.Controllers["waiver-loss"] = WaiverLossController
