import { Controller } from "@hotwired/stimulus"
import WorkflowValidationService from './workflow-validation-service.js'
import WorkflowVariablePicker from './workflow-variable-picker.js'
import WorkflowConfigPanel from './workflow-config-panel.js'
import WorkflowHistoryManager from './workflow-history-manager.js'
import WorkflowSerializer from './workflow-serializer.js'
import WorkflowNodeConfigHandler from './workflow-node-config-handler.js'

/**
 * WorkflowDesignerController
 *
 * Coordinator for the workflow visual designer. Manages the Drawflow canvas,
 * node palette, and delegates to extracted modules:
 * - WorkflowSerializer: data conversion and auto-layout
 * - WorkflowNodeConfigHandler: config panel form interactions
 * - WorkflowToolbarController: toolbar actions (separate Stimulus controller)
 */
class WorkflowDesignerController extends Controller {
    static targets = [
        "canvas", "nodeConfig", "nodePalette",
        "versionInfo", "validationResults",
        "loadingOverlay", "unsavedIndicator"
    ]

    static values = {
        loadUrl: String,
        registryUrl: String,
        workflowId: Number,
        versionId: Number,
        csrfToken: String,
        readonly: { type: Boolean, default: false },
        maxHistory: { type: Number, default: 50 }
    }

    editor = null

    registryData = {
        triggers: [],
        actions: [],
        conditions: [],
        entities: []
    }

    _selectedNodes = new Set()
    _zoom = 1
    _isDirty = false
    _lastSavedSnapshot = null
    _shiftHeld = false

    async connect() {
        this._showLoading(true)
        try {
            this.initEditor()
            await this.loadRegistry()
            if (this.hasWorkflowIdValue && this.workflowIdValue) {
                await this.loadWorkflow()
            }
            this._nodeConfigHandler.restoreConfigPanelWidth()
            this._lastSavedSnapshot = JSON.stringify(this.editor.export())
        } catch (error) {
            this._showError('Failed to initialize the workflow designer.')
            console.error('Designer init error:', error)
        } finally {
            this._showLoading(false)
        }
    }

    // --- Public API for toolbar/external controllers ---

    get historyManager() { return this._historyManager }
    get validationService() { return this._validationService }
    get selectedNodes() { return this._selectedNodes }

    exportWorkflow() {
        return this._serializer.exportWorkflow(this.editor)
    }

    markSaved() {
        this._lastSavedSnapshot = JSON.stringify(this.editor.export())
        this._isDirty = false
        this._updateDirtyState()
    }

    clearMultiSelect() {
        this._selectedNodes.clear()
        this.canvasTarget.querySelectorAll('.wf-multi-selected').forEach(el => {
            el.classList.remove('wf-multi-selected')
        })
    }

    showValidationResults({ valid, errors, warnings }) {
        if (this.hasValidationResultsTarget) {
            let html = ''
            if (valid && warnings.length === 0) {
                html = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i> Workflow is valid.</div>'
            }
            errors.forEach(e => {
                html += `<div class="alert alert-danger mb-1"><i class="bi bi-x-circle me-1"></i> ${e}</div>`
            })
            warnings.forEach(w => {
                html += `<div class="alert alert-warning mb-1"><i class="bi bi-exclamation-triangle me-1"></i> ${w}</div>`
            })
            this.validationResultsTarget.innerHTML = html
            this.validationResultsTarget.style.display = 'block'

            if (valid) {
                setTimeout(() => {
                    if (this.hasValidationResultsTarget) this.validationResultsTarget.style.display = 'none'
                }, 4000)
            }
        }
    }

    // --- Loading & Error UX ---

    _showLoading(show) {
        if (this.hasLoadingOverlayTarget) {
            this.loadingOverlayTarget.style.display = show ? 'flex' : 'none'
        }
    }

    _showError(message) {
        if (this.hasCanvasTarget) {
            const overlay = this.canvasTarget.querySelector('.wf-error-overlay')
            if (overlay) {
                overlay.querySelector('.wf-error-message').textContent = message
                overlay.style.display = 'flex'
            }
        }
    }

    // --- Editor Init ---

