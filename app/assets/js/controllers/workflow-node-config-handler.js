/**
 * WorkflowNodeConfigHandler
 *
 * Manages node configuration panel interactions including form handling,
 * approver type changes, value pickers, and config panel resize.
 * Instantiated by the main WorkflowDesignerController.
 */
export default class WorkflowNodeConfigHandler {
    constructor(designer) {
        this.designer = designer
        this._boundResizeMove = null
        this._boundResizeEnd = null
    }

    get editor() { return this.designer.editor }
    get configPanel() { return this.designer._configPanel }
    get variablePicker() { return this.designer._variablePicker }

    get nodeConfigTarget() { return this.designer.nodeConfigTarget }
    get hasNodeConfigTarget() { return this.designer.hasNodeConfigTarget }

    // --- Node Selection ---

    onNodeSelected(nodeId) {
        this.designer._selectedNodes.add(nodeId)
        this._highlightSelectedNodes()
        const nodeData = this.editor.getNodeFromId(nodeId)
        if (this.hasNodeConfigTarget && this.configPanel) {
            const variableInfo = this._buildVariableInfo(nodeId, nodeData)
            this.nodeConfigTarget.innerHTML = this._resizeHandleHTML + this.configPanel.renderConfigHTML(nodeId, nodeData, variableInfo)
            this.variablePicker?.attachPickers(this.nodeConfigTarget, nodeId, this.editor)
            const nodeConfig = nodeData.data?.config || {}
            if (nodeConfig.approverType === 'policy' && nodeConfig.policyClass) {
                this._loadPolicyActions(nodeConfig.policyClass, nodeConfig.policyAction)
            }
            this.nodeConfigTarget.querySelectorAll('[data-vp-settings-select]').forEach(selectEl => {
                const currentVal = selectEl.value
                if (currentVal) {
                    this._loadAppSettingsForPicker(selectEl, currentVal)
                }
            })
            // Load email template dropdown options
            this._loadEmailTemplateOptions(nodeConfig, nodeId)
        }
    }

    onNodeUnselected() {
        if (!this.designer._shiftHeld) {
            this.designer.clearMultiSelect()
        }
        if (this.hasNodeConfigTarget && this.configPanel) {
            this.nodeConfigTarget.innerHTML = this._resizeHandleHTML + this.configPanel.renderEmptyHTML()
        }
    }

    /**
     * Build variable info for the catalog tab.
     * @returns {{ available: Array, produced: Array }}
     */
    _buildVariableInfo(nodeId, nodeData) {
        if (!this.variablePicker) return null
        const available = this.variablePicker.buildVariableList(nodeId, this.editor)
        const produced = this.variablePicker.getNodeOutputSchema(nodeData)
        return { available, produced }
    }

    _highlightSelectedNodes() {
        this.designer.canvasTarget.querySelectorAll('.drawflow-node').forEach(el => {
            el.classList.remove('wf-multi-selected')
        })
        this.designer._selectedNodes.forEach(id => {
            const el = this.designer.canvasTarget.querySelector(`#node-${id}`)
            if (el) el.classList.add('wf-multi-selected')
        })
    }

    // --- Config Form Handlers ---

    onApproverTypeChange(event) {
        this.updateNodeConfig(event)
        const form = event.target.closest('form')
        const selectedType = event.target.value
        form.querySelectorAll('[data-approver-section]').forEach(section => {
            section.style.display = section.dataset.approverSection === selectedType ? 'block' : 'none'
        })
    }

    onApproverValueModeChange(event) {
        const section = event.target.closest('[data-approver-section]')
        if (!section) return
        const mode = event.target.value
        const fixedDiv = section.querySelector('[data-approver-value-fixed]')
        const contextDiv = section.querySelector('[data-approver-value-context]')
        if (fixedDiv) fixedDiv.style.display = mode === 'fixed' ? 'block' : 'none'
        if (contextDiv) contextDiv.style.display = mode === 'context' ? 'block' : 'none'

        // Clear the value in the hidden mode so it doesn't persist
        if (mode === 'fixed') {
            const ctxInput = contextDiv?.querySelector('input[name="approverValue"]')
            if (ctxInput) ctxInput.value = ''
        } else {
            const acHidden = fixedDiv?.querySelector('input[name="approverValue"]')
            if (acHidden) acHidden.value = ''
        }
        this.updateNodeConfig(event)
    }

