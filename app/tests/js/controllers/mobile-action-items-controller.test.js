import MobileControllerBase from '../../../assets/js/controllers/mobile-controller-base.js';
import '../../../assets/js/controllers/mobile-action-items-controller.js';

const MobileActionItemsController = window.Controllers['mobile-action-items'];

describe('MobileActionItemsController', () => {
    let controller;

    const groups = [{
        label: 'Award Recipient — Silver Star',
        url: '/awards/bestowals/view/9',
        entityType: 'Awards.Bestowals',
        entityId: 9,
        details: [
            { label: 'Specializations', value: 'Scribal Arts' },
            {
                label: 'Gathering',
                value: 'Spring Crown',
                url: '/gatherings/view/spring-crown?tab=gathering-bestowals',
            },
            { label: 'Gathering Date', value: '2026-05-01 to 2026-05-03' },
            { label: 'Hosting Group', value: 'Barony of Example' },
            { label: 'Court Assigned', value: 'Evening Court' },
        ],
        items: [{
            id: 41,
            title: 'Scroll finished',
            description: 'Calligraphy complete',
            isGating: true,
            branchName: 'Kingdom',
        }],
    }];

    beforeEach(() => {
        MobileControllerBase.setOnlineState(true, false);
        MobileControllerBase.connectionListeners = new Set();
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token">';
        document.body.innerHTML = `
            <div data-controller="mobile-action-items">
                <span data-mobile-action-items-target="countBadge" hidden></span>
                <span data-mobile-action-items-target="status" role="status" aria-live="polite"></span>
                <div data-mobile-action-items-target="loading"></div>
                <div data-mobile-action-items-target="empty" hidden></div>
                <div data-mobile-action-items-target="error" hidden>
                    <span data-mobile-action-items-target="errorMessage"></span>
                </div>
                <div data-mobile-action-items-target="list"></div>
                <div data-mobile-action-items-target="loadMore" hidden>
                    <button type="button" data-mobile-action-items-target="loadMoreBtn"></button>
                </div>
                <button data-mobile-action-items-target="refreshBtn"></button>
            </div>
        `;
        controller = new MobileActionItemsController();
        controller.perPageValue = 25;
        controller.initialize();
        controller.element = document.querySelector('[data-controller="mobile-action-items"]');
        controller.listTarget = document.querySelector('[data-mobile-action-items-target="list"]');
        controller.hasListTarget = true;
        controller.loadingTarget = document.querySelector('[data-mobile-action-items-target="loading"]');
        controller.hasLoadingTarget = true;
        controller.emptyTarget = document.querySelector('[data-mobile-action-items-target="empty"]');
        controller.hasEmptyTarget = true;
        controller.errorTarget = document.querySelector('[data-mobile-action-items-target="error"]');
        controller.hasErrorTarget = true;
        controller.errorMessageTarget = document.querySelector('[data-mobile-action-items-target="errorMessage"]');
        controller.hasErrorMessageTarget = true;
        controller.countBadgeTarget = document.querySelector('[data-mobile-action-items-target="countBadge"]');
        controller.hasCountBadgeTarget = true;
        controller.loadMoreTarget = document.querySelector('[data-mobile-action-items-target="loadMore"]');
        controller.hasLoadMoreTarget = true;
        controller.loadMoreBtnTarget = document.querySelector('[data-mobile-action-items-target="loadMoreBtn"]');
        controller.hasLoadMoreBtnTarget = true;
        controller.statusTarget = document.querySelector('[data-mobile-action-items-target="status"]');
        controller.hasStatusTarget = true;
        controller.refreshBtnTarget = document.querySelector('[data-mobile-action-items-target="refreshBtn"]');
        controller.hasRefreshBtnTarget = true;
        controller.dataUrlValue = '/action-items/mobile-data';
        controller.completeUrlValue = '/action-items/complete';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['mobile-action-items']).toBe(MobileActionItemsController);
    });

    test('renders expandable owner cards with accessible controls', () => {
        controller._groups = groups;
        controller._renderCards();
        controller._updateBadge();

        const summary = document.querySelector('.todo-owner-summary');
        expect(summary.tagName).toBe('BUTTON');
        expect(summary).toHaveAttribute('aria-expanded', 'false');
        expect(summary).toHaveAttribute('aria-controls', 'todo-owner-detail-Awards.Bestowals-9');
        expect(summary).toHaveAccessibleName(/Award Recipient/);
        expect(document.querySelector('[data-mobile-action-items-target="countBadge"]')).toHaveTextContent('1 open');
        expect(document.querySelector('.todo-owner-detail')).toHaveTextContent('Specializations Scribal Arts');
        expect(document.querySelector('.todo-owner-detail')).toHaveTextContent('Gathering Date 2026-05-01');
        expect(document.querySelector('.todo-owner-detail')).toHaveTextContent('Hosting Group Barony of Example');
        expect(document.querySelector('.todo-owner-detail')).toHaveTextContent('Court Assigned Evening Court');
        expect(document.querySelector('.todo-owner-detail a')).toHaveAttribute(
            'href',
            '/gatherings/view/spring-crown?tab=gathering-bestowals',
        );
        expect(document.querySelector('.todo-owner-detail a')).toHaveAttribute('data-turbo-frame', '_top');
        expect(document.querySelector('.todo-owner-detail')).not.toHaveTextContent('Reason');
        expect(document.querySelector('.todo-owner-detail')).not.toHaveTextContent('Linked Recommendation');
        expect(document.querySelector('.todo-owner-detail')).not.toHaveTextContent('Noble Notes');
        expect(document.querySelector('[data-item-id="41"]')).toHaveTextContent('Scroll finished');
        expect(document.querySelector('[data-item-id="41"] button i')).toBeNull();
    });

    test('toggleGroup synchronizes expanded state and detail visibility', () => {
        controller._groups = groups;
        controller._renderCards();

        const summary = document.querySelector('.todo-owner-summary');
        controller.toggleGroup({ currentTarget: summary });

        expect(summary).toHaveAttribute('aria-expanded', 'true');
        expect(document.querySelector('.todo-owner-detail')).not.toHaveAttribute('hidden');
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(expect.stringContaining('Expanded'));
    });

    test('completeItem requires online state', async () => {
        controller._groups = groups;
        controller._renderCards();
        controller._showToast = jest.fn();
        global.fetch = jest.fn();
        MobileControllerBase.setOnlineState(false, false);

        await controller.completeItem({
            preventDefault: jest.fn(),
            currentTarget: document.querySelector('[data-item-id="41"] button'),
        });

        expect(global.fetch).not.toHaveBeenCalled();
        expect(controller._showToast).toHaveBeenCalledWith('You must be online to complete to-dos.', 'danger');
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('You must be online to complete to-dos.');
    });

    test('completeItem removes completed cards and shows empty state', async () => {
        controller._groups = groups;
        controller._renderCards();
        controller._showToast = jest.fn();
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            status: 200,
            headers: { get: () => 'application/json' },
            json: jest.fn().mockResolvedValue({ success: true, itemId: 41, status: 'completed' }),
        });

        await controller.completeItem({
            preventDefault: jest.fn(),
            currentTarget: document.querySelector('[data-item-id="41"] button'),
        });

        expect(global.fetch).toHaveBeenCalledWith('/action-items/complete/41', expect.objectContaining({
            method: 'POST',
        }));
        expect(controller._groups).toEqual([]);
        expect(controller.emptyTarget.hidden).toBe(false);
        expect(controller.countBadgeTarget.hidden).toBe(true);
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('To-do marked complete.');
    });

    test('loadItems renders first page and exposes load more state', async () => {
        controller.fetchWithRetry = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue({
                openCount: 2,
                groups,
                pagination: {
                    page: 1,
                    perPage: 25,
                    total: 2,
                    pageCount: 2,
                    hasNextPage: true,
                },
            }),
        });

        await controller.loadItems();

        expect(controller.fetchWithRetry).toHaveBeenCalledWith(expect.stringContaining('page=1'));
        expect(controller.fetchWithRetry).toHaveBeenCalledWith(expect.stringContaining('per_page=25'));
        expect(controller.countBadgeTarget).toHaveTextContent('1 of 2 open');
        expect(controller.loadMoreTarget.hidden).toBe(false);
        expect(controller.loadMoreBtnTarget).toHaveTextContent('Load more (1 remaining)');
    });

    test('loadMore appends groups and announces loaded total', async () => {
        const secondGroup = [{
            label: 'Another Owner',
            url: '/awards/bestowals/view/10',
            entityType: 'Awards.Bestowals',
            entityId: 10,
            items: [{
                id: 42,
                title: 'Second task',
                description: '',
                isGating: false,
                branchName: '',
            }],
        }];
        controller.fetchWithRetry = jest.fn()
            .mockResolvedValueOnce({
                json: jest.fn().mockResolvedValue({
                    groups,
                    pagination: {
                        page: 1,
                        perPage: 25,
                        total: 2,
                        pageCount: 2,
                        hasNextPage: true,
                    },
                }),
            })
            .mockResolvedValueOnce({
                json: jest.fn().mockResolvedValue({
                    groups: secondGroup,
                    pagination: {
                        page: 2,
                        perPage: 25,
                        total: 2,
                        pageCount: 2,
                        hasNextPage: false,
                    },
                }),
            });

        await controller.loadItems();
        await controller.loadMore();

        expect(controller.fetchWithRetry).toHaveBeenLastCalledWith(expect.stringContaining('page=2'));
        expect(controller.fetchWithRetry).toHaveBeenLastCalledWith(expect.stringContaining('per_page=25'));
        expect(controller._openCount()).toBe(2);
        expect(controller.countBadgeTarget).toHaveTextContent('2 open');
        expect(controller.loadMoreTarget.hidden).toBe(true);
        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Loaded 2 of 2 open to-dos.');
    });
});
