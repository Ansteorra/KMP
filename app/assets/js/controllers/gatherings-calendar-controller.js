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
        view: String
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
                    if (closeButton) {
                        closeButton.addEventListener('click', () => {
                            if (this.modalInstance) {
                                this.modalInstance.hide()
                            }
                        })
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
            // doesn't work on dynamically inserted content
            const closeButton = modalContent.querySelector('.btn-close')
            if (closeButton) {
                closeButton.addEventListener('click', () => {
                    const bsModal = bootstrap.Modal.getInstance(attendanceModal)
                    if (bsModal) {
                        bsModal.hide()
                    }
                })
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
        if (this.modalInstance) {
            this.modalInstance.dispose()
        }
    }

}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["gatherings-calendar"] = GatheringsCalendarController;

export default GatheringsCalendarController;
