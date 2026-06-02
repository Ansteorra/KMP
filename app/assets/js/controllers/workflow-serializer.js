/**
 * WorkflowSerializer
 *
 * Handles conversion between Drawflow canvas data and workflow definition JSON.
 * Provides node port definitions, HTML rendering, and auto-layout computation.
 */
export default class WorkflowSerializer {
    constructor(registryData) {
        this.registryData = registryData || { triggers: [], actions: [], conditions: [], entities: [] }
    }

    getNodePorts(type) {
        switch (type) {
            case 'trigger': return { inputs: 0, outputs: 1 }
            case 'action': return { inputs: 1, outputs: 1 }
            case 'condition': return { inputs: 1, outputs: 2 }
            case 'approval': return { inputs: 1, outputs: 3 }
            case 'fork': return { inputs: 1, outputs: 2 }
            case 'join': return { inputs: 2, outputs: 1 }
            case 'loop': return { inputs: 1, outputs: 2 }
            case 'forEach': return { inputs: 1, outputs: 3 }
            case 'delay': return { inputs: 1, outputs: 1 }
            case 'subworkflow': return { inputs: 1, outputs: 1 }
            case 'end': return { inputs: 1, outputs: 0 }
            default: return { inputs: 1, outputs: 1 }
        }
    }

    getPortLabel(type, portIndex) {
        const portLabels = {
            trigger: ['default'],
            action: ['default'],
            condition: ['true', 'false'],
            approval: ['approved', 'rejected', 'on_each_approval'],
            loop: ['continue', 'exit'],
            forEach: ['iterate', 'complete', 'error'],
            fork: ['path-1', 'path-2', 'path-3', 'path-4'],
            delay: ['default'],
            join: ['default'],
            subworkflow: ['default'],
        }
        const zeroBasedIndex = portIndex - 1
        return portLabels[type]?.[zeroBasedIndex] || `output-${portIndex}`
    }

    buildNodeHTML(type, nodeKey, config) {
        const icons = {
            trigger: 'fa-bolt', action: 'fa-gear', condition: 'fa-diamond',
            approval: 'fa-check-double', fork: 'fa-code-branch', join: 'fa-code-merge',
            loop: 'fa-rotate', forEach: 'fa-list-ol', delay: 'fa-clock', subworkflow: 'fa-sitemap', end: 'fa-stop'
        }
        const typeLabels = {
            trigger: 'Trigger', action: 'Action', condition: 'Condition',
            approval: 'Approval', fork: 'Parallel Fork', join: 'Parallel Join',
            loop: 'Loop', forEach: 'For Each', delay: 'Delay', subworkflow: 'Sub-workflow', end: 'End'
        }

        const icon = icons[type] || 'fa-circle'
        let label = typeLabels[type] || type

        if (config.event) {
            const trigger = this.registryData.triggers?.find(t => t.event === config.event)
            if (trigger) label = trigger.label
        }
        if (config.action) {
            const action = this.registryData.actions?.find(a => a.action === config.action)
            if (action) label = action.label
        }
        if (config._nodeLabel) {
            label = config._nodeLabel
        }
        const escapedLabel = this._escapeHtml(label)

        let description = ''
        if (config.event) description = config.event.split('.').pop()
        else if (config.action) description = config.action.split('.').pop()
        else if (config.condition) description = config.condition

        let portLabelsHtml = ''
        if (['condition', 'loop'].includes(type)) {
            const labels = {
                condition: ['True', 'False'],
                loop: ['Continue', 'Exit'],
            }
            const pair = labels[type]
            portLabelsHtml = `<div class="wf-port-labels">
                <span class="wf-port-label wf-port-label-yes">${pair[0]}</span>
                <span class="wf-port-label wf-port-label-no">${pair[1]}</span>
            </div>`
        }
        if (type === 'forEach') {
            portLabelsHtml = `<div class="wf-port-labels">
                <span class="wf-port-label wf-port-label-yes">Iterate</span>
                <span class="wf-port-label wf-port-label-no">Complete</span>
                <span class="wf-port-label wf-port-label-mid">Error</span>
            </div>`
        }
        if (type === 'approval') {
            portLabelsHtml = `<div class="wf-port-labels">
                <span class="wf-port-label wf-port-label-yes">Approved</span>
                <span class="wf-port-label wf-port-label-no">Rejected</span>
                <span class="wf-port-label wf-port-label-mid">Each Step</span>
            </div>`
        }
        if (type === 'fork') {
            portLabelsHtml = `<div class="wf-port-labels">
                <span class="wf-port-label wf-port-label-yes">Path A</span>
                <span class="wf-port-label wf-port-label-yes">Path B</span>
            </div>`
        }

        return `<div class="wf-node wf-node-${type}">
            <div class="wf-node-header">
                <span class="wf-node-icon"><i class="fa-solid ${icon}"></i></span>
                <span class="wf-node-title" title="${escapedLabel}">${escapedLabel}</span>
            </div>
            <div class="wf-node-body">
                <span class="wf-node-type-label">${typeLabels[type] || type}</span>
                ${description ? `<div class="wf-node-description">${description}</div>` : ''}
            </div>
            ${portLabelsHtml}
        </div>`
    }

