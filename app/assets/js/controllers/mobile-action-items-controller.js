import MobileControllerBase from "./mobile-controller-base.js"
import { MOBILE_QUEUE_DEFAULT_PER_PAGE } from "../mobile-queue-pagination.js"

/**
 * Mobile Action Items Controller
 *
 * Touch-optimized My To-Dos queue with expandable owner cards and inline
 * completion. This page is online-only because completion writes server state.
 */
class MobileActionItemsController extends MobileControllerBase {
    static targets = [
        "list", "loading", "empty", "error", "errorMessage",
        "countBadge", "refreshBtn", "loadMore", "loadMoreBtn",
        "status", "toast", "toastBody"
    ]

    static values = {
        dataUrl: String,
        completeUrl: String,
        perPage: { type: Number, default: MOBILE_QUEUE_DEFAULT_PER_PAGE },
    }

    initialize() {
        super.initialize()
        this._groups = []
        this._expandedKey = null
        this._submitting = false
        this._page = 1
        this._perPage = this._configuredPerPage()
        this._totalCount = 0
        this._hasNextPage = false
    }

    onConnect() {
        if (this.online) {
            this.loadItems()
        }
    }

    onConnectionStateChanged(isOnline) {
        if (isOnline && this._groups.length === 0) {
            this.loadItems()
        }
    }

    async refresh() {
        if (this.hasRefreshBtnTarget) {
            this.refreshBtnTarget.disabled = true
        }
        await this.loadItems()
        if (this.hasRefreshBtnTarget) {
            this.refreshBtnTarget.disabled = false
        }
    }

    async loadItems({ append = false } = {}) {
        if (!append) {
            this._page = 1
            this._groups = []
            this._expandedKey = null
            this._showState("loading")
        } else {
            this._setLoadMoreBusy(true)
        }

        try {
            const response = await this.fetchWithRetry(this._pageUrl(this._page))
            const data = await response.json()
            const pagination = data.pagination || {}
            this._totalCount = parseInt(pagination.total ?? data.openCount ?? this._openCountForGroups(data.groups || []), 10) || 0
            this._perPage = parseInt(pagination.perPage ?? this._perPage, 10) || this._perPage
            this._hasNextPage = pagination.hasNextPage === true
            this._page = parseInt(pagination.page ?? this._page, 10) || this._page
            this._groups = append
                ? this._mergeGroups(this._groups, data.groups || [])
                : data.groups || []

            if (this._openCount() === 0) {
                this._showState("empty")
            } else {
                this._showState("list")
                this._renderCards()
            }

            this._updateBadge()
            this._updateLoadMore()
            if (append) {
                this._announce(`Loaded ${this._openCount()} of ${this._totalCount} open to-dos.`)
            }
        } catch (error) {
            console.error("Failed to load to-dos:", error)
            if (this.hasErrorMessageTarget) {
                this.errorMessageTarget.textContent = error.message || "Failed to load to-dos."
            }
            this._showState("error")
        } finally {
            this._setLoadMoreBusy(false)
        }
    }

    async loadMore() {
        if (!this._hasNextPage) return
        this._page += 1
        await this.loadItems({ append: true })
    }

    toggleGroup(event) {
        const trigger = event.currentTarget
        const key = trigger.dataset.groupKey
        if (!key) return

        const card = this.listTarget.querySelector(`[data-group-key="${this._cssEscape(key)}"]`)
        if (!card) return

        if (this._expandedKey === key) {
            this._collapseCard(card)
            this._expandedKey = null
            return
        }

        if (this._expandedKey !== null) {
            const previous = this.listTarget.querySelector(`[data-group-key="${this._cssEscape(this._expandedKey)}"]`)
            if (previous) {
                this._collapseCard(previous)
            }
        }

        this._expandCard(card, key)
        this._expandedKey = key
    }

    async completeItem(event) {
        event.preventDefault()
        if (this._submitting) return

        if (!this.online) {
            this._showToast("You must be online to complete to-dos.", "danger")
            this._announce("You must be online to complete to-dos.")
            return
        }

        const button = event.currentTarget
        const itemId = parseInt(button.dataset.itemId || "0", 10)
        if (itemId <= 0) return

        this._submitting = true
        const item = button.closest("[data-item-id]")
        if (item) {
            item.classList.add("todo-item-completing")
        }
        button.disabled = true
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Completing...'

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ""
            const body = new FormData()
            body.append("_csrfToken", csrfToken)

            const response = await fetch(this._completeUrl(itemId), {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-Token": csrfToken,
                },
                body,
            })
            const result = await this._readJsonResponse(response, "Failed to complete to-do. Please refresh and try again.")
            if (!response.ok || result.success === false) {
                throw new Error(result.error || `HTTP ${response.status}`)
            }

