import { Controller } from "@hotwired/stimulus"

class ApprovalKanbanController extends Controller {
    static targets = ["lane", "card", "cardList", "loadMore", "status"]
    static values = {
        triageUrl: String,
        detailUrl: String,
    }

    connect() {
        this.draggedCard = null
    }

    disconnect() {
        this.draggedCard = null
    }

    handleBoardClick(event) {
        const button = event.target?.closest?.("[data-approval-kanban-load-more]")
        if (!button || !this.element.contains(button)) return

        return this.loadMore({ currentTarget: button, preventDefault: () => event.preventDefault() })
    }

    async loadMore(event) {
        event.preventDefault()
        const button = event.currentTarget
        const nextUrl = this.normalizeLoadMoreUrl(button.dataset.nextUrl)
        if (!nextUrl || button.disabled) return

        const lane = button.closest("[data-lane-state]")
        button.disabled = true
        button.setAttribute("aria-busy", "true")

        try {
            const response = await fetch(nextUrl, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            })
            if (!response.ok) throw new Error(`HTTP ${response.status}`)

            const html = await response.text()
            const doc = new DOMParser().parseFromString(html, "text/html")
            const incomingLane = doc.querySelector(".approval-kanban-lane")
            const incomingCards = incomingLane?.querySelectorAll(".approval-kanban-card") || []
            const cardList = lane.querySelector("[data-approval-kanban-target~='cardList']")
            if (incomingCards.length > 0) {
                cardList.querySelector(".approval-kanban-empty")?.remove()
            }
            incomingCards.forEach((card) => {
                const approvalId = card.dataset.approvalId
                if (approvalId && cardList.querySelector(`[data-approval-id="${this.escapeSelector(approvalId)}"]`)) {
                    return
                }
                cardList.appendChild(card)
            })

            const footer = lane.querySelector(".approval-kanban-lane-footer")
            const incomingFooter = incomingLane?.querySelector(".approval-kanban-lane-footer")
            if (footer && incomingFooter) {
                footer.replaceWith(incomingFooter)
            }

            this.announce("More approvals loaded.")
        } catch (error) {
            console.error("Failed to load more approvals:", error)
            button.disabled = false
            button.removeAttribute("aria-busy")
            this.announce("Unable to load more approvals. Please try again.", "assertive")
        }
    }

    normalizeLoadMoreUrl(rawUrl) {
        if (!rawUrl) return ""

        let decoded = rawUrl
        while (decoded.includes("&amp;")) {
            decoded = decoded.replaceAll("&amp;", "&")
        }

        try {
            const url = new URL(decoded, window.location.href)
            Array.from(url.searchParams.keys()).forEach((key) => {
                if (this.isEscapedAmpersandParam(key)) {
                    url.searchParams.delete(key)
                }
            })

            return url.origin === window.location.origin
                ? `${url.pathname}${url.search}${url.hash}`
                : url.toString()
        } catch (_error) {
            return decoded
        }
    }

    isEscapedAmpersandParam(key) {
        let decoded = key
        try {
            decoded = decodeURIComponent(decoded)
        } catch (_error) {
            // Keep the original key when it is not percent-encoded.
        }

        return decoded.startsWith("amp;")
    }

    dragStart(event) {
        this.draggedCard = event.currentTarget
        event.currentTarget.classList.add("approval-kanban-card-dragging")
        event.dataTransfer.effectAllowed = "move"
        event.dataTransfer.setData("text/plain", event.currentTarget.dataset.approvalId)
    }

    dragEnd(event) {
        event.currentTarget.classList.remove("approval-kanban-card-dragging")
        this.element.querySelectorAll(".approval-kanban-lane-drop").forEach((lane) => {
            lane.classList.remove("approval-kanban-lane-drop")
        })
        this.draggedCard = null
    }

    dragOver(event) {
        if (!this.draggedCard) return
        event.preventDefault()
        event.currentTarget.classList.add("approval-kanban-lane-drop")
        event.dataTransfer.dropEffect = "move"
    }

    dragLeave(event) {
        event.currentTarget.classList.remove("approval-kanban-lane-drop")
    }

    async drop(event) {
        event.preventDefault()
        const lane = event.currentTarget
        lane.classList.remove("approval-kanban-lane-drop")
        const card = this.draggedCard
        if (!card) return
        await this.moveCard(card, lane.dataset.laneState)
    }

    async moveFromSelect(event) {
        const select = event.currentTarget
        const card = select.closest(".approval-kanban-card")
        if (!card) return
        const previousState = card.dataset.currentState
        const moved = await this.moveCard(card, select.value)
        if (!moved) {
            select.value = previousState
        }
    }

    async moveCard(card, nextState) {
        const previousState = card.dataset.currentState
        if (!nextState || nextState === previousState) return true

        card.setAttribute("aria-busy", "true")
        const formData = new FormData()
        formData.append("approvalId", card.dataset.approvalId)
        formData.append("state", nextState)
        formData.append("note", card.dataset.triageNote || "")

        try {
            const response = await fetch(this.triageUrlValue, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-Token": this.getCsrfToken(),
                },
                body: formData,
            })
            const result = await this.readJsonResponse(response)
            if (!response.ok || result.success === false) {
                throw new Error(result.error || `HTTP ${response.status}`)
            }

            const sourceLane = card.closest(".approval-kanban-lane")
            this.placeCard(card, nextState)
            card.dataset.currentState = nextState
            const select = card.querySelector("select")
            if (select) select.value = nextState
            this.updateLaneCount(sourceLane, -1)
            this.updateLaneCount(card.closest(".approval-kanban-lane"), 1)
            this.refreshEmptyState(sourceLane)
            this.announce(`${card.dataset.cardTitle || "Approval"} moved.`)
            card.focus?.()

            return true
        } catch (error) {
            console.error("Failed to move approval card:", error)
            this.announce(error.message || "Unable to move approval card.", "assertive")

            return false
        } finally {
            card.removeAttribute("aria-busy")
        }
    }

    placeCard(card, state) {
        const escapedState = window.CSS?.escape
            ? window.CSS.escape(state)
            : state.replace(/["\\]/g, "\\$&")
        const targetLane = this.element.querySelector(`.approval-kanban-lane[data-lane-state="${escapedState}"]`)
        const targetList = targetLane?.querySelector("[data-approval-kanban-target~='cardList']")
        if (!targetList) return

        const empty = targetList.querySelector(".approval-kanban-empty")
        if (empty) empty.remove()
        targetList.prepend(card)
    }

    updateLaneCount(lane, delta) {
        if (!lane || delta === 0) return

        const nextCount = Math.max(0, Number.parseInt(lane.dataset.totalCount || "0", 10) + delta)
        lane.dataset.totalCount = String(nextCount)
        const countBadge = lane.querySelector(".approval-kanban-lane-header .badge")
        if (countBadge) {
            countBadge.textContent = String(nextCount)
        }
        const subtitle = lane.querySelector(".approval-kanban-lane-subtitle")
        if (subtitle) {
            subtitle.textContent = `${nextCount} approval${nextCount === 1 ? "" : "s"}`
        }
    }

    refreshEmptyState(lane) {
        if (!lane) return

        const cardList = lane.querySelector("[data-approval-kanban-target~='cardList']")
        if (!cardList || cardList.querySelector(".approval-kanban-card")) return
        if (cardList.querySelector(".approval-kanban-empty")) return

        const empty = document.createElement("div")
        empty.className = "approval-kanban-empty"
        empty.setAttribute("role", "note")
        empty.innerHTML = '<i class="bi bi-inbox" aria-hidden="true"></i><span>No approvals here.</span>'
        cardList.appendChild(empty)
    }

    async toggleDetails(event) {
        event.preventDefault()
        const button = event.currentTarget
        const card = button.closest(".approval-kanban-card")
        const region = card?.querySelector(`#${button.getAttribute("aria-controls")}`)
        const approvalId = button.dataset.approvalId
        if (!region || !approvalId) return

        const expanded = button.getAttribute("aria-expanded") === "true"
        if (expanded) {
            region.hidden = true
            button.setAttribute("aria-expanded", "false")
            this.setButtonIcon(button, "bi-chevron-down")
            return
        }

        button.disabled = true
        this.setButtonIcon(button, "bi-hourglass-split")
        try {
            if (!region.dataset.loaded) {
                const response = await fetch(`${this.detailUrlValue.replace(/\/$/, "")}/${approvalId}`, {
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                })
                if (!response.ok) throw new Error(`HTTP ${response.status}`)
                const data = await response.json()
                region.innerHTML = this.renderDetail(data)
                region.dataset.loaded = "true"
            }
            region.hidden = false
            button.setAttribute("aria-expanded", "true")
            this.setButtonIcon(button, "bi-chevron-up")
        } catch (error) {
            console.error("Failed to load approval detail:", error)
            this.announce("Unable to load approval details.", "assertive")
            this.setButtonIcon(button, "bi-chevron-down")
        } finally {
            button.disabled = false
        }
    }

    renderDetail(data) {
        const fields = data.context?.fields || []
        const fieldHtml = fields.map((field) => `
            <div class="approval-kanban-detail-field">
                <dt>${this.escapeHtml(field.label)}</dt>
                <dd>${this.escapeHtml(field.value)}</dd>
            </div>
        `).join("")
        const responses = data.responses || []
        const responseHtml = responses.length
            ? responses.map((response) => `
                <li>
                    <strong>${this.escapeHtml(response.memberName)}</strong>
                    <span class="text-muted">${this.escapeHtml(response.decision)} · ${this.escapeHtml(response.respondedAt)}</span>
                </li>
            `).join("")
            : `<li class="text-muted">${this.escapeHtml("No responses yet.")}</li>`

        return `
            <div class="approval-kanban-detail-panel">
                <p>${this.escapeHtml(data.context?.description || "")}</p>
                <dl>${fieldHtml}</dl>
                <ul class="approval-kanban-response-list">${responseHtml}</ul>
            </div>
        `
    }

    setButtonIcon(button, iconClass) {
        const icon = button.querySelector("i.bi")
        if (!icon) return
        icon.className = `bi ${iconClass} me-1`
        icon.setAttribute("aria-hidden", "true")
    }

    async readJsonResponse(response) {
        const contentType = response.headers.get("content-type") || ""
        if (!contentType.includes("application/json")) {
            throw new Error("Unexpected response from the server.")
        }
        return response.json()
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || ""
    }

    announce(message, politeness = "polite") {
        if (window.KMP_accessibility?.announce) {
            window.KMP_accessibility.announce(message, politeness)
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message
        }
    }

    escapeHtml(value) {
        const div = document.createElement("div")
        div.textContent = value == null ? "" : String(value)
        return div.innerHTML
    }

    escapeSelector(value) {
        if (window.CSS?.escape) {
            return window.CSS.escape(value)
        }

        return String(value).replace(/["\\]/g, "\\$&")
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["approval-kanban"] = ApprovalKanbanController

export default ApprovalKanbanController
