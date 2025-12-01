import { Controller } from "@hotwired/stimulus"

/**
 * Grid View Controller - Simplified Architecture
 * 
 * This controller follows a clean MVC pattern where:
 * - Server generates complete state
 * - Templates display state
 * - Controller captures user actions and navigates
 * 
 * NO state management in JavaScript - server is source of truth.
 */
class GridViewController extends Controller {
    static targets = ["gridState", "searchInput"]

    /**
     * Initialize controller
     */
    connect() {
        console.log("GridViewController (simplified) connected")

        // State will be loaded when frame loads
        this.state = null

        // Bind handler once for use in addEventListener/removeEventListener
        this.boundHandleFrameLoad = this.handleFrameLoad.bind(this)

        // Listen for Turbo Frame updates
        document.addEventListener('turbo:frame-load', this.boundHandleFrameLoad)
    }

    /**
     * Cleanup when controller disconnects
     */
    disconnect() {
        document.removeEventListener('turbo:frame-load', this.boundHandleFrameLoad)
    }

    /**
     * Handle Turbo Frame load - update state from table frame
     */
    handleFrameLoad(event) {
        // Only handle frames that belong to THIS grid controller
        // Check if the event target is inside this controller's element
        if (!this.element.contains(event.target)) {
            return
        }

        // Listen for table frame loads (not the outer grid frame)
        if (!event.target.id || !event.target.id.endsWith('-table')) {
            return
        }

        // Get state from the table frame's script tag
        const tableFrame = event.target
        const stateScript = tableFrame.querySelector('script[type="application/json"]')

        if (!stateScript) {
            console.warn('No state script found in table frame')
            return
        }

        try {
            const stateJson = stateScript.textContent
            this.state = JSON.parse(stateJson)
            console.log('Grid state updated from table frame:', this.state)

            // Update toolbar UI based on new state
            this.updateToolbar()
        } catch (e) {
            console.error('Failed to parse grid state from table frame:', e)
        }
    }

    /**
     * Update toolbar UI based on current state
     */
    updateToolbar() {
        // Update all UI elements from state
        this.updateViewTabs()
        this.updateFilterPills()
        this.updateSearchDisplay()
        this.updateFilterCount()
        this.updateFilterDropdownCheckboxes()
        this.updateFilterNavigation()
        this.updateFilterPanels()
        this.updateClearFiltersFooter()
        this.updateColumnPicker()
    }

    /**
     * Update filter pill display
     */
    updateFilterPills() {
        const container = this.element.querySelector('[data-filter-pills-container]')
        if (!container) return

        // Clear existing pills
        container.innerHTML = ''

        // If no filters, hide container
        if (!this.state.filters.active || Object.keys(this.state.filters.active).length === 0) {
            //container.classList.add('d-none')
            return
        }

        container.classList.remove('d-none')

        // Get OR grouping information from state
        const orGroups = this.state.filters.grouping?.orGroups || []

        // Get locked filters from config
        const lockedFilters = this.state.config?.lockedFilters || []

        // Build map of field -> group index for quick lookup
        const fieldToGroup = new Map()
        orGroups.forEach((group, groupIndex) => {
            group.forEach(field => {
                fieldToGroup.set(field, groupIndex)
            })
        })

        // Organize filters by OR groups and multi-value filters
        const groupedFilters = new Map() // groupIndex -> array of {column, value} objects
        const ungroupedFilters = [] // Filters not in any OR group
        let nextAutoGroupIndex = orGroups.length // Start auto-group indices after explicit OR groups

        Object.entries(this.state.filters.active).forEach(([column, values]) => {
            const valueArray = Array.isArray(values) ? values : [values]

            // Check direct match or date range base field match (e.g., expires_on_end matches expires_on)
            let groupIndex = null
            if (fieldToGroup.has(column)) {
                groupIndex = fieldToGroup.get(column)
            } else {
                // Try matching date range suffixes (_start, _end) to base field
                const baseField = column.replace(/_(start|end)$/, '')
                if (baseField !== column && fieldToGroup.has(baseField)) {
                    groupIndex = fieldToGroup.get(baseField)
                }
            }

            if (groupIndex !== null) {
                // This filter is part of an explicit expression-based OR group
                if (!groupedFilters.has(groupIndex)) {
                    groupedFilters.set(groupIndex, [])
                }
                valueArray.forEach(value => {
                    groupedFilters.get(groupIndex).push({ column, value })
                })
            } else if (valueArray.length > 1) {
                // Multiple values for same field (IN clause) - create implicit OR group
                const autoGroupIndex = `auto-${nextAutoGroupIndex++}`
                groupedFilters.set(autoGroupIndex, [])
                valueArray.forEach(value => {
                    groupedFilters.get(autoGroupIndex).push({ column, value })
                })
            } else {
                // Single value, not part of OR group
                ungroupedFilters.push({ column, value: valueArray[0] })
            }
        })

        // Render ungrouped filters first
        ungroupedFilters.forEach(({ column, value }) => {
            const isLocked = this.isFilterLocked(column, lockedFilters)
            const pill = this.createFilterPill(column, value, isLocked)
            container.appendChild(pill)
        })

        // Render OR groups with visual indicators
        groupedFilters.forEach((filters, groupIndex) => {
            if (filters.length === 0) return

            // Create a wrapper for the OR group
            const groupWrapper = document.createElement('div')
            groupWrapper.className = 'd-inline-flex align-items-center gap-1'
            groupWrapper.style.cssText = 'padding: 2px 6px; border-radius: 6px; background-color: rgba(13, 110, 253, 0.08); border: 1px dashed rgba(13, 110, 253, 0.3);'
            groupWrapper.setAttribute('data-or-group', groupIndex)

            filters.forEach((filterData, index) => {
                const isLocked = this.isFilterLocked(filterData.column, lockedFilters)
                const pill = this.createFilterPill(filterData.column, filterData.value, isLocked)
                groupWrapper.appendChild(pill)

                // Add OR indicator between pills (but not after the last one)
                if (index < filters.length - 1) {
                    const orIndicator = document.createElement('span')
                    orIndicator.className = 'text-primary fw-bold px-1'
                    orIndicator.style.cssText = 'font-size: 0.65rem; letter-spacing: 0.5px;'
                    orIndicator.textContent = 'OR'
                    orIndicator.setAttribute('title', 'These filters are combined with OR logic - any one can match')
                    groupWrapper.appendChild(orIndicator)
                }
            })

            container.appendChild(groupWrapper)
        })
    }

    /**
     * Create a filter pill element
     * 
     * @param {string} column - The filter column key
     * @param {string} value - The filter value
     * @param {boolean} isLocked - Whether this filter is locked (cannot be removed)
     */
    createFilterPill(column, value, isLocked = false) {
        // Match the exact styling from grid_view_toolbar.php
        const badge = document.createElement('span')
        badge.className = 'badge d-inline-flex align-items-center gap-1 pe-1'
        badge.style.cssText = 'background-color: #f6f6f7; color: #202223; border: 1px solid #c9cccf; font-weight: 500; font-size: 0.75rem; padding: 0.25rem 0.4rem 0.25rem 0.5rem; border-radius: 0.4rem;'
        badge.setAttribute('data-filter-badge', '')

        if (isLocked) {
            badge.setAttribute('data-filter-locked', 'true')
        }

        // Get the label for this value from filters metadata
        const valueLabel = this.getFilterValueLabel(column, value)
        const columnLabel = this.getFilterColumnLabel(column)

        const textSpan = document.createElement('span')
        textSpan.innerHTML = `${columnLabel}: <strong>${this.escapeHtml(valueLabel)}</strong>`
        badge.appendChild(textSpan)

        // Only add remove button if filter is not locked
        if (!isLocked) {
            const removeBtn = document.createElement('button')
            removeBtn.type = 'button'
            removeBtn.className = 'btn btn-link p-0 m-0 text-decoration-none d-flex align-items-center justify-content-center'
            removeBtn.style.cssText = 'width: 18px; height: 18px; border-radius: 50%; background: rgba(0,0,0,0.1); color: #202223; font-size: 0.7rem; line-height: 1;'
            removeBtn.setAttribute('aria-label', 'Remove filter')
            removeBtn.setAttribute('data-action', 'click->grid-view#removeFilter')
            removeBtn.setAttribute('data-filter-column', column)
            removeBtn.setAttribute('data-filter-value', value)

            const icon = document.createElement('i')
            icon.className = 'bi bi-x'
            icon.style.cssText = 'font-size: 0.9rem; font-weight: bold;'
            removeBtn.appendChild(icon)

            badge.appendChild(removeBtn)
        } else {
            // For locked filters, add a lock icon instead
            const lockIcon = document.createElement('i')
            lockIcon.className = 'bi bi-lock-fill ms-1'
            lockIcon.style.cssText = 'font-size: 0.65rem; opacity: 0.5;'
            lockIcon.setAttribute('title', 'This filter cannot be removed')
            badge.appendChild(lockIcon)
        }

        return badge
    }

