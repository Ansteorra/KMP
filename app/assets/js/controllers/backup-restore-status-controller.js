import { Controller } from "@hotwired/stimulus"

/**
 * Polls restore status and drives AJAX restore modal feedback.
 */
class BackupRestoreStatusController extends Controller {
    static values = {
        url: String,
        interval: { type: Number, default: 1000 },
        autoReload: { type: Boolean, default: true },
        terminalWindow: { type: Number, default: 30 }
    }

    static targets = [
        "panel",
        "badge",
        "message",
        "details",
        "modal",
        "modalBadge",
        "modalMessage",
        "modalDetails",
        "modalSpinner",
        "modalClose"
    ]

    connect() {
        this.reloadScheduled = false
        this.hasSeenRunningState = false
        this.awaitingFreshRunningState = false
        this.restoreRequestInFlight = false
        this.currentStatus = null
        this.statusRequestInFlight = false
        this.modalInstance = this.hasModalTarget ? new bootstrap.Modal(this.modalTarget) : null
        this.pollStatus()
        this.startPolling()
    }

    disconnect() {
        this.stopPolling()
    }

    startPolling() {
        this.stopPolling()
        this.timer = setInterval(() => this.pollStatus(), this.intervalValue)
    }

    stopPolling() {
        if (this.timer) {
            clearInterval(this.timer)
            this.timer = null
        }
    }

    async submitRestore(event) {
        event.preventDefault()
        if (this.restoreRequestInFlight) {
            return
        }

        const form = event.currentTarget
        if (!(form instanceof HTMLFormElement)) {
            return
        }

        const confirmMessage = form.dataset.confirmMessage || 'Restore this backup and replace all current data?'
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return
        }

        const restoreKeyPrompt = form.dataset.restoreKeyPrompt || 'Enter the backup encryption key to continue restore:'
        const restoreKey = window.prompt(restoreKeyPrompt)
        if (restoreKey === null) {
            return
        }
        if (restoreKey.trim() === '') {
            window.alert('An encryption key is required to restore this backup.')
            return
        }

        this.reloadScheduled = false
        this.awaitingFreshRunningState = true
        this.restoreRequestInFlight = true
        this.showModal({
            state: 'running',
            badgeLabel: 'starting',
            badgeClass: 'bg-info',
            message: 'Restore request submitted. Waiting for status updates...',
            details: 'Preparing restore...',
            panelClass: 'alert-warning',
            showSpinner: true,
        })
        this.setModalClosable(false)