    async initEditor() {
        const { default: Drawflow } = await import('drawflow')
        this.editor = new Drawflow(this.canvasTarget)
        this.editor.reroute = true
        this.editor.reroute_fix_curvature = true
        this.editor.force_first_input = false

        if (this.readonlyValue) {
            this.editor.editor_mode = 'view'
        }

        this.editor.start()
        this.registerNodeTemplates()

        this._historyManager = new WorkflowHistoryManager(this.maxHistoryValue)
        this._nodeConfigHandler = new WorkflowNodeConfigHandler(this)

        this.editor.on('nodeSelected', (nodeId) => this._nodeConfigHandler.onNodeSelected(nodeId))
        this.editor.on('nodeUnselected', () => this._nodeConfigHandler.onNodeUnselected())
        this.editor.on('connectionCreated', (connection) => this.onConnectionCreated(connection))
        this.editor.on('nodeRemoved', (nodeId) => this.onNodeRemoved(nodeId))

        this.editor.on('nodeCreated', () => this._onGraphChange())
        this.editor.on('nodeRemoved', () => this._onGraphChange())
        this.editor.on('connectionCreated', () => this._onGraphChange())
        this.editor.on('connectionRemoved', () => this._onGraphChange())
        this.editor.on('nodeMoved', () => this._onGraphChange())

        this._historyManager.push(this.editor)
    }

    _onGraphChange() {
        this._historyManager.push(this.editor)
        this._updateDirtyState()
    }

    _updateDirtyState() {
        const current = JSON.stringify(this.editor.export())
        this._isDirty = current !== this._lastSavedSnapshot
        if (this.hasUnsavedIndicatorTarget) {
            this.unsavedIndicatorTarget.style.display = this._isDirty ? 'inline' : 'none'
        }
    }

    registerNodeTemplates() {
        // Define HTML templates for each node type
    }

    // --- Registry & Workflow Loading ---

    async loadRegistry() {
        if (!this.hasRegistryUrlValue) return
        try {
            const response = await fetch(this.registryUrlValue, {
                headers: { 'Accept': 'application/json' }
            })
            if (response.ok) {
                this.registryData = await response.json()
                let policyClasses = []
                try {
                    const policyResponse = await fetch('/workflows/policy-classes')
                    if (policyResponse.ok) {
                        const policyData = await policyResponse.json()
                        policyClasses = policyData.policyClasses || []
                    }
                } catch (e) {
                    console.warn('Could not load policy classes:', e)
                }
                this._configPanel = new WorkflowConfigPanel(this.registryData, policyClasses)
                this._variablePicker = new WorkflowVariablePicker(this.registryData)
                this._serializer = new WorkflowSerializer(this.registryData)
                this._validationService = new WorkflowValidationService(
                    (type) => this._serializer.getNodePorts(type),
                    this.registryData
                )
                this.populateNodePalette()
            } else {
                throw new Error(`Registry returned ${response.status}`)
            }
        } catch (error) {
            console.error('Failed to load workflow registry:', error)
            this._showError('Failed to load the node registry. Please reload the page.')
            throw error
        }
    }

    async loadWorkflow() {
        if (!this.hasLoadUrlValue) return
        try {
            const response = await fetch(this.loadUrlValue, {
                headers: { 'Accept': 'application/json' }
            })
            if (response.ok) {
                const data = await response.json()
                if (data.definition) {
                    this._serializer.importWorkflow(this.editor, data.definition, data.canvasLayout)
                }
            } else {
                throw new Error(`Load returned ${response.status}`)
            }
        } catch (error) {
            console.error('Failed to load workflow:', error)
            this._showError('Failed to load workflow data. Please reload the page.')
            throw error
        }
    }

    // --- Palette ---

    populateNodePalette() {
        if (!this.hasNodePaletteTarget) return
        let html = this._buildPaletteHTML()
        this.nodePaletteTarget.innerHTML = html
        const filterInput = this.nodePaletteTarget.querySelector('#workflow-palette-filter')
        if (filterInput) {
            this.filterNodePalette({ target: filterInput })
        }
    }