    /**
     * Get filter column label from metadata
     */
    getFilterColumnLabel(column) {
        if (this.state.filters.available && this.state.filters.available[column]) {
            return this.state.filters.available[column].label
        }
        return this.formatColumnName(column)
    }

    /**
     * Get filter value label from metadata
     */
    getFilterValueLabel(column, value) {
        if (this.state.filters.available && this.state.filters.available[column]) {
            const filterMeta = this.state.filters.available[column]

            // For date range filters, just return the value (it's already a formatted date)
            if (filterMeta.type === 'date-range-start' || filterMeta.type === 'date-range-end') {
                return value
            }

            // For dropdown filters, look up the option label
            const options = filterMeta.options
            if (options) {
                const option = options.find(opt => opt.value === value)
                if (option) {
                    return option.label
                }
            }
        }
        return value
    }

    /**
     * Escape HTML for safe insertion
     */
    escapeHtml(text) {
        const div = document.createElement('div')
        div.textContent = text
        return div.innerHTML
    }

    /**
     * Format column name for display
     */
    formatColumnName(column) {
        // Simple title case formatting
        return column.split('_').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ')
    }

    /**
     * Update search display
     */
    updateSearchDisplay() {
        const container = this.element.querySelector('[data-filter-pills-container]')
        if (!container) return

        // Update search input value
        if (this.hasSearchInputTarget) {
            this.searchInputTarget.value = this.state.search || ''
        }

        // Remove existing search badge if present
        const existingSearchBadge = container.querySelector('[data-search-badge]')
        if (existingSearchBadge) {
            existingSearchBadge.remove()
        }

        // If search is active, create and insert search badge as first element
        if (this.state.search) {
            const searchBadge = this.createSearchBadge(this.state.search)
            container.insertBefore(searchBadge, container.firstChild)
        }
    }

    /**
     * Create a search badge element
     */
    createSearchBadge(searchTerm) {
        // Match the exact styling from grid_view_toolbar.php
        const badge = document.createElement('span')
        badge.className = 'badge d-inline-flex align-items-center gap-1 pe-1'
        badge.style.cssText = 'background-color: #f6f6f7; color: #202223; border: 1px solid #c9cccf; font-weight: 500; font-size: 0.75rem; padding: 0.25rem 0.4rem 0.25rem 0.5rem; border-radius: 0.4rem;'
        badge.setAttribute('data-search-badge', '')

        const textSpan = document.createElement('span')
        textSpan.innerHTML = `Search: <strong data-search-text>${this.escapeHtml(searchTerm)}</strong>`
        badge.appendChild(textSpan)

        const removeBtn = document.createElement('button')
        removeBtn.type = 'button'
        removeBtn.className = 'btn btn-link p-0 m-0 text-decoration-none d-flex align-items-center justify-content-center'
        removeBtn.style.cssText = 'width: 18px; height: 18px; border-radius: 50%; background: rgba(0,0,0,0.1); color: #202223; font-size: 0.7rem; line-height: 1;'
        removeBtn.setAttribute('aria-label', 'Remove search')
        removeBtn.setAttribute('data-action', 'click->grid-view#clearSearch')

        const icon = document.createElement('i')
        icon.className = 'bi bi-x'
        icon.style.cssText = 'font-size: 0.9rem; font-weight: bold;'
        removeBtn.appendChild(icon)

        badge.appendChild(removeBtn)

        return badge
    }

    /**
     * Update filter count badge
     */
    updateFilterCount() {
        // Find the filter button (it has a Filter icon and text)
        const filterButton = Array.from(this.element.querySelectorAll('button'))
            .find(btn => btn.textContent.includes('Filter') && btn.querySelector('.bi-funnel'))

        if (!filterButton) return

        // Calculate active filter count (matching PHP logic)
        let activeCount = 0

        // Count search as 1 filter if present
        if (this.state.search) {
            activeCount++
        }

        // Count each active filter value
        if (this.state.filters && this.state.filters.active) {
            Object.values(this.state.filters.active).forEach(values => {
                activeCount += Array.isArray(values) ? values.length : 1
            })
        }

        // Find or create badge
        let badge = filterButton.querySelector('.badge')

        if (activeCount > 0) {
            if (!badge) {
                // Create badge if it doesn't exist
                badge = document.createElement('span')
                badge.className = 'badge bg-primary rounded-circle'
                filterButton.appendChild(badge)
            }
            badge.textContent = activeCount
        } else {
            // Remove badge if count is 0
            if (badge) {
                badge.remove()
            }
        }
    }

    /**
     * Update filter dropdown checkboxes to match current state
     * (This is now mostly redundant since we rebuild the panels,
     * but keeping it for any dynamic checkbox updates)
     */
    updateFilterDropdownCheckboxes() {
        if (!this.state.filters || !this.state.filters.active) return

        // Get all filter checkboxes and sync their state
        const checkboxes = this.element.querySelectorAll('[data-filter-column][type="checkbox"]')

        checkboxes.forEach(checkbox => {
            const column = checkbox.dataset.filterColumn
            const value = checkbox.value

            // Check if this filter value is active in the current state
            const activeValues = this.state.filters.active[column]
            let isActive = false

            if (activeValues !== undefined) {
                if (Array.isArray(activeValues)) {
                    isActive = activeValues.includes(value)
                } else {
                    isActive = activeValues === value
                }
            }

            checkbox.checked = isActive
        })
    }

    /**
     * Update view tabs from state
     */
    updateViewTabs() {
        const container = this.element.querySelector('[data-view-tabs-container]')
        if (!container) return

        // Check if we should show "All" tab (marker present means DON'T show it)
        const showAllTab = !this.element.querySelector('[data-no-all-tab]')

        // Find the "Create View" button to preserve it
        const createViewBtn = container.querySelector('[data-action*="saveView"]')?.closest('li')

        // Find the marker element to preserve it
        const markerElement = container.querySelector('[data-no-all-tab]')?.closest('li')

        // Clear existing tabs (except create button and marker)
        container.querySelectorAll('li').forEach(li => {
            if (li !== createViewBtn && li !== markerElement) {
                li.remove()
            }
        })

        // Add "All" tab if enabled
        if (showAllTab) {
            const allTab = this.createViewTab('All', null, !this.state.view.currentId, false, false)
            container.insertBefore(allTab, createViewBtn)
        }

        // Add user views
        if (this.state.view.available && Array.isArray(this.state.view.available)) {
            this.state.view.available.forEach(view => {
                const isActive = (this.state.view.currentId == view.id)
                const isPreferred = view.isPreferred || view.isUserDefault || false
                const canManage = view.canManage !== false
                const viewTab = this.createViewTab(view.name, view.id, isActive, isPreferred, canManage)
                container.insertBefore(viewTab, createViewBtn)
            })
        }
    }

