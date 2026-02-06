import { Controller } from "@hotwired/stimulus"

/**
 * Gatherings Calendar Controller
 *
 * Manages the interactive calendar view for gatherings across the kingdom.
 * 
 * Features:
 * - Quick view modal for gathering details
 * - Toggle attendance for gatherings
 * - Location map integration
 * - Real-time UI updates
 * - Responsive calendar navigation
 * 
 * HTML Structure:
 * ```html
 * <div data-controller="gatherings-calendar"
 *      data-gatherings-calendar-year-value="2025"
 *      data-gatherings-calendar-month-value="10"
 *      data-gatherings-calendar-view-value="month">
 *   
 *   <!-- Calendar grid -->
 *   <div class="gathering-item"
 *        data-action="click->gatherings-calendar#showQuickView"
 *        data-gathering-id="123">
 *     Gathering Name
 *   </div>
 * </div>
 * ```
 */
class GatheringsCalendarController extends Controller {

    static values = {
        year: Number,
        month: Number,
        view: String,
        weekStart: String
    }

    /**
     * Initialize the calendar controller
     */
    initialize() {
        this.modalElement = null
        this.modalInstance = null
        this.turboFrame = null
    }

    /**
     * Connect event - setup Bootstrap modal
     */
    connect() {
        console.log('Gatherings Calendar Controller connected')

        // Find the modal and turbo-frame elements
        this.modalElement = document.getElementById('gatheringQuickViewModal')
        this.turboFrame = document.getElementById('gatheringQuickView')

        console.log('Modal element:', this.modalElement)
        console.log('Turbo frame:', this.turboFrame)

        if (this.modalElement) {
            this.modalInstance = new bootstrap.Modal(this.modalElement)
            console.log('Modal instance created')
        } else {
            console.error('Modal element not found!')
        }

        if (!this.turboFrame) {
            console.error('Turbo frame element not found!')
        }

        this.updateCalendarHeader()
        this.updateCalendarNavigation()
        this.updateFeedUrl()

        // Update feed URL when browser URL changes (after filter navigation)
        this._popstateHandler = () => this.updateFeedUrl()
        window.addEventListener('popstate', this._popstateHandler)

        // Also catch pushState calls from the grid-view controller
        this._pushStateHandler = () => this.updateFeedUrl()
        window.addEventListener('grid-view:navigated', this._pushStateHandler)
    }

