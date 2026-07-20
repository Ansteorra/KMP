// Guifier is dynamically imported - mock with __esModule + default
const MockGuifier = jest.fn().mockImplementation((params) => {
    return {
        getData: jest.fn((type) => '{"key":"value"}'),
    };
});
jest.mock('guifier', () => ({
    __esModule: true,
    default: MockGuifier,
}));

require('../../../assets/js/controllers/guifier-controller.js');
const GuifierController = window.Controllers['guifier-control'];

describe('GuifierController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="guifier-control" data-guifier-control-type-value="json">
                <input data-guifier-control-target="hidden" type="hidden" name="settings" value='{"key":"value"}'>
                <div data-guifier-control-target="container" id="guifier-container"></div>
            </div>
        `;

        controller = new GuifierController();
        controller.element = document.querySelector('[data-controller="guifier-control"]');
        controller.hiddenTarget = document.querySelector('[data-guifier-control-target="hidden"]');
        controller.containerTarget = document.querySelector('[data-guifier-control-target="container"]');
        controller.typeValue = 'json';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers as guifier-control', async () => {
        expect(window.Controllers['guifier-control']).toBe(GuifierController);
    });

    test('has correct static targets', async () => {
        expect(GuifierController.targets).toEqual(['hidden', 'container']);
    });

    test('has correct static values', async () => {
        expect(GuifierController.values).toHaveProperty('type', String);
    });

    test('connect initializes Guifier instance', async () => {
        await controller.connect();
        expect(MockGuifier).toHaveBeenCalled();
        expect(controller.guifier).toBeDefined();
    });

    test('connect passes correct element selector', async () => {
        await controller.connect();
        expect(MockGuifier).toHaveBeenCalledWith(
            expect.objectContaining({
                elementSelector: '#guifier-container',
                data: '{"key":"value"}',
                dataType: 'json',
                rootContainerName: 'setting',
                fullScreen: true,
                autoDownloadFontAwesome: false,
            })
        );
    });

    test('connect clicks all collapse buttons', async () => {
        // Add some mock collapse buttons
        const btn1 = document.createElement('button');
        btn1.className = 'guifierContainerCollapseButton';
        const btn2 = document.createElement('button');
        btn2.className = 'guifierContainerCollapseButton';
        controller.containerTarget.appendChild(btn1);
        controller.containerTarget.appendChild(btn2);

        const clickSpy1 = jest.spyOn(btn1, 'click');
        const clickSpy2 = jest.spyOn(btn2, 'click');

        await controller.connect();

        expect(clickSpy1).toHaveBeenCalled();
        expect(clickSpy2).toHaveBeenCalled();
    });

    test('onChange callback updates hidden field value', async () => {
        let onChangeCallback;
        MockGuifier.mockImplementation((params) => {
            onChangeCallback = params.onChange;
            return { getData: jest.fn(() => '{"updated":"data"}') };
        });

        await controller.connect();
        onChangeCallback();

        expect(controller.hiddenTarget.value).toBe('{"updated":"data"}');
    });

    test('onChange callback dispatches change event', async () => {
        let onChangeCallback;
        MockGuifier.mockImplementation((params) => {
            onChangeCallback = params.onChange;
            return { getData: jest.fn(() => '{}') };
        });

        const dispatchSpy = jest.spyOn(controller.hiddenTarget, 'dispatchEvent');
        await controller.connect();
        onChangeCallback();

        expect(dispatchSpy).toHaveBeenCalledWith(expect.any(Event));
    });
});
