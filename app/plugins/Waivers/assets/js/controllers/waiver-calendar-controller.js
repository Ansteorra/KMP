import { Controller } from "@hotwired/stimulus"

/**
 * Waiver Calendar Controller
 *
 * Renders a monthly calendar showing gatherings color-coded by waiver status.
 * Red = no waivers, Yellow = partial, Green = complete, Blue = closed.
 *
 * Values:
 * - url: API endpoint for calendar data
 *
 * Targets:
 * - calendar: Container for the calendar grid
 * - monthLabel: Displays current month/year
 * - prevBtn: Previous month button
 * - nextBtn: Next month button
 */
class WaiverCalendarController extends Controller {
    static targets = ["calendar", "monthLabel", "prevBtn", "nextBtn"]
    static values = { url: String }

    connect() {
        const now = new Date()
        this.year = now.getFullYear()
        this.month = now.getMonth() + 1
        this.loadMonth()
    }

    prevMonth() {
        this.month--
        if (this.month < 1) {
            this.month = 12
            this.year--
        }
        this.loadMonth()
    }

    nextMonth() {
        this.month++
        if (this.month > 12) {
            this.month = 1
            this.year++
        }
        this.loadMonth()
    }

    async loadMonth() {
        const sep = this.urlValue.includes('?') ? '&' : '?'
        const url = `${this.urlValue}${sep}year=${this.year}&month=${this.month}`

        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            })
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`)
            }
            const data = await response.json()
            this.monthLabelTarget.textContent = data.monthName
            this.renderCalendar(data)
        } catch (error) {
            console.error('Failed to load calendar data:', error)
            this.calendarTarget.innerHTML =
                '<div class="alert alert-danger">Failed to load calendar data.</div>'
        }
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
        for (const evt of data.events) {
            const key = evt.start_date
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
            badges += '<span class="badge bg-primary"><i class="bi bi-lock-fill"></i> Closed</span>'
        } else {
            if (evt.uploaded > 0) {
                badges += `<span class="badge bg-success">${evt.uploaded} Uploaded</span>`
            }
            if (evt.exempted > 0) {
                badges += `<span class="badge bg-info">${evt.exempted} Exempted</span>`
            }
            if (evt.pending > 0) {
                badges += `<span class="badge bg-warning text-dark">${evt.pending} Pending</span>`
            }
            if (evt.ready_to_close) {
                badges += '<span class="badge bg-info"><i class="bi bi-check2-square"></i> Ready to Close</span>'
            }
            if (evt.uploaded === 0 && evt.exempted === 0 && evt.pending === 0) {
                badges += '<span class="badge bg-danger">No Waivers</span>'
            }
        }

        return `<a href="${this.escapeHtml(evt.url)}" class="waiver-calendar-item${multiDayClass}" style="background-color: ${color}22; border-left-color: ${color};" title="${this.escapeHtml(title)}">` +
            `<div class="fw-bold">${this.escapeHtml(evt.name)}</div>` +
            `<div class="text-muted small text-truncate">${this.escapeHtml(evt.branch)}</div>` +
            `<div class="waiver-calendar-badges">${badges}</div>` +
            `</a>`
    }

    dateKey(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    }

    escapeHtml(str) {
        const div = document.createElement('div')
        div.textContent = str
        return div.innerHTML
    }
}

if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["waiver-calendar"] = WaiverCalendarController;