    onSerialPickNextChange(event) {
        const form = event.target.closest('form')
        const checked = event.target.checked
        const allowParallel = form.querySelector('[name="allowParallel"]')
        if (checked && allowParallel) {
            allowParallel.checked = false
            allowParallel.disabled = true
        } else if (allowParallel) {
            allowParallel.disabled = false
        }
        this.updateNodeConfig(event)
    }

    onResolverChange(event) {
        const form = event.target.closest('form')
        const nodeId = form.dataset.nodeId
        const resolverKey = event.target.value
        const nodeData = this.editor.getNodeFromId(nodeId)

        if (!nodeData.data) nodeData.data = {}
        if (!nodeData.data.config) nodeData.data.config = {}

        if (resolverKey) {
            const resolver = this.configPanel.resolvers.find(r => r.resolver === resolverKey)
            nodeData.data.config.approverConfig = {
                service: resolverKey,
                method: resolver?.method || '',
            }
            if (resolver?.configSchema) {
                for (const key of Object.keys(resolver.configSchema)) {
                    nodeData.data.config.approverConfig[key] = nodeData.data.config.approverConfig[key] || ''
                }
            }
        } else {
            nodeData.data.config.approverConfig = {}
        }

        this.editor.updateNodeDataFromId(nodeId, nodeData.data)

        if (this.hasNodeConfigTarget && this.configPanel) {
            const updatedNode = this.editor.getNodeFromId(nodeId)
            this.nodeConfigTarget.innerHTML = this._resizeHandleHTML + this.configPanel.renderConfigHTML(nodeId, updatedNode)
            if (this.variablePicker) {
                this.variablePicker.attachPickers(this.nodeConfigTarget, nodeId, this.editor)
            }
        }
    }

    onValuePickerTypeChange(event) {
        const select = event.target
        const fieldName = select.dataset.vpType
        const selectedType = select.value
        const form = select.closest('form')
        const nodeId = form?.dataset.nodeId
        const inputGroup = select.closest('.input-group')

        const existingInput = inputGroup.querySelector(`[name="${fieldName}"]`)
        const isNumber = existingInput?.type === 'number'
        const dataType = isNumber ? 'integer' : 'string'

        const oldInputs = inputGroup.querySelectorAll(`[name="${fieldName}"]`)
        oldInputs.forEach(el => {
            if (el.type === 'checkbox') {
                el.closest('.form-check')?.remove()
            } else {
                el.remove()
            }
        })

        let newInputHTML = ''
        if (selectedType === 'context') {
            newInputHTML = `<input type="text" class="form-control form-control-sm"
                name="${fieldName}" value="" placeholder="$.path.to.value"
                data-action="change->workflow-designer#updateNodeConfig"
                data-variable-picker="true">`
        } else if (selectedType === 'app_setting') {
            newInputHTML = `<select class="form-select form-select-sm" name="${fieldName}"
                data-action="change->workflow-designer#updateNodeConfig"
                data-vp-settings-select="${fieldName}">
                <option value="">Loading settings...</option>
            </select>`
        } else {
            if (dataType === 'integer') {
                newInputHTML = `<input type="number" class="form-control form-control-sm"
                    name="${fieldName}" value=""
                    data-action="change->workflow-designer#updateNodeConfig">`
            } else {
                newInputHTML = `<input type="text" class="form-control form-control-sm"
                    name="${fieldName}" value=""
                    data-action="change->workflow-designer#updateNodeConfig">`
            }
        }

        inputGroup.insertAdjacentHTML('beforeend', newInputHTML)

        if (selectedType === 'app_setting') {
            this._loadAppSettingsForPicker(inputGroup.querySelector(`[data-vp-settings-select="${fieldName}"]`))
        }

        if (selectedType === 'context' && this.variablePicker && nodeId) {
            this.variablePicker.attachPickers(this.nodeConfigTarget, nodeId, this.editor)
        }

        this.updateNodeConfig(event)
    }

