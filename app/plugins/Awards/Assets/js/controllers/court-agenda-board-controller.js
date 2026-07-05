import { Controller } from "@hotwired/stimulus"

class CourtAgendaBoardController extends Controller {
    static targets = ["item", "segment", "status"]
    static values = {
        moveUrl: String,
        csrfToken: String,
    }

    connect() {
        this.draggedItem = null
        this.dropPlaceholder = null
        this.currentDropSegment = null
        this.currentDropPlacement = null
    }

    disconnect() {
        this.clearDropPreview()
        this.draggedItem = null
    }

    dragStart(event) {
        const item = event.currentTarget
        this.draggedItem = item
        event.dataTransfer.effectAllowed = "move"
        event.dataTransfer.setData("text/plain", item.dataset.itemId || "")
        event.dataTransfer.setData("application/x-court-agenda-item", item.dataset.itemId || "")
        item.classList.add("opacity-50", "border-primary")
    }

    dragEnd(event) {
        event.currentTarget.classList.remove("opacity-50", "border-primary")
        this.clearDropPreview()
        this.draggedItem = null
    }

    dragOver(event) {
        if (!this.draggedItem) {
            return
        }
        event.preventDefault()
        const segment = event.currentTarget
        const placement = this.dropPlacement(event, segment, this.draggedItem)
        this.showDropPreview(segment, placement)
        event.dataTransfer.dropEffect = "move"
    }

    dragLeave(event) {
        const relatedTarget = event.relatedTarget
        if (relatedTarget instanceof Node && event.currentTarget.contains(relatedTarget)) {
            return
        }

        this.clearDropPreview()
    }

    async drop(event) {
        event.preventDefault()
        const segment = event.currentTarget
        const itemId = event.dataTransfer.getData("application/x-court-agenda-item")
            || event.dataTransfer.getData("text/plain")
        const segmentId = segment.dataset.segmentId
        const item = this.draggedItem || this.element.querySelector(`[data-item-id="${this.escapeSelector(itemId)}"]`)
        if (!itemId || !segmentId || !item) {
            this.clearDropPreview()
            this.announce("Could not identify the agenda item to move.")
            return
        }

        const placement = this.currentDropSegment === segment && this.currentDropPlacement
            ? this.currentDropPlacement
            : this.dropPlacement(event, segment, item)
        const sortOrder = this.sortOrderForPlacement(segment, placement, item)
        this.clearDropPreview()
        const moved = await this.moveItem(itemId, segmentId, sortOrder)
        if (moved) {
            this.placeItem(item, segment, placement)
            item.dataset.sortOrder = String(sortOrder)
        }
    }

    async moveByButton(event) {
        const button = event.currentTarget
        const item = button.closest("[data-item-id]")
        if (!item) {
            return
        }

        const segment = item.closest("[data-segment-id]")
        const direction = button.dataset.direction
        if (!segment || !direction) {
            return
        }

        const items = this.itemsForSegment(segment)
        const index = items.indexOf(item)
        let targetSort = Number(item.dataset.sortOrder || "10")
        let targetSegment = segment
        let placement = { targetItem: null, position: "end" }
        if (direction === "up" && index > 0) {
            placement = { targetItem: items[index - 1], position: "before" }
            targetSort = this.sortOrderForPlacement(segment, placement, item)
        } else if (direction === "down" && index < items.length - 1) {
            placement = { targetItem: items[index + 1], position: "after" }
            targetSort = this.sortOrderForPlacement(segment, placement, item)
        } else if (direction === "previous-segment" || direction === "next-segment") {
            targetSegment = this.adjacentSegment(segment, direction === "previous-segment" ? -1 : 1)
            if (!targetSegment) {
                this.announce("Agenda item is already in the edge segment.")
                return
            }
            targetSort = this.nextSortOrder(targetSegment)
        } else {
            this.announce("Agenda item is already at that edge.")
            return
        }

        const moved = await this.moveItem(item.dataset.itemId, targetSegment.dataset.segmentId, targetSort)
        if (moved) {
            this.placeItem(item, targetSegment, placement)
            item.dataset.sortOrder = String(targetSort)
            item.focus({ preventScroll: true })
        }
    }

    async moveItem(itemId, segmentId, sortOrder) {
        const body = new URLSearchParams()
        body.set("item_id", itemId)
        body.set("court_agenda_segment_id", segmentId)
        body.set("sort_order", String(sortOrder))

        const response = await fetch(this.moveUrlValue, {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
                "X-CSRF-Token": this.csrfTokenValue,
            },
            body,
        })

