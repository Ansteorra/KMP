import { renderAutoComplete } from '../autocomplete-helper.js'

/**
 * WorkflowConfigPanel
 *
 * Renders per-node-type configuration forms inside the side panel.
 */
export default class WorkflowConfigPanel {
    /**
     * @param {object} registryData - { triggers, actions, conditions, entities, resolvers }
     */
    constructor(registryData, policyClasses) {
        this.registryData = registryData
        this.policyClasses = policyClasses || []
        this.resolvers = registryData.resolvers || []
    }

    /**
     * Render the full config panel HTML for a selected node.
     * @param {string} nodeId
     * @param {object} nodeData
     * @param {object} [variableInfo] - { available: [], produced: [] }
     */
    renderConfigHTML(nodeId, nodeData, variableInfo) {
        const type = nodeData.data?.type || 'unknown'
        const typeLabels = {
            trigger: 'Trigger', action: 'Action', condition: 'Condition',
            approval: 'Approval Gate', fork: 'Parallel Fork', join: 'Parallel Join',
            loop: 'Loop', forEach: 'For Each', delay: 'Delay', subworkflow: 'Sub-workflow', end: 'End'
        }

        const configTabId = `cfg-tab-${nodeId}`
        const varsTabId = `vars-tab-${nodeId}`
        const hasTabs = variableInfo && (variableInfo.available?.length || variableInfo.produced?.length)

        let html = `<div class="config-panel-header">
                <h6><i class="bi bi-sliders me-1"></i>${typeLabels[type] || type} Configuration</h6>
            </div>`

        if (hasTabs) {
            html += `<ul class="nav nav-tabs nav-fill small" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-1 px-2" id="${configTabId}-btn" data-bs-toggle="tab"
                        data-bs-target="#${configTabId}" type="button" role="tab"
                        aria-controls="${configTabId}" aria-selected="true">
                        <i class="bi bi-gear me-1"></i>Config
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-2" id="${varsTabId}-btn" data-bs-toggle="tab"
                        data-bs-target="#${varsTabId}" type="button" role="tab"
                        aria-controls="${varsTabId}" aria-selected="false">
                        <i class="bi bi-code-slash me-1"></i>Variables
                    </button>
                </li>
            </ul>`
        }

        // Config tab content
        const activeClass = 'show active'
        html += hasTabs ? `<div class="tab-content"><div class="tab-pane fade ${activeClass}" id="${configTabId}" role="tabpanel">` : ''
        html += `<div class="config-panel-body" aria-live="polite">
                <form data-node-id="${nodeId}">
                    <div class="mb-3">
                        <label class="form-label" for="workflow-node-label-${this._escapeAttr(nodeId)}">Label</label>
                        <input type="text" class="form-control form-control-sm" id="workflow-node-label-${this._escapeAttr(nodeId)}" name="label" value="${this._escapeAttr(nodeData.data?.label || nodeData.data?.config?._nodeLabel || nodeData.name || '')}"
                            data-action="change->workflow-designer#updateNodeConfig">
                    </div>`
        html += this.getTypeSpecificHTML(type, nodeData.data?.config || {})
        html += `</form></div>`
        html += hasTabs ? `</div>` : ''

        // Variables tab content
        if (hasTabs) {
            html += `<div class="tab-pane fade" id="${varsTabId}" role="tabpanel">`
            html += this._variableCatalogHTML(variableInfo)
            html += `</div></div>`
        }

        return html
    }

    /**
     * Return the empty-state HTML for the config panel.
     */
    renderEmptyHTML() {
        return `
            <div class="config-panel-header">
                <h6><i class="bi bi-sliders me-1"></i>Configuration</h6>
            </div>
            <div class="config-panel-empty">
                <i class="bi bi-hand-index"></i>
                <p>Select a node on the canvas to configure it</p>
            </div>`
    }

    /**
     * Render the variable catalog tab content.
     * @param {{ available: Array, produced: Array }} variableInfo
     */
    _variableCatalogHTML(variableInfo) {
        const { available = [], produced = [] } = variableInfo
        let html = '<div class="p-2">'

        if (produced.length) {
            html += '<h6 class="small text-muted mb-2"><i class="bi bi-box-arrow-right me-1"></i>Produced by this Node</h6>'
            html += this._variableListHTML(produced)
        }

        if (available.length) {
            if (produced.length) html += '<hr class="my-2">'
            html += '<h6 class="small text-muted mb-2"><i class="bi bi-box-arrow-in-right me-1"></i>Available Variables</h6>'
            html += '<input type="text" class="form-control form-control-sm mb-2" placeholder="Filter variables..." data-action="input->workflow-designer#filterVariableCatalog">'
            html += this._variableListHTML(available, true)
        }

        if (!produced.length && !available.length) {
            html += '<p class="text-muted small">No variables available for this node.</p>'
        }

        html += '</div>'
        return html
    }