    async onPolicyClassChange(event) {
        this.updateNodeConfig(event)
        const form = event.target.closest('form')
        const policyClass = event.target.value
        const actionSelect = form.querySelector('[name="policyAction"]')

        if (!policyClass) {
            actionSelect.innerHTML = '<option value="">Select a policy class first...</option>'
            return
        }

        try {
            const response = await fetch('/workflows/policy-actions?class=' + encodeURIComponent(policyClass))
            const data = await response.json()
            let options = '<option value="">Select an action...</option>'
            data.policyActions.forEach(a => {
                options += `<option value="${a.action}">${a.label}</option>`
            })
            actionSelect.innerHTML = options
        } catch (error) {
            console.error('Failed to load policy actions:', error)
            actionSelect.innerHTML = '<option value="">Error loading actions</option>'
        }
    }

    async _loadPolicyActions(policyClass, selectedAction) {
        try {
            const response = await fetch('/workflows/policy-actions?class=' + encodeURIComponent(policyClass))
            const data = await response.json()
            const actionSelect = this.nodeConfigTarget.querySelector('[name="policyAction"]')
            if (actionSelect) {
                let options = '<option value="">Select an action...</option>'
                data.policyActions.forEach(a => {
                    const selected = a.action === selectedAction ? 'selected' : ''
                    options += `<option value="${a.action}" ${selected}>${a.label}</option>`
                })
                actionSelect.innerHTML = options
            }
        } catch (error) {
            console.error('Failed to load policy actions:', error)
        }
    }

    async _loadAppSettingsForPicker(selectEl, selectedKey) {
        if (!selectEl) return

        try {
            const response = await fetch('/workflows/app-settings', {
                headers: { 'Accept': 'application/json' }
            })
            if (!response.ok) throw new Error(`HTTP ${response.status}`)
            const data = await response.json()
            let options = '<option value="">Select a setting...</option>'
            const items = Array.isArray(data) ? data : (data.appSettings || data.settings || [])
            items.forEach(s => {
                const key = s.name || s.value || ''
                const label = s.name || key
                const selected = key === selectedKey ? 'selected' : ''
                options += `<option value="${key}" ${selected}>${label}</option>`
            })
            selectEl.innerHTML = options
        } catch (error) {
            console.error('Failed to load app settings:', error)
            if (!selectedKey) {
                selectEl.innerHTML = '<option value="">Settings unavailable</option>'
            } else {
                selectEl.innerHTML =
                    `<option value="">Settings unavailable</option>` +
                    `<option value="${selectedKey}" selected>${selectedKey}</option>`
            }
        }
    }