    _buildPaletteHTML() {
        let html = `<div class="palette-filter mb-2">
            <label for="workflow-palette-filter" class="form-label small mb-1">Filter nodes</label>
            <input id="workflow-palette-filter" type="search" class="form-control form-control-sm"
                placeholder="Type to filter nodes..."
                aria-describedby="workflow-palette-filter-status"
                data-action="input->workflow-designer#filterNodePalette">
            <div id="workflow-palette-filter-status" class="form-text small" role="status" aria-live="polite"
                data-palette-filter-status></div>
        </div>
        <p class="text-muted small mb-2" data-palette-empty-message hidden>No matching nodes found.</p>`

        const makeIcon = (faClass) =>
            `<span class="palette-node-icon"><i class="fa-solid ${faClass}"></i></span>`
        const makeNode = ({ type, label, icon, data = {}, searchText = '' }) => {
            const dataAttrs = Object.entries(data)
                .map(([key, value]) => ` ${key}="${this._escapeAttr(value)}"`)
                .join('')
            const searchableText = `${label} ${type} ${searchText}`.trim()

            return `<div class="palette-node" draggable="true" data-node-type="${this._escapeAttr(type)}"${dataAttrs}
                    data-palette-node data-palette-search="${this._escapeAttr(searchableText)}"
                    data-action="dragstart->workflow-designer#onPaletteDragStart"
                    role="button" aria-label="${this._escapeAttr(label)}">${makeIcon(icon)} ${this._escapeHtml(label)}</div>`
        }

        const groupBySource = (items) => {
            const groups = {}
            items.forEach(item => {
                const src = item.source || 'Other'
                if (!groups[src]) groups[src] = []
                groups[src].push(item)
            })
            return groups
        }

        html += '<div class="palette-category" data-palette-category><h6 class="palette-category-title">Flow Control</h6>'
        const flowNodes = [
            { type: 'condition', label: 'Condition', icon: 'fa-diamond' },
            { type: 'fork', label: 'Parallel Fork', icon: 'fa-code-branch' },
            { type: 'join', label: 'Parallel Join', icon: 'fa-code-merge' },
            { type: 'loop', label: 'Loop', icon: 'fa-rotate' },
            { type: 'forEach', label: 'For Each', icon: 'fa-list-ol' },
            { type: 'delay', label: 'Delay / Wait', icon: 'fa-clock' },
            { type: 'end', label: 'End', icon: 'fa-stop' },
        ]
        flowNodes.forEach(node => {
            html += makeNode(node)
        })
        html += '</div>'

        html += '<div class="palette-category" data-palette-category><h6 class="palette-category-title">Approvals</h6>'
        html += makeNode({ type: 'approval', label: 'Approval Gate', icon: 'fa-check-double' })
        html += '</div>'

        if (this.registryData.triggers && this.registryData.triggers.length > 0) {
            const groups = groupBySource(this.registryData.triggers)
            for (const [source, triggers] of Object.entries(groups)) {
                html += `<div class="palette-category" data-palette-category><h6 class="palette-category-title"><i class="fa-solid fa-bolt fa-xs me-1"></i>Triggers — ${this._escapeHtml(source)}</h6>`
                triggers.forEach(trigger => {
                    html += makeNode({
                        type: 'trigger',
                        label: trigger.label,
                        icon: 'fa-bolt',
                        data: { 'data-node-event': trigger.event },
                        searchText: `${trigger.event} ${source} trigger`,
                    })
                })
                html += '</div>'
            }
        }

        if (this.registryData.actions && this.registryData.actions.length > 0) {
            const groups = groupBySource(this.registryData.actions)
            for (const [source, actions] of Object.entries(groups)) {
                html += `<div class="palette-category" data-palette-category><h6 class="palette-category-title"><i class="fa-solid fa-gear fa-xs me-1"></i>Actions — ${this._escapeHtml(source)}</h6>`
                actions.forEach(action => {
                    html += makeNode({
                        type: 'action',
                        label: action.label,
                        icon: 'fa-gear',
                        data: { 'data-node-action': action.action },
                        searchText: `${action.action} ${source} action`,
                    })
                })
                html += '</div>'
            }
        }

        if (this.registryData.conditions && this.registryData.conditions.length > 0) {
            const groups = groupBySource(this.registryData.conditions)
            for (const [source, conditions] of Object.entries(groups)) {
                html += `<div class="palette-category" data-palette-category><h6 class="palette-category-title"><i class="fa-solid fa-diamond fa-xs me-1"></i>Conditions — ${this._escapeHtml(source)}</h6>`
                conditions.forEach(cond => {
                    html += makeNode({
                        type: 'condition',
                        label: cond.label,
                        icon: 'fa-diamond',
                        data: { 'data-node-condition': cond.condition },
                        searchText: `${cond.condition} ${source} condition`,
                    })
                })
                html += '</div>'
            }
        }

        return html
    }

    filterNodePalette(event) {
        const palette = event.target.closest('[data-workflow-designer-target~="nodePalette"]') || this.nodePaletteTarget
        const filter = (event.target.value || '').trim().toLowerCase()
        const nodes = Array.from(palette.querySelectorAll('[data-palette-node]'))
        let visibleCount = 0

        nodes.forEach(node => {
            const searchText = (node.dataset.paletteSearch || node.textContent || '').toLowerCase()
            const matches = filter === '' || searchText.includes(filter)
            node.hidden = !matches
            if (matches) {
                visibleCount += 1
            }
        })

        palette.querySelectorAll('[data-palette-category]').forEach(category => {
            const hasVisibleNode = Array.from(category.querySelectorAll('[data-palette-node]'))
                .some(node => !node.hidden)
            category.hidden = !hasVisibleNode
        })

        const emptyMessage = palette.querySelector('[data-palette-empty-message]')
        if (emptyMessage) {
            emptyMessage.hidden = visibleCount > 0
        }

        const status = palette.querySelector('[data-palette-filter-status]')
        if (status) {
            const totalCount = nodes.length
            status.textContent = filter === ''
                ? `${totalCount} nodes available.`
                : `${visibleCount} of ${totalCount} nodes match.`
        }
    }