    /**
     * Render a list of variables as copyable badges.
     * @param {Array<{path:string, label:string, type:string}>} vars
     * @param {boolean} filterable - add data-var-item for filtering
     */
    _variableListHTML(vars, filterable = false) {
        let html = '<div class="list-group list-group-flush">'
        for (const v of vars) {
            const filterAttr = filterable ? ` data-var-item data-var-path="${this._escapeAttr(v.path)}" data-var-label="${this._escapeAttr(v.label)}"` : ''
            html += `<div class="list-group-item px-0 py-1 border-0"${filterAttr}>
                <div class="d-flex justify-content-between align-items-start">
                    <span class="small">${v.label} <span class="text-muted">(${v.type})</span></span>
                </div>
                <code class="small text-primary user-select-all d-block">${v.path}</code>
            </div>`
        }
        html += '</div>'
        return html
    }

    getTypeSpecificHTML(type, config) {
        switch (type) {
            case 'trigger': return this._triggerHTML(config)
            case 'action': return this._actionHTML(config)
            case 'condition': return this._conditionHTML(config)
            case 'approval': return this._approvalHTML(config)
            case 'delay': return this._delayHTML(config)
            case 'loop': return this._loopHTML(config)
            case 'forEach': return this._forEachHTML(config)
            case 'subworkflow': return this._subworkflowHTML(config)
            case 'fork': return this._forkHTML(config)
            case 'join': return this._joinHTML(config)
            case 'end': return this._endHTML(config)
            default: return ''
        }
    }

    _triggerHTML(config) {
        let options = '<option value="">Select a trigger...</option>'
        this.registryData.triggers?.forEach(t => {
            const selected = config.event === t.event ? 'selected' : ''
            options += `<option value="${t.event}" ${selected}>${t.label}</option>`
        })
        let html = `<div class="mb-3">
            <label class="form-label">Trigger Event</label>
            <select class="form-select form-select-sm" name="event" data-action="change->workflow-designer#updateNodeConfig">${options}</select>
        </div>`

        if (config.event) {
            const trigger = this.registryData.triggers?.find(t => t.event === config.event)
            if (trigger?.payloadSchema) {
                html += '<h6 class="mt-3 mb-2 text-muted small">Trigger Variables</h6>'
                html += '<small class="form-text text-muted d-block mb-2">Available as <code>$.trigger.*</code> in downstream nodes</small>'
                html += '<div class="list-group list-group-flush">'
                for (const [key, meta] of Object.entries(trigger.payloadSchema)) {
                    html += `<div class="list-group-item px-0 py-1 border-0 d-flex justify-content-between align-items-center">
                        <span class="small">${meta.label || key} <span class="text-muted">(${meta.type})</span></span>
                        <code class="small text-primary user-select-all">$.trigger.${key}</code>
                    </div>`
                }
                html += '</div>'
            }
        }

        return html
    }

    _actionHTML(config) {
        let options = '<option value="">Select an action...</option>'
        this.registryData.actions?.forEach(a => {
            const selected = config.action === a.action ? 'selected' : ''
            options += `<option value="${a.action}" ${selected}>${a.label}</option>`
        })

        let html = `<div class="mb-3">
            <label class="form-label">Action</label>
            <select class="form-select form-select-sm" name="action"
                data-action="change->workflow-designer#updateNodeConfig">${options}</select>
        </div>`

        if (config.action) {
            const action = this.registryData.actions?.find(a => a.action === config.action)
            if (action?.inputSchema) {
                html += '<h6 class="mt-3 mb-2 text-muted small">Input Parameters</h6>'
                const params = config.params || {}
                for (const [key, meta] of Object.entries(action.inputSchema)) {
                    if (this._isSchemaFieldHidden(meta)) continue

                    const currentVal = params[key] !== undefined ? params[key] : ''
                    const desc = meta.description ? `<small class="form-text text-muted">${meta.description}</small>` : ''
                    if (meta.type === 'emailTemplate') {
                        html += this._renderEmailTemplateSelect(`params.${key}`, meta, currentVal)
                        html += desc
                    } else if (meta.type === 'object') {
                        html += this._renderKeyValueEditor(`params.${key}`, meta, currentVal)
                        html += desc
                    } else if (meta.type === 'array' && meta.editor === 'options') {
                        html += this._renderOptionArrayEditor(`params.${key}`, meta, currentVal)
                    } else {
                        html += this.renderValuePicker(`params.${key}`, {
                            label: meta.label || key,
                            type: meta.type || 'string',
                            required: meta.required || false,
                            description: meta.description || ''
                        }, currentVal, {allowContext: true, allowAppSetting: true})
                        html += desc
                    }
                }
            }
        }

        return html
    }

