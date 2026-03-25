// Controller registers on window.Controllers (no default export)
import '../../../plugins/Awards/Assets/js/controllers/recommendation-kanban-controller.js';
const RecommendationKanbanController = window.Controllers['recommendation-kanban'];

describe('RecommendationKanbanController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="recommendation-kanban">
                <div data-recommendation-kanban-target="stateRulesBlock"></div>
            </div>
        `;

        controller = new RecommendationKanbanController();
        controller.element = document.querySelector('[data-controller="recommendation-kanban"]');

        // Wire up targets
        controller.stateRulesBlockTarget = document.querySelector('[data-recommendation-kanban-target="stateRulesBlock"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(RecommendationKanbanController.targets).toEqual(
            expect.arrayContaining(['stateRulesBlock'])
        );
    });

    test('has correct static outlets', () => {
        expect(RecommendationKanbanController.outlets).toEqual(
            expect.arrayContaining(['kanban'])
        );
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['recommendation-kanban']).toBe(RecommendationKanbanController);
    });

    // --- kanbanOutletConnected ---

    test('kanbanOutletConnected stores outlet and registers beforeDrop callback', () => {
        const mockOutlet = {
            registerBeforeDrop: jest.fn()
        };
        const mockElement = document.createElement('div');

        controller.kanbanOutletConnected(mockOutlet, mockElement);

        expect(controller.board).toBe(mockOutlet);
        expect(mockOutlet.registerBeforeDrop).toHaveBeenCalledWith(expect.any(Function));
    });

    // --- checkRules ---

    test('checkRules returns true for any transition', () => {
        expect(controller.checkRules('rec-1', 'Approved')).toBe(true);
        expect(controller.checkRules('rec-2', 'Denied')).toBe(true);
    });

    // --- beforeDrop callback integration ---

    test('beforeDrop callback calls checkRules with correct args', () => {
        const spy = jest.spyOn(controller, 'checkRules');
        const mockOutlet = {
            registerBeforeDrop: jest.fn(cb => cb('rec-5', 'Given'))
        };

        controller.kanbanOutletConnected(mockOutlet, document.createElement('div'));

        expect(spy).toHaveBeenCalledWith('rec-5', 'Given');
    });
});
