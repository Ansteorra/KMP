import { Controller } from "@hotwired/stimulus"

/** Render the Dataverse-filtered waiver month and navigate through the shared grid controller. */
class WaiverCalendarController extends Controller {
    static outlets = ['grid-view']
    static targets = ['frame', 'monthData', 'calendar', 'monthLabel', 'prevBtn', 'nextBtn', 'filterControls', 'status']
    static values = { emptyMessage: String, errorMessage: String }

    monthDataTargetConnected(target) {
        const data = JSON.parse(target.textContent)
        this.year = data.year
        this.month = data.month
        this.monthLabelTarget.textContent = data.monthName
        this.prevBtnTarget.disabled = data.year === 2000 && data.month === 1
        this.nextBtnTarget.disabled = data.year === 2100 && data.month === 12
        this.filterControlsTarget.disabled = false
        this.renderCalendar(data)
        this.statusTarget.textContent = data.events.length ? data.summary : this.emptyMessageValue
        this.statusTarget.classList.toggle('visually-hidden', Boolean(data.events.length))
    }

    prevMonth() {
        this.moveMonth(-1)
    }

    nextMonth() {
        this.moveMonth(1)
    }

    moveMonth(offset) {
        if (!this.hasGridViewOutlet || !this.month) return
        const date = new Date(this.year, this.month - 1 + offset, 1)
        const url = this.gridViewOutlet.buildUrl({ year: date.getFullYear(), month: date.getMonth() + 1 })
        this.gridViewOutlet.navigate(url)
    }

    loadFailed() {
        if (!this.month) this.frameTarget.replaceChildren()
        this.statusTarget.classList.remove('visually-hidden')
        this.statusTarget.textContent = this.errorMessageValue
    }

    renderCalendar(data) {
        const year = data.year
        const month = data.month
        const firstDay = new Date(year, month - 1, 1)
        const lastDay = new Date(year, month, 0)
        const daysInMonth = lastDay.getDate()
        const startDow = firstDay.getDay() // 0=Sun

        // Build event lookup by date (only show on start_date to avoid clutter)
        const eventsByDate = {}
        const firstDate = this.dateKey(firstDay)
        for (const evt of data.events) {
            const key = evt.start_date < firstDate && evt.end_date >= firstDate ? firstDate : evt.start_date
            if (!eventsByDate[key]) eventsByDate[key] = []
            eventsByDate[key].push(evt)
        }

        const today = new Date()
        const todayKey = this.dateKey(today)

        let html = '<div class="waiver-calendar-grid">'

        // Day headers
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
        for (const name of dayNames) {
            html += `<div class="waiver-calendar-header">${name}</div>`
        }

        // Leading empty cells from previous month
        const prevMonth = new Date(year, month - 1, 0)
        const prevDays = prevMonth.getDate()
        for (let i = startDow - 1; i >= 0; i--) {
            const day = prevDays - i
            html += `<div class="waiver-calendar-day other-month"><span class="waiver-calendar-day-number">${day}</span></div>`
        }

        // Current month days
        for (let d = 1; d <= daysInMonth; d++) {
            const dateObj = new Date(year, month - 1, d)
            const key = this.dateKey(dateObj)
            const isToday = key === todayKey
            const classes = ['waiver-calendar-day']
            if (isToday) classes.push('today')

            html += `<div class="${classes.join(' ')}"><span class="waiver-calendar-day-number">${d}</span>`

            const dayEvents = eventsByDate[key] || []
            for (const evt of dayEvents) {
                html += this.renderEvent(evt)
            }

            html += '</div>'
        }

        // Trailing empty cells
        const totalCells = startDow + daysInMonth
        const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7)
        for (let i = 1; i <= remaining; i++) {
            html += `<div class="waiver-calendar-day other-month"><span class="waiver-calendar-day-number">${i}</span></div>`
        }

        html += '</div>'
        this.calendarTarget.innerHTML = html
    }

    renderEvent(evt) {
        const statusColors = {
            missing: '#dc3545',
            partial: '#ffc107',
            complete: '#198754',
            closed: '#0d6efd'
        }
        const color = statusColors[evt.status] || '#6c757d'
        const multiDayClass = evt.multi_day ? ' multi-day' : ''
        const title = `${evt.name} (${evt.branch})`

        let badges = ''

        if (evt.status === 'closed') {
            badges += '<span class="badge bg-primary"><kmp-icon name="lock"  aria-hidden="true"></kmp-icon> Closed</span>'
        } else {
            if (evt.uploaded > 0) {
                badges += `<span class="badge bg-success">${evt.uploaded} Uploaded</span>`
            }
            if (evt.exempted > 0) {
                badges += `<span class="badge bg-info text-dark">${evt.exempted} Exempted</span>`
            }
            if (evt.pending > 0) {
                badges += `<span class="badge bg-warning text-dark">${evt.pending} Pending</span>`
            }
            if (evt.ready_to_close) {
                badges += '<span class="badge bg-info text-dark"><kmp-icon name="checkbox"  aria-hidden="true"></kmp-icon> Ready to Close</span>'
            }
            if (evt.uploaded === 0 && evt.exempted === 0 && evt.pending === 0) {
                badges += '<span class="badge bg-danger">No Waivers</span>'
            }
        }

        return `<a href="${this.escapeHtml(evt.url)}" class="waiver-calendar-item${multiDayClass}" style="background-color: ${color}22; border-left-color: ${color};" title="${this.escapeHtml(title)}">` +
            `<div class="fw-bold">${this.escapeHtml(evt.name)}</div>` +
            `<div class="waiver-calendar-meta small text-truncate">${this.escapeHtml(evt.branch)}</div>` +
            `<div class="waiver-calendar-badges">${badges}</div>` +
            `</a>`
    }

    dateKey(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }

    escapeHtml(str) {
        const div = document.createElement('div')
        div.textContent = str
        return div.innerHTML.replaceAll('"', '&quot;')
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["waiver-calendar"] = WaiverCalendarController;