    _conditionHTML(config) {
        let options = '<option value="">Select a condition...</option>'
        options += '<optgroup label="Built-in">'
        options += '<option value="Core.FieldEquals">Field Equals Value</option>'
        options += '<option value="Core.FieldNotEmpty">Field Is Not Empty</option>'
        options += '<option value="Core.Expression">Custom Expression</option>'
        options += '</optgroup>'
        if (this.registryData.conditions?.length > 0) {
            options += '<optgroup label="Plugin Conditions">'
            this.registryData.conditions.forEach(c => {
                const selected = config.condition === c.condition ? 'selected' : ''
                options += `<option value="${c.condition}" ${selected}>${c.label}</option>`
            })
            options += '</optgroup>'
        }
        const isCore = !config.condition || config.condition.startsWith('Core.')
        let html = `<div class="mb-3">
            <label class="form-label">Condition</label>
            <select class="form-select form-select-sm" name="condition" data-action="change->workflow-designer#updateNodeConfig">${options}</select>
        </div>`

        if (isCore) {
            html += `<div class="mb-3">
                <label class="form-label">Field Path</label>
                <input type="text" class="form-control form-control-sm" name="field" value="${config.field || ''}" placeholder="$.entity.field_name" data-action="change->workflow-designer#updateNodeConfig" data-variable-picker="true">
            </div>`
            html += this.renderValuePicker('expectedValue', {
                label: 'Expected Value',
                type: 'string',
                required: false,
                description: 'Value to compare against'
            }, config.expectedValue, {allowContext: true, allowAppSetting: true})
        }

        if (config.condition && !config.condition.startsWith('Core.')) {
            const cond = this.registryData.conditions?.find(c => c.condition === config.condition)
            if (cond?.inputSchema) {
                html += '<h6 class="mt-3 mb-2 text-muted small">Condition Parameters</h6>'
                const params = config.params || {}
                for (const [key, meta] of Object.entries(cond.inputSchema)) {
                    if (this._isSchemaFieldHidden(meta)) continue

                    const currentVal = params[key] !== undefined ? params[key] : (config[key] || '')
                    html += this.renderValuePicker(`params.${key}`, {
                        label: meta.label || key,
                        type: meta.type || 'string',
                        required: meta.required || false,
                        description: meta.description || ''
                    }, currentVal, {allowContext: true, allowAppSetting: true})
                }
            }
        }

        return html
    }

