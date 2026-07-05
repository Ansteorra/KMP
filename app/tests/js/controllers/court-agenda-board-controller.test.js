import '../../../plugins/Awards/Assets/js/controllers/court-agenda-board-controller.js';

const CourtAgendaBoardController = window.Controllers['court-agenda-board'];

describe('CourtAgendaBoardController', () => {
    let controller;
    let root;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="court-agenda-board">
                <div data-court-agenda-board-target="status"></div>
                <section data-court-agenda-board-target="segment" data-segment-id="10">
                    <article data-court-agenda-board-target="item" data-item-id="1" data-sort-order="10" tabindex="-1"></article>
                    <article data-court-agenda-board-target="item" data-item-id="2" data-sort-order="20" tabindex="-1"></article>
                </section>
                <section data-court-agenda-board-target="segment" data-segment-id="20"></section>
            </div>
        `;
        root = document.querySelector('[data-controller="court-agenda-board"]');
        controller = new CourtAgendaBoardController();
        controller.element = root;
        controller.statusTarget = root.querySelector('[data-court-agenda-board-target="status"]');
        controller.hasStatusTarget = true;
        controller.moveUrlValue = '/awards/court-agendas/move-item';
        controller.csrfTokenValue = 'csrf-token';
        global.fetch = jest.fn().mockResolvedValue({ ok: true });
        window.COURT_AGENDA_DISABLE_RELOAD = true;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.COURT_AGENDA_DISABLE_RELOAD;
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['court-agenda-board']).toBe(CourtAgendaBoardController);
    });

    test('drop posts move request for target segment', async () => {
        const segment = root.querySelector('[data-segment-id="20"]');
        const event = {
            preventDefault: jest.fn(),
            currentTarget: segment,
            target: segment,
            dataTransfer: {
                getData: jest.fn((type) => (type === 'application/x-court-agenda-item' ? '1' : '')),
            },
        };

        await controller.drop(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(fetch).toHaveBeenCalledWith('/awards/court-agendas/move-item', expect.objectContaining({
            method: 'POST',
        }));
        expect(root.querySelector('[data-segment-id="20"] [data-item-id="1"]')).not.toBeNull();
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Agenda item moved.');
    });

    test('drag starts from the whole agenda card', () => {
        const item = root.querySelector('[data-item-id="1"]');
        const dataTransfer = {
            effectAllowed: '',
            setData: jest.fn(),
        };

        controller.dragStart({
            currentTarget: item,
            target: item,
            dataTransfer,
        });

        expect(dataTransfer.effectAllowed).toBe('move');
        expect(dataTransfer.setData).toHaveBeenCalledWith('text/plain', '1');
        expect(dataTransfer.setData).toHaveBeenCalledWith('application/x-court-agenda-item', '1');
        expect(item.classList.contains('opacity-50')).toBe(true);
        expect(controller.draggedItem).toBe(item);
    });

    test('drag over highlights a segment while dragging', () => {
        const item = root.querySelector('[data-item-id="1"]');
        const segment = root.querySelector('[data-segment-id="20"]');
        controller.draggedItem = item;
        const event = {
            preventDefault: jest.fn(),
            currentTarget: segment,
            dataTransfer: {
                dropEffect: '',
            },
        };

        controller.dragOver(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(event.dataTransfer.dropEffect).toBe('move');
        expect(segment.classList.contains('border-primary')).toBe(true);
    });

    test('drag over inserts a ghost placeholder at the pending sort position', () => {
        const dragged = root.querySelector('[data-item-id="2"]');
        const target = root.querySelector('[data-item-id="1"]');
        const segment = root.querySelector('[data-segment-id="10"]');
        controller.draggedItem = dragged;
        target.getBoundingClientRect = jest.fn(() => ({
            top: 100,
            height: 40,
        }));
        const event = {
            preventDefault: jest.fn(),
            currentTarget: segment,
            target,
            clientY: 105,
            dataTransfer: {
                dropEffect: '',
            },
        };

        controller.dragOver(event);

        const placeholder = root.querySelector('.court-agenda-drop-placeholder');
        expect(placeholder).not.toBeNull();
        expect(placeholder.getAttribute('aria-hidden')).toBe('true');
        expect(placeholder.classList.contains('pe-none')).toBe(true);
        expect(Array.from(segment.children)).toEqual([
            placeholder,
            target,
            dragged,
        ]);
    });

    test('drag over stays stable when the ghost placeholder is under the pointer', () => {
        const dragged = root.querySelector('[data-item-id="2"]');
        const target = root.querySelector('[data-item-id="1"]');
        const segment = root.querySelector('[data-segment-id="10"]');
        controller.draggedItem = dragged;
        target.getBoundingClientRect = jest.fn(() => ({
            top: 100,
            height: 40,
        }));
        const dataTransfer = {
            dropEffect: '',
        };
        controller.dragOver({
            preventDefault: jest.fn(),
            currentTarget: segment,
            target,
            clientY: 105,
            dataTransfer,
        });
        const placeholder = root.querySelector('.court-agenda-drop-placeholder');

        controller.dragOver({
            preventDefault: jest.fn(),
            currentTarget: segment,
            target: placeholder,
            clientY: 105,
            dataTransfer,
        });

        expect(Array.from(segment.children)).toEqual([
            placeholder,
            target,
            dragged,
        ]);
    });

    test('drop removes the ghost placeholder after placing the item', async () => {
        const dragged = root.querySelector('[data-item-id="2"]');
        const target = root.querySelector('[data-item-id="1"]');
        const segment = root.querySelector('[data-segment-id="10"]');
        controller.draggedItem = dragged;
        target.getBoundingClientRect = jest.fn(() => ({
            top: 100,
            height: 40,
        }));
        controller.dragOver({
            preventDefault: jest.fn(),
            currentTarget: segment,
            target,
            clientY: 105,
            dataTransfer: {
                dropEffect: '',
            },
        });

        await controller.drop({
            preventDefault: jest.fn(),
            currentTarget: segment,
            target,
            dataTransfer: {
                getData: jest.fn((type) => (type === 'application/x-court-agenda-item' ? '2' : '')),
            },
        });

        expect(root.querySelector('.court-agenda-drop-placeholder')).toBeNull();
        expect(Array.from(segment.querySelectorAll('[data-item-id]'))).toEqual([
            dragged,
            target,
        ]);
    });

    test('drop announces when the dragged agenda item payload is missing', () => {
        const segment = root.querySelector('[data-segment-id="20"]');
        const event = {
            preventDefault: jest.fn(),
            currentTarget: segment,
            target: segment,
            dataTransfer: {
                getData: jest.fn().mockReturnValue(''),
            },
        };

        controller.drop(event);

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Could not identify the agenda item to move.');
        expect(fetch).not.toHaveBeenCalled();
    });

    test('keyboard move announces when item cannot move farther', () => {
        const firstItem = root.querySelector('[data-item-id="1"]');
        const button = document.createElement('button');
        button.dataset.direction = 'up';
        firstItem.appendChild(button);

        controller.moveByButton({ currentTarget: button });

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Agenda item is already at that edge.');
        expect(fetch).not.toHaveBeenCalled();
    });

    test('keyboard move can send an item to the next segment', async () => {
        const firstItem = root.querySelector('[data-item-id="1"]');
        const button = document.createElement('button');
        button.dataset.direction = 'next-segment';
        firstItem.appendChild(button);

        await controller.moveByButton({ currentTarget: button });

        const requestBody = fetch.mock.calls[0][1].body;
        expect(requestBody.get('item_id')).toBe('1');
        expect(requestBody.get('court_agenda_segment_id')).toBe('20');
        expect(requestBody.get('sort_order')).toBe('10');
        expect(root.querySelector('[data-segment-id="20"] [data-item-id="1"]')).not.toBeNull();
        expect(document.activeElement).toBe(firstItem);
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Agenda item moved.');
    });

    test('keyboard move announces when item cannot move to another segment', () => {
        const firstItem = root.querySelector('[data-item-id="1"]');
        const button = document.createElement('button');
        button.dataset.direction = 'previous-segment';
        firstItem.appendChild(button);

        controller.moveByButton({ currentTarget: button });

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Agenda item is already in the edge segment.');
        expect(fetch).not.toHaveBeenCalled();
    });
});
