import '../../../assets/js/controllers/kanban-controller.js';
const KanbanController = window.Controllers['kanban'];

describe('KanbanController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="kanban"
                 data-kanban-csrf-token-value="test-token"
                 data-kanban-url-value="/api/cards/move">
                <div data-kanban-target="column" class="sortable" data-col="todo">
                    <div data-kanban-target="card" class="card" data-rec-id="1" data-stack-rank="100" draggable="true">Card 1</div>
                    <div data-kanban-target="card" class="card" data-rec-id="2" data-stack-rank="200" draggable="true">Card 2</div>
                </div>
                <div data-kanban-target="column" class="sortable" data-col="done">
                    <div data-kanban-target="card" class="card" data-rec-id="3" data-stack-rank="300" draggable="true">Card 3</div>
                </div>
            </div>
        `;

        controller = new KanbanController();
        controller.element = document.querySelector('[data-controller="kanban"]');
        controller.cardTargets = Array.from(document.querySelectorAll('[data-kanban-target="card"]'));
        controller.columnTargets = Array.from(document.querySelectorAll('[data-kanban-target="column"]'));
        controller.csrfTokenValue = 'test-token';
        controller.urlValue = '/api/cards/move';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(KanbanController.targets).toEqual(expect.arrayContaining(['card', 'column']));
    });

    test('has correct static values', () => {
        expect(KanbanController.values).toHaveProperty('csrfToken', String);
        expect(KanbanController.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['kanban']).toBe(KanbanController);
    });

    // --- Initialize ---

    test('initialize sets draggedItem to null', () => {
        controller.initialize();
        expect(controller.draggedItem).toBeNull();
    });

    // --- registerBeforeDrop ---

    test('registerBeforeDrop stores callback', () => {
        const callback = jest.fn();
        controller.registerBeforeDrop(callback);
        expect(controller.beforeDropCallback).toBe(callback);
    });

    // --- connect/disconnect ---

    test('connect adds global event listeners', () => {
        const addSpy = jest.spyOn(document, 'addEventListener');
        controller.connect();
        expect(addSpy).toHaveBeenCalledWith('dragover', expect.any(Function));
        expect(addSpy).toHaveBeenCalledWith('drop', expect.any(Function));
    });

    test('disconnect removes global event listeners', () => {
        const removeSpy = jest.spyOn(document, 'removeEventListener');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledWith('dragover', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('drop', expect.any(Function));
    });

    // --- handleDragOver ---

    test('handleDragOver prevents default', () => {
        const event = { preventDefault: jest.fn() };
        controller.handleDragOver(event);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    // --- handleDrop outside element ---

    test('handleDrop restores position when dropped outside element', () => {
        const restoreSpy = jest.spyOn(controller, 'restoreOriginalPosition').mockImplementation(() => {});
        controller.draggedItem = document.querySelector('[data-rec-id="1"]');
        const event = {
            preventDefault: jest.fn(),
            target: document.body
        };
        // target is outside controller element
        controller.handleDrop(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(restoreSpy).toHaveBeenCalled();
    });

    test('handleDrop does not restore when dropped inside element', () => {
        const restoreSpy = jest.spyOn(controller, 'restoreOriginalPosition').mockImplementation(() => {});
        controller.draggedItem = document.querySelector('[data-rec-id="1"]');
        const event = {
            preventDefault: jest.fn(),
            target: document.querySelector('[data-rec-id="2"]')
        };
        controller.handleDrop(event);
        expect(restoreSpy).not.toHaveBeenCalled();
    });

    // --- grabCard ---

    test('grabCard sets draggedItem and adds opacity class', () => {
        controller.initialize();
        const card = document.querySelector('[data-rec-id="1"]');
        const event = { target: card };

        controller.grabCard(event);

        expect(controller.draggedItem).toBe(card);
        expect(card.classList.contains('opacity-25')).toBe(true);
        expect(controller.originalParent).toBeTruthy();
        expect(controller.originalIndex).toBe(0);
    });

    // --- restoreOriginalPosition ---

    test('restoreOriginalPosition moves card back and cleans up', () => {
        const card = document.querySelector('[data-rec-id="1"]');
        const col = document.querySelector('[data-col="todo"]');

        controller.draggedItem = card;
        controller.originalParent = col;
        controller.originalIndex = 0;
        card.classList.add('opacity-25');

        // Move card elsewhere
        const doneCol = document.querySelector('[data-col="done"]');
        doneCol.appendChild(card);

        controller.restoreOriginalPosition();

        expect(controller.draggedItem).toBeNull();
        expect(card.classList.contains('opacity-25')).toBe(false);
        expect(col.children[0]).toBe(card);
    });

    test('restoreOriginalPosition does nothing when no draggedItem', () => {
        controller.draggedItem = null;
        controller.restoreOriginalPosition();
        // Should not throw
    });

    // --- cardDrag ---

    test('cardDrag prevents default and calls processDrag', () => {
        const processSpy = jest.spyOn(controller, 'processDrag').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };
        controller.cardDrag(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(processSpy).toHaveBeenCalledWith(event, false);
    });

    // --- dropCard ---

    test('dropCard processes drag and cleans up', () => {
        const card = document.querySelector('[data-rec-id="1"]');
        card.classList.add('opacity-25');
        controller.draggedItem = card;

        const processSpy = jest.spyOn(controller, 'processDrag').mockImplementation(() => {});
        const event = { preventDefault: jest.fn() };

        controller.dropCard(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(processSpy).toHaveBeenCalledWith(event, true);
        expect(card.classList.contains('opacity-25')).toBe(false);
        expect(controller.draggedItem).toBeNull();
    });

    // --- processDrag with isDrop ---

    test('processDrag sends fetch on drop', () => {
        global.fetch = jest.fn().mockResolvedValue({ ok: true });
        const card1 = document.querySelector('[data-rec-id="1"]');
        const card2 = document.querySelector('[data-rec-id="2"]');
        const col = document.querySelector('[data-col="todo"]');
        controller.draggedItem = card1;

        const event = {
            target: card2,
            dataTransfer: { getData: jest.fn().mockReturnValue('') }
        };

        controller.processDrag(event, true);

        expect(global.fetch).toHaveBeenCalledWith(
            '/api/cards/move/1',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'X-CSRF-Token': 'test-token'
                })
            })
        );
    });

    test('processDrag calls restoreOriginalPosition when beforeDropCallback returns false', () => {
        global.fetch = jest.fn();
        const card1 = document.querySelector('[data-rec-id="1"]');
        const card2 = document.querySelector('[data-rec-id="2"]');
        controller.draggedItem = card1;
        controller.originalParent = card1.parentElement;
        controller.originalIndex = 0;
        controller.beforeDropCallback = jest.fn().mockReturnValue(false);

        const event = {
            target: card2,
            dataTransfer: { getData: jest.fn().mockReturnValue('') }
        };

        controller.processDrag(event, true);

        expect(controller.beforeDropCallback).toHaveBeenCalled();
        expect(global.fetch).not.toHaveBeenCalled();
    });

    test('processDrag does not fetch when not a drop', () => {
        global.fetch = jest.fn();
        const card1 = document.querySelector('[data-rec-id="1"]');
        const card2 = document.querySelector('[data-rec-id="2"]');
        controller.draggedItem = card1;

        const event = {
            target: card2,
            dataTransfer: { getData: jest.fn().mockReturnValue('') }
        };

        controller.processDrag(event, false);
        expect(global.fetch).not.toHaveBeenCalled();
    });
});