    _approvalHTML(config) {
        const ac = config.approverConfig || {}
        const approverType = config.approverType || 'permission'

        // Resolve the effective approver value: prefer approverValue, fall back to approverConfig
        const typeKeyMap = { permission: 'permission', role: 'role', member: 'member_id' }
        const effectiveValue = config.approverValue
            || ac[typeKeyMap[approverType] || '']
            || ''

        // Detect context variable references (e.g. "$.trigger.approvalPermission")
        const isContextRef = typeof effectiveValue === 'string' && effectiveValue.startsWith('$.')

        // Determine the active mode for the value picker
        const valueMode = isContextRef ? 'context' : 'fixed'

        // Build autocomplete for "fixed" mode
        const acSharedOpts = {
            size: 'sm',
            name: 'approverValue',
            minLength: 2,
            hiddenAttrs: 'data-action="change->workflow-designer#updateNodeConfig"',
        };

        const buildAC = (type, url, allowOther, placeholder) => renderAutoComplete({
            ...acSharedOpts,
            url,
            allowOther,
            value: approverType === type && !isContextRef ? effectiveValue : '',
            placeholder,
            initSelection: approverType === type && effectiveValue && !isContextRef
                ? { value: effectiveValue, text: effectiveValue } : null,
        })

        const permissionAC = buildAC('permission', '/permissions/auto-complete', true, 'Search permissions...')
        const roleAC = buildAC('role', '/roles/auto-complete', true, 'Search roles...')
        const memberAC = buildAC('member', '/members/auto-complete', false, 'Search members...')

        // Context path input for "context" mode
        const contextInput = `<input type="text" class="form-control form-control-sm"
            name="approverValue" value="${this._escapeAttr(isContextRef ? effectiveValue : '')}"
            placeholder="$.trigger.approvalPermission"
            data-action="change->workflow-designer#updateNodeConfig"
            data-variable-picker="true">`

        // Mode toggle dropdown (Fixed Value / Context Variable)
        const modeToggle = `<select class="form-select form-select-sm" data-approver-value-mode
                style="max-width: 140px;"
                data-action="change->workflow-designer#onApproverValueModeChange">
            <option value="fixed" ${valueMode === 'fixed' ? 'selected' : ''}>Fixed Value</option>
            <option value="context" ${valueMode === 'context' ? 'selected' : ''}>Context Variable</option>
        </select>`

        // Build each approver section with the mode toggle
        const approverSection = (type, label, acHtml) => {
            const show = approverType === type ? 'block' : 'none'
            return `<div data-approver-section="${type}" style="display:${show};">
              <div class="mb-3">
                <label class="form-label">${label}</label>
                <div class="input-group input-group-sm mb-1">
                    ${modeToggle}
                </div>
                <div data-approver-value-fixed style="display:${valueMode === 'fixed' ? 'block' : 'none'};">
                    ${acHtml}
                </div>
                <div data-approver-value-context style="display:${valueMode === 'context' ? 'block' : 'none'};">
                    ${contextInput}
                </div>
              </div>
            </div>`
        }

        return `<div class="mb-3">
            <label class="form-label">Approver Type</label>
            <select class="form-select form-select-sm" name="approverType" data-action="change->workflow-designer#onApproverTypeChange">
                <option value="permission" ${approverType === 'permission' ? 'selected' : ''}>By Permission</option>
                <option value="role" ${approverType === 'role' ? 'selected' : ''}>By Role</option>
                <option value="member" ${approverType === 'member' ? 'selected' : ''}>Specific Member</option>
                <option value="dynamic" ${approverType === 'dynamic' ? 'selected' : ''}>Dynamic (from context)</option>
                <option value="policy" ${approverType === 'policy' ? 'selected' : ''}>By Policy</option>
            </select>
        </div>
        ${approverSection('permission', 'Permission', permissionAC)}
        ${approverSection('role', 'Role', roleAC)}
        ${approverSection('member', 'Member', memberAC)}
        <div data-approver-section="dynamic" style="display:${approverType === 'dynamic' ? 'block' : 'none'};">
          ${(() => {
            const ac = config.approverConfig || {};
            const selectedResolver = this.resolvers.find(r => r.resolver === ac.service);
            const schemaKeys = selectedResolver ? Object.keys(selectedResolver.configSchema || {}) : [];
            const internalKeys = ['service', 'method', 'serial_pick_next', 'exclude_member_ids', 'current_approver_id', 'approval_chain'];
            const skipKeys = [...internalKeys, ...schemaKeys];

            // Resolver dropdown
            let resolverHTML = `
              <div class="mb-3">
                <label class="form-label">Resolver Service</label>
                <select class="form-select form-select-sm" name="resolverKey"
                        data-action="change->workflow-designer#onResolverChange">
                    <option value="">Select a resolver...</option>
                    ${this.resolvers.map(r =>
                        `<option value="${this._escapeAttr(r.resolver)}" ${(ac.service === r.resolver) ? 'selected' : ''}>${this._escapeAttr(r.label)} (${this._escapeAttr(r.source)})</option>`
                    ).join('')}
                </select>
              </div>`;

            // Method — read-only, auto-populated from selected resolver
            resolverHTML += `
              <div class="mb-3">
                <label class="form-label text-muted">Method</label>
                <input type="text" class="form-control form-control-sm"
                       name="approverConfig.method" value="${this._escapeAttr(ac.method || '')}" readonly disabled>
              </div>`;

            // Config params from schema
            if (selectedResolver && selectedResolver.configSchema) {
                for (const [key, schema] of Object.entries(selectedResolver.configSchema)) {
                    resolverHTML += this.renderValuePicker('approverConfig.' + key, {
                        label: schema.label || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                        type: schema.type || 'string',
                        required: schema.required || false,
                        description: schema.description || ''
                    }, ac[key] || '', {allowContext: true, allowAppSetting: false});
                }
            }



            return resolverHTML;
          })()}
        </div>
        <div data-approver-section="policy" style="display:${approverType === 'policy' ? 'block' : 'none'};">
          <div class="mb-3">
            <label class="form-label">Policy Class</label>
            <select class="form-select form-select-sm" name="policyClass"
                    data-action="change->workflow-designer#onPolicyClassChange">
              <option value="">Select a policy class...</option>
              ${this.policyClasses.map(p =>
                `<option value="${p.class}" ${config.policyClass === p.class ? 'selected' : ''}>${p.label}</option>`
              ).join('')}
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Policy Action</label>
            <select class="form-select form-select-sm" name="policyAction"
                    data-action="change->workflow-designer#updateNodeConfig">
              <option value="">Select a policy class first...</option>
              ${config.policyAction ? `<option value="${config.policyAction}" selected>${config.policyAction}</option>` : ''}
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Entity Table</label>
            <input type="text" class="form-control form-control-sm" name="entityTable"
                   value="${config.entityTable || ''}" placeholder="e.g. WarrantRosters"
                   data-action="change->workflow-designer#updateNodeConfig">
          </div>
          <div class="mb-3">
            <label class="form-label">Entity ID Key</label>
            <input type="text" class="form-control form-control-sm" name="entityIdKey"
                   value="${config.entityIdKey || ''}" placeholder="e.g. trigger.rosterId"
                   data-action="change->workflow-designer#updateNodeConfig" data-variable-picker="true">
          </div>
          <div class="mb-3">
            <label class="form-label">Permission Label</label>
            <input type="text" class="form-control form-control-sm" name="permission"
                   value="${config.permission || ''}" placeholder="e.g. Can Approve Warrant Rosters"
                   data-action="change->workflow-designer#updateNodeConfig">
          </div>
        </div>
        ${this.renderValuePicker('requiredCount', {
            label: 'Required Approvals',
            type: 'integer',
            required: true,
            description: 'Number of approvals needed'
        }, config.requiredCount, {allowContext: true, allowAppSetting: true})}
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="allowParallel" id="allowParallel" ${config.allowParallel !== false && !config.serialPickNext ? 'checked' : ''} ${config.serialPickNext ? 'disabled' : ''} data-action="change->workflow-designer#updateNodeConfig">
            <label class="form-check-label" for="allowParallel">Allow Parallel Approvals</label>
        </div>
        <div data-approver-section="dynamic" style="display:${approverType === 'dynamic' ? 'block' : 'none'};">
          <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" name="serialPickNext" id="serialPickNext" ${config.serialPickNext ? 'checked' : ''} data-action="change->workflow-designer#onSerialPickNextChange">
            <label class="form-check-label" for="serialPickNext">
              Serial Pick Next Approver
              <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="Each approver picks the next approver from the eligible pool. Approvals happen one at a time in sequence."></i>
            </label>
          </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Deadline</label>
            <input type="text" class="form-control form-control-sm" name="deadline" value="${config.deadline || ''}" placeholder="e.g. 7d, 24h" data-action="change->workflow-designer#updateNodeConfig">
        </div>`
    }