    /**
     * Create a view tab element
     */
    createViewTab(name, viewId, isActive, isPreferred, canManage) {
        const li = document.createElement('li')
        li.className = 'nav-item'
        li.setAttribute('role', 'presentation')

        if (isActive && canManage) {
            // Active tab with dropdown
            const btnGroup = document.createElement('div')
            btnGroup.className = 'btn-group'
            btnGroup.setAttribute('role', 'group')
            btnGroup.style.marginBottom = '-1px'

            const mainBtn = document.createElement('button')
            mainBtn.type = 'button'
            mainBtn.className = 'nav-link active split-tab-left'
            mainBtn.setAttribute('role', 'tab')
            mainBtn.setAttribute('aria-selected', 'true')

            if (viewId) {
                mainBtn.setAttribute('data-action', 'click->grid-view#switchView')
                mainBtn.setAttribute('data-view-id', viewId)
            } else {
                mainBtn.setAttribute('data-action', 'click->grid-view#showAll')
            }

            mainBtn.textContent = name
            if (isPreferred) {
                const star = document.createElement('i')
                star.className = 'bi bi-star-fill text-warning'
                star.style.fontSize = '0.75rem'
                mainBtn.appendChild(document.createTextNode(' '))
                mainBtn.appendChild(star)
            }

            const dropdownBtn = document.createElement('button')
            dropdownBtn.type = 'button'
            dropdownBtn.className = 'nav-link active dropdown-toggle dropdown-toggle-split split-tab-right'
            dropdownBtn.setAttribute('data-bs-toggle', 'dropdown')
            dropdownBtn.setAttribute('aria-expanded', 'false')
            dropdownBtn.style.cssText = 'padding-left: 5px; padding-right: 5px;'
            dropdownBtn.innerHTML = '<span class="visually-hidden">Toggle Dropdown</span>'

            const dropdownMenu = document.createElement('ul')
            dropdownMenu.className = 'dropdown-menu'

            if (viewId && canManage) {
                // Update View
                const updateItem = document.createElement('li')
                updateItem.innerHTML = `
                    <button type="button" class="dropdown-item" data-action="click->grid-view#updateView">
                        <i class="bi bi-arrow-repeat me-2"></i> Update View
                    </button>
                `
                dropdownMenu.appendChild(updateItem)
            }

            // Set/Clear Default
            const defaultItem = document.createElement('li')
            if (isPreferred) {
                defaultItem.innerHTML = `
                    <button type="button" class="dropdown-item" data-action="click->grid-view#clearDefault">
                        <i class="bi bi-star-fill me-2"></i> Remove as Default
                    </button>
                `
            } else {
                defaultItem.innerHTML = `
                    <button type="button" class="dropdown-item" data-action="click->grid-view#setDefault">
                        <i class="bi bi-star me-2"></i> Set as Default
                    </button>
                `
            }
            dropdownMenu.appendChild(defaultItem)

            if (viewId && canManage) {
                // Divider
                const divider = document.createElement('li')
                divider.innerHTML = '<hr class="dropdown-divider">'
                dropdownMenu.appendChild(divider)

                // Delete View
                const deleteItem = document.createElement('li')
                deleteItem.innerHTML = `
                    <button type="button" class="dropdown-item text-danger" data-action="click->grid-view#deleteView">
                        <i class="bi bi-trash me-2"></i> Delete View
                    </button>
                `
                dropdownMenu.appendChild(deleteItem)
            }

            btnGroup.appendChild(mainBtn)
            btnGroup.appendChild(dropdownBtn)
            btnGroup.appendChild(dropdownMenu)
            li.appendChild(btnGroup)
        } else if (isActive && !canManage) {
            // Active system view with limited dropdown
            const btnGroup = document.createElement('div')
            btnGroup.className = 'btn-group'
            btnGroup.setAttribute('role', 'group')
            btnGroup.style.marginBottom = '-1px'

            const mainBtn = document.createElement('button')
            mainBtn.type = 'button'
            mainBtn.className = 'nav-link active split-tab-left'
            mainBtn.setAttribute('role', 'tab')
            mainBtn.setAttribute('aria-selected', 'true')
            mainBtn.textContent = name

            if (isPreferred) {
                const star = document.createElement('i')
                star.className = 'bi bi-star-fill text-warning'
                star.style.fontSize = '0.75rem'
                mainBtn.appendChild(document.createTextNode(' '))
                mainBtn.appendChild(star)
            }

            const dropdownBtn = document.createElement('button')
            dropdownBtn.type = 'button'
            dropdownBtn.className = 'nav-link active dropdown-toggle dropdown-toggle-split split-tab-right'
            dropdownBtn.setAttribute('data-bs-toggle', 'dropdown')
            dropdownBtn.setAttribute('aria-expanded', 'false')
            dropdownBtn.style.cssText = 'padding-left: 5px; padding-right: 5px;'
            dropdownBtn.innerHTML = '<span class="visually-hidden">Toggle Dropdown</span>'

            const dropdownMenu = document.createElement('ul')
            dropdownMenu.className = 'dropdown-menu'

            const defaultItem = document.createElement('li')
            if (isPreferred) {
                defaultItem.innerHTML = `
                    <button type="button" class="dropdown-item" data-action="click->grid-view#clearDefault">
                        <i class="bi bi-star-fill me-2"></i> Remove as Default
                    </button>
                `
            } else {
                defaultItem.innerHTML = `
                    <button type="button" class="dropdown-item" data-action="click->grid-view#setDefault">
                        <i class="bi bi-star me-2"></i> Set as Default
                    </button>
                `
            }
            dropdownMenu.appendChild(defaultItem)

            btnGroup.appendChild(mainBtn)
            btnGroup.appendChild(dropdownBtn)
            btnGroup.appendChild(dropdownMenu)
            li.appendChild(btnGroup)
        } else {
            // Inactive tab
            const btn = document.createElement('button')
            btn.type = 'button'
            btn.className = 'nav-link'
            btn.setAttribute('role', 'tab')
            btn.setAttribute('aria-selected', 'false')

            if (viewId) {
                btn.setAttribute('data-action', 'click->grid-view#switchView')
                btn.setAttribute('data-view-id', viewId)
            } else {
                btn.setAttribute('data-action', 'click->grid-view#showAll')
            }

            btn.textContent = name
            if (isPreferred) {
                const star = document.createElement('i')
                star.className = 'bi bi-star-fill text-warning'
                star.style.fontSize = '0.75rem'
                btn.appendChild(document.createTextNode(' '))
                btn.appendChild(star)
            }

            li.appendChild(btn)
        }

        return li
    }