    updateNodeConfig(event) {
        const form = event.target.closest('form')
        const nodeId = form.dataset.nodeId
        const formData = new FormData(form)

        const nodeData = this.editor.getNodeFromId(nodeId)
        if (!nodeData.data) nodeData.data = {}
        if (!nodeData.data.config) nodeData.data.config = {}

        const newParams = {}
        let hasParams = false
        const newApproverConfig = {}
        let hasApproverConfig = false

        const vpTypeSelects = form.querySelectorAll('[data-vp-type]')
        const vpFields = new Set()
        vpTypeSelects.forEach(select => vpFields.add(select.dataset.vpType))

        for (const [key, value] of formData.entries()) {
            if (vpFields.has(key)) continue
            if (key.startsWith('params.') && vpFields.has(key)) continue
            if (key.startsWith('approverConfig.') && vpFields.has(key)) continue
            // Skip structured editor sub-fields — they are handled by dedicated extractors.
            if (
                key.includes('__key__') ||
                key.includes('__val__') ||
                key.includes('__option_value__') ||
                key.includes('__option_label__')
            ) continue

            if (key.startsWith('params.')) {
                const paramKey = key.substring(7)
                newParams[paramKey] = value
                hasParams = true
            } else if (key.startsWith('approverConfig.')) {
                const acKey = key.substring(15)
                newApproverConfig[acKey] = value
                hasApproverConfig = true
            } else if (key === 'label') {
                nodeData.data.label = value
                nodeData.data.config._nodeLabel = value
            } else {
                nodeData.data.config[key] = value
            }
        }

        vpTypeSelects.forEach(select => {
            const fieldName = select.dataset.vpType
            const selectedType = select.value
            const container = select.closest('.value-picker')
            const input = container.querySelector(`[name="${fieldName}"]`)
            const rawValue = input?.value ?? ''

            let composedValue
            if (selectedType === 'fixed') {
                const isNumber = input?.type === 'number'
                composedValue = isNumber ? (rawValue === '' ? '' : Number(rawValue)) : rawValue
            } else if (selectedType === 'context') {
                composedValue = rawValue ? { type: 'context', path: rawValue } : ''
            } else if (selectedType === 'app_setting') {
                composedValue = rawValue ? { type: 'app_setting', key: rawValue } : ''
            }

            if (fieldName.startsWith('params.')) {
                const paramKey = fieldName.substring(7)
                newParams[paramKey] = composedValue
                hasParams = true
            } else if (fieldName.startsWith('approverConfig.')) {
                const acKey = fieldName.substring(15)
                newApproverConfig[acKey] = composedValue
                hasApproverConfig = true
            } else {
                nodeData.data.config[fieldName] = composedValue
            }
        })

        if (hasParams) {
            nodeData.data.config.params = newParams
        }
        if (hasApproverConfig) {
            nodeData.data.config.approverConfig = newApproverConfig
        }

        // Extract key-value editor fields (e.g., vars for Core.SendEmail)
        this._extractKvFields(form, nodeData)
        this._extractArrayFields(form, nodeData)

        nodeData.data.config.allowParallel = form.querySelector('[name="allowParallel"]')?.checked ?? true
        nodeData.data.config.serialPickNext = form.querySelector('[name="serialPickNext"]')?.checked ?? false

        this.editor.updateNodeDataFromId(nodeId, nodeData.data)
        this.designer._updateDirtyState?.()
        this._refreshTemplateAnalysis(form, nodeId)

        const changedField = event.target?.name
        if (changedField === 'label') {
            this._refreshNodeHtml(nodeId, nodeData)
        }
        const shouldRerenderConfig = [
            'action',
            'condition',
            'event',
            'params.entityType',
            'params.name',
        ].includes(changedField)
        if (shouldRerenderConfig) {
            const updatedNode = this.editor.getNodeFromId(nodeId)
            if (this.hasNodeConfigTarget && this.configPanel) {
                const variableInfo = this._buildVariableInfo(nodeId, updatedNode)
                this.nodeConfigTarget.innerHTML = this._resizeHandleHTML + this.configPanel.renderConfigHTML(nodeId, updatedNode, variableInfo)
                if (this.variablePicker) {
                    this.variablePicker.attachPickers(this.nodeConfigTarget, nodeId, this.editor)
                }
            }
        } else if (this.variablePicker && this.hasNodeConfigTarget) {
            this.variablePicker.attachPickers(this.nodeConfigTarget, nodeId, this.editor)
        }
    }

    _refreshNodeHtml(nodeId, nodeData) {
        if (!this.designer._serializer || !this.designer.canvasTarget) return

        const type = nodeData.data?.type || 'unknown'
        const nodeKey = nodeData.data?.nodeKey || nodeData.name
        const config = {
            ...(nodeData.data?.config || {}),
            _nodeLabel: nodeData.data?.label || '',
        }
        const html = this.designer._serializer.buildNodeHTML(type, nodeKey, config)
        const nodeEl = this.designer.canvasTarget.querySelector(`#node-${nodeId}`)
        const contentEl = nodeEl?.querySelector('.drawflow_content_node')
        if (contentEl) {
            contentEl.innerHTML = html
        }
    }

    // --- Config Panel Resize ---

    get _resizeHandleHTML() {
        return '<div class="config-panel-resize-handle" data-action="mousedown->workflow-designer#onResizeStart"></div>'
    }

    restoreConfigPanelWidth() {
        const saved = localStorage.getItem('wf-config-panel-width')
        if (saved && this.hasNodeConfigTarget) {
            const width = parseInt(saved, 10)
            if (width >= 300 && width <= window.innerWidth * 0.6) {
                this.nodeConfigTarget.style.width = width + 'px'
                this.nodeConfigTarget.style.minWidth = width + 'px'
            }
        }
    }

    onResizeStart(event) {
        event.preventDefault()
        this._resizeStartX = event.clientX
        this._resizeStartWidth = this.nodeConfigTarget.getBoundingClientRect().width
        const handle = event.currentTarget
        handle.classList.add('dragging')

        this._boundResizeMove = this._onResizeMove.bind(this)
        this._boundResizeEnd = this._onResizeEnd.bind(this, handle)
        document.addEventListener('mousemove', this._boundResizeMove)
        document.addEventListener('mouseup', this._boundResizeEnd)
    }