    _delayHTML(config) {
        return this.renderValuePicker('duration', {
            label: 'Duration',
            type: 'string',
            required: false,
            description: 'e.g. 1h, 2d, 30m'
        }, config.duration, {allowContext: true, allowAppSetting: true}) +
        `<div class="mb-3">
            <label class="form-label">Wait For Event (optional)</label>
            <input type="text" class="form-control form-control-sm" name="waitEvent"
                value="${config.waitEvent || ''}" placeholder="Event to resume on"
                data-action="change->workflow-designer#updateNodeConfig" data-variable-picker="true">
        </div>`
    }

    _loopHTML(config) {
        return this.renderValuePicker('maxIterations', {
            label: 'Max Iterations',
            type: 'integer',
            required: false,
            description: 'Maximum loop iterations'
        }, config.maxIterations !== undefined ? config.maxIterations : 100, {allowContext: true, allowAppSetting: true}) +
        `<div class="mb-3">
            <label class="form-label">Exit Condition</label>
            <input type="text" class="form-control form-control-sm" name="exitCondition" value="${config.exitCondition || ''}" placeholder="Expression to evaluate" data-action="change->workflow-designer#updateNodeConfig" data-variable-picker="true">
        </div>`
    }

    _forEachHTML(config) {
        return `<div class="mb-3">
            <label class="form-label">Collection Path</label>
            <input type="text" class="form-control form-control-sm" name="collection"
                value="${config.collection || ''}" placeholder="e.g. $.roster.warrants"
                data-action="change->workflow-designer#updateNodeConfig" data-variable-picker="true">
            <small class="form-text text-muted">Context path to the array to iterate over</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Item Variable</label>
            <input type="text" class="form-control form-control-sm" name="itemVariable"
                value="${config.itemVariable || 'currentItem'}" placeholder="currentItem"
                data-action="change->workflow-designer#updateNodeConfig">
            <small class="form-text text-muted">Context variable name for the current item</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Index Variable</label>
            <input type="text" class="form-control form-control-sm" name="indexVariable"
                value="${config.indexVariable || 'currentIndex'}" placeholder="currentIndex"
                data-action="change->workflow-designer#updateNodeConfig">
            <small class="form-text text-muted">Context variable name for the current index</small>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="continueOnError"
                id="forEach-continueOnError" ${config.continueOnError ? 'checked' : ''}
                data-action="change->workflow-designer#updateNodeConfig">
            <label class="form-check-label" for="forEach-continueOnError">Continue on Error</label>
            <div><small class="form-text text-muted">If checked, errors are logged but processing continues</small></div>
        </div>`
    }

    _subworkflowHTML(config) {
        return `<div class="mb-3">
            <label class="form-label">Workflow Slug</label>
            <input type="text" class="form-control form-control-sm" name="workflowSlug"
                value="${config.workflowSlug || ''}" placeholder="e.g. warrant-approval"
                data-action="change->workflow-designer#updateNodeConfig">
            <small class="form-text text-muted">The slug of the child workflow to execute</small>
        </div>`
    }

    _forkHTML(config) {
        return `<div class="mb-3">
            <small class="form-text text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Executes all connected output paths in parallel. No additional configuration needed.
            </small>
        </div>`
    }