    /**
     * Update filter navigation (left side filter tabs)
     */
    updateFilterNavigation() {
        const container = this.element.querySelector('[data-filter-nav-container]')
        if (!container) return

        container.innerHTML = ''

        if (!this.state.filters.available) return

        // Group date range filters by base field
        const filterGroups = new Map()
        const standaloneFilters = []

        Object.entries(this.state.filters.available).forEach(([key, meta]) => {
            if (meta.type === 'date-range-start' || meta.type === 'date-range-end') {
                const baseField = meta.baseField || key.replace(/_start$|_end$/, '')
                if (!filterGroups.has(baseField)) {
                    filterGroups.set(baseField, {
                        baseField,
                        label: meta.label.replace(' (after)', '').replace(' (before)', ''),
                        filters: []
                    })
                }
                filterGroups.get(baseField).filters.push({ key, meta })
            } else {
                standaloneFilters.push({ key, meta })
            }
        })

        // Build array of all filters (standalone + grouped date ranges)
        const allFilterItems = [
            ...standaloneFilters.map(({ key, meta }) => ({ key, label: meta.label, type: 'dropdown', meta })),
            ...Array.from(filterGroups.values()).map(group => ({
                key: group.baseField,
                label: group.label,
                type: 'date-range',
                group
            }))
        ]

        if (allFilterItems.length === 0) return

        const firstFilterKey = allFilterItems[0].key

        allFilterItems.forEach((item) => {
            let activeCount = 0

            if (item.type === 'date-range') {
                // Count active date range filters
                item.group.filters.forEach(({ key }) => {
                    if (this.state.filters.active[key]) activeCount++
                })
            } else {
                // Count active dropdown filters
                const activeValues = this.state.filters.active?.[item.key] || []
                const activeArray = Array.isArray(activeValues) ? activeValues : [activeValues]
                activeCount = activeArray.filter(v => v !== null && v !== undefined && v !== '').length
            }

            const button = document.createElement('button')
            button.type = 'button'
            button.className = `list-group-item list-group-item-action d-flex justify-content-between align-items-center${item.key === firstFilterKey ? ' active' : ''}`
            button.setAttribute('data-filter-key', item.key)
            button.setAttribute('data-filter-type', item.type)
            button.setAttribute('data-filter-nav-item', '')
            button.setAttribute('data-action', 'click->grid-view#selectFilter')

            const label = document.createElement('span')
            label.textContent = item.label
            button.appendChild(label)

            if (activeCount > 0) {
                const badge = document.createElement('span')
                badge.className = 'badge bg-primary rounded-pill'
                badge.textContent = activeCount
                button.appendChild(badge)
            }

            container.appendChild(button)
        })
    }

    /**
     * Update filter panels (right side filter options)
     */
    updateFilterPanels() {
        const container = this.element.querySelector('[data-filter-panels-container]')
        if (!container) return

        container.innerHTML = ''

        if (!this.state.filters.available) return

        // Get locked filters from config
        const lockedFilters = this.state.config?.lockedFilters || []

        // Group date range filters by base field (same logic as navigation)
        const filterGroups = new Map()
        const standaloneFilters = []

        Object.entries(this.state.filters.available).forEach(([key, meta]) => {
            if (meta.type === 'date-range-start' || meta.type === 'date-range-end') {
                const baseField = meta.baseField || key.replace(/_start$|_end$/, '')
                if (!filterGroups.has(baseField)) {
                    filterGroups.set(baseField, {
                        baseField,
                        label: meta.label.replace(' (after)', '').replace(' (before)', ''),
                        filters: []
                    })
                }
                filterGroups.get(baseField).filters.push({ key, meta })
            } else {
                standaloneFilters.push({ key, meta })
            }
        })

        // Build array of all filters (standalone + grouped date ranges)
        const allFilterItems = [
            ...standaloneFilters.map(({ key, meta }) => ({ key, label: meta.label, type: 'dropdown', meta })),
            ...Array.from(filterGroups.values()).map(group => ({
                key: group.baseField,
                label: group.label,
                type: 'date-range',
                group
            }))
        ]

        if (allFilterItems.length === 0) return

        const firstFilterKey = allFilterItems[0].key

        allFilterItems.forEach((item) => {
            const panel = document.createElement('div')
            panel.className = item.key === firstFilterKey ? '' : 'd-none'
            panel.setAttribute('data-filter-key', item.key)
            panel.setAttribute('data-filter-panel', '')

            const innerDiv = document.createElement('div')
            innerDiv.className = 'px-3 py-3 border-bottom'

            if (item.type === 'date-range') {
                // Render date range panel with From/To inputs
                const startFilter = item.group.filters.find(f => f.meta.type === 'date-range-start')
                const endFilter = item.group.filters.find(f => f.meta.type === 'date-range-end')
                const startValue = startFilter ? (this.state.filters.active[startFilter.key] || '') : ''
                const endValue = endFilter ? (this.state.filters.active[endFilter.key] || '') : ''
                const activeCount = (startValue ? 1 : 0) + (endValue ? 1 : 0)

                // Check if this date range filter is locked
                const isLocked = this.isFilterLocked(item.key, lockedFilters)

                const headerDiv = document.createElement('div')
                headerDiv.className = 'd-flex justify-content-between align-items-center mb-1'

                const title = document.createElement('strong')
                title.textContent = item.label
                if (isLocked) {
                    const lockIcon = document.createElement('i')
                    lockIcon.className = 'bi bi-lock-fill ms-2'
                    lockIcon.style.cssText = 'font-size: 0.75rem; opacity: 0.5;'
                    lockIcon.setAttribute('title', 'This filter is locked and cannot be changed')
                    title.appendChild(lockIcon)
                }
                headerDiv.appendChild(title)

                if (activeCount > 0) {
                    const countText = document.createElement('small')
                    countText.className = 'text-muted'
                    countText.textContent = `${activeCount} selected`
                    headerDiv.appendChild(countText)
                }

                innerDiv.appendChild(headerDiv)

                const helpText = document.createElement('div')
                helpText.className = 'text-muted small mb-3'
                helpText.textContent = isLocked ? 'This filter is locked' : 'Select date range'
                innerDiv.appendChild(helpText)

                // Create row for From/To inputs
                const row = document.createElement('div')
                row.className = 'row g-2'

                // From date
                if (startFilter) {
                    const fromCol = document.createElement('div')
                    fromCol.className = 'col-12'

                    const fromLabel = document.createElement('label')
                    fromLabel.className = 'form-label small text-muted'
                    fromLabel.textContent = 'From'
                    fromCol.appendChild(fromLabel)

                    const fromInput = document.createElement('input')
                    fromInput.type = 'date'
                    fromInput.className = 'form-control'
                    fromInput.value = startValue
                    fromInput.setAttribute('data-filter-column', startFilter.key)
                    if (isLocked) {
                        fromInput.disabled = true
                        fromInput.setAttribute('title', 'This filter is locked and cannot be changed')
                    } else {
                        fromInput.setAttribute('data-action', 'change->grid-view#updateDateRangeFilter')
                    }
                    fromCol.appendChild(fromInput)

                    row.appendChild(fromCol)
                }

                // To date
                if (endFilter) {
                    const toCol = document.createElement('div')
                    toCol.className = 'col-12'

                    const toLabel = document.createElement('label')
                    toLabel.className = 'form-label small text-muted'
                    toLabel.textContent = 'To'
                    toCol.appendChild(toLabel)

                    const toInput = document.createElement('input')
                    toInput.type = 'date'
                    toInput.className = 'form-control'
                    toInput.value = endValue
                    toInput.setAttribute('data-filter-column', endFilter.key)
                    if (isLocked) {
                        toInput.disabled = true
                        toInput.setAttribute('title', 'This filter is locked and cannot be changed')
                    } else {
                        toInput.setAttribute('data-action', 'change->grid-view#updateDateRangeFilter')
                    }
                    toCol.appendChild(toInput)

                    row.appendChild(toCol)
                }

                innerDiv.appendChild(row)

            } else {
                // Render dropdown panel with checkboxes
                const activeValues = this.state.filters.active?.[item.key] || []
                const activeArray = Array.isArray(activeValues) ? activeValues : [activeValues]
                const activeFiltered = activeArray.filter(v => v !== null && v !== undefined && v !== '')
                const activeCount = activeFiltered.length

                // Check if this filter is locked
                const isLocked = this.isFilterLocked(item.key, lockedFilters)

                const headerDiv = document.createElement('div')
                headerDiv.className = 'd-flex justify-content-between align-items-center mb-1'

                const title = document.createElement('strong')
                title.textContent = item.label
                if (isLocked) {
                    const lockIcon = document.createElement('i')
                    lockIcon.className = 'bi bi-lock-fill ms-2'
                    lockIcon.style.cssText = 'font-size: 0.75rem; opacity: 0.5;'
                    lockIcon.setAttribute('title', 'This filter is locked and cannot be changed')
                    title.appendChild(lockIcon)
                }
                headerDiv.appendChild(title)

                if (activeCount > 0) {
                    const countText = document.createElement('small')
                    countText.className = 'text-muted'
                    countText.textContent = `${activeCount} selected`
                    headerDiv.appendChild(countText)
                }

                innerDiv.appendChild(headerDiv)

                const helpText = document.createElement('div')
                helpText.className = 'text-muted small mb-2'
                helpText.textContent = isLocked ? 'This filter is locked' : 'Choose one or more options'
                innerDiv.appendChild(helpText)

                // Add checkboxes for each option
                item.meta.options.forEach(option => {
                    const isChecked = activeFiltered.includes(option.value)

                    const formCheck = document.createElement('div')
                    formCheck.className = 'form-check mb-1'

                    const checkbox = document.createElement('input')
                    checkbox.className = 'form-check-input'
                    checkbox.type = 'checkbox'
                    checkbox.id = `filter_${item.key}_${option.value}`
                    checkbox.value = option.value
                    checkbox.checked = isChecked
                    checkbox.setAttribute('data-filter-column', item.key)
                    if (isLocked) {
                        checkbox.disabled = true
                        checkbox.setAttribute('title', 'This filter is locked and cannot be changed')
                    } else {
                        checkbox.setAttribute('data-action', 'change->grid-view#toggleFilter')
                    }

                    const label = document.createElement('label')
                    label.className = 'form-check-label'
                    if (isLocked) {
                        label.classList.add('text-muted')
                    }
                    label.htmlFor = checkbox.id
                    label.textContent = option.label

                    formCheck.appendChild(checkbox)
                    formCheck.appendChild(label)
                    innerDiv.appendChild(formCheck)
                })
            }

            panel.appendChild(innerDiv)
            container.appendChild(panel)
        })
    }

