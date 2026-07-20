/**
 * WorkflowVariablePicker
 *
 * Builds context-variable lists from upstream nodes and renders
 * a searchable dropdown for inserting variable references into form fields.
 */
export default class WorkflowVariablePicker {
    /**
     * @param {object} registryData - { triggers, actions, conditions, entities }
     */
    constructor(registryData) {
        this.registryData = registryData
        this.activeDropdownCleanup = null
    }

    /**
     * Get upstream nodes by traversing connections backward from nodeId.
     */
    getUpstreamNodes(nodeId, editor) {
        const drawflowData = editor.export()
        const moduleData = drawflowData.drawflow?.Home?.data || {}
        const upstream = []
        const visited = new Set()

        const traverse = (currentId) => {
            if (visited.has(currentId)) return
            visited.add(currentId)
            const node = moduleData[currentId]
            if (!node) return
            for (const inp of Object.values(node.inputs || {})) {
                for (const conn of inp.connections || []) {
                    const srcId = conn.node.toString()
                    const srcNode = moduleData[srcId]
                    if (srcNode) {
                        upstream.push({ id: srcId, ...srcNode })
                        traverse(srcId)
                    }
                }
            }
        }

        traverse(nodeId.toString())
        return upstream
    }

    /**
     * Collect output schema variables from a single node.
     */
    getNodeOutputSchema(node) {
        const type = node.data?.type
        const config = node.data?.config || {}
        const vars = []

        if (type === 'trigger') {
            const trigger = this.registryData.triggers?.find(t => t.event === config.event)
            if (trigger?.payloadSchema) {
                for (const [key, meta] of Object.entries(trigger.payloadSchema)) {
                    vars.push({
                        path: `$.trigger.${key}`,
                        label: `Trigger: ${meta.label || key}`,
                        type: meta.type || 'string'
                    })
                }
            } else if (config.event) {
                vars.push({ path: '$.trigger.entity', label: 'Trigger: entity', type: 'object' })
                vars.push({ path: '$.trigger.entity.id', label: 'Trigger: entity.id', type: 'integer' })
            }
        } else if (type === 'action') {
            const action = this.registryData.actions?.find(a => a.action === config.action)
            const nodeKey = node.data?.nodeKey || node.name
            const nodeLabel = this.getNodeDisplayLabel(node, action?.label || node.name)
            if (action?.outputSchema) {
                for (const [key, meta] of Object.entries(action.outputSchema)) {
                    vars.push({
                        path: `$.nodes.${nodeKey}.result.${key}`,
                        label: `${nodeLabel}: ${meta.label || key}`,
                        type: meta.type || 'string'
                    })
                }
            } else {
                vars.push({ path: `$.nodes.${nodeKey}.result`, label: `${nodeLabel}: result`, type: 'mixed' })
            }
            if (config.action === 'Core.GetObjectById') {
                vars.push(...this.getObjectLookupFieldVariables(config, nodeKey, action, nodeLabel))
            }
            if (config.action === 'Core.SetVariable') {
                const variableName = this.getFixedParamValue(config, 'name')
                if (variableName) {
                    vars.push({
                        path: `$.variables.${variableName}`,
                        label: `Variable: ${variableName}`,
                        type: this.getAssignedVariableType(config)
                    })
                }
            }
        } else if (type === 'approval') {
            const nodeKey = node.data?.nodeKey || node.name
            const nodeLabel = this.getNodeDisplayLabel(node, 'Approval')
            const schema = this.registryData?.approvalOutputSchema
            if (schema) {
                for (const [key, meta] of Object.entries(schema)) {
                    vars.push({
                        path: `$.nodes.${nodeKey}.${key}`,
                        label: `${nodeLabel}: ${meta.label || key}`,
                        type: meta.type || 'string'
                    })
                }
            } else {
                vars.push({ path: `$.nodes.${nodeKey}.status`, label: `${nodeLabel}: status`, type: 'string' })
                vars.push({ path: `$.nodes.${nodeKey}.approvedBy`, label: `${nodeLabel}: approvedBy`, type: 'array' })
                vars.push({ path: `$.nodes.${nodeKey}.comment`, label: `${nodeLabel}: comment`, type: 'string' })
            }
        } else if (type === 'condition') {
            const nodeKey = node.data?.nodeKey || node.name
            const nodeLabel = this.getNodeDisplayLabel(node, 'Condition')
            vars.push({ path: `$.nodes.${nodeKey}.result`, label: `${nodeLabel}: result`, type: 'boolean' })
        } else if (type === 'delay') {
            const nodeKey = node.data?.nodeKey || node.name
            const nodeLabel = this.getNodeDisplayLabel(node, 'Delay')
            vars.push({ path: `$.nodes.${nodeKey}.result.delayConfig`, label: `${nodeLabel}: delayConfig`, type: 'object' })
        } else if (type === 'loop') {
            const nodeKey = node.data?.nodeKey || node.name
            const nodeLabel = this.getNodeDisplayLabel(node, 'Loop')
            vars.push({ path: `$.nodes.${nodeKey}.result.iteration`, label: `${nodeLabel}: iteration`, type: 'integer' })
            vars.push({ path: `$.nodes.${nodeKey}.result.maxIterations`, label: `${nodeLabel}: maxIterations`, type: 'integer' })
        } else if (type === 'subworkflow') {
            const nodeKey = node.data?.nodeKey || node.name
            const nodeLabel = this.getNodeDisplayLabel(node, 'Sub-workflow')
            vars.push({ path: `$.nodes.${nodeKey}.result.childInstanceId`, label: `${nodeLabel}: childInstanceId`, type: 'integer' })
            vars.push({ path: `$.nodes.${nodeKey}.result`, label: `${nodeLabel}: result`, type: 'object' })
        }

        return vars
    }