    _joinHTML(config) {
        return `<div class="mb-3">
            <small class="form-text text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Waits for all incoming paths to complete before advancing.
            </small>
        </div>`
    }

    _endHTML(config) {
        let options = ''
        const statuses = ['completed', 'cancelled', 'failed']
        statuses.forEach(s => {
            const selected = config.status === s ? 'selected' : ''
            options += `<option value="${s}" ${selected}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`
        })
        return `<div class="mb-3">
            <label class="form-label">End Status</label>
            <select class="form-select form-select-sm" name="status"
                data-action="change->workflow-designer#updateNodeConfig">
                ${options}
            </select>
        </div>`
    }

    /**
     * Render a universal value picker for any parameter field.
     * @param {string} fieldName - Config key (e.g., 'requiredCount', 'params.memberId', 'duration')
     * @param {object} fieldMeta - {label, type ('string'|'integer'|'boolean'), required, description}
     * @param {*} currentValue - Current value (scalar, $.path string, or {type,value|path|key} object)
     * @param {object} options - {allowContext: true, allowAppSetting: true}
     * @returns {string} HTML string
     */
    renderValuePicker(fieldName, fieldMeta, currentValue, options = {}) {
        const allowContext = options.allowContext !== false
        const allowAppSetting = options.allowAppSetting !== false

        // Parse currentValue to determine active type and value
        let activeType = 'fixed'
        let fixedValue = ''
        let contextPath = ''
        let settingKey = ''

        if (currentValue === null || currentValue === undefined) {
            activeType = 'fixed'
            fixedValue = ''
        } else if (typeof currentValue === 'object' && currentValue !== null && currentValue.type) {
            activeType = currentValue.type
            if (activeType === 'context') contextPath = currentValue.path || ''
            else if (activeType === 'app_setting') settingKey = currentValue.key || ''
            else if (activeType === 'fixed') fixedValue = currentValue.value ?? ''
        } else if (typeof currentValue === 'string' && currentValue.startsWith('$.')) {
            activeType = 'context'
            contextPath = currentValue
        } else {
            activeType = 'fixed'
            fixedValue = currentValue
        }

        const label = fieldMeta.label || fieldName
        const requiredMark = fieldMeta.required ? ' <span class="text-danger">*</span>' : ''
        const escapedFieldName = this._escapeAttr(fieldName)

        // Type dropdown options
        let typeOptions = `<option value="fixed" ${activeType === 'fixed' ? 'selected' : ''}>Fixed Value</option>`
        if (allowContext) {
            typeOptions += `<option value="context" ${activeType === 'context' ? 'selected' : ''}>Context Path</option>`
        }
        if (allowAppSetting) {
            typeOptions += `<option value="app_setting" ${activeType === 'app_setting' ? 'selected' : ''}>App Setting</option>`
        }

        // Build the dynamic input based on active type
        const inputHTML = this._renderValuePickerInput(fieldName, fieldMeta, activeType, {
            fixedValue, contextPath, settingKey
        })

        return `<div class="mb-2 value-picker" data-vp-field="${escapedFieldName}">
            <label class="form-label form-label-sm mb-0">${label}${requiredMark}</label>
            <div class="input-group input-group-sm mb-1">
                <select class="form-select form-select-sm" data-vp-type="${escapedFieldName}"
                    data-action="change->workflow-designer#onValuePickerTypeChange"
                    style="max-width: 140px;">
                    ${typeOptions}
                </select>
                ${inputHTML}
            </div>
        </div>`
    }

    _renderValuePickerInput(fieldName, fieldMeta, activeType, values) {
        const escapedFieldName = this._escapeAttr(fieldName)
        const dataType = fieldMeta.type || 'string'

        if (activeType === 'context') {
            return `<input type="text" class="form-control form-control-sm"
                name="${escapedFieldName}" value="${this._escapeAttr(values.contextPath)}"
                placeholder="$.path.to.value"
                data-action="change->workflow-designer#updateNodeConfig"
                data-variable-picker="true">`
        }

        if (activeType === 'app_setting') {
            const keyEsc = this._escapeAttr(values.settingKey)
            return `<select class="form-select form-select-sm" name="${escapedFieldName}"
                data-action="change->workflow-designer#updateNodeConfig"
                data-vp-settings-select="${escapedFieldName}">
                <option value="">Loading settings...</option>
                ${values.settingKey ? `<option value="${keyEsc}" selected>${keyEsc}</option>` : ''}
            </select>`
        }

        // Fixed value input
        if (dataType === 'integer') {
            return `<input type="number" class="form-control form-control-sm"
                name="${escapedFieldName}" value="${this._escapeAttr(String(values.fixedValue))}"
                data-action="change->workflow-designer#updateNodeConfig">`
        }

        if (dataType === 'boolean') {
            const checked = values.fixedValue ? 'checked' : ''
            return `<div class="form-check ms-2 mt-1">
                <input type="checkbox" class="form-check-input" name="${escapedFieldName}"
                    ${checked}
                    data-action="change->workflow-designer#updateNodeConfig">
            </div>`
        }

        if (dataType === 'entity') {
            const selectedValue = String(values.fixedValue || '')
            let options = '<option value="">Select an object/table...</option>'
            this.registryData.entities?.forEach(entity => {
                const value = entity.entityType || ''
                const selected = selectedValue === value ? 'selected' : ''
                const label = entity.label ? `${entity.label} (${value})` : value
                options += `<option value="${this._escapeAttr(value)}" ${selected}>${this._escapeAttr(label)}</option>`
            })

            return `<select class="form-select form-select-sm" name="${escapedFieldName}"
                data-action="change->workflow-designer#updateNodeConfig">
                ${options}
            </select>`
        }

        // Default: string text input
        return `<input type="text" class="form-control form-control-sm"
            name="${escapedFieldName}" value="${this._escapeAttr(String(values.fixedValue))}"
            data-action="change->workflow-designer#updateNodeConfig">`
    }

