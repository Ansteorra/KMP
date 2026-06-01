import '../../../assets/js/controllers/sortable-list-controller.js';
const SortableListController = window.Controllers['sortable-list'];

describe('SortableListController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <ul data-controller="sortable-list">
                <li data-sortable-list-target="item" data-item-id="a">Item A</li>
                <li data-sortable-list-target="item" data-item-id="b">Item B</li>
                <li data-sortable-list-target="item" data-item-id="c">Item C</li>
            </ul>
        `;

        controller = new SortableListController();
        controller.element = document.querySelector('[data-controller="sortable-list"]');
        controller.itemTargets = Array.from(document.querySelectorAll('[data-sortable-list-target="item"]'));
        controller.handleTargets = [];
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(SortableListController.targets).toEqual(expect.arrayContaining(['item', 'handle']));
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['sortable-list']).toBe(SortableListController);
    });

    // --- Initialize ---

    test('initialize sets up tracking variables and bound handlers', () => {
        controller.initialize();
        expect(controller.draggedElement).toBeNull();
        expect(controller.draggedOverElement).toBeNull();
        expect(controller.boundHandlers).toBeDefined();
        expect(controller.boundHandlers.dragstart).toBeInstanceOf(Function);
        expect(controller.boundHandlers.drop).toBeInstanceOf(Function);
    });

    // --- Connect ---

    test('connect makes items draggable and adds listeners', () => {
        controller.initialize();
        const addSpy = jest.spyOn(controller, 'addDragListeners');
        controller.connect();

        controller.itemTargets.forEach(item => {
            expect(item.getAttribute('draggable')).toBe('true');
        });
        expect(addSpy).toHaveBeenCalledTimes(3);
    });

    // --- Disconnect ---

    test('disconnect removes drag listeners from items', () => {
        controller.initialize();
        controller.connect();
        const removeSpy = jest.spyOn(controller, 'removeDragListeners');
        controller.disconnect();
        expect(removeSpy).toHaveBeenCalledTimes(3);
    });

    // --- dragStart ---

    test('dragStart sets draggedElement and adds class', () => {
        controller.initialize();
        const item = controller.itemTargets[0];
        const event = {
            currentTarget: item,
            dataTransfer: { effectAllowed: null }
        };

        controller.dragStart(event);

        expect(controller.draggedElement).toBe(item);
        expect(item.classList.contains('dragging')).toBe(true);
        expect(event.dataTransfer.effectAllowed).toBe('move');
    });

    // --- dragOver ---

    test('dragOver prevents default and sets drag-over class on different item', () => {
        controller.initialize();
        controller.draggedElement = controller.itemTargets[0];
        const targetItem = controller.itemTargets[1];
        const event = {
            preventDefault: jest.fn(),
            currentTarget: targetItem,
            dataTransfer: { dropEffect: null }
        };

        const result = controller.dragOver(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.dataTransfer.dropEffect).toBe('move');
        expect(targetItem.classList.contains('drag-over')).toBe(true);
        expect(result).toBe(false);
    });

    test('dragOver does not add class on same item', () => {
        controller.initialize();
        const item = controller.itemTargets[0];
        controller.draggedElement = item;
        const event = {
            preventDefault: jest.fn(),
            currentTarget: item,
            dataTransfer: { dropEffect: null }
        };

        controller.dragOver(event);

        expect(item.classList.contains('drag-over')).toBe(false);
    });

    // --- dragEnter / dragLeave ---

    test('dragEnter adds visual feedback class', () => {
        controller.initialize();
        controller.draggedElement = controller.itemTargets[0];
        const target = controller.itemTargets[1];

        controller.dragEnter({ currentTarget: target });

        expect(target.classList.contains('drag-over')).toBe(true);
    });

    test('dragLeave removes visual feedback class', () => {
        controller.initialize();
        const target = controller.itemTargets[1];
        target.classList.add('drag-over');

        controller.dragLeave({ currentTarget: target });

        expect(target.classList.contains('drag-over')).toBe(false);
    });

    // --- drop ---

    test('drop reorders items and emits event', () => {
        controller.initialize();
        const itemA = controller.itemTargets[0];
        const itemC = controller.itemTargets[2];
        controller.draggedElement = itemA;

        const emitSpy = jest.spyOn(controller, 'emitReorderedEvent').mockImplementation(() => {});
        const rect = { top: 0, height: 40 };
        itemC.getBoundingClientRect = jest.fn().mockReturnValue(rect);

        // Drop below midpoint (after itemC)
        const event = {
            stopPropagation: jest.fn(),
            currentTarget: itemC,
            clientY: 30 // below midpoint of 20
        };

        controller.drop(event);

        expect(event.stopPropagation).toHaveBeenCalled();
        expect(emitSpy).toHaveBeenCalled();
    });

    test('drop does nothing when dropping on self', () => {
        controller.initialize();
        const item = controller.itemTargets[0];
        controller.draggedElement = item;
        const emitSpy = jest.spyOn(controller, 'emitReorderedEvent');

        const event = {
            stopPropagation: jest.fn(),
            currentTarget: item,
            clientY: 10
        };

        controller.drop(event);

        expect(emitSpy).not.toHaveBeenCalled();
    });

    // --- dragEnd ---

    test('dragEnd removes all drag classes and resets state', () => {
        controller.initialize();
        controller.itemTargets.forEach(item => {
            item.classList.add('dragging');
            item.classList.add('drag-over');
        });
        controller.draggedElement = controller.itemTargets[0];
        controller.draggedOverElement = controller.itemTargets[1];

        controller.dragEnd({});

        controller.itemTargets.forEach(item => {
            expect(item.classList.contains('dragging')).toBe(false);
            expect(item.classList.contains('drag-over')).toBe(false);
        });
        expect(controller.draggedElement).toBeNull();
        expect(controller.draggedOverElement).toBeNull();
    });

    // --- emitReorderedEvent ---

    test('emitReorderedEvent dispatches event with order', () => {
        controller.initialize();
        const handler = jest.fn();
        controller.element.addEventListener('sortable-list:reordered', handler);

        controller.emitReorderedEvent();

        expect(handler).toHaveBeenCalled();
        const detail = handler.mock.calls[0][0].detail;
        expect(detail.order).toEqual(['a', 'b', 'c']);
        expect(detail.items).toEqual(controller.itemTargets);
    });

    test('moveUp moves an item up and announces the new position', () => {
        controller.initialize();
        const status = document.createElement('div');
        controller.element.appendChild(status);
        controller.statusTarget = status;
        controller.hasStatusTarget = true;
        const emitSpy = jest.spyOn(controller, 'emitReorderedEvent');
        const button = document.createElement('button');
        controller.itemTargets[1].appendChild(button);

        controller.moveUp({
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            currentTarget: button
        });

        expect(controller.getOrder()).toEqual(['b', 'a', 'c']);
        expect(emitSpy).toHaveBeenCalled();
        expect(status.textContent).toContain('position 1 of 3');
    });

    test('moveDown moves an item down and announces the new position', () => {
        controller.initialize();
        const status = document.createElement('div');
        controller.element.appendChild(status);
        controller.statusTarget = status;
        controller.hasStatusTarget = true;
        const emitSpy = jest.spyOn(controller, 'emitReorderedEvent');
        const button = document.createElement('button');
        controller.itemTargets[1].appendChild(button);

        controller.moveDown({
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
            currentTarget: button
        });

        expect(controller.getOrder()).toEqual(['a', 'c', 'b']);
        expect(emitSpy).toHaveBeenCalled();
        expect(status.textContent).toContain('position 3 of 3');
    });

    // --- getOrder ---

    test('getOrder returns current item IDs', () => {
        controller.initialize();
        expect(controller.getOrder()).toEqual(['a', 'b', 'c']);
    });

    // --- addDragListeners / removeDragListeners ---

    test('addDragListeners attaches all drag event handlers', () => {
        controller.initialize();
        const item = document.createElement('li');
        const addSpy = jest.spyOn(item, 'addEventListener');

        controller.addDragListeners(item);

        const eventTypes = addSpy.mock.calls.map(c => c[0]);
        expect(eventTypes).toContain('dragstart');
        expect(eventTypes).toContain('dragover');
        expect(eventTypes).toContain('dragenter');
        expect(eventTypes).toContain('dragleave');
        expect(eventTypes).toContain('drop');
        expect(eventTypes).toContain('dragend');
    });

    test('removeDragListeners removes all drag event handlers', () => {
        controller.initialize();
        const item = document.createElement('li');
        const removeSpy = jest.spyOn(item, 'removeEventListener');

        controller.removeDragListeners(item);

        const eventTypes = removeSpy.mock.calls.map(c => c[0]);
        expect(eventTypes).toContain('dragstart');
        expect(eventTypes).toContain('drop');
        expect(eventTypes).toContain('dragend');
    });
});