    /**
     * Update clear filters footer visibility
     */
    updateClearFiltersFooter() {
        const container = this.element.querySelector('[data-clear-filters-container]')
        if (!container) return

        const hasSearch = this.state.search && this.state.search.trim() !== ''
        const hasFilters = this.state.filters.active && Object.keys(this.state.filters.active).length > 0

        if (hasSearch || hasFilters) {
            container.style.display = ''
        } else {
            container.style.display = 'none'
        }
    }

    /**
     * Update column picker modal
     */
    updateColumnPicker() {
        const container = this.element.querySelector('[data-column-list-container]')
        if (!container) return

        container.innerHTML = ''

        if (!this.state.columns || !this.state.columns.all) return

        // Normalize columns.visible to array (handle both array and object formats from PHP)
        const visibleColumns = Array.isArray(this.state.columns.visible)
            ? this.state.columns.visible
            : Object.values(this.state.columns.visible)

        // Build ordered list: visible first, then remaining
        const orderedColumns = []
        const orderedKeys = []

        // Add visible columns first
        visibleColumns.forEach(key => {
            if (this.state.columns.all[key]) {
                orderedColumns.push({ key, ...this.state.columns.all[key] })
                orderedKeys.push(key)
            }
        })

        // Add remaining columns
        Object.entries(this.state.columns.all).forEach(([key, column]) => {
            if (!orderedKeys.includes(key)) {
                orderedColumns.push({ key, ...column })
            }
        })

        // Create list items
        orderedColumns.forEach(column => {
            // Skip export-only columns - they shouldn't appear in the column picker
            if (column.exportOnly) return

            const isVisible = visibleColumns.includes(column.key)
            const isRequired = column.required || false

            const label = document.createElement('label')
            label.className = `list-group-item d-flex align-items-center${!isVisible ? ' list-group-item-secondary' : ''}`
            label.setAttribute('data-sortable-list-target', 'item')
            label.setAttribute('data-column-key', column.key)
            if (isRequired) {
                label.setAttribute('data-column-required', 'true')
            }
            label.draggable = true
            label.setAttribute('data-action', `dragstart->sortable-list#dragStart 
                dragover->sortable-list#dragOver 
                dragenter->sortable-list#dragEnter 
                dragleave->sortable-list#dragLeave 
                drop->sortable-list#drop 
                dragend->sortable-list#dragEnd`)

            // Drag handle
            const dragHandle = document.createElement('span')
            dragHandle.className = 'drag-handle me-2'
            dragHandle.style.cursor = 'move'
            dragHandle.title = 'Drag to reorder'
            dragHandle.innerHTML = '<i class="bi bi-grip-vertical"></i>'
            label.appendChild(dragHandle)

            // Checkbox
            const checkbox = document.createElement('input')
            checkbox.className = 'form-check-input me-2'
            checkbox.type = 'checkbox'
            checkbox.value = column.key
            checkbox.checked = isVisible
            if (isRequired) {
                checkbox.disabled = true
            }
            checkbox.setAttribute('data-action', 'change->grid-view#toggleColumn')
            checkbox.setAttribute('data-column-key', column.key)
            label.appendChild(checkbox)

            // Label content
            const contentDiv = document.createElement('div')
            contentDiv.className = 'flex-grow-1'

            const columnLabel = column.label && column.label.trim() !== '' ? column.label : column.key
            const strong = document.createElement('strong')
            strong.textContent = columnLabel
            contentDiv.appendChild(strong)

            if (isRequired) {
                const requiredText = document.createElement('small')
                requiredText.className = 'text-muted ms-1'
                requiredText.textContent = '(Required)'
                contentDiv.appendChild(requiredText)
            }

            if (column.description) {
                contentDiv.appendChild(document.createElement('br'))
                const desc = document.createElement('small')
                desc.className = 'text-muted'
                desc.textContent = column.description
                contentDiv.appendChild(desc)
            }

            label.appendChild(contentDiv)
            container.appendChild(label)
        })
    }

    // ============================================================================
    // View Actions
    // ============================================================================

    /**
     * Show all records (clear view selection)
     */
    showAll() {
        // When showing all, clear everything except ignore_default
        const url = new URL(window.location.href)
        url.search = '' // Clear all query parameters
        url.searchParams.set('ignore_default', '1')

        this.navigate(url.pathname + url.search) // Table frame nav
    }

    /**
     * Switch to a specific view
     */
    switchView(event) {
        const viewId = event.currentTarget.dataset.viewId
        if (!viewId) return

        // When switching to a view, clear everything except the view_id
        const url = new URL(window.location.href)
        url.search = '' // Clear all query parameters
        url.searchParams.set('view_id', viewId)

        this.navigate(url.pathname + url.search) // Table frame nav
    }