    /**
     * Render a dropdown for selecting a workflow-native email template.
     * Options are loaded async from /email-templates/options.json.
     * When a template is selected, its variable schema and placeholders are
     * compared against available workflow context variables and surfaced below.
     */
    _renderEmailTemplateSelect(fieldName, fieldMeta, currentValue) {
        const label = fieldMeta.label || fieldName
        const escaped = this._escapeAttr(fieldName)
        const currentId = currentValue ? String(currentValue) : ''

        return `<div class="mb-3">
            <label class="form-label form-label-sm">${label}${fieldMeta.required ? ' <span class="text-danger">*</span>' : ''}</label>
            <select class="form-select form-select-sm" name="${escaped}"
                data-action="change->workflow-designer#onEmailTemplateChange"
                data-email-template-select="true">
                <option value="">Loading templates…</option>
                ${currentId ? `<option value="${this._escapeAttr(currentId)}" selected>Template #${this._escapeAttr(currentId)}</option>` : ''}
            </select>
            <div class="email-template-analysis mt-2" data-template-hint="${escaped}"></div>
        </div>`
    }

    /**
     * Render a key-value dictionary editor for object-type fields.
     * Each row has a variable name input and a value picker (fixed/context/app_setting).
     */
    _renderKeyValueEditor(fieldName, fieldMeta, currentValue) {
        const label = fieldMeta.label || fieldName
        const escapedFieldName = this._escapeAttr(fieldName)
        const entries = (typeof currentValue === 'object' && currentValue !== null && !currentValue.type)
            ? Object.entries(currentValue)
            : []

        let rowsHTML = ''
        entries.forEach(([key, val], idx) => {
            rowsHTML += this._renderKvRow(escapedFieldName, idx, key, val)
        })

        return `<div class="mb-2 kv-editor wf-template-variable-editor" data-kv-field="${escapedFieldName}">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <label class="form-label form-label-sm mb-0">${label}</label>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-action="click->workflow-designer#addKvRow"
                    data-kv-target="${escapedFieldName}">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> Add Variable
                </button>
            </div>
            <div class="kv-rows" data-kv-rows="${escapedFieldName}">
                ${rowsHTML}
            </div>
        </div>`
    }

    _renderOptionArrayEditor(fieldName, fieldMeta, currentValue) {
        const label = fieldMeta.label || fieldName
        const escapedFieldName = this._escapeAttr(fieldName)
        const options = Array.isArray(currentValue) ? currentValue : []
        const description = fieldMeta.description
            ? `<small class="form-text text-muted d-block mb-2">${this._escapeAttr(fieldMeta.description)}</small>`
            : ''

        let rowsHTML = ''
        options.forEach((option, idx) => {
            rowsHTML += this._renderOptionArrayRow(escapedFieldName, idx, option)
        })

        return `<div class="mb-2 option-array-editor" data-array-field="${escapedFieldName}">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <label class="form-label form-label-sm mb-0">${label}</label>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-action="click->workflow-designer#addArrayRow"
                    data-array-target="${escapedFieldName}">
                    <i class="bi bi-plus-circle" aria-hidden="true"></i> Add Option
                </button>
            </div>
            ${description}
            <div class="option-array-rows" data-array-rows="${escapedFieldName}">
                ${rowsHTML}
            </div>
        </div>`
    }

    _renderOptionArrayRow(fieldName, idx, option = {}) {
        const rawValue = typeof option === 'object' && option !== null ? (option.value ?? '') : String(option ?? '')
        const rawLabel = typeof option === 'object' && option !== null ? (option.label ?? rawValue) : String(option ?? '')
        const safeFieldId = String(fieldName).replace(/[^a-zA-Z0-9_-]/g, '-')
        const valueId = `${safeFieldId}-option-value-${idx}`
        const labelId = `${safeFieldId}-option-label-${idx}`
        const optionName = rawLabel || rawValue
        const removeLabel = optionName ? `Remove option ${optionName}` : `Remove option ${idx + 1}`

        return `<div class="card border bg-light mb-2 option-array-row" data-array-idx="${idx}">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="flex-grow-1">
                        <label class="form-label form-label-sm mb-1" for="${this._escapeAttr(valueId)}">Stored value</label>
                        <input type="text" class="form-control form-control-sm"
                            id="${this._escapeAttr(valueId)}"
                            placeholder="support"
                            name="${fieldName}__option_value__${idx}" value="${this._escapeAttr(rawValue)}"
                            data-action="change->workflow-designer#updateNodeConfig">
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm"
                        data-action="click->workflow-designer#removeArrayRow"
                        aria-label="${this._escapeAttr(removeLabel)}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </div>
                <label class="form-label form-label-sm mb-1" for="${this._escapeAttr(labelId)}">Display label</label>
                <input type="text" class="form-control form-control-sm"
                    id="${this._escapeAttr(labelId)}"
                    placeholder="Support"
                    name="${fieldName}__option_label__${idx}" value="${this._escapeAttr(rawLabel)}"
                    data-action="change->workflow-designer#updateNodeConfig">
            </div>
        </div>`
    }

    _renderKvRow(fieldName, idx, key, val) {
        const escapedKey = this._escapeAttr(key)

        // Determine value display
        let valueDisplay = ''
        let valueType = 'fixed'
        if (typeof val === 'string' && val.startsWith('$.')) {
            valueDisplay = val
            valueType = 'context'
        } else if (typeof val === 'object' && val !== null && val.type === 'context') {
            valueDisplay = val.path || ''
            valueType = 'context'
        } else if (typeof val === 'object' && val !== null && val.type === 'app_setting') {
            valueDisplay = val.key || ''
            valueType = 'app_setting'
        } else {
            valueDisplay = String(val ?? '')
            valueType = 'fixed'
        }

        const typeOptions = [
            `<option value="fixed" ${valueType === 'fixed' ? 'selected' : ''}>Fixed</option>`,
            `<option value="context" ${valueType === 'context' ? 'selected' : ''}>Context Path</option>`,
            `<option value="app_setting" ${valueType === 'app_setting' ? 'selected' : ''}>App Setting</option>`,
        ].join('')

        const safeFieldId = String(fieldName).replace(/[^a-zA-Z0-9_-]/g, '-')
        const keyId = `${safeFieldId}-key-${idx}`
        const typeId = `${safeFieldId}-type-${idx}`
        const valueId = `${safeFieldId}-value-${idx}`
        const removeLabel = escapedKey
            ? `Remove template variable ${escapedKey}`
            : `Remove template variable ${idx + 1}`

        return `<div class="card border bg-light mb-2 kv-row wf-template-variable-card" data-kv-idx="${idx}">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="flex-grow-1">
                        <label class="form-label form-label-sm mb-1" for="${this._escapeAttr(keyId)}">Template variable</label>
                        <input type="text" class="form-control form-control-sm"
                            id="${this._escapeAttr(keyId)}"
                            placeholder="Variable name"
                            name="${fieldName}__key__${idx}" value="${escapedKey}"
                            data-action="change->workflow-designer#updateNodeConfig">
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm wf-template-variable-remove"
                        data-action="click->workflow-designer#removeKvRow"
                        aria-label="${this._escapeAttr(removeLabel)}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="row g-2 align-items-start">
                    <div class="col-12 col-sm-4">
                        <label class="form-label form-label-sm mb-1" for="${this._escapeAttr(typeId)}">Source</label>
                        <select class="form-select form-select-sm"
                            id="${this._escapeAttr(typeId)}"
                            data-kv-vtype="${fieldName}__${idx}"
                            data-action="change->workflow-designer#onKvValueTypeChange">
                            ${typeOptions}
                        </select>
                    </div>
                    <div class="col-12 col-sm-8 wf-template-variable-value">
                        <label class="form-label form-label-sm mb-1" for="${this._escapeAttr(valueId)}">Value</label>
                        <input type="text" class="form-control form-control-sm"
                            id="${this._escapeAttr(valueId)}"
                            placeholder="${valueType === 'context' ? 'Choose a workflow variable' : 'Value'}"
                            name="${fieldName}__val__${idx}" value="${this._escapeAttr(valueDisplay)}"
                            data-action="change->workflow-designer#updateNodeConfig"
                            ${valueType === 'context' ? 'data-variable-picker="true"' : ''}>
                    </div>
                </div>
            </div>
        </div>`
    }

    _escapeAttr(str) {
        if (!str) return ''
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    }

    _isSchemaFieldHidden(meta) {
        return meta?.hidden === true || meta?.visible === false
    }
}