    /**
     * Build variables for fields returned by the selected Get Object by ID entity.
     */
    getObjectLookupFieldVariables(config, nodeKey, action, nodeLabel = null) {
        const entityType = this.getFixedParamValue(config, 'entityType')
        if (!entityType) {
            return []
        }

        const entity = this.registryData.entities?.find(e => e.entityType === entityType)
        if (!entity?.fields) {
            return []
        }

        return Object.entries(entity.fields).map(([field, meta]) => ({
            path: `$.nodes.${nodeKey}.result.record.${field}`,
            label: `${nodeLabel || action?.label || 'Get Object by ID'}: record.${meta.label || field}`,
            type: meta.type || 'string'
        }))
    }

    getNodeDisplayLabel(node, fallback = null) {
        return node.data?.label || node.data?.config?._nodeLabel || fallback || node.name || node.data?.nodeKey || 'Node'
    }

    /**
     * Resolve a fixed action parameter from either params or legacy top-level config.
     */
    getFixedParamValue(config, paramName) {
        const value = config.params?.[paramName] ?? config[paramName]
        if (typeof value === 'string') {
            return value.startsWith('$.') ? null : value
        }
        if (value && typeof value === 'object') {
            if (value.type === 'fixed') {
                return value.value || null
            }
            return null
        }

        return value || null
    }

    /**
     * Infer assigned variable type from the configured value.
     */
    getAssignedVariableType(config) {
        const value = config.params?.value ?? config.value
        if (typeof value === 'boolean') return 'boolean'
        if (Number.isInteger(value)) return 'integer'
        if (typeof value === 'number') return 'number'
        if (Array.isArray(value)) return 'array'
        if (value && typeof value === 'object') return 'object'

        return 'mixed'
    }

    /**
     * Build a list of available context variables for a given node.
     */
    buildVariableList(nodeId, editor) {
        const upstream = this.getUpstreamNodes(nodeId, editor)
        const variables = []

        const drawflowData = editor.export()
        const moduleData = drawflowData.drawflow?.Home?.data || {}
        for (const node of Object.values(moduleData)) {
            if (node.data?.type === 'trigger') {
                variables.push(...this.getNodeOutputSchema(node))
                break
            }
        }

        upstream.forEach(node => {
            if (node.data?.type !== 'trigger') {
                variables.push(...this.getNodeOutputSchema(node))
            }
        })

        // If any upstream node is an approval gate, resumeData is available at runtime
        const hasUpstreamApproval = upstream.some(n => n.data?.type === 'approval')
        if (hasUpstreamApproval) {
            variables.push(
                { path: '$.resumeData.approverId', label: 'Resume: Approver ID', type: 'integer' },
                { path: '$.resumeData.decision', label: 'Resume: Decision', type: 'string' },
                { path: '$.resumeData.comment', label: 'Resume: Comment', type: 'string' },
            )
        }

        const builtins = this.registryData?.builtinContext
        if (builtins && Array.isArray(builtins)) {
            variables.push(...builtins)
        } else {
            variables.push(
                { path: '$.instance.id', label: 'Instance ID', type: 'integer' },
                { path: '$.instance.created', label: 'Instance Created', type: 'datetime' },
                { path: '$.context', label: 'Full Context', type: 'object' }
            )
        }

        return variables
    }

