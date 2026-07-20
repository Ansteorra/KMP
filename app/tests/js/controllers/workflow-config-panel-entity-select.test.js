import WorkflowConfigPanel from '../../../assets/js/controllers/workflow-config-panel.js'

describe('WorkflowConfigPanel entity value picker', () => {
    test('renders registered workflow entities as a fixed-value select', () => {
        const panel = new WorkflowConfigPanel({
            triggers: [],
            actions: [],
            conditions: [],
            resolvers: [],
            entities: [
                {
                    entityType: 'Core.Members',
                    label: 'Members',
                    source: 'Core',
                },
                {
                    entityType: 'Awards.Recommendations',
                    label: 'Award Recommendations',
                    source: 'Awards',
                },
            ],
        })

        document.body.innerHTML = panel.renderValuePicker('entityType', {
            type: 'entity',
            label: 'Object/Table',
            required: true,
        }, 'Awards.Recommendations')

        const select = document.querySelector('select[name="entityType"]')
        expect(select).not.toBeNull()
        expect(select.value).toBe('Awards.Recommendations')
        expect([...select.options].map(option => option.value)).toEqual([
            '',
            'Core.Members',
            'Awards.Recommendations',
        ])
        expect(select.options[2].textContent).toBe('Award Recommendations (Awards.Recommendations)')
    })

    test('keeps context path mode available for dynamic entity fields', () => {
        const panel = new WorkflowConfigPanel({
            triggers: [],
            actions: [],
            conditions: [],
            resolvers: [],
            entities: [],
        })

        document.body.innerHTML = panel.renderValuePicker('entityType', {
            type: 'entity',
            label: 'Object/Table',
        }, '$.trigger.entityType')

        expect(document.querySelector('[data-vp-type="entityType"]').value).toBe('context')
        expect(document.querySelector('input[name="entityType"]').value).toBe('$.trigger.entityType')
    })
})