        const formData = new FormData(form)
        formData.set('restore_key', restoreKey.trim())
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: this.requestHeaders(),
            })
            const payload = await this.parseJson(response)
            if (!response.ok || payload?.success === false) {
                throw new Error(payload?.message || 'Restore request failed.')
            }
            this.awaitingFreshRunningState = false
            this.hasSeenRunningState = true

            const completedStatus = {
                locked: false,
                status: 'completed',
                phase: 'completed',
                message: payload?.message || 'Restore/import completed.',
                table_count: payload?.stats?.table_count,
                tables_processed: payload?.stats?.table_count,
                row_count: payload?.stats?.row_count,
                rows_processed: payload?.stats?.row_count,
                completed_at: new Date().toISOString(),
            }
            this.render(completedStatus)
            this.scheduleReload()
            await this.pollStatus(true)
        } catch (error) {
            const failedStatus = {
                locked: false,
                status: 'failed',
                phase: 'failed',
                message: error instanceof Error ? error.message : 'Restore request failed.',
                completed_at: new Date().toISOString(),
            }
            this.render(failedStatus)
        } finally {
            this.restoreRequestInFlight = false
            this.setModalClosable(true)
        }
    }

    async pollStatus() {
        if (!this.hasUrlValue) {
            return
        }

        if (this.statusRequestInFlight) {
            return
        }
        this.statusRequestInFlight = true

        try {
            const response = await fetch(this.urlValue, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            })
            if (!response.ok) {
                return
            }

            const status = await response.json()
            this.currentStatus = status
            this.render(status)
            this.reloadOnCompletion(status)
        } catch (error) {
            console.debug('Backup restore status poll failed:', error)
        } finally {
            this.statusRequestInFlight = false
        }
    }

    render(status) {
        const normalizedStatus = this.normalizeStatus(status)

        if (this.hasBadgeTarget) {
            this.badgeTarget.textContent = normalizedStatus.badgeLabel
            this.badgeTarget.className = `badge ${normalizedStatus.badgeClass}`
        }

        if (this.hasMessageTarget) {
            this.messageTarget.textContent = normalizedStatus.message
        }

        if (this.hasDetailsTarget) {
            this.detailsTarget.textContent = normalizedStatus.details
        }

        if (this.hasPanelTarget) {
            this.panelTarget.className = `alert ${normalizedStatus.panelClass} mb-3`
        }

        this.renderModal(normalizedStatus)
    }

    reloadOnCompletion(status) {
        if (!this.autoReloadValue || this.reloadScheduled) {
            return
        }

        const locked = Boolean(status?.locked)
        const state = status?.status || 'idle'
        if (locked || state === 'running') {
            this.hasSeenRunningState = true
            this.awaitingFreshRunningState = false
            return
        }

        if (this.awaitingFreshRunningState) {
            return
        }

        if (!this.hasSeenRunningState) {
            return
        }

        const normalizedStatus = this.normalizeStatus(status)
        if (normalizedStatus.state === 'completed') {
            this.scheduleReload()
        }
    }

    normalizeStatus(status) {
        const locked = Boolean(status?.locked)
        const rawState = status?.status || 'idle'
        const state = !locked && this.isTerminalState(rawState) && !this.isRecentTerminalState(status)
            ? 'idle'
            : rawState
        const phase = status?.phase || state
        const tableCount = Number(status?.table_count || 0)
        const tablesProcessed = Number(status?.tables_processed || 0)
        const rowsProcessed = Number(status?.rows_processed || 0)
        const source = status?.source || ''
        const currentTable = status?.current_table || ''
        const message = state === 'idle'
            ? 'No restore currently running.'
            : (status?.message || 'No restore currently running.')

        const details = []
        if (state !== 'idle' && source) {
            details.push(`Source: ${source}`)
        }
        if (state !== 'idle' && tableCount > 0) {
            details.push(`Tables: ${tablesProcessed}/${tableCount}`)
        }
        if (state !== 'idle' && rowsProcessed > 0) {
            details.push(`Rows: ${rowsProcessed.toLocaleString()}`)
        }
        if (state !== 'idle' && currentTable) {
            details.push(`Current: ${currentTable}`)
        }

        return {
            state,
            locked,
            message,
            details: details.length > 0 ? details.join(' | ') : 'No active restore.',
            badgeLabel: locked ? phase : state,
            badgeClass: this.badgeClass(locked, state),
            panelClass: this.panelClass(locked, state),
            showSpinner: locked || state === 'running',
        }
    }

    isTerminalState(state) {
        return state === 'completed' || state === 'failed' || state === 'interrupted'
    }

    isRecentTerminalState(status) {
        if (!status?.completed_at) {
            return false
        }

        const completedAt = Date.parse(status.completed_at)
        if (Number.isNaN(completedAt)) {
            return false
        }

        return (Date.now() - completedAt) <= (this.terminalWindowValue * 1000)
    }

    badgeClass(locked, state) {
        if (locked || state === 'running') {
            return 'bg-info'
        }
        if (state === 'completed') {
            return 'bg-success'
        }
        if (state === 'failed') {
            return 'bg-danger'
        }

        return 'bg-secondary'
    }

    panelClass(locked, state) {
        if (locked || state === 'running') {
            return 'alert-warning'
        }
        if (state === 'completed') {
            return 'alert-success'
        }
        if (state === 'failed') {
            return 'alert-danger'
        }

        return 'alert-secondary'
    }

    renderModal(normalizedStatus) {
        if (!this.hasModalTarget || !this.modalInstance) {
            return
        }

        if (this.hasModalBadgeTarget) {
            this.modalBadgeTarget.textContent = normalizedStatus.badgeLabel
            this.modalBadgeTarget.className = `badge ${normalizedStatus.badgeClass}`
        }
        if (this.hasModalMessageTarget) {
            this.modalMessageTarget.textContent = normalizedStatus.message
        }
        if (this.hasModalDetailsTarget) {
            this.modalDetailsTarget.textContent = normalizedStatus.details
        }
        if (this.hasModalSpinnerTarget) {
            this.modalSpinnerTarget.classList.toggle('d-none', !normalizedStatus.showSpinner)
        }

        if (this.restoreRequestInFlight || normalizedStatus.showSpinner) {
            this.modalInstance.show()
        }
    }

    showModal(normalizedStatus) {
        if (!this.hasModalTarget || !this.modalInstance) {
            return
        }

        this.modalInstance.show()
        this.renderModal(normalizedStatus)
    }

    setModalClosable(isClosable) {
        if (!this.hasModalCloseTarget) {
            return
        }

        this.modalCloseTargets.forEach((button) => {
            button.disabled = !isClosable
        })
    }

    requestHeaders() {
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }
        const csrfMeta = document.querySelector("meta[name='csrf-token']")
        if (csrfMeta && csrfMeta.content) {
            headers['X-CSRF-Token'] = csrfMeta.content
        }

        return headers
    }

    async parseJson(response) {
        const text = await response.text()
        if (!text) {
            return null
        }
        try {
            return JSON.parse(text)
        } catch (_error) {
            return null
        }
    }

    scheduleReload() {
        if (this.reloadScheduled) {
            return
        }
        this.reloadScheduled = true
        window.setTimeout(() => window.location.reload(), 1200)
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["backup-restore-status"] = BackupRestoreStatusController;