    /**
     * Attach variable picker buttons to inputs with data-variable-picker="true".
     */
    attachPickers(panel, nodeId, editor) {
        if (!panel) return
        const inputs = panel.querySelectorAll('input[data-variable-picker="true"], textarea[data-variable-picker="true"]')
        inputs.forEach(input => {
            if (input.parentElement.querySelector('.wf-var-picker-btn')) return
            const originalParent = input.parentElement
            const wrapper = document.createElement('div')
            wrapper.className = 'd-flex gap-1 position-relative wf-var-picker-wrapper'
            originalParent.insertBefore(wrapper, input)
            wrapper.appendChild(input)

            const btn = document.createElement('button')
            btn.type = 'button'
            btn.className = 'btn btn-outline-secondary btn-sm wf-var-picker-btn'
            btn.innerHTML = '<i class="fa-solid fa-code"></i>'
            btn.title = 'Insert variable reference'
            btn.setAttribute('aria-label', 'Insert variable reference')
            btn.addEventListener('click', (e) => {
                e.preventDefault()
                e.stopPropagation()
                this.showDropdown(btn, input, nodeId, editor, true)
            })
            input.addEventListener('focus', () => this.showDropdown(btn, input, nodeId, editor))
            input.addEventListener('input', () => this.showDropdown(btn, input, nodeId, editor))
            input.addEventListener('keydown', (e) => this.handleDropdownKeydown(e, input))
            wrapper.appendChild(btn)

            const hint = this.ensureSelectedVariableHint(input, wrapper)
            const variables = this.buildVariableList(nodeId, editor)
            const initialVariable = variables.find(variable => variable.path === input.value) || null
            input.dataset.lastValidContextPath = initialVariable?.path || ''
            input.dataset.lastValidContextLabel = initialVariable?.label || ''
            input.dataset.lastValidContextType = initialVariable?.type || ''
            input.addEventListener('input', () => {
                this.updateSelectedVariableHint(input, variables.find(variable => variable.path === input.value) || null, hint)
            })
            input.addEventListener('change', () => {
                this.enforceSelectedVariable(input, variables, hint)
            })
            input.addEventListener('blur', () => {
                this.enforceSelectedVariable(input, variables, hint)
            })
            this.updateSelectedVariableHint(input, initialVariable, hint)
        })
    }

    /**
     * Show the searchable variable dropdown anchored to anchorBtn.
     */
    showDropdown(anchorBtn, targetInput, nodeId, editor, showAll = false) {
        this.closeDropdown()

        const variables = this.buildVariableList(nodeId, editor)
        if (variables.length === 0) return

        const wrapper = anchorBtn.parentElement
        wrapper.classList.add('wf-var-picker-wrapper')
        const group = wrapper.closest('.input-group')
        const dropdown = document.createElement('ul')
        dropdown.className = 'wf-var-dropdown list-group position-absolute auto-complete-list'
        dropdown.setAttribute('role', 'listbox')
        dropdown.setAttribute('aria-label', 'Workflow variables')
        dropdown.id = `wf-var-dropdown-${Date.now()}`

        const filter = showAll ? '' : this.getFilterText(targetInput)
        const filtered = variables.filter(v =>
            v.path.toLowerCase().includes(filter) || v.label.toLowerCase().includes(filter)
        )
        filtered.forEach((variable, index) => {
            const item = document.createElement('li')
            item.className = `list-group-item list-group-item-action py-1 px-2 small${index === 0 ? ' active' : ''}`
            item.setAttribute('role', 'option')
            item.setAttribute('aria-selected', index === 0 ? 'true' : 'false')
            item.dataset.variablePath = variable.path
            item.dataset.variableLabel = variable.label
            item.dataset.variableType = variable.type
            item.id = `${dropdown.id}-option-${index}`
            item.innerHTML = `<span>${this._escapeHtml(variable.label)} <span class="text-muted">(${this._escapeHtml(variable.type)})</span></span><br><code class="small">${this._escapeHtml(variable.path)}</code>`
            item.addEventListener('mousedown', (event) => event.preventDefault())
            item.addEventListener('click', () => this.commitVariable(targetInput, variable))
            dropdown.appendChild(item)
        })
        if (filtered.length === 0) {
            const emptyItem = document.createElement('li')
            emptyItem.className = 'list-group-item text-muted small p-2'
            emptyItem.textContent = 'No variables found'
            dropdown.appendChild(emptyItem)
        }

        document.body.appendChild(dropdown)
        wrapper.classList.add('wf-var-picker-wrapper-open')
        group?.classList.add('wf-var-picker-group-open')
        this.positionDropdown(dropdown, wrapper)
        targetInput.setAttribute('role', 'combobox')
        targetInput.setAttribute('aria-autocomplete', 'list')
        targetInput.setAttribute('aria-expanded', 'true')
        targetInput.setAttribute('aria-controls', dropdown.id)
        this.updateActiveDescendant(targetInput, dropdown)

        const closeHandler = (event) => {
            if (!wrapper.contains(event.target) && !dropdown.contains(event.target)) {
                this.closeDropdown()
            }
        }
        const repositionHandler = () => this.positionDropdown(dropdown, wrapper)
        document.addEventListener('click', closeHandler)
        window.addEventListener('resize', repositionHandler)
        window.addEventListener('scroll', repositionHandler, true)
        this.activeDropdownCleanup = () => {
            dropdown.remove()
            wrapper.classList.remove('wf-var-picker-wrapper-open')
            group?.classList.remove('wf-var-picker-group-open')
            targetInput.setAttribute('aria-expanded', 'false')
            targetInput.removeAttribute('aria-activedescendant')
            document.removeEventListener('click', closeHandler)
            window.removeEventListener('resize', repositionHandler)
            window.removeEventListener('scroll', repositionHandler, true)
        }
    }

