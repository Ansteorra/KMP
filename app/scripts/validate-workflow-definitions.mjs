import { existsSync, readdirSync, readFileSync } from 'fs'
import { dirname, join } from 'path'
import { fileURLToPath } from 'url'

const scriptDir = dirname(fileURLToPath(import.meta.url))
const appDir = dirname(scriptDir)
const definitionsDir = join(appDir, 'config', 'Seeds', 'WorkflowDefinitions')
const schemaPath = join(definitionsDir, 'schema.json')
const allowedNodeTypes = new Set([
    'trigger',
    'action',
    'condition',
    'approval',
    'fork',
    'join',
    'loop',
    'forEach',
    'delay',
    'subworkflow',
    'humanTask',
    'stateMachine',
    'end',
])

if (!existsSync(schemaPath)) {
    throw new Error(`Missing workflow definition schema: ${schemaPath}`)
}

const files = readdirSync(definitionsDir)
    .filter(file => file.endsWith('.json') && file !== 'schema.json')
    .sort()

let failures = 0

for (const file of files) {
    const path = join(definitionsDir, file)
    let definition
    try {
        definition = JSON.parse(readFileSync(path, 'utf8'))
    } catch (error) {
        failures++
        console.error(`${file}: invalid JSON: ${error.message}`)
        continue
    }

    const errors = validateDefinition(definition)
    if (errors.length > 0) {
        failures++
        console.error(`${file}:`)
        errors.forEach(error => console.error(`  - ${error}`))
    }
}

if (failures > 0) {
    console.error(`Workflow definition validation failed for ${failures} file(s).`)
    process.exit(1)
}

console.log(`Validated ${files.length} workflow definition JSON file(s).`)

function validateDefinition(definition) {
    const errors = []

    if (definition?.$schema !== './schema.json') {
        errors.push('must declare "$schema": "./schema.json"')
    }
    if (definition?.schemaVersion !== '1.0') {
        errors.push('must declare "schemaVersion": "1.0"')
    }
    if (!definition?.nodes || typeof definition.nodes !== 'object' || Array.isArray(definition.nodes)) {
        errors.push('must contain a non-empty "nodes" object')
        return errors
    }

    const nodes = definition.nodes
    const nodeKeys = Object.keys(nodes)
    if (nodeKeys.length === 0) {
        errors.push('must contain at least one node')
        return errors
    }

    const triggerKeys = nodeKeys.filter(key => nodes[key]?.type === 'trigger')
    const endKeys = nodeKeys.filter(key => nodes[key]?.type === 'end')
    if (triggerKeys.length !== 1) {
        errors.push(`must contain exactly one trigger node; found ${triggerKeys.length}`)
    }
    if (endKeys.length < 1) {
        errors.push('must contain at least one end node')
    }
    if (definition.startNode && !nodes[definition.startNode]) {
        errors.push(`startNode "${definition.startNode}" does not reference an existing node`)
    }

    for (const key of nodeKeys) {
        const node = nodes[key]
        validateNode(key, node, nodes, errors)
    }

    if (triggerKeys.length === 1) {
        const reachable = findReachableNodes(triggerKeys[0], nodes)
        for (const key of nodeKeys) {
            if (!reachable.has(key)) {
                errors.push(`node "${key}" is not reachable from the trigger node`)
            }
        }
    }

    return errors
}

function validateNode(key, node, nodes, errors) {
    if (!node || typeof node !== 'object' || Array.isArray(node)) {
        errors.push(`node "${key}" must be an object`)
        return
    }
    if (!allowedNodeTypes.has(node.type)) {
        errors.push(`node "${key}" has unsupported type "${node.type ?? ''}"`)
    }
    if (!node.config || typeof node.config !== 'object' || Array.isArray(node.config)) {
        errors.push(`node "${key}" must define a config object`)
    }
    if (!Array.isArray(node.outputs)) {
        errors.push(`node "${key}" must define an outputs array`)
        return
    }

    node.outputs.forEach((output, index) => {
        if (!output || typeof output !== 'object' || Array.isArray(output)) {
            errors.push(`node "${key}" output #${index + 1} must be an object`)
            return
        }
        if (output.target === undefined || output.target === null || output.target === '') {
            return
        }
        if (typeof output.target !== 'string') {
            errors.push(`node "${key}" output #${index + 1} target must be a string`)
            return
        }
        if (!nodes[output.target]) {
            errors.push(`node "${key}" references missing target "${output.target}"`)
        }
    })

    if (node.type === 'loop') {
        const maxIterations = Number.parseInt(node.config?.maxIterations, 10)
        if (!Number.isFinite(maxIterations) || maxIterations <= 0) {
            errors.push(`loop node "${key}" must set config.maxIterations to a positive number`)
        }
    }
    if (node.type === 'forEach' && !node.config?.collection) {
        errors.push(`forEach node "${key}" must set config.collection`)
    }
}

function findReachableNodes(triggerKey, nodes) {
    const reachable = new Set([triggerKey])
    const queue = [triggerKey]

    while (queue.length > 0) {
        const current = queue.shift()
        for (const output of nodes[current]?.outputs || []) {
            const target = output.target
            if (target && nodes[target] && !reachable.has(target)) {
                reachable.add(target)
                queue.push(target)
            }
        }
    }

    return reachable
}