    /**
     * Save current state as new view
     */
    async saveView() {
        const name = prompt("Enter a name for this view:")
        if (!name || name.trim() === "") return

        const config = this.getCurrentConfig()

        try {
            const response = await fetch(`/grid-views/add`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": this.getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    gridKey: this.state.config.gridKey,
                    name: name.trim(),
                    config: config
                })
            })

            const data = await response.json()

            if (response.ok && data.success) {
                alert("View saved successfully")
                // Navigate to the new view
                const url = this.buildUrl({ view_id: data.data.view.id })
                window.location.assign(url)
            } else {
                throw new Error(data.error || "Failed to save view")
            }
        } catch (error) {
            console.error("Error saving view:", error)
            alert("Failed to save view: " + error.message)
        }
    }

    /**
     * Update existing view with current state
     */
    async updateView() {
        if (!this.state.view.currentId) {
            alert("No view selected to update")
            return
        }

        if (!confirm("Update this view with current settings?")) return

        const config = this.getCurrentConfig()

        try {
            const response = await fetch(`/grid-views/edit/${this.state.view.currentId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": this.getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin",
                body: JSON.stringify({ config: config })
            })

            const data = await response.json()

            if (response.ok && data.success) {
                alert("View updated successfully")
                window.location.reload()
            } else {
                throw new Error(data.error || "Failed to update view")
            }
        } catch (error) {
            console.error("Error updating view:", error)
            alert("Failed to update view: " + error.message)
        }
    }

    /**
     * Delete current view
     */
    async deleteView() {
        if (!this.state.view.currentId) {
            alert("No view selected to delete")
            return
        }

        if (!confirm("Are you sure you want to delete this view?")) return

        try {
            const response = await fetch(`/grid-views/delete/${this.state.view.currentId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": this.getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin"
            })

            const data = await response.json()

            if (response.ok && data.success) {
                alert("View deleted successfully")
                const url = this.buildUrl({ view_id: null })
                window.location.assign(url)
            } else {
                throw new Error(data.error || "Failed to delete view")
            }
        } catch (error) {
            console.error("Error deleting view:", error)
            alert("Failed to delete view: " + error.message)
        }
    }

    /**
     * Set current view as user default
     */
    async setDefault() {
        if (!this.state.view.currentId) {
            alert("No view selected to set as default")
            return
        }

        try {
            const response = await fetch(`/grid-views/set-default/${this.state.view.currentId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": this.getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin",
                body: JSON.stringify({ gridKey: this.state.config.gridKey })
            })

            const data = await response.json()

            if (response.ok && data.success) {
                alert("Default view set successfully")
                window.location.reload()
            } else {
                throw new Error(data.error || "Failed to set default")
            }
        } catch (error) {
            console.error("Error setting default:", error)
            alert("Failed to set default: " + error.message)
        }
    }

    /**
     * Clear user default view
     */
    async clearDefault() {
        try {
            const response = await fetch(`/grid-views/clear-default`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-Token": this.getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest"
                },
                credentials: "same-origin",
                body: JSON.stringify({ gridKey: this.state.config.gridKey })
            })

            const data = await response.json()

            if (response.ok && data.success) {
                alert("Default view cleared successfully")
                window.location.reload()
            } else {
                throw new Error(data.error || "Failed to clear default")
            }
        } catch (error) {
            console.error("Error clearing default:", error)
            alert("Failed to clear default: " + error.message)
        }
    }

    // ============================================================================
    // Search Actions
    // ============================================================================

    /**
     * Handle search input keyup with debouncing
     */
    handleSearchKeyup(event) {
        if (event.key === 'Enter') return // Handled separately

        if (this.searchDebounceTimer) {
            clearTimeout(this.searchDebounceTimer)
        }

        this.searchDebounceTimer = setTimeout(() => {
            this.performSearch(event)
        }, 500)
    }

    /**
     * Perform search
     */
    performSearch() {
        const searchTerm = this.searchInputTarget.value.trim()
        const updates = { search: searchTerm || null }

        // If we're on a view and search differs from view default, mark as dirty
        if (this.state.view.currentId && searchTerm !== (this.state.view.search || '')) {
            updates['dirty[search]'] = '1'
        }

        const url = this.buildUrl(updates)
        this.navigate(url)
    }

    /**
     * Clear search
     */
    clearSearch() {
        const updates = { search: '' } // Use empty string instead of null to explicitly clear

        // If we're on a view, mark search as dirty to override the view's potential saved search
        if (this.state.view.currentId) {
            updates['dirty[search]'] = '1'
        }

        const url = this.buildUrl(updates)
        this.navigate(url)
    }

    /**
     * Apply date range filter
     */
    applyDateRangeFilter(event) {
        const input = event.currentTarget
        const fieldName = input.dataset.dateRangeField
        const value = input.value

        // Build updates object with the date range parameter
        const updates = {}
        updates[fieldName] = value || null

        // If value is empty, explicitly set to null to clear the filter
        if (!value) {
            updates[fieldName] = null
        }

        // Mark filters as dirty to ensure they're applied
        updates['dirty[filters]'] = '1'

        const url = this.buildUrl(updates)
        this.navigate(url)
    }

    // ============================================================================
    // Filter Actions
    // ============================================================================

    /**
     * Toggle a filter value
     */
    toggleFilter(event) {
        const checkbox = event.currentTarget
        const column = checkbox.dataset.filterColumn
        const value = checkbox.value

        // Check if this filter is locked
        const lockedFilters = this.state.config?.lockedFilters || []
        if (this.isFilterLocked(column, lockedFilters)) {
            console.warn(`Filter '${column}' is locked and cannot be toggled`)
            // Restore checkbox to its previous state
            checkbox.checked = !checkbox.checked
            return
        }

        // Get current filter values for this column
        let currentValues = this.state.filters.active[column] || []
        if (!Array.isArray(currentValues)) {
            currentValues = currentValues ? [currentValues] : []
        }

        // Toggle the value
        const newValues = checkbox.checked
            ? [...currentValues, value]
            : currentValues.filter(v => v !== value)

        // Build URL with updated filter
        const filterParams = { ...this.state.filters.active }
        if (newValues.length > 0) {
            filterParams[column] = newValues
        } else {
            delete filterParams[column]
        }

        let url = this.buildUrlWithFilters(filterParams)

        // If we're on a view, mark filters as dirty
        if (this.state.view.currentId) {
            const urlObj = new URL(url, window.location.origin)
            urlObj.searchParams.set('dirty[filters]', '1')
            url = urlObj.pathname + urlObj.search
        }

        this.navigate(url)
    }

    /**
     * Remove a specific filter value
     */
    removeFilter(event) {
        const column = event.currentTarget.dataset.filterColumn
        const value = event.currentTarget.dataset.filterValue

        // Check if this filter is locked
        const lockedFilters = this.state.config?.lockedFilters || []
        if (this.isFilterLocked(column, lockedFilters)) {
            console.warn(`Filter '${column}' is locked and cannot be removed`)
            return
        }

        // Get current filter values for this column (ensure it's an array)
        let currentValues = this.state.filters.active[column] || []
        if (!Array.isArray(currentValues)) {
            currentValues = [currentValues]
        }
        const newValues = currentValues.filter(v => v !== value)

        // Build URL with updated filter
        const filterParams = { ...this.state.filters.active }
        if (newValues.length > 0) {
            filterParams[column] = newValues
        } else {
            delete filterParams[column]
        }

        // Build URL and mark filters as dirty (keep view_id)
        let url = this.buildUrlWithFilters(filterParams)
        const urlObj = new URL(url, window.location.origin)

        // If we're on a view, mark filters as dirty
        if (this.state.view.currentId) {
            urlObj.searchParams.set('dirty[filters]', '1')
        }

        url = urlObj.pathname + urlObj.search
        this.navigate(url) // Frame nav - toolbar will update via handleFrameLoad
    }

    /**
     * Clear all filters and search (preserves locked filters)
     */
    clearAllFilters() {
        const lockedFilters = this.state.config?.lockedFilters || []

        // Build filter params preserving only locked filters
        const preservedFilters = {}
        if (this.state.filters.active && lockedFilters.length > 0) {
            for (const [column, values] of Object.entries(this.state.filters.active)) {
                if (this.isFilterLocked(column, lockedFilters)) {
                    preservedFilters[column] = values
                }
            }
        }

        // Build URL with only locked filters preserved
        let url
        if (Object.keys(preservedFilters).length > 0) {
            // Has locked filters - use buildUrlWithFilters to preserve them
            url = this.buildUrlWithFilters(preservedFilters)
            const urlObj = new URL(url, window.location.origin)
            // Clear search
            urlObj.searchParams.delete('search')
            // If we're on a view, mark filters as dirty
            if (this.state.view.currentId) {
                urlObj.searchParams.set('dirty[filters]', '1')
            }
            url = urlObj.pathname + urlObj.search
        } else {
            // No locked filters - simple clear
            const updates = { search: null }
            // If we're on a view, mark filters as dirty instead of removing view
            if (this.state.view.currentId) {
                updates['dirty[filters]'] = '1'
            }
            url = this.buildUrl(updates)
        }

        this.navigate(url)
    }

    /**
     * Check if a filter column is locked
     * 
     * @param {string} column - The filter column key
     * @param {string[]} lockedFilters - Array of locked filter keys
     * @returns {boolean} True if the filter is locked
     */
    isFilterLocked(column, lockedFilters) {
        // Check exact match
        if (lockedFilters.includes(column)) {
            return true
        }
        // Check date range variants (e.g., 'expires_on' locks 'expires_on_start' and 'expires_on_end')
        const baseField = column.replace(/_(start|end)$/, '')
        if (baseField !== column && lockedFilters.includes(baseField)) {
            return true
        }
        return false
    }

    /**
     * Show specific filter panel in dropdown
     */
    selectFilter(event) {
        const key = event.currentTarget.dataset.filterKey

        // Hide all panels
        this.element.querySelectorAll('[data-filter-panel]').forEach(panel => {
            panel.classList.add('d-none')
        })

        // Show selected panel
        const targetPanel = this.element.querySelector(`[data-filter-panel][data-filter-key="${key}"]`)
        if (targetPanel) {
            targetPanel.classList.remove('d-none')
        }

        // Update nav item active states
        this.element.querySelectorAll('[data-filter-nav-item]').forEach(item => {
            item.classList.remove('active')
        })
        event.currentTarget.classList.add('active')
    }

    /**
     * Update date range filter value
     */
    updateDateRangeFilter(event) {
        const columnKey = event.target.dataset.filterColumn
        const value = event.target.value

        // Check if this filter is locked
        const lockedFilters = this.state.config?.lockedFilters || []
        if (this.isFilterLocked(columnKey, lockedFilters)) {
            console.warn(`Filter '${columnKey}' is locked and cannot be changed`)
            // Restore the input to its previous value
            const activeValue = this.state.filters.active[columnKey] || ''
            event.target.value = activeValue
            return
        }

        // Build URL with updated filter
        const filterParams = { ...this.state.filters.active }
        if (value) {
            filterParams[columnKey] = value
        } else {
            delete filterParams[columnKey]
        }

        // Build URL and mark filters as dirty (keep view_id)
        let url = this.buildUrlWithFilters(filterParams)
        const urlObj = new URL(url, window.location.origin)

        // If we're on a view, mark filters as dirty
        if (this.state.view.currentId) {
            urlObj.searchParams.set('dirty[filters]', '1')
        }

        url = urlObj.pathname + urlObj.search
        this.navigate(url) // Frame nav - toolbar will update via handleFrameLoad
    }

    // ============================================================================
    // Sort Actions
    // ============================================================================

    /**
     * Apply sort to a column
     */
    applySort(event) {
        const field = event.currentTarget.dataset.columnKey
        if (!field) return

        const currentSort = this.state.sort

        // Cycle through: none -> asc -> desc -> none
        let direction = null
        if (!currentSort || currentSort.field !== field) {
            direction = 'asc'
        } else if (currentSort.direction === 'asc') {
            direction = 'desc'
        }
        // else direction stays null (clear sort)

        const updates = {
            sort: direction ? field : null,
            direction: direction
        }

        // If we're on a view, mark sort as dirty
        if (this.state.view.currentId) {
            updates['dirty[sort]'] = '1'
        }

        const url = this.buildUrl(updates)
        this.navigate(url)
    }

    // ============================================================================
    // Column Actions
    // ============================================================================

    /**
     * Toggle column visibility (checkbox in modal)
     */
    toggleColumn(event) {
        const columnKey = event.currentTarget.dataset.columnKey
        const listItem = event.currentTarget.closest('[data-column-key]')

        // Prevent toggling required columns
        if (listItem && listItem.dataset.columnRequired === 'true') {
            event.preventDefault()
            return
        }

        const isVisible = event.currentTarget.checked

        // Update visual state
        if (listItem) {
            console.log(`Toggling column ${columnKey}: ${isVisible ? 'visible' : 'hidden'}`)
            if (isVisible) {
                listItem.classList.remove('list-group-item-secondary')
                console.log(`Removed 'list-group-item-secondary' class from ${columnKey}`)
            } else {
                listItem.classList.add('list-group-item-secondary')
                console.log(`Added 'list-group-item-secondary' class to ${columnKey}`)
            }
            // Force a visual refresh
            listItem.offsetHeight
        }
    }

    /**
     * Handle column reorder from sortable list
     */
    handleColumnReorder(event) {
        console.log("Column reorder detected:", event.detail.order)
    }

    /**
     * Apply column changes (from modal)
     */
    applyColumnChanges() {
        // Get visible columns from checkboxes in modal
        const modal = this.element.querySelector(`#columnPickerModal-${this.state.config.gridKey.replace(/\./g, '\\.')}`)
        if (!modal) return

        const visibleColumns = []

        // Include checked columns
        modal.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
            const key = checkbox.dataset.columnKey || checkbox.value
            if (key) visibleColumns.push(key)
        })

        // Always include required columns even if disabled
        modal.querySelectorAll('[data-column-required="true"]').forEach(item => {
            const key = item.dataset.columnKey
            if (key && !visibleColumns.includes(key)) {
                visibleColumns.push(key)
            }
        })

        const url = this.buildUrl({ columns: visibleColumns.join(',') })
        this.navigate(url)
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Build URL with updated parameters
     */
    buildUrl(updates) {
        const params = new URLSearchParams(window.location.search)

        // Apply updates
        for (const [key, value] of Object.entries(updates)) {
            if (value === null || value === undefined) {
                // Only delete if value is null/undefined
                params.delete(key)
            } else if (value === '') {
                // Empty string means explicitly set to empty (e.g., clearing search on a view)
                params.set(key, '')
            } else {
                params.set(key, value)
            }
        }

        // Remove filter parameters if not explicitly included
        if (!('filter' in updates)) {
            // Keep existing filters unless we're explicitly clearing them
            // (This is handled by buildUrlWithFilters)
        }

        return `${window.location.pathname}?${params.toString()}`
    }

    /**
     * Build URL with filter parameters
     */
    buildUrlWithFilters(filterParams) {
        const url = new URL(window.location)
        const params = url.searchParams

        // If we're on a saved view and search hasn't been explicitly dirtied,
        // preserve the current search value in the URL
        const hasSearchDirty = params.has('dirty[search]')
        if (this.state.view.currentId && !hasSearchDirty && this.state.search) {
            params.set('search', this.state.search)
        }

        // Remove all existing filter parameters (both filter[] and date range)
        const keysToDelete = []
        for (const key of params.keys()) {
            if (key.startsWith('filter[')) {
                keysToDelete.push(key)
            }
            // Also remove date range parameters (_start and _end suffixes)
            if (key.endsWith('_start') || key.endsWith('_end')) {
                keysToDelete.push(key)
            }
        }
        keysToDelete.forEach(key => params.delete(key))

        // Add new filter parameters
        for (const [column, values] of Object.entries(filterParams)) {
            // Check if this is a date range filter (has _start or _end suffix)
            const isDateRangeFilter = column.endsWith('_start') || column.endsWith('_end')

            if (isDateRangeFilter) {
                // Date range filters use direct query parameters (no filter[] prefix)
                const valueArray = Array.isArray(values) ? values : [values]
                if (valueArray.length > 0) {
                    params.set(column, valueArray[0])
                }
            } else {
                // Regular filters use filter[] prefix
                const valueArray = Array.isArray(values) ? values : [values]
                if (valueArray.length === 1) {
                    params.set(`filter[${column}]`, valueArray[0])
                } else if (valueArray.length > 1) {
                    valueArray.forEach(v => params.append(`filter[${column}][]`, v))
                }
            }
        }

        // Reset to page 1
        params.delete('page')

        return url.pathname + url.search
    }

    /**
     * Navigate to URL via Turbo (frame or full page)
     */
    navigate(url, fullPage = false) {
        console.log('Navigating to:', url, 'fullPage:', fullPage)

        if (fullPage) {
            // Full page navigation
            if (window.Turbo) {
                window.Turbo.visit(url)
            } else {
                window.location.assign(url)
            }
        } else {
            // Frame navigation - find the table frame and update its src
            const tableFrame = this.element.querySelector('turbo-frame[id$="-table"]')
            if (tableFrame) {
                // Get the base grid-data URL from the frame's current src
                // This handles embedded grids with custom endpoints like /members/roles-grid-data/1
                const currentSrc = tableFrame.getAttribute('src') || tableFrame.src
                if (!currentSrc) {
                    console.warn('Table frame has no src attribute')
                    return
                }

                // Parse current src to extract base URL and context parameters
                const currentSrcUrl = new URL(currentSrc, window.location.origin)
                const baseGridDataUrl = currentSrcUrl.pathname

                // Context parameters that must be preserved (e.g., member_id, branch_id)
                // These identify which entity's data we're viewing
                const contextParams = ['member_id', 'branch_id', 'gathering_id']

                // Parse the navigation URL to get new query params
                const urlObj = new URL(url, window.location.origin)

                // Build final URL starting with base path
                const finalUrl = new URL(baseGridDataUrl, window.location.origin)

                // Copy all params from the incoming URL
                urlObj.searchParams.forEach((value, key) => {
                    finalUrl.searchParams.set(key, value)
                })

                // Preserve context params from original src if not in new URL
                contextParams.forEach(param => {
                    if (currentSrcUrl.searchParams.has(param) && !finalUrl.searchParams.has(param)) {
                        finalUrl.searchParams.set(param, currentSrcUrl.searchParams.get(param))
                    }
                })

                const gridDataUrl = finalUrl.pathname + finalUrl.search

                // Update browser history with the original URL (for page reload)
                window.history.pushState({}, '', url)

                // Navigate the frame by setting src to gridData URL
                tableFrame.src = gridDataUrl
            } else {
                console.warn('Table frame not found, falling back to full page navigation')
                if (window.Turbo) {
                    window.Turbo.visit(url)
                } else {
                    window.location.assign(url)
                }
            }
        }
    }

    /**
     * Get current configuration for saving
     */
    getCurrentConfig() {
        // Build filters array from active filters
        const filters = []

        // Add search if present
        if (this.state.search) {
            filters.push({
                field: '_search',
                operator: 'contains',
                value: this.state.search
            })
        }

        // Add regular filters
        // First, collect date range pairs
        const dateRanges = new Map()
        const regularFilters = []

        for (const [field, values] of Object.entries(this.state.filters.active)) {
            const valueArray = Array.isArray(values) ? values : [values]
            if (valueArray.length > 0) {
                // Check if this is a date range filter (field ends with _start or _end)
                if (field.endsWith('_start')) {
                    const baseField = field.slice(0, -6) // Remove '_start' suffix
                    if (!dateRanges.has(baseField)) {
                        dateRanges.set(baseField, [null, null])
                    }
                    dateRanges.get(baseField)[0] = valueArray[0]
                } else if (field.endsWith('_end')) {
                    const baseField = field.slice(0, -4) // Remove '_end' suffix
                    if (!dateRanges.has(baseField)) {
                        dateRanges.set(baseField, [null, null])
                    }
                    dateRanges.get(baseField)[1] = valueArray[0]
                } else {
                    regularFilters.push({
                        field: field,
                        operator: 'in',
                        value: valueArray
                    })
                }
            }
        }

        // Add date range filters with [start, end] array
        for (const [field, range] of dateRanges) {
            filters.push({
                field: field,
                operator: 'dateRange',
                value: range
            })
        }

        // Add regular filters
        filters.push(...regularFilters)


        // Build sort array
        const sort = []
        if (this.state.sort && this.state.sort.field) {
            sort.push({
                field: this.state.sort.field,
                direction: this.state.sort.direction
            })
        }

        // Build columns array - normalize visible columns (handle both array and object formats)
        const visibleColumns = Array.isArray(this.state.columns.visible)
            ? this.state.columns.visible
            : Object.values(this.state.columns.visible)

        const columns = visibleColumns.map((key, index) => ({
            key: key,
            visible: true,
            order: index
        }))

        return {
            filters: filters,
            sort: sort,
            columns: columns,
            pageSize: this.state.config.pageSize,
            search: this.state.search
        }
    }

    /**
     * Toggle sub-row expansion for additional details
     * 
     * @param {Event} event - Click event from cell with toggleSubRow action
     */
    toggleSubRow(event) {
        event.preventDefault()

        const link = event.currentTarget
        const rowId = link.dataset.rowId
        const subRowType = link.dataset.subrowType

        if (!rowId || !subRowType) {
            console.error('Missing rowId or subRowType for toggleSubRow')
            return
        }

        // Find the parent table row
        const mainRow = link.closest('tr')
        if (!mainRow) {
            console.error('Could not find parent row for toggleSubRow')
            return
        }

        // Look for existing sub-row immediately after the main row
        const existingSubRow = mainRow.nextElementSibling
        const subRowId = `subrow-${rowId}-${subRowType}`

        if (existingSubRow && existingSubRow.id === subRowId) {
            // Sub-row exists - collapse it
            existingSubRow.remove()
            mainRow.classList.remove('row-expanded')

            // Update icon if present
            const icon = link.querySelector('.toggle-icon')
            if (icon) {
                icon.classList.remove('bi-chevron-down')
                icon.classList.add('bi-chevron-right')
            }
        } else {
            // Sub-row doesn't exist - expand it
            const colspan = mainRow.querySelectorAll('td').length

            // Fetch sub-row content from server
            const url = `/members/sub-row/${rowId}/${subRowType}`

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error ${response.status}`)
                    }
                    return response.text()
                })
                .then(html => {
                    // Create sub-row element
                    const subRow = document.createElement('tr')
                    subRow.id = subRowId
                    subRow.className = 'sub-row'
                    subRow.innerHTML = `<td colspan="${colspan}" class="sub-row-content">${html}</td>`

                    // Insert after main row
                    mainRow.insertAdjacentElement('afterend', subRow)
                    mainRow.classList.add('row-expanded')

                    // Update icon if present
                    const icon = link.querySelector('.toggle-icon')
                    if (icon) {
                        icon.classList.remove('bi-chevron-right')
                        icon.classList.add('bi-chevron-down')
                    }
                })
                .catch(error => {
                    console.error('Error loading sub-row:', error)
                    // Show error in a sub-row
                    const subRow = document.createElement('tr')
                    subRow.id = subRowId
                    subRow.className = 'sub-row sub-row-error'
                    subRow.innerHTML = `<td colspan="${colspan}" class="sub-row-content text-danger">
                    <small>Error loading details. Please try again.</small>
                </td>`
                    mainRow.insertAdjacentElement('afterend', subRow)
                })
        }
    }

    /**
     * Export current grid data to CSV
     * 
     * Triggers a CSV export with current filters, search, sort, and column selection.
     * Uses the table frame's src URL as base (handles embedded grids with custom endpoints).
     */
    exportCsv() {
        // Find the table frame to get the correct data endpoint
        const tableFrame = this.element.querySelector('turbo-frame[id$="-table"]')
        if (!tableFrame) {
            console.warn('Table frame not found, cannot export')
            return
        }

        // Get the base grid-data URL from the frame's src
        const currentSrc = tableFrame.getAttribute('src') || tableFrame.src
        if (!currentSrc) {
            console.warn('Table frame has no src attribute')
            return
        }

        // Parse current src to build export URL
        const srcUrl = new URL(currentSrc, window.location.origin)

        // Add export parameter
        srcUrl.searchParams.set('export', 'csv')

        // Navigate to CSV export URL (will trigger download)
        window.location.href = srcUrl.toString()
    }

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]')
        return meta ? meta.content : ""
    }
}

// Register controller globally
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["grid-view"] = GridViewController

export default GridViewController