    /**
     * Close the active variable dropdown if one is open.
     */
    closeDropdown() {
        if (this.activeDropdownCleanup) {
            this.activeDropdownCleanup()
            this.activeDropdownCleanup = null
        }
    }

    getFilterText(input) {
        return this.getCurrentToken(input).toLowerCase()
    }

    getCurrentToken(input) {
        const caret = input.selectionStart ?? input.value.length
        const beforeCaret = input.value.substring(0, caret)

        return beforeCaret.split(/[\s"'{}()[\],]+/).pop() || ''
    }

    getCurrentTokenBounds(input) {
        const caret = input.selectionStart ?? input.value.length
        const beforeCaret = input.value.substring(0, caret)
        const token = this.getCurrentToken(input)

        return {
            start: beforeCaret.length - token.length,
            end: caret,
            token,
        }
    }

    getOpenDropdown(input) {
        const dropdownId = input.getAttribute('aria-controls')

        return dropdownId ? document.getElementById(dropdownId) : null
    }

    getDropdownOptions(dropdown) {
        return Array.from(dropdown.querySelectorAll('[role="option"]'))
    }

    updateActiveDescendant(input, dropdown) {
        const selected = dropdown.querySelector('[aria-selected="true"]')
        if (selected) {
            input.setAttribute('aria-activedescendant', selected.id)
        } else {
            input.removeAttribute('aria-activedescendant')
        }
    }

    selectOption(input, dropdown, next = true) {
        const options = this.getDropdownOptions(dropdown)
        if (options.length === 0) return

        const current = dropdown.querySelector('[aria-selected="true"]')
        const currentIndex = options.indexOf(current)
        const selectedIndex = next
            ? (currentIndex + 1) % options.length
            : (currentIndex <= 0 ? options.length - 1 : currentIndex - 1)
        options.forEach((option, index) => {
            option.setAttribute('aria-selected', index === selectedIndex ? 'true' : 'false')
            option.classList.toggle('active', index === selectedIndex)
        })
        options[selectedIndex].scrollIntoView?.({ behavior: 'auto', block: 'nearest' })
        this.updateActiveDescendant(input, dropdown)
    }

    handleDropdownKeydown(event, input) {
        const dropdown = this.getOpenDropdown(input)
        if (!dropdown) return

        if (event.key === 'Escape') {
            this.closeDropdown()
            event.preventDefault()
            return
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            this.selectOption(input, dropdown, event.key === 'ArrowDown')
            event.preventDefault()
            return
        }
        if (event.key === 'Enter') {
            const selected = dropdown.querySelector('[aria-selected="true"]')
            if (selected?.dataset.variablePath) {
                this.commitVariable(input, {
                    path: selected.dataset.variablePath,
                    label: selected.dataset.variableLabel || selected.dataset.variablePath,
                    type: selected.dataset.variableType || 'mixed',
                })
                event.preventDefault()
            }
        }
    }

    commitVariable(targetInput, variable) {
        const selectedVariable = typeof variable === 'string'
            ? { path: variable, label: variable, type: 'mixed' }
            : variable
        targetInput.value = selectedVariable.path
        targetInput.dataset.lastValidContextPath = selectedVariable.path
        targetInput.dataset.lastValidContextLabel = selectedVariable.label
        targetInput.dataset.lastValidContextType = selectedVariable.type || 'mixed'
        this.updateSelectedVariableHint(targetInput, selectedVariable)
        targetInput.dispatchEvent(new Event('input', { bubbles: true }))
        targetInput.dispatchEvent(new Event('change', { bubbles: true }))
        this.closeDropdown()
        targetInput.focus()
    }

    ensureSelectedVariableHint(input, wrapper) {
        const existingId = input.dataset.variableHintId
        if (existingId) {
            const existing = document.getElementById(existingId)
            if (existing) return existing
        }

        const hint = document.createElement('div')
        hint.id = `wf-var-hint-${Date.now()}-${Math.floor(Math.random() * 100000)}`
        hint.className = 'form-text small text-muted wf-var-picker-hint'
        hint.hidden = true
        wrapper.insertAdjacentElement('afterend', hint)
        input.dataset.variableHintId = hint.id
        this.appendDescribedBy(input, hint.id)

        return hint
    }

    appendDescribedBy(input, id) {
        const ids = (input.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(Boolean)
        if (!ids.includes(id)) {
            ids.push(id)
            input.setAttribute('aria-describedby', ids.join(' '))
        }
    }

    removeSelectedVariableHint(input) {
        const hintId = input.dataset.variableHintId
        if (!hintId) return

        document.getElementById(hintId)?.remove()
        const ids = (input.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(id => id && id !== hintId)
        if (ids.length > 0) {
            input.setAttribute('aria-describedby', ids.join(' '))
        } else {
            input.removeAttribute('aria-describedby')
        }
        delete input.dataset.variableHintId
        delete input.dataset.lastValidContextPath
        delete input.dataset.lastValidContextLabel
        delete input.dataset.lastValidContextType
    }

    updateSelectedVariableHint(input, variable, hint = null) {
        const hintEl = hint || document.getElementById(input.dataset.variableHintId || '')
        if (!hintEl) return

        if (!variable?.path || input.value !== variable.path) {
            hintEl.hidden = true
            hintEl.textContent = ''
            return
        }

        hintEl.hidden = false
        hintEl.textContent = `Selected variable: ${variable.label} (${variable.type || 'mixed'})`
    }

    enforceSelectedVariable(input, variables, hint = null) {
        const selectedVariable = variables.find(variable => variable.path === input.value)
        if (selectedVariable) {
            input.dataset.lastValidContextPath = selectedVariable.path
            input.dataset.lastValidContextLabel = selectedVariable.label
            input.dataset.lastValidContextType = selectedVariable.type || 'mixed'
            this.updateSelectedVariableHint(input, selectedVariable, hint)
            return
        }

        const lastValidPath = input.dataset.lastValidContextPath || ''
        if (!input.value && !lastValidPath) {
            this.updateSelectedVariableHint(input, null, hint)
            return
        }

        input.value = lastValidPath
        if (lastValidPath) {
            this.updateSelectedVariableHint(input, {
                path: lastValidPath,
                label: input.dataset.lastValidContextLabel || lastValidPath,
                type: input.dataset.lastValidContextType || 'mixed',
            }, hint)
        } else {
            this.updateSelectedVariableHint(input, null, hint)
        }
    }

    positionDropdown(dropdown, wrapper) {
        const anchorRect = wrapper.getBoundingClientRect()
        const margin = 8
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0
        const availableRight = Math.max(280, viewportWidth - anchorRect.left - margin)
        const width = Math.max(anchorRect.width, availableRight)
        const desiredMaxHeight = 320
        const belowTop = anchorRect.bottom + 4
        const belowSpace = viewportHeight - belowTop - margin
        const aboveSpace = anchorRect.top - margin
        const openAbove = belowSpace < 180 && aboveSpace > belowSpace
        const maxHeight = Math.max(120, Math.min(desiredMaxHeight, openAbove ? aboveSpace - 4 : belowSpace))
        const top = openAbove
            ? Math.max(margin, anchorRect.top - maxHeight - 4)
            : belowTop

        dropdown.style.position = 'fixed'
        dropdown.style.left = `${Math.max(margin, anchorRect.left)}px`
        dropdown.style.top = `${top}px`
        dropdown.style.width = `${width}px`
        dropdown.style.maxHeight = `${maxHeight}px`
    }

    _escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }
}