            this._removeItem(itemId)
            this._totalCount = Math.max(0, this._totalCount - 1)
            this._renderCards()
            this._updateBadge()
            this._updateLoadMore()
            const message = "To-do marked complete."
            this._showToast(message, "success")
            this._announce(message)
            if (this._openCount() === 0 && this._hasNextPage) {
                await this.loadMore()
            } else if (this._openCount() === 0) {
                this._showState("empty")
            }
        } catch (error) {
            const message = error.message || "Failed to complete to-do."
            this._showToast(message, "danger")
            this._announce(message)
            if (item) {
                item.classList.remove("todo-item-completing")
            }
            button.disabled = false
            button.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>Mark complete'
        } finally {
            this._submitting = false
        }
    }

    _renderCards() {
        if (!this.hasListTarget) return

        this.listTarget.innerHTML = this._groups.map(group => this._renderGroup(group)).join("")
    }

    _renderGroup(group) {
        const key = this._groupKey(group)
        const detailId = `todo-owner-detail-${this._escHtml(key)}`
        const count = Array.isArray(group.items) ? group.items.length : 0
        const meta = count === 1 ? "1 open to-do" : `${count} open to-dos`
        const sourceLink = group.url
            ? `<a href="${this._escHtml(group.url)}" class="btn btn-sm btn-outline-primary mb-3" data-turbo-frame="_top"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>View Source</a>`
            : ""

        return `<section class="card todo-owner-card" data-group-key="${this._escHtml(key)}" aria-labelledby="todo-owner-title-${this._escHtml(key)}">
            <button type="button"
                    class="todo-owner-summary"
                    data-action="click->mobile-action-items#toggleGroup"
                    data-group-key="${this._escHtml(key)}"
                    aria-expanded="false"
                    aria-controls="${detailId}">
                <span class="todo-owner-icon"><i class="bi bi-check2-all" aria-hidden="true"></i></span>
                <span class="todo-owner-info">
                    <span class="todo-owner-title d-block" id="todo-owner-title-${this._escHtml(key)}">${this._escHtml(group.label)}</span>
                    <span class="todo-owner-meta d-block">${meta}</span>
                </span>
                <span class="todo-owner-chevron"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
            </button>
            <div class="todo-owner-detail" id="${detailId}" hidden>
                ${sourceLink}
                ${(group.items || []).map(item => this._renderItem(item)).join("")}
            </div>
        </section>`
    }

    _renderItem(item) {
        const badge = item.isGating
            ? '<span class="badge bg-warning text-dark"><i class="bi bi-flag-fill me-1" aria-hidden="true"></i>Required</span>'
            : '<span class="badge bg-light text-dark border"><i class="bi bi-flag me-1" aria-hidden="true"></i>Optional</span>'
        const branch = item.branchName
            ? `<span class="badge bg-light text-dark border"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>${this._escHtml(item.branchName)}</span>`
            : ""

        return `<article class="todo-item" data-item-id="${item.id}">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div class="me-auto">
                    <h3 class="h6 mb-1">${this._escHtml(item.title)}</h3>
                    ${item.description ? `<p class="todo-item-description mb-2">${this._escHtml(item.description)}</p>` : ""}
                    <div class="d-flex flex-wrap gap-1">${badge}${branch}</div>
                </div>
                <button type="button"
                        class="btn btn-sm btn-success flex-shrink-0"
                        data-action="click->mobile-action-items#completeItem"
                        data-item-id="${item.id}"
                        aria-label="Mark complete: ${this._escHtml(item.title)}">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Mark complete
                </button>
            </div>
        </article>`
    }

    _expandCard(card, key) {
        card.classList.add("expanded")
        card.querySelector(".todo-owner-summary")?.setAttribute("aria-expanded", "true")
        const detail = card.querySelector(".todo-owner-detail")
        if (detail) {
            detail.hidden = false
        }
        this._announce(`Expanded ${card.querySelector(".todo-owner-title")?.textContent || "to-do group"}.`)
    }

    _collapseCard(card) {
        card.classList.remove("expanded")
        card.querySelector(".todo-owner-summary")?.setAttribute("aria-expanded", "false")
        const detail = card.querySelector(".todo-owner-detail")
        if (detail) {
            detail.hidden = true
        }
    }

    _removeItem(itemId) {
        this._groups = this._groups
            .map(group => ({
                ...group,
                items: (group.items || []).filter(item => item.id !== itemId),
            }))
            .filter(group => (group.items || []).length > 0)
    }

    async _readJsonResponse(response, fallbackMessage) {
        const contentType = response.headers.get("content-type") || ""
        if (!contentType.includes("application/json")) {
            throw new Error(fallbackMessage)
        }

        try {
            return await response.json()
        } catch (error) {
            throw new Error(fallbackMessage)
        }
    }

    _completeUrl(itemId) {
        return `${this.completeUrlValue.replace(/\/$/, "")}/${itemId}`
    }

    _groupKey(group) {
        return `${group.entityType || "entity"}-${group.entityId || "0"}`
    }

    _openCount() {
        return this._openCountForGroups(this._groups)
    }

    _openCountForGroups(groups) {
        return groups.reduce((count, group) => count + ((group.items || []).length), 0)
    }

    _showState(state) {
        if (this.hasLoadingTarget) this.loadingTarget.hidden = state !== "loading"
        if (this.hasEmptyTarget) this.emptyTarget.hidden = state !== "empty"
        if (this.hasErrorTarget) this.errorTarget.hidden = state !== "error"
        if (this.hasListTarget) this.listTarget.hidden = state !== "list"
        if (this.hasLoadMoreTarget) this.loadMoreTarget.hidden = state !== "list" || !this._hasNextPage
    }

    _updateBadge() {
        if (!this.hasCountBadgeTarget) return

        const loaded = this._openCount()
        const total = this._totalCount || loaded
        this.countBadgeTarget.textContent = loaded === total
            ? `${total} open`
            : `${loaded} of ${total} open`
        this.countBadgeTarget.hidden = total === 0
    }

    _updateLoadMore() {
        if (this.hasLoadMoreTarget) {
            this.loadMoreTarget.hidden = !this._hasNextPage || this._openCount() === 0
        }
        if (this.hasLoadMoreBtnTarget) {
            this.loadMoreBtnTarget.disabled = !this._hasNextPage
            const remaining = Math.max(0, this._totalCount - this._openCount())
            this.loadMoreBtnTarget.textContent = remaining > 0
                ? `Load more (${remaining} remaining)`
                : "Load more"
        }
    }

    _setLoadMoreBusy(isBusy) {
        if (!this.hasLoadMoreBtnTarget) return
        this.loadMoreBtnTarget.disabled = isBusy || !this._hasNextPage
        this.loadMoreBtnTarget.setAttribute("aria-busy", isBusy ? "true" : "false")
        if (isBusy) {
            this.loadMoreBtnTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Loading...'
        } else {
            this._updateLoadMore()
        }
    }

    _mergeGroups(existing, incoming) {
        const merged = existing.map(group => ({
            ...group,
            items: [...(group.items || [])],
        }))
        const groupIndex = new Map(merged.map((group, index) => [this._groupKey(group), index]))
        incoming.forEach(group => {
            const key = this._groupKey(group)
            if (!groupIndex.has(key)) {
                groupIndex.set(key, merged.length)
                merged.push({
                    ...group,
                    items: [...(group.items || [])],
                })
                return
            }
            const target = merged[groupIndex.get(key)]
            const seen = new Set((target.items || []).map(item => item.id))
            ;(group.items || []).forEach(item => {
                if (!seen.has(item.id)) {
                    target.items.push(item)
                    seen.add(item.id)
                }
            })
            target.openCount = target.items.length
        })

        return merged
    }

    _pageUrl(page) {
        const url = new URL(this.dataUrlValue, window.location.origin)
        url.searchParams.set("page", page)
        url.searchParams.set("per_page", this._perPage)
        return url.toString()
    }

    _configuredPerPage() {
        const perPage = parseInt(this.perPageValue, 10)
        return perPage > 0 ? perPage : MOBILE_QUEUE_DEFAULT_PER_PAGE
    }

    _showToast(message, type) {
        if (this.hasToastTarget && this.hasToastBodyTarget && window.bootstrap?.Toast) {
            this.toastBodyTarget.textContent = message
            this.toastTarget.className = `toast align-items-center border-0 text-bg-${type}`
            new window.bootstrap.Toast(this.toastTarget).show()
        }
    }

    _announce(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message
        }
        window.KMP_accessibility?.announce?.(message)
    }

    _cssEscape(value) {
        if (window.CSS?.escape) {
            return window.CSS.escape(value)
        }

        return String(value).replace(/["\\]/g, "\\$&")
    }

    _escHtml(str) {
        if (str === null || str === undefined) return ""
        const div = document.createElement("div")
        div.textContent = String(str)
        return div.innerHTML
    }
}

if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["mobile-action-items"] = MobileActionItemsController

export default MobileActionItemsController