    /**
     * Show quick view modal for a gathering
     * 
     * @param {Event} event Click event
     */
    async showQuickView(event) {
        event.preventDefault() // Prevent normal navigation

        console.log('showQuickView called - opening modal')

        // Get the gathering URL from the link
        const url = event.currentTarget.getAttribute('href')
        console.log('Loading gathering from:', url)

        // Show the modal first
        if (this.modalInstance) {
            this.modalInstance.show()
            console.log('Modal shown')
        } else {
            console.error('Modal instance not found')
            return
        }

        // Fetch and load content into turbo-frame
        if (this.turboFrame) {
            try {
                console.log('Fetching content from:', url)
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'Turbo-Frame': 'gatheringQuickView'
                    }
                })

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`)
                }

                const html = await response.text()
                console.log('Received HTML, length:', html.length)

                // Parse the HTML to extract just the turbo-frame content
                const parser = new DOMParser()
                const doc = parser.parseFromString(html, 'text/html')
                const turboFrameContent = doc.querySelector('turbo-frame#gatheringQuickView')

                if (turboFrameContent) {
                    // Clear existing content
                    while (this.turboFrame.firstChild) {
                        this.turboFrame.removeChild(this.turboFrame.firstChild)
                    }

                    // Move child nodes from parsed content to our turbo-frame
                    // This preserves attributes without HTML encoding
                    while (turboFrameContent.firstChild) {
                        this.turboFrame.appendChild(turboFrameContent.firstChild)
                    }

                    console.log('Content loaded into turbo-frame')

                    // Fix close button - Bootstrap's event delegation doesn't work on dynamically loaded content
                    const closeButton = this.modalElement.querySelector('.btn-close')
                    // Remove previous listener if exists to avoid accumulating handlers
                    if (this._closeButtonHandler && closeButton) {
                        closeButton.removeEventListener('click', this._closeButtonHandler)
                    }
                    if (closeButton) {
                        this._closeButtonHandler = () => {
                            if (this.modalInstance) {
                                this.modalInstance.hide()
                            }
                        }
                        closeButton.addEventListener('click', this._closeButtonHandler)
                    }
                } else {
                    console.error('Could not find turbo-frame in response')
                    console.log('Response HTML:', html.substring(0, 500))
                    this.turboFrame.innerHTML = '<div class="alert alert-danger">Failed to load gathering details</div>'
                }
            } catch (error) {
                console.error('Error loading gathering:', error)
                this.turboFrame.innerHTML = '<div class="alert alert-danger">Error loading gathering details</div>'
            }
        } else {
            console.error('Turbo frame not found')
        }
    }

    getCalendarElement() {
        if (this.element && this.element.dataset) {
            if (
                this.element.dataset.gatheringsCalendarYearValue !== undefined ||
                this.element.dataset.gatheringsCalendarViewValue !== undefined
            ) {
                return this.element
            }
        }

        return document.querySelector('[data-gatherings-calendar-year-value]')
    }

    getDisplayedCalendarState() {
        const state = {
            year: null,
            month: null,
            view: null,
            weekStart: null
        }

        const element = this.getCalendarElement()
        if (element && element.dataset) {
            const yearValue = parseInt(element.dataset.gatheringsCalendarYearValue, 10)
            if (!Number.isNaN(yearValue)) {
                state.year = yearValue
            }

            const monthValue = parseInt(element.dataset.gatheringsCalendarMonthValue, 10)
            if (!Number.isNaN(monthValue)) {
                state.month = monthValue
            }

            const viewValue = element.dataset.gatheringsCalendarViewValue
            if (viewValue) {
                state.view = viewValue
            }

            const weekStartValue = element.dataset.gatheringsCalendarWeekStartValue
            if (weekStartValue) {
                state.weekStart = weekStartValue
            }
        }

        if (state.year === null && this.hasYearValue) {
            state.year = this.yearValue
        }

        if (state.month === null && this.hasMonthValue) {
            state.month = this.monthValue
        }

        if (!state.view && this.hasViewValue) {
            state.view = this.viewValue
        }

        if (!state.weekStart && this.hasWeekStartValue) {
            state.weekStart = this.weekStartValue
        }

        return state
    }

    updateCalendarHeader() {
        const header = document.querySelector('[data-gatherings-calendar-header]')
        if (!header) {
            return
        }

        const displayed = this.getDisplayedCalendarState()
        if (!displayed.year || !displayed.month) {
            return
        }

        const date = new Date(displayed.year, displayed.month - 1, 1)
        if (Number.isNaN(date.getTime())) {
            return
        }

        const label = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(date)
        header.textContent = label
    }

    /**
     * Rebuild the subscribe feed URL from current browser URL filter params
     */
    updateFeedUrl() {
        const feedInput = document.getElementById('calendarFeedUrl')
        if (!feedInput) {
            return
        }

        const baseFeedUrl = feedInput.dataset.baseFeedUrl
        if (!baseFeedUrl) {
            return
        }

        // Pass through all filter[*] params to the feed URL
        const params = new URLSearchParams(window.location.search)
        const feedParams = new URLSearchParams()

        params.forEach((value, key) => {
            if (key.startsWith('filter[')) {
                feedParams.append(key, value)
            }
        })

        const feedQuery = feedParams.toString()
        feedInput.value = feedQuery ? baseFeedUrl + '?' + feedQuery : baseFeedUrl
    }

    updateCalendarNavigation() {
        const tableFrame = document.getElementById('gatherings-calendar-grid-table')
        if (!tableFrame) {
            return
        }

        const frameSrc = tableFrame.getAttribute('src') || tableFrame.src || tableFrame.getAttribute('data-grid-src') || tableFrame.dataset.gridSrc
        if (!frameSrc) {
            return
        }

        let url
        try {
            url = new URL(frameSrc, window.location.origin)
        } catch (error) {
            return
        }

        const params = new URLSearchParams(url.search)
        params.delete('page')

        const displayed = this.getDisplayedCalendarState()

        const parseNumber = (value) => {
            const parsed = parseInt(value, 10)
            return Number.isNaN(parsed) ? null : parsed
        }

        const pad2 = (value) => String(value).padStart(2, '0')
        const formatDate = (date) => `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`
        const parseDate = (value) => {
            if (!value) {
                return null
            }

            const parts = String(value).split('-')
            if (parts.length !== 3) {
                return null
            }

            const year = parseNumber(parts[0])
            const month = parseNumber(parts[1])
            const day = parseNumber(parts[2])

            if (!year || !month || !day) {
                return null
            }

            return new Date(year, month - 1, day)
        }

        const view = displayed.view || params.get('view') || (this.hasViewValue ? this.viewValue : 'month')
        if (view) {
            params.set('view', view)
        }

        let currentYear = displayed.year
        if (currentYear === null) {
            currentYear = parseNumber(params.get('year'))
        }
        if (currentYear === null && this.hasYearValue) {
            currentYear = this.yearValue
        }
        if (currentYear === null) {
            currentYear = new Date().getFullYear()
        }

        let currentMonth = displayed.month
        if (currentMonth === null) {
            currentMonth = parseNumber(params.get('month'))
        }
        if (currentMonth === null && this.hasMonthValue) {
            currentMonth = this.monthValue
        }
        if (currentMonth === null) {
            currentMonth = new Date().getMonth() + 1
        }

        if (!Number.isNaN(currentYear)) {
            params.set('year', currentYear)
        }
        if (!Number.isNaN(currentMonth)) {
            params.set('month', pad2(currentMonth))
        }

        const buildHref = (nextParams) => {
            const nextUrl = new URL(url.pathname, window.location.origin)
            nextUrl.search = nextParams.toString()
            return nextUrl.pathname + (nextUrl.search ? `?${nextParams.toString()}` : '')
        }

        const prevLink = document.querySelector('[data-gatherings-calendar-nav="prev"]')
        const nextLink = document.querySelector('[data-gatherings-calendar-nav="next"]')
        const todayLink = document.querySelector('[data-gatherings-calendar-nav="today"]')

        if (view === 'week') {
            const weekStartParam = displayed.weekStart || params.get('week_start')
            let weekStart = parseDate(weekStartParam)
            if (!weekStart && currentYear && currentMonth) {
                weekStart = new Date(currentYear, currentMonth - 1, 1)
            }

            if (weekStart && !Number.isNaN(weekStart.getTime())) {
                const prevWeek = new Date(weekStart)
                prevWeek.setDate(prevWeek.getDate() - 7)
                const nextWeek = new Date(weekStart)
                nextWeek.setDate(nextWeek.getDate() + 7)
                const today = new Date()

                if (prevLink) {
                    const prevParams = new URLSearchParams(params)
                    prevParams.set('year', prevWeek.getFullYear())
                    prevParams.set('month', pad2(prevWeek.getMonth() + 1))
                    prevParams.set('week_start', formatDate(prevWeek))
                    prevLink.setAttribute('href', buildHref(prevParams))
                }

                if (nextLink) {
                    const nextParams = new URLSearchParams(params)
                    nextParams.set('year', nextWeek.getFullYear())
                    nextParams.set('month', pad2(nextWeek.getMonth() + 1))
                    nextParams.set('week_start', formatDate(nextWeek))
                    nextLink.setAttribute('href', buildHref(nextParams))
                }

                if (todayLink) {
                    const todayParams = new URLSearchParams(params)
                    todayParams.set('year', today.getFullYear())
                    todayParams.set('month', pad2(today.getMonth() + 1))
                    todayParams.set('week_start', formatDate(today))
                    todayLink.setAttribute('href', buildHref(todayParams))
                }
            }

            return
        }

        if (Number.isNaN(currentYear) || Number.isNaN(currentMonth)) {
            return
        }

        const baseDate = new Date(currentYear, currentMonth - 1, 1)
        const prevMonth = new Date(baseDate)
        prevMonth.setMonth(prevMonth.getMonth() - 1)
        const nextMonth = new Date(baseDate)
        nextMonth.setMonth(nextMonth.getMonth() + 1)
        const today = new Date()

        if (prevLink) {
            const prevParams = new URLSearchParams(params)
            prevParams.set('year', prevMonth.getFullYear())
            prevParams.set('month', pad2(prevMonth.getMonth() + 1))
            prevParams.delete('week_start')
            prevLink.setAttribute('href', buildHref(prevParams))
        }

        if (nextLink) {
            const nextParams = new URLSearchParams(params)
            nextParams.set('year', nextMonth.getFullYear())
            nextParams.set('month', pad2(nextMonth.getMonth() + 1))
            nextParams.delete('week_start')
            nextLink.setAttribute('href', buildHref(nextParams))
        }

        if (todayLink) {
            const todayParams = new URLSearchParams(params)
            todayParams.set('year', today.getFullYear())
            todayParams.set('month', pad2(today.getMonth() + 1))
            todayParams.delete('week_start')
            todayLink.setAttribute('href', buildHref(todayParams))
        }
    }

    /**
     * Show attendance modal with prepopulated data
     * 
     * @param {Event} event Click event
     */
    /**
     * Show attendance modal for marking or editing attendance
     * Loads modal content dynamically from server to get full form UI
     * 
     * @param {Event} event Click event
     */
    async showAttendanceModal(event) {
        const button = event.currentTarget
        const action = button.dataset.attendanceAction || 'add'
        const gatheringId = button.dataset.gatheringId
        const attendanceId = button.dataset.attendanceId || ''

        // Get the attendance modal
        const attendanceModal = document.getElementById('attendanceModal')
        if (!attendanceModal) {
            console.error('Attendance modal not found')
            return
        }

        const modalContent = document.getElementById('attendanceModalContent')
        if (!modalContent) {
            console.error('Attendance modal content container not found')
            return
        }

        try {
            // Show loading state
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h5 class="modal-title">Loading...</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `

            // Show modal
            const bsModal = new bootstrap.Modal(attendanceModal)
            bsModal.show()

            // Fetch the modal content from server
            let url
            if (action === 'edit' && attendanceId) {
                url = `/gatherings/attendance-modal/${gatheringId}?attendance_id=${attendanceId}`
            } else {
                url = `/gatherings/attendance-modal/${gatheringId}`
            }

            const response = await fetch(url)
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const html = await response.text()
            modalContent.innerHTML = html

            // Manually attach click handler to close button since Bootstrap's event delegation
            // doesn't work on dynamically inserted content. Remove previous listener first.
            const closeButton = modalContent.querySelector('.btn-close')
            if (this._attendanceCloseHandler && closeButton) {
                closeButton.removeEventListener('click', this._attendanceCloseHandler)
            }
            if (closeButton) {
                this._attendanceCloseHandler = () => {
                    const bsModal = bootstrap.Modal.getInstance(attendanceModal)
                    if (bsModal) {
                        bsModal.hide()
                    }
                }
                closeButton.addEventListener('click', this._attendanceCloseHandler)
            }

        } catch (error) {
            console.error('Error loading attendance modal:', error)
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        Failed to load attendance form. Please try again or refresh the page.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            `
        }
    }
    /**
     * Mark attendance for a gathering from quick view
     * 
     * @param {Event} event Click event
     */
    async markAttendance(event) {
        if (event) {
            event.preventDefault()
        }

        const button = event?.currentTarget
        const gatheringId = button?.dataset.gatheringId

        if (!gatheringId) {
            console.error('No gathering ID found')
            return
        }

        if (button && !button.dataset.attendanceAction) {
            button.dataset.attendanceAction = 'add'
        }

        return this.showAttendanceModal(event)
    }

    /**
     * Update attendance for a gathering from quick view
     * 
     * @param {Event} event Click event
     */
    async updateAttendance(event) {
        if (event) {
            event.preventDefault()
        }

        const button = event?.currentTarget

        if (button && !button.dataset.attendanceAction) {
            button.dataset.attendanceAction = 'edit'
        }

        return this.showAttendanceModal(event)
    }

    /**
     * Toggle attendance for a gathering (legacy method for list view)
     * 
     * @param {Event} event Click event
     */
    async toggleAttendance(event) {
        const button = event.currentTarget
        const gatheringId = button.dataset.gatheringId
        const attendanceId = button.dataset.attendanceId
        const isCurrentlyAttending = button.dataset.attending === 'true'

        if (!gatheringId) {
            console.error('No gathering ID found')
            return
        }

        // Disable button during request
        const originalContent = button.innerHTML
        button.disabled = true
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Processing...'

        try {
            let url, method, body

            if (isCurrentlyAttending) {
                // Remove attendance - use DELETE request
                if (!attendanceId) {
                    throw new Error('No attendance ID found for removal')
                }
                url = `/gathering-attendances/delete/${attendanceId}`
                method = 'DELETE'
                // No body needed for DELETE
            } else {
                // Add attendance - use POST request
                url = `/gathering-attendances/add`
                method = 'POST'
                body = new FormData()
                body.append('gathering_id', gatheringId)
                body.append('status', 'attending')
            }

            const fetchOptions = {
                method: method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.getCsrfToken()
                }
            }

            // Add body only for POST requests
            if (body) {
                fetchOptions.body = body
            }

            const response = await fetch(url, fetchOptions)

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const data = await response.json()

            // Update UI based on action
            if (data.success) {
                if (isCurrentlyAttending) {
                    // Removed attendance
                    button.dataset.attending = 'false'
                    button.removeAttribute('data-attendance-id')
                    button.classList.remove('btn-success')
                    button.classList.add('btn-outline-success')
                    button.innerHTML = '<i class="bi bi-calendar-check"></i> Attend'

                    // Show success message
                    this.showToast('Success!', 'Your attendance has been removed.', 'success')
                } else {
                    // Added attendance
                    button.dataset.attending = 'true'
                    if (data.attendance_id) {
                        button.dataset.attendanceId = data.attendance_id
                    }
                    button.classList.remove('btn-outline-success')
                    button.classList.add('btn-success')
                    button.innerHTML = '<i class="bi bi-check-circle"></i> Attending'

                    // Show success message
                    this.showToast('Success!', 'Your attendance has been recorded.', 'success')
                }

                // Reload page to update calendar display
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            } else {
                throw new Error(data.message || 'Failed to update attendance')
            }

        } catch (error) {
            console.error('Error toggling attendance:', error)
            this.showToast('Error', 'Failed to update attendance. Please try again.', 'danger')

            // Restore button
            button.disabled = false
            button.innerHTML = originalContent
        }
    }

    /**
     * Show location map for a gathering
     * 
     * @param {Event} event Click event
     */
    showLocation(event) {
        const gatheringId = event.currentTarget.dataset.gatheringId

        if (!gatheringId) {
            console.error('No gathering ID found')
            return
        }

        // Navigate to gathering view with location tab active
        window.location.href = `/gatherings/view/${gatheringId}#nav-location-tab`
    }

    /**
     * Get CSRF token from meta tag or form
     * 
     * @returns {string} CSRF token
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]')
        if (meta) {
            return meta.getAttribute('content')
        }

        const input = document.querySelector('input[name="_csrfToken"]')
        if (input) {
            return input.value
        }

        return ''
    }

    /**
     * Show toast notification
     * 
     * @param {string} title Toast title
     * @param {string} message Toast message
     * @param {string} type Bootstrap color type (success, danger, warning, info)
     */
    showToast(title, message, type = 'info') {
        // Create toast container if it doesn't exist
        let container = document.getElementById('toast-container')
        if (!container) {
            container = document.createElement('div')
            container.id = 'toast-container'
            container.className = 'toast-container position-fixed top-0 end-0 p-3'
            container.style.zIndex = '9999'
            document.body.appendChild(container)
        }

        // Create toast element
        const toastId = `toast-${Date.now()}`
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `

        container.insertAdjacentHTML('beforeend', toastHtml)

        // Show toast
        const toastElement = document.getElementById(toastId)
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 3000
        })
        toast.show()

        // Remove from DOM after hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove()
        })
    }

    /**
     * Disconnect event - cleanup
     */
    disconnect() {
        // Remove event listeners attached to dynamically loaded modal content
        try {
            if (this._popstateHandler) {
                window.removeEventListener('popstate', this._popstateHandler)
                this._popstateHandler = null
            }
            if (this._pushStateHandler) {
                window.removeEventListener('grid-view:navigated', this._pushStateHandler)
                this._pushStateHandler = null
            }

            if (this.modalElement) {
                const closeButton = this.modalElement.querySelector('.btn-close')
                if (closeButton && this._closeButtonHandler) {
                    closeButton.removeEventListener('click', this._closeButtonHandler)
                    this._closeButtonHandler = null
                }
            }

            if (this.turboFrame) {
                // If attendance modal content was rendered into a separate container, try to clean it
                const attendanceClose = document.querySelector('#attendanceModalContent .btn-close')
                if (attendanceClose && this._attendanceCloseHandler) {
                    attendanceClose.removeEventListener('click', this._attendanceCloseHandler)
                    this._attendanceCloseHandler = null
                }
            }

            if (this.modalInstance) {
                this.modalInstance.dispose()
            }
        } catch (e) {
            console.warn('Error during disconnect cleanup:', e)
        }
    }

}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gatherings-calendar"] = GatheringsCalendarController;

export default GatheringsCalendarController;