    _onResizeMove(event) {
        const delta = this._resizeStartX - event.clientX
        const maxWidth = window.innerWidth * 0.6
        let newWidth = Math.max(300, Math.min(maxWidth, this._resizeStartWidth + delta))
        this.nodeConfigTarget.style.width = newWidth + 'px'
        this.nodeConfigTarget.style.minWidth = newWidth + 'px'
    }

    _onResizeEnd(handle) {
        handle.classList.remove('dragging')
        document.removeEventListener('mousemove', this._boundResizeMove)
        document.removeEventListener('mouseup', this._boundResizeEnd)
        const currentWidth = Math.round(this.nodeConfigTarget.getBoundingClientRect().width)
        localStorage.setItem('wf-config-panel-width', currentWidth)
    }

    // --- Email Template Select methods ---

    /**
     * Fetch active email templates from the API and populate any
     * email-template-select dropdowns in the config panel.
     * @param {object} nodeConfig
     * @param {string} nodeId
     */
    _loadEmailTemplateOptions(nodeConfig, nodeId) {
        const selects = this.nodeConfigTarget.querySelectorAll('[data-email-template-select]')
        if (selects.length === 0) return

        // Cache the fetch to avoid re-fetching every node click
        if (!this._emailTemplateOptionsPromise) {
            this._emailTemplateOptionsPromise = fetch('/email-templates/options.json')
                .then(r => r.ok ? r.json() : { options: [] })
                .then(data => data.options || [])
                .catch(() => [])
        }

        const currentTemplateId = String(nodeConfig?.params?.template ?? '')

        this._emailTemplateOptionsPromise.then(options => {
            selects.forEach(select => {
                select.innerHTML = '<option value="">Select a template…</option>'
                options.forEach(opt => {
                    const sel = String(opt.value) === currentTemplateId ? 'selected' : ''
                    // Build accessible option label: prefer name, show slug as secondary
                    let optLabel = opt.label
                    if (opt.slug && opt.label !== opt.slug) {
                        optLabel = `${opt.label} [${opt.slug}]`
                    } else if (!opt.slug && !opt.isWorkflowNative) {
                        // Legacy mailer-backed: keep label as-is (already humanized)
                    }
                    const el = document.createElement('option')
                    el.value = opt.value
                    if (sel) el.selected = true
                    el.textContent = optLabel
                    el.dataset.availableVars = JSON.stringify(opt.availableVars || [])
                    el.dataset.variablesSchema = JSON.stringify(opt.variablesSchema || [])
                    el.dataset.parsedPlaceholders = JSON.stringify(opt.parsedPlaceholders || [])
                    el.dataset.subject = opt.subjectPreview || ''
                    el.dataset.slug = opt.slug || ''
                    el.dataset.isWorkflowNative = opt.isWorkflowNative ? '1' : '0'
                    select.appendChild(el)
                })
                // Show analysis for the currently selected template
                if (currentTemplateId) {
                    this._renderTemplateAnalysis(select, nodeId)
                }
            })
        })
    }

    /**
     * Handle email template dropdown change: save to config and show analysis.
     */
    onEmailTemplateChange(event) {
        const select = event.target
        const nodeId = event.target.closest('form')?.dataset.nodeId
        this._renderTemplateAnalysis(select, nodeId)
        // Trigger normal config save
        this.updateNodeConfig(event)
    }