    _escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
    }

    exportWorkflow(editor) {
        const drawflowData = editor.export()
        const nodes = {}
        const canvasLayout = {}
        const moduleData = drawflowData.drawflow?.Home?.data || {}

        for (const [drawflowId, node] of Object.entries(moduleData)) {
            const nodeKey = node.data?.nodeKey || node.name
            const type = node.data?.type || 'unknown'
            const config = node.data?.config || {}

            const outputs = []
            for (const [outputKey, outputData] of Object.entries(node.outputs || {})) {
                for (const conn of outputData.connections || []) {
                    const targetNode = moduleData[conn.node]
                    const targetKey = targetNode?.data?.nodeKey || targetNode?.name
                    const port = outputKey.replace('output_', '')
                    outputs.push({
                        port: this.getPortLabel(type, parseInt(port)),
                        target: targetKey,
                    })
                }
            }

            const label = node.data?.label || config._nodeLabel || node.name
            const persistedConfig = { ...config }
            delete persistedConfig._nodeLabel
            nodes[nodeKey] = { type, label, config: persistedConfig, outputs }
            canvasLayout[nodeKey] = { x: node.pos_x, y: node.pos_y, drawflowId: parseInt(drawflowId) }
        }

        return { definition: { nodes }, canvasLayout }
    }

    importWorkflow(editor, definition, canvasLayout) {
        editor.clear()
        const nodeIdMap = {}
        const nodeEntries = Object.entries(definition.nodes || {})

        const hasLayout = canvasLayout && typeof canvasLayout === 'object' &&
            !Array.isArray(canvasLayout) && Object.keys(canvasLayout).length > 0

        let autoPositions = {}
        if (!hasLayout) {
            autoPositions = this.computeAutoLayout(definition)
        }

        for (const [nodeKey, nodeDef] of nodeEntries) {
            const pos = hasLayout
                ? (canvasLayout[nodeKey] || { x: 100, y: 100 })
                : (autoPositions[nodeKey] || { x: 100, y: 100 })
            const { inputs, outputs } = this.getNodePorts(nodeDef.type)
            const config = { ...(nodeDef.config || {}), _nodeLabel: nodeDef.label || '' }
            const html = this.buildNodeHTML(nodeDef.type, nodeKey, config)

            const drawflowId = editor.addNode(
                nodeKey, inputs, outputs,
                pos.x, pos.y, `${nodeKey} wf-type-${nodeDef.type}`,
                { type: nodeDef.type, config, nodeKey, label: nodeDef.label || '' },
                html
            )
            nodeIdMap[nodeKey] = drawflowId
        }

        for (const [nodeKey, nodeDef] of nodeEntries) {
            const sourceId = nodeIdMap[nodeKey]
            for (const [idx, output] of (nodeDef.outputs || []).entries()) {
                const targetId = nodeIdMap[output.target]
                if (targetId) {
                    editor.addConnection(sourceId, targetId, `output_${idx + 1}`, 'input_1')
                }
            }
        }
    }

    computeAutoLayout(definition) {
        const nodes = definition.nodes || {}
        const nodeKeys = Object.keys(nodes)
        const positions = {}

        const inDegree = {}
        const children = {}
        nodeKeys.forEach(k => { inDegree[k] = 0; children[k] = [] })
        for (const [key, node] of Object.entries(nodes)) {
            for (const out of (node.outputs || [])) {
                if (out.target && children[key]) {
                    children[key].push(out.target)
                    inDegree[out.target] = (inDegree[out.target] || 0) + 1
                }
            }
        }

        const layers = []
        let queue = nodeKeys.filter(k => inDegree[k] === 0)
        const visited = new Set()

        while (queue.length > 0) {
            layers.push([...queue])
            queue.forEach(k => visited.add(k))
            const nextQueue = []
            for (const k of queue) {
                for (const child of (children[k] || [])) {
                    inDegree[child]--
                    if (inDegree[child] <= 0 && !visited.has(child)) {
                        nextQueue.push(child)
                        visited.add(child)
                    }
                }
            }
            queue = nextQueue
        }

        nodeKeys.filter(k => !visited.has(k)).forEach(k => layers.push([k]))

        const nodeW = 260, nodeH = 120, gapX = 60, startX = 80, startY = 60

        layers.forEach((layer, rowIdx) => {
            const totalWidth = layer.length * nodeW + (layer.length - 1) * gapX
            const offsetX = startX + Math.max(0, (600 - totalWidth) / 2)
            layer.forEach((key, colIdx) => {
                positions[key] = {
                    x: offsetX + colIdx * (nodeW + gapX),
                    y: startY + rowIdx * nodeH
                }
            })
        })

        return positions
    }
}