    _escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }

    _escapeAttr(value) {
        return this._escapeHtml(value)
    }

    // --- Drag & Drop ---

    onPaletteDragStart(event) {
        const el = event.currentTarget
        event.dataTransfer.setData('node-type', el.dataset.nodeType)
        event.dataTransfer.setData('node-event', el.dataset.nodeEvent || '')
        event.dataTransfer.setData('node-action', el.dataset.nodeAction || '')
        event.dataTransfer.effectAllowed = 'move'
    }

    onCanvasDrop(event) {
        event.preventDefault()
        const nodeType = event.dataTransfer.getData('node-type')
        if (!nodeType) return

        const canvasRect = this.canvasTarget.getBoundingClientRect()
        const zoom = this._zoom || 1
        const canvasX = this.editor.canvas_x || 0
        const canvasY = this.editor.canvas_y || 0
        const x = (event.clientX - canvasRect.left - canvasX) / zoom
        const y = (event.clientY - canvasRect.top - canvasY) / zoom

        this.addNode(nodeType, x, y, {
            event: event.dataTransfer.getData('node-event'),
            action: event.dataTransfer.getData('node-action')
        })
    }

    onCanvasDragOver(event) {
        event.preventDefault()
        event.dataTransfer.dropEffect = 'move'
    }

    // --- Node CRUD ---

    addNode(type, x, y, config = {}) {
        const nodeKey = `${type}-${Date.now()}`
        const { inputs, outputs } = this._serializer.getNodePorts(type)
        const html = this._serializer.buildNodeHTML(type, nodeKey, config)

        return this.editor.addNode(
            nodeKey, inputs, outputs,
            x, y, `${nodeKey} wf-type-${type}`,
            { type, config, nodeKey },
            html
        )
    }

    // --- Node Selection (multi-select via shift-click) ---

    onCanvasClick(event) {
        if (!event.shiftKey) return
        const nodeEl = event.target.closest('.drawflow-node')
        if (!nodeEl) return
        const nodeId = nodeEl.id.replace('node-', '')
        if (this._selectedNodes.has(nodeId)) {
            this._selectedNodes.delete(nodeId)
        } else {
            this._selectedNodes.add(nodeId)
        }
        this._nodeConfigHandler._highlightSelectedNodes()
    }

    // --- Delegate config panel actions to handler ---

    updateNodeConfig(event) { this._nodeConfigHandler.updateNodeConfig(event) }
    onApproverTypeChange(event) { this._nodeConfigHandler.onApproverTypeChange(event) }
    onApproverValueModeChange(event) { this._nodeConfigHandler.onApproverValueModeChange(event) }
    onSerialPickNextChange(event) { this._nodeConfigHandler.onSerialPickNextChange(event) }
    onResolverChange(event) { this._nodeConfigHandler.onResolverChange(event) }
    onValuePickerTypeChange(event) { this._nodeConfigHandler.onValuePickerTypeChange(event) }
    onPolicyClassChange(event) { this._nodeConfigHandler.onPolicyClassChange(event) }
    onResizeStart(event) { this._nodeConfigHandler.onResizeStart(event) }
    addKvRow(event) { this._nodeConfigHandler.addKvRow(event) }
    removeKvRow(event) { this._nodeConfigHandler.removeKvRow(event) }
    onKvValueTypeChange(event) { this._nodeConfigHandler.onKvValueTypeChange(event) }

    filterVariableCatalog(event) {
        const filter = (event.target.value || '').toLowerCase()
        const items = event.target.closest('.tab-pane')?.querySelectorAll('[data-var-item]') || []
        items.forEach(item => {
            const path = (item.dataset.varPath || '').toLowerCase()
            const label = (item.dataset.varLabel || '').toLowerCase()
            item.style.display = (path.includes(filter) || label.includes(filter)) ? '' : 'none'
        })
    }
    onEmailTemplateChange(event) { this._nodeConfigHandler.onEmailTemplateChange(event) }

    // --- Node Events ---

    onConnectionCreated(connection) {
        // Validate connection rules
    }

    onNodeRemoved(nodeId) {
        if (this.hasNodeConfigTarget) {
            this._nodeConfigHandler.onNodeUnselected()
        }
    }

    // --- Lifecycle ---

    disconnect() {
        if (this._nodeConfigHandler) {
            this._nodeConfigHandler.disconnect()
        }
        if (this.editor) {
            this.editor.clear()
        }
    }
}

// Register controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["workflow-designer"] = WorkflowDesignerController;