    /**
     * Render a rich variable-mapping analysis panel below the template dropdown.
     *
     * Shows:
     *  - Template identity (name + slug badge, workflow-native indicator)
     *  - Required vs optional variables from variables_schema
     *  - Whether each is configured in this Send Email node's template-variable mappings
     *  - Parsed subject placeholders not covered by variables_schema
     *
     * @param {HTMLSelectElement} select
     * @param {string|null} nodeId - current node id, used to resolve available vars
     */
    _renderTemplateAnalysis(select, nodeId) {
        const option = select.selectedOptions[0]
        const analysisEl = select.closest('.mb-3')?.querySelector('.email-template-analysis')
        if (!analysisEl) return

        if (!option || !option.value) {
            analysisEl.innerHTML = ''
            return
        }

        // Parse metadata stored on the option
        let variablesSchema = []
        let parsedPlaceholders = []
        let availableVars = []
        try { variablesSchema = JSON.parse(option.dataset.variablesSchema || '[]') } catch { /* ignore */ }
        try { parsedPlaceholders = JSON.parse(option.dataset.parsedPlaceholders || '[]') } catch { /* ignore */ }
        try { availableVars = JSON.parse(option.dataset.availableVars || '[]') } catch { /* ignore */ }

        const slug = option.dataset.slug || ''
        const isWorkflowNative = option.dataset.isWorkflowNative === '1'
        const subjectPreview = option.dataset.subject || ''

        const configuredTemplateVars = this._getConfiguredTemplateVars(nodeId)

        let html = '<div class="border rounded p-2 bg-light">'

        // Identity badge row
        html += '<div class="d-flex align-items-center gap-2 mb-2 flex-wrap">'
        if (isWorkflowNative && slug) {
            html += `<span class="badge bg-primary" title="Workflow-native template"><i class="bi bi-diagram-3 me-1"></i>${this._escHtml(slug)}</span>`
        } else if (slug) {
            html += `<span class="badge bg-secondary" title="Slug">${this._escHtml(slug)}</span>`
        } else {
            html += '<span class="badge bg-warning text-dark" title="Legacy mailer-backed template (no slug)"><i class="bi bi-exclamation-triangle me-1"></i>Legacy</span>'
        }
        if (subjectPreview) {
            html += `<small class="text-muted text-truncate" style="max-width:220px;" title="${this._escHtml(subjectPreview)}">Subject: ${this._escHtml(subjectPreview.substring(0, 60))}${subjectPreview.length > 60 ? '…' : ''}</small>`
        }
        html += '</div>'

        const schemaNames = new Set(variablesSchema.map(e => e.name).filter(Boolean))
        const hasSchema = variablesSchema.length > 0
        const hasPlaceholders = parsedPlaceholders.length > 0

        if (hasSchema) {
            const required = variablesSchema.filter(e => e.required)
            const optional = variablesSchema.filter(e => !e.required)
            let missingRequired = 0

            const renderRow = (entry) => {
                const name = entry.name || ''
                const type = entry.type || 'string'
                const desc = entry.description ? ` — ${entry.description}` : ''
                const req = entry.required
                const mapped = configuredTemplateVars.has(name)
                let statusIcon, statusClass
                if (mapped) {
                    statusIcon = '<i class="bi bi-check-circle-fill text-success"></i>'
                    statusClass = ''
                } else {
                    statusIcon = req
                        ? '<i class="bi bi-x-circle-fill text-danger"></i>'
                        : '<i class="bi bi-dash-circle text-warning"></i>'
                    statusClass = req ? 'text-danger' : 'text-warning'
                    if (req) missingRequired++
                }
                const reqBadge = req ? '<span class="badge bg-danger ms-1" style="font-size:0.65em;">required</span>' : '<span class="badge bg-secondary ms-1" style="font-size:0.65em;">optional</span>'
                return `<li class="d-flex align-items-center gap-1 ${statusClass}" style="font-size:0.8em;">
                    ${statusIcon}
                    <code>${this._escHtml(name)}</code><span class="text-muted">(${this._escHtml(type)})</span>${reqBadge}${desc ? `<span class="text-muted ms-1">${this._escHtml(desc)}</span>` : ''}
                </li>`
            }

            if (required.length > 0) {
                html += '<div class="mb-1"><strong class="small">Required variables</strong>'
                html += '<ul class="list-unstyled mb-0 mt-1">'
                required.forEach(e => { html += renderRow(e) })
                html += '</ul></div>'
            }
            if (optional.length > 0) {
                html += '<div class="mb-1"><strong class="small">Optional variables</strong>'
                html += '<ul class="list-unstyled mb-0 mt-1">'
                optional.forEach(e => { html += renderRow(e) })
                html += '</ul></div>'
            }

            // Summary warning if required vars are unmapped
            if (missingRequired > 0) {
                html += `<div class="alert alert-danger py-1 px-2 mb-0 mt-1" style="font-size:0.8em;" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    ${missingRequired} required template variable${missingRequired > 1 ? 's are' : ' is'} not configured in this Send Email step.
                </div>`
            }

            // Surface any subject placeholders not covered by schema
            const uncoveredPlaceholders = parsedPlaceholders.filter(p => !schemaNames.has(p))
            if (uncoveredPlaceholders.length > 0) {
                html += `<div class="mt-1"><small class="text-muted"><i class="bi bi-info-circle me-1"></i>Subject also uses: ${uncoveredPlaceholders.map(p => `<code>${this._escHtml(p)}</code>`).join(', ')} (not in schema)</small></div>`
            }
        } else if (hasPlaceholders) {
            // No schema, show placeholders from subject as a fallback hint
            html += `<div><small class="text-muted"><i class="bi bi-info-circle me-1"></i>Subject placeholders: ${parsedPlaceholders.map(p => `<code>${this._escHtml(p)}</code>`).join(', ')}</small></div>`
        } else if (availableVars.length > 0) {
            const varNames = availableVars.map(v => typeof v === 'object' ? (v.name || v) : v)
            html += `<small class="text-muted"><i class="bi bi-info-circle me-1"></i>Available vars: <code>${varNames.map(n => this._escHtml(n)).join('</code>, <code>')}</code></small>`
        } else {
            html += '<small class="text-muted"><i class="bi bi-info-circle me-1"></i>No variable schema defined for this template.</small>'
        }

        html += '</div>'
        analysisEl.innerHTML = html
    }