        if (!response.ok) {
            this.announce("Could not move agenda item.")
            return false
        }

        this.announce("Agenda item moved.")
        return true
    }

    nextSortOrder(segment) {
        const items = this.itemsForSegment(segment)
        if (items.length === 0) {
            return 10
        }

        return Math.max(...items.map((item) => Number(item.dataset.sortOrder || "0"))) + 10
    }

    itemsForSegment(segment) {
        return Array.from(segment.querySelectorAll("[data-item-id]"))
    }

    adjacentSegment(segment, offset) {
        const segments = Array.from(this.element.querySelectorAll("[data-court-agenda-board-target~='segment']"))
        const index = segments.indexOf(segment)
        if (index === -1) {
            return null
        }

        return segments[index + offset] || null
    }

    dropPlacement(event, segment, draggedItem) {
        if (!Number.isFinite(event.clientY)) {
            return { targetItem: null, position: "end" }
        }

        const items = this.itemsForSegment(segment).filter((item) => item !== draggedItem)
        for (const item of items) {
            const rect = item.getBoundingClientRect()
            if (event.clientY < rect.top + (rect.height / 2)) {
                return { targetItem: item, position: "before" }
            }
        }

        return { targetItem: null, position: "end" }
    }

    showDropPreview(segment, placement) {
        const placeholder = this.ensureDropPlaceholder()
        this.element.querySelectorAll(".court-agenda-segment-drop").forEach((dropSegment) => {
            dropSegment.classList.remove("court-agenda-segment-drop", "border", "border-primary", "rounded")
        })
        segment.classList.add("court-agenda-segment-drop", "border", "border-primary", "rounded")

        if (this.draggedItem && this.draggedItem.offsetHeight > 0) {
            placeholder.style.minHeight = `${this.draggedItem.offsetHeight}px`
        } else {
            placeholder.style.removeProperty("min-height")
        }

        this.placeItem(placeholder, segment, placement)
        this.currentDropSegment = segment
        this.currentDropPlacement = placement
    }

    ensureDropPlaceholder() {
        if (this.dropPlaceholder) {
            return this.dropPlaceholder
        }

        const placeholder = document.createElement("article")
        placeholder.className = [
            "card",
            "mb-3",
            "border",
            "border-primary",
            "border-2",
            "bg-primary",
            "bg-opacity-10",
            "pe-none",
            "court-agenda-drop-placeholder",
        ].join(" ")
        placeholder.setAttribute("aria-hidden", "true")
        placeholder.innerHTML = `
            <div class="card-body py-3 text-center">
                <span class="fw-semibold text-primary">Drop here</span>
            </div>
        `
        this.dropPlaceholder = placeholder

        return placeholder
    }

    clearDropPreview() {
        if (this.dropPlaceholder) {
            this.dropPlaceholder.remove()
            this.dropPlaceholder = null
        }
        this.currentDropSegment = null
        this.currentDropPlacement = null
        this.element.querySelectorAll(".court-agenda-segment-drop").forEach((segment) => {
            segment.classList.remove("court-agenda-segment-drop", "border", "border-primary", "rounded")
        })
    }

    sortOrderForPlacement(segment, placement, movingItem = this.draggedItem) {
        if (!placement.targetItem) {
            return this.nextSortOrder(segment)
        }

        const items = this.itemsForSegment(segment).filter((item) => item !== movingItem)
        const targetIndex = items.indexOf(placement.targetItem)
        if (targetIndex === -1) {
            return this.nextSortOrder(segment)
        }

        if (placement.position === "before") {
            return Number(placement.targetItem.dataset.sortOrder || "10") - 1
        }

        return Number(placement.targetItem.dataset.sortOrder || "10") + 1
    }

    placeItem(item, segment, placement) {
        if (!placement.targetItem) {
            segment.appendChild(item)
            return
        }

        if (placement.position === "before") {
            segment.insertBefore(item, placement.targetItem)
            return
        }

        segment.insertBefore(item, placement.targetItem.nextSibling)
    }

    escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === "function") {
            return window.CSS.escape(String(value))
        }

        return String(value).replace(/["\\]/g, "\\$&")
    }

    announce(message) {
        if (window.KMP_accessibility && typeof window.KMP_accessibility.announce === "function") {
            window.KMP_accessibility.announce(message)
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message
        }
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}

window.Controllers["court-agenda-board"] = CourtAgendaBoardController

export default CourtAgendaBoardController