    _getConfiguredTemplateVars(nodeId) {
        const mappedVars = new Set()
        if (!nodeId) return mappedVars

        const nodeData = this.editor.getNodeFromId(nodeId)
        const vars = nodeData?.data?.config?.params?.vars ?? nodeData?.data?.config?.vars ?? {}
        if (!vars || typeof vars !== 'object' || Array.isArray(vars)) {
            return mappedVars
        }

        Object.entries(vars).forEach(([key, value]) => {
            if (!key || value === '' || value === null || typeof value === 'undefined') return
            if (typeof value === 'object' && value !== null) {
                const hasContextPath = value.type === 'context' && Boolean(value.path)
                const hasAppSetting = value.type === 'app_setting' && Boolean(value.key)
                if (!hasContextPath && !hasAppSetting) return
            }
            mappedVars.add(key)
        })

        return mappedVars
    }

    /**
     * HTML-escape a string for safe inline rendering.
     * @param {string} str
     * @returns {string}
     */
    _escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }

    // --- Key-Value Editor methods ---

    addKvRow(event) {
        const btn = event.target.closest('[data-kv-target]')
        const fieldName = btn.dataset.kvTarget
        const container = btn.closest('.kv-editor').querySelector(`[data-kv-rows="${fieldName}"]`)
        const existingRows = container.querySelectorAll('.kv-row')
        const nextIdx = existingRows.length

        const rowHTML = this.configPanel._renderKvRow(fieldName, nextIdx, '', '')
        container.insertAdjacentHTML('beforeend', rowHTML)
        if (this.variablePicker) {
            const nodeId = btn.closest('form')?.dataset.nodeId
            this.variablePicker.attachPickers(container, nodeId, this.editor)
        }
    }

    addArrayRow(event) {
        const btn = event.target.closest('[data-array-target]')
        const fieldName = btn.dataset.arrayTarget
        const container = btn.closest('.option-array-editor').querySelector(`[data-array-rows="${fieldName}"]`)
        const existingIndexes = [...container.querySelectorAll('.option-array-row')]
            .map(row => Number(row.dataset.arrayIdx))
            .filter(Number.isFinite)
        const nextIdx = existingIndexes.length > 0 ? Math.max(...existingIndexes) + 1 : 0

        const rowHTML = this.configPanel._renderOptionArrayRow(fieldName, nextIdx)
        container.insertAdjacentHTML('beforeend', rowHTML)
    }

    removeKvRow(event) {
        const row = event.target.closest('.kv-row')
        const form = row.closest('form')
        row.remove()
        // Re-trigger config save after removal
        this._saveStructuredFieldsFromForm(form)
    }

    removeArrayRow(event) {
        const row = event.target.closest('.option-array-row')
        const form = row.closest('form')
        row.remove()
        this._saveStructuredFieldsFromForm(form)
    }

    onKvValueTypeChange(event) {
        const select = event.target
        const selectedType = select.value
        const row = select.closest('.kv-row')
        const valInput = row.querySelector('[name*="__val__"]')
        if (valInput) {
            valInput.placeholder = selectedType === 'context' ? 'Choose a workflow variable' : 'Value'
            valInput.value = ''
            if (selectedType === 'context') {
                valInput.setAttribute('data-variable-picker', 'true')
            } else {
                this.variablePicker?.removeSelectedVariableHint?.(valInput)
                valInput.removeAttribute('data-variable-picker')
                valInput.closest('.wf-var-picker-wrapper')?.replaceWith(valInput)
            }
        }
        if (this.variablePicker) {
            const nodeId = select.closest('form')?.dataset.nodeId
            this.variablePicker.attachPickers(row, nodeId, this.editor)
        }
        // Trigger config update
        const form = select.closest('form')
        if (form) this._saveStructuredFieldsFromForm(form)
    }

    _saveStructuredFieldsFromForm(form) {
        const nodeId = form.dataset.nodeId
        const nodeData = this.editor.getNodeFromId(nodeId)
        if (!nodeData?.data?.config) return

        this._extractKvFields(form, nodeData)
        this._extractArrayFields(form, nodeData)
        this.editor.updateNodeDataFromId(nodeId, nodeData.data)
        this._refreshTemplateAnalysis(form, nodeId)
    }

    _extractKvFields(form, nodeData) {
        const kvEditors = form.querySelectorAll('.kv-editor')
        kvEditors.forEach(editor => {
            const fieldName = editor.querySelector('[data-kv-rows]')?.dataset.kvRows
            if (!fieldName) return

            const obj = {}
            const rows = editor.querySelectorAll('.kv-row')
            rows.forEach(row => {
                const keyInput = row.querySelector('[name*="__key__"]')
                const valInput = row.querySelector('[name*="__val__"]')
                const typeSelect = row.querySelector('[data-kv-vtype]')
                const key = keyInput?.value?.trim()
                if (!key) return

                const rawVal = valInput?.value ?? ''
                const valType = typeSelect?.value ?? 'fixed'

                if (valType === 'context') {
                    obj[key] = rawVal.startsWith('$.') ? rawVal : `$.${rawVal}`
                } else if (valType === 'app_setting') {
                    obj[key] = rawVal ? { type: 'app_setting', key: rawVal } : ''
                } else {
                    obj[key] = rawVal
                }
            })

            // Write into the correct nested location (e.g., params.vars)
            if (fieldName.startsWith('params.')) {
                const paramKey = fieldName.substring(7)
                if (!nodeData.data.config.params) nodeData.data.config.params = {}
                nodeData.data.config.params[paramKey] = obj
            } else {
                nodeData.data.config[fieldName] = obj
            }
        })
    }

    _extractArrayFields(form, nodeData) {
        const arrayEditors = form.querySelectorAll('.option-array-editor')
        arrayEditors.forEach(editor => {
            const fieldName = editor.querySelector('[data-array-rows]')?.dataset.arrayRows
            if (!fieldName) return

            const options = []
            const rows = editor.querySelectorAll('.option-array-row')
            rows.forEach(row => {
                const valueInput = row.querySelector('[name*="__option_value__"]')
                const labelInput = row.querySelector('[name*="__option_label__"]')
                const value = valueInput?.value?.trim()
                const label = labelInput?.value?.trim()
                if (!value && !label) return

                options.push({
                    value: value || label,
                    label: label || value,
                })
            })

            if (fieldName.startsWith('params.')) {
                const paramKey = fieldName.substring(7)
                if (!nodeData.data.config.params) nodeData.data.config.params = {}
                nodeData.data.config.params[paramKey] = options
            } else {
                nodeData.data.config[fieldName] = options
            }
        })
    }

    _refreshTemplateAnalysis(form, nodeId) {
        const templateSelect = form.querySelector('[data-email-template-select="true"]')
        if (templateSelect) {
            this._renderTemplateAnalysis(templateSelect, nodeId)
        }
    }

    disconnect() {
        if (this._boundResizeMove) {
            document.removeEventListener('mousemove', this._boundResizeMove)
        }
        if (this._boundResizeEnd) {
            document.removeEventListener('mouseup', this._boundResizeEnd)
        }
    }
}
