import '../../../assets/js/controllers/auto-complete-controller.js';
const AutoComplete = window.Controllers['ac'];

describe('AutoCompleteController', () => {
    let controller;

    function buildDOM(opts = {}) {
        const url = opts.url || '';
        const minLength = opts.minLength || 2;
        const allowOther = opts.allowOther || false;
        const delay = opts.delay || 300;
        const queryParam = opts.queryParam || 'q';
        const showOnFocus = opts.showOnFocus || false;
        const dataListContent = opts.dataListContent || '';

        return `
            <div data-controller="ac"
                 ${url ? `data-auto-complete-url-value="${url}"` : ''}
                 data-auto-complete-min-length-value="${minLength}"
                 data-auto-complete-allow-other-value="${allowOther}"
                 data-auto-complete-delay-value="${delay}"
                 data-auto-complete-query-param-value="${queryParam}"
                 data-auto-complete-show-on-focus-value="${showOnFocus}"
                 aria-expanded="false">
                <input type="text" data-auto-complete-target="input" class="form-control">
                <input type="hidden" data-auto-complete-target="hidden" name="member_id" value="">
                <input type="hidden" data-auto-complete-target="hiddenText" name="member_name" value="">
                <button type="button" data-auto-complete-target="clearBtn" disabled>Clear</button>
                <div data-auto-complete-target="results" class="autocomplete-results" hidden></div>
                ${dataListContent ? `<div data-auto-complete-target="dataList" style="display: none;">${dataListContent}</div>` : ''}
            </div>
        `;
    }

    function setupController(opts = {}) {
        document.body.innerHTML = buildDOM(opts);

        controller = new AutoComplete();
        controller.element = document.querySelector('[data-controller="ac"]');

        // Wire targets
        controller.inputTarget = document.querySelector('[data-auto-complete-target="input"]');
        controller.hiddenTarget = document.querySelector('[data-auto-complete-target="hidden"]');
        controller.hiddenTextTarget = document.querySelector('[data-auto-complete-target="hiddenText"]');
        controller.resultsTarget = document.querySelector('[data-auto-complete-target="results"]');
        controller.clearBtnTarget = document.querySelector('[data-auto-complete-target="clearBtn"]');

        const dataList = document.querySelector('[data-auto-complete-target="dataList"]');
        if (dataList) {
            controller.dataListTarget = dataList;
            controller.hasDataListTarget = true;
        } else {
            controller.hasDataListTarget = false;
        }

        // Wire has* checks
        controller.hasInputTarget = true;
        controller.hasHiddenTarget = true;
        controller.hasHiddenTextTarget = true;
        controller.hasResultsTarget = true;
        controller.hasClearBtnTarget = true;

        // Wire values
        controller.urlValue = opts.url || '';
        controller.hasUrlValue = !!opts.url;
        controller.minLengthValue = opts.minLength || 2;
        controller.allowOtherValue = opts.allowOther || false;
        controller.requiredValue = opts.required || false;
        controller.delayValue = opts.delay || 300;
        controller.queryParamValue = opts.queryParam || 'q';
        controller.showOnFocusValue = opts.showOnFocus || false;
        controller.hasSubmitOnEnterValue = false;
        controller.hasSelectedClass = false;
        controller.hasInitSelectionValue = false;
        controller.initSelectionValue = {};

        return controller;
    }

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        if (global.fetch) {
            delete global.fetch;
        }
    });

    // ==================== Static properties ====================

    test('registers on window.Controllers as "ac"', () => {
        expect(window.Controllers['ac']).toBe(AutoComplete);
    });

    test('has correct static targets', () => {
        expect(AutoComplete.targets).toEqual(
            expect.arrayContaining(['input', 'hidden', 'hiddenText', 'results', 'dataList', 'clearBtn'])
        );
    });

    test('has correct static values', () => {
        expect(AutoComplete.values).toHaveProperty('url');
        expect(AutoComplete.values).toHaveProperty('minLength');
        expect(AutoComplete.values).toHaveProperty('allowOther');
        expect(AutoComplete.values).toHaveProperty('delay');
        expect(AutoComplete.values).toHaveProperty('queryParam');
        expect(AutoComplete.values).toHaveProperty('ready');
        expect(AutoComplete.values).toHaveProperty('submitOnEnter');
        expect(AutoComplete.values).toHaveProperty('required');
        expect(AutoComplete.values).toHaveProperty('showOnFocus');
        expect(AutoComplete.values).toHaveProperty('initSelection');
    });

    test('has correct static classes', () => {
        expect(AutoComplete.classes).toEqual(['selected']);
    });

    // ==================== Initialization ====================

    test('initialize sets default state', () => {
        setupController();
        controller.initialize();
        expect(controller._selectOptions).toEqual([]);
        expect(controller._datalistLoaded).toBe(false);
    });

    // ==================== Connect ====================

    test('connect sets autocomplete off and spellcheck false on input', () => {
        setupController();
        controller.connect();

        expect(controller.inputTarget.getAttribute('autocomplete')).toBe('off');
        expect(controller.inputTarget.getAttribute('spellcheck')).toBe('false');
    });

    test('connect sets readyValue to true', () => {
        setupController();
        controller.connect();
        expect(controller.readyValue).toBe(true);
    });

    test('connect dispatches ready event', () => {
        setupController();
        const handler = jest.fn();
        controller.element.addEventListener('ready', handler);
        controller.connect();
        expect(handler).toHaveBeenCalled();
    });

    test('connect closes results initially', () => {
        setupController();
        controller.connect();
        expect(controller.resultsTarget.hidden).toBe(true);
    });

    // ==================== Value getter/setter ====================

    test('value getter returns hidden value when set', () => {
        setupController();
        controller.hiddenTarget.value = '42';
        expect(controller.value).toBe('42');
    });

    test('value getter returns empty string when hidden empty and allowOther false', () => {
        setupController({ allowOther: false });
        controller.hiddenTarget.value = '';
        controller.inputTarget.value = 'some text';
        expect(controller.value).toBe('');
    });

    test('value getter returns input value when hidden empty and allowOther true', () => {
        setupController({ allowOther: true });
        controller.hiddenTarget.value = '';
        controller.inputTarget.value = 'custom text';
        expect(controller.value).toBe('custom text');
    });

    test('value setter with object sets input and hidden fields', () => {
        setupController();
        controller.value = { value: '99', text: 'Test Member' };

        expect(controller.inputTarget.value).toBe('Test Member');
        expect(controller.hiddenTarget.value).toBe('99');
        expect(controller.hiddenTextTarget.value).toBe('Test Member');
        expect(controller.clearBtnTarget.disabled).toBe(false);
        expect(controller.inputTarget.disabled).toBe(true);
    });

    test('value setter with matching option value', () => {
        setupController();
        controller._selectOptions = [
            { value: '1', text: 'Option One' },
            { value: '2', text: 'Option Two' }
        ];
        controller.value = '2';

        expect(controller.inputTarget.value).toBe('Option Two');
        expect(controller.hiddenTarget.value).toBe('2');
        expect(controller.hiddenTextTarget.value).toBe('Option Two');
        expect(controller.clearBtnTarget.disabled).toBe(false);
        expect(controller.inputTarget.disabled).toBe(true);
    });

    test('value setter with unknown value and allowOther false clears fields', () => {
        setupController({ allowOther: false });
        controller._selectOptions = [{ value: '1', text: 'Opt' }];
        controller.value = 'unknown';

        expect(controller.inputTarget.value).toBe('');
        expect(controller.hiddenTarget.value).toBe('');
        expect(controller.hiddenTextTarget.value).toBe('');
        expect(controller.clearBtnTarget.disabled).toBe(true);
        expect(controller.inputTarget.disabled).toBe(false);
    });

    test('value setter with unknown value and allowOther true sets custom value', () => {
        setupController({ allowOther: true });
        controller._selectOptions = [{ value: '1', text: 'Opt' }];
        controller.value = 'custom';

        expect(controller.inputTarget.value).toBe('custom');
        expect(controller.hiddenTarget.value).toBe('custom');
        expect(controller.hiddenTextTarget.value).toBe('custom');
        expect(controller.clearBtnTarget.disabled).toBe(false);
        expect(controller.inputTarget.disabled).toBe(true);
    });

    test('value setter with empty/null clears all fields', () => {
        setupController();
        controller.inputTarget.value = 'something';
        controller.hiddenTarget.value = '1';
        controller.value = '';

        expect(controller.inputTarget.value).toBe('');
        expect(controller.hiddenTarget.value).toBe('');
        expect(controller.hiddenTextTarget.value).toBe('');
        expect(controller.clearBtnTarget.disabled).toBe(true);
        expect(controller.inputTarget.disabled).toBe(false);
    });

    test('value setter skips disabled options', () => {
        setupController({ allowOther: false });
        controller._selectOptions = [
            { value: '1', text: 'Disabled Opt', enabled: false },
            { value: '2', text: 'Enabled Opt' }
        ];
        controller.value = '1';

        // disabled option should not match
        expect(controller.inputTarget.value).toBe('');
        expect(controller.hiddenTarget.value).toBe('');
    });

    // ==================== disabled getter/setter ====================

    test('disabled getter returns input disabled state', () => {
        setupController();
        controller.inputTarget.disabled = true;
        expect(controller.disabled).toBe(true);
    });

    test('disabled setter disables hidden and hiddenText', () => {
        setupController();
        controller.disabled = true;
        expect(controller.hiddenTarget.disabled).toBe(true);
        expect(controller.hiddenTextTarget.disabled).toBe(true);
    });

    test('disabled setter with value present keeps input disabled and clearBtn inherits disabled', () => {
        setupController();
        controller.inputTarget.value = 'Test';
        controller.disabled = true;

        expect(controller.inputTarget.disabled).toBe(true);
        expect(controller.clearBtnTarget.disabled).toBe(true);
    });

    test('disabled setter with empty value disables input and clearBtn', () => {
        setupController();
        controller.inputTarget.value = '';
        controller.disabled = true;

        expect(controller.inputTarget.disabled).toBe(true);
        expect(controller.clearBtnTarget.disabled).toBe(true);
    });

    // ==================== options getter/setter ====================

    test('options getter returns _selectOptions', () => {
        setupController();
        controller._selectOptions = [{ value: '1', text: 'A' }];
        expect(controller.options).toEqual([{ value: '1', text: 'A' }]);
    });

    test('options setter updates _selectOptions and calls makeDataListItems', () => {
        setupController({
            dataListContent: JSON.stringify([{ value: '1', text: 'Old' }])
        });
        const spy = jest.spyOn(controller, 'makeDataListItems');
        controller.options = [{ value: '2', text: 'New' }];

        expect(controller._selectOptions).toEqual([{ value: '2', text: 'New' }]);
        expect(spy).toHaveBeenCalled();
    });

    // ==================== clear ====================

    test('clear resets all fields and enables input', () => {
        setupController();
        controller.inputTarget.value = 'Test';
        controller.hiddenTarget.value = '1';
        controller.hiddenTextTarget.value = 'Test';
        controller.clearBtnTarget.disabled = false;
        controller.inputTarget.disabled = true;

        controller.clear();

        expect(controller.inputTarget.value).toBe('');
        expect(controller.hiddenTarget.value).toBe('');
        expect(controller.hiddenTextTarget.value).toBe('');
        expect(controller.clearBtnTarget.disabled).toBe(true);
        expect(controller.inputTarget.disabled).toBe(false);
    });

    // ==================== open / close ====================

    test('open sets aria-expanded and shows results', () => {
        setupController();
        controller.resultsShown = false;
        controller.open();

        expect(controller.element.getAttribute('aria-expanded')).toBe('true');
        expect(controller.resultsTarget.hidden).toBe(false);
    });

    test('open dispatches toggle event with action open', () => {
        setupController();
        controller.resultsShown = false;
        const handler = jest.fn();
        controller.element.addEventListener('toggle', handler);
        controller.open();
        expect(handler).toHaveBeenCalled();
        expect(handler.mock.calls[0][0].detail.action).toBe('open');
    });

    test('open is idempotent when already shown', () => {
        setupController();
        controller.resultsShown = true;
        const handler = jest.fn();
        controller.element.addEventListener('toggle', handler);
        controller.open();
        expect(handler).not.toHaveBeenCalled();
    });

    test('close hides results and sets aria-expanded false', () => {
        setupController();
        controller.resultsShown = true;
        controller.close();

        expect(controller.element.getAttribute('aria-expanded')).toBe('false');
        expect(controller.resultsTarget.hidden).toBe(true);
    });

    test('close dispatches toggle event with action close', () => {
        setupController();
        controller.resultsShown = true;
        const handler = jest.fn();
        controller.element.addEventListener('toggle', handler);
        controller.close();
        expect(handler).toHaveBeenCalled();
        expect(handler.mock.calls[0][0].detail.action).toBe('close');
    });

    test('close is idempotent when already hidden', () => {
        setupController();
        controller.resultsShown = false;
        const handler = jest.fn();
        controller.element.addEventListener('toggle', handler);
        controller.close();
        expect(handler).not.toHaveBeenCalled();
    });

    // ==================== resultsShown getter/setter ====================

    test('resultsShown returns negation of results hidden', () => {
        setupController();
        controller.resultsTarget.hidden = false;
        expect(controller.resultsShown).toBe(true);

        controller.resultsTarget.hidden = true;
        expect(controller.resultsShown).toBe(false);
    });

    test('resultsShown setter toggles hidden attribute', () => {
        setupController();
        controller.resultsShown = true;
        expect(controller.resultsTarget.hidden).toBe(false);

        controller.resultsShown = false;
        expect(controller.resultsTarget.hidden).toBe(true);
    });

    // ==================== Keyboard navigation ====================

    test('onEscapeKeydown closes results when shown', () => {
        setupController();
        controller.resultsShown = true;
        controller.resultsTarget.innerHTML = '<li role="option">Test</li>';

        const event = {
            key: 'Escape',
            stopPropagation: jest.fn(),
            preventDefault: jest.fn()
        };
        controller.onEscapeKeydown(event);

        expect(controller.resultsTarget.hidden).toBe(true);
        expect(controller.resultsTarget.innerHTML).toBe('');
        expect(event.stopPropagation).toHaveBeenCalled();
        expect(event.preventDefault).toHaveBeenCalled();
    });

    test('onEscapeKeydown does nothing when results hidden', () => {
        setupController();
        controller.resultsShown = false;

        const event = {
            key: 'Escape',
            stopPropagation: jest.fn(),
            preventDefault: jest.fn()
        };
        controller.onEscapeKeydown(event);

        expect(event.stopPropagation).not.toHaveBeenCalled();
    });

    test('onArrowDownKeydown calls preventDefault and selects via sibling', () => {
        setupController();
        const mockElement = document.createElement('li');
        mockElement.id = 'mock-opt';
        mockElement.scrollIntoView = jest.fn();
        jest.spyOn(controller, 'sibling').mockReturnValue(mockElement);

        const event = { key: 'ArrowDown', preventDefault: jest.fn() };
        controller.onArrowDownKeydown(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.sibling).toHaveBeenCalledWith(true);
    });

    test('onArrowDownKeydown selects first rendered combobox option from object-backed list', () => {
        setupController();
        controller.resultsTarget.innerHTML = `
            <li id="opt-1" role="option" data-ac-value="1" aria-selected="false">General</li>
            <li id="opt-2" role="option" data-ac-value="2" aria-selected="false">Service</li>
        `;
        controller.resultsTarget.querySelectorAll('li').forEach((item) => {
            item.scrollIntoView = jest.fn();
        });
        controller._selectOptions = [
            { value: '1', text: 'General' },
            { value: '2', text: 'Service' },
        ];
        controller.resultsShown = true;

        controller.onArrowDownKeydown({ key: 'ArrowDown', preventDefault: jest.fn() });

        expect(controller.resultsTarget.querySelector('#opt-1').getAttribute('aria-selected')).toBe('true');
        expect(controller.inputTarget.getAttribute('aria-activedescendant')).toBe('opt-1');
    });

    test('onArrowUpKeydown calls preventDefault and selects via sibling', () => {
        setupController();
        const mockElement = document.createElement('li');
        mockElement.id = 'mock-opt';
        mockElement.scrollIntoView = jest.fn();
        jest.spyOn(controller, 'sibling').mockReturnValue(mockElement);

        const event = { key: 'ArrowUp', preventDefault: jest.fn() };
        controller.onArrowUpKeydown(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(controller.sibling).toHaveBeenCalledWith(false);
    });

    test('onEnterKeydown commits when selectedOption exists and results shown', () => {
        setupController();
        controller.resultsTarget.innerHTML = `
            <li id="opt-1" role="option" aria-selected="true" data-ac-value="1" data-ac-label="Option 1">Option 1</li>
        `;
        controller.resultsShown = true;

        // Define selectedOption getter that queries the DOM
        Object.defineProperty(controller, 'selectedOption', {
            get: () => controller.resultsTarget.querySelector("[aria-selected='true']"),
            configurable: true
        });
        jest.spyOn(controller, 'commit').mockImplementation(() => {});

        const event = { key: 'Enter', preventDefault: jest.fn() };
        controller.onEnterKeydown(event);

        expect(controller.commit).toHaveBeenCalled();
    });

    test('onEnterKeydown does nothing with no selected option', () => {
        setupController();
        controller.resultsShown = true;
        controller.resultsTarget.innerHTML = '<li role="option">Test</li>';

        Object.defineProperty(controller, 'selectedOption', {
            get: () => controller.resultsTarget.querySelector("[aria-selected='true']"),
            configurable: true
        });
        jest.spyOn(controller, 'commit');

        const event = { key: 'Enter', preventDefault: jest.fn() };
        controller.onEnterKeydown(event);

        expect(controller.commit).not.toHaveBeenCalled();
    });

    // ==================== commit ====================

    test('commit sets input and hidden values from selected element', () => {
        setupController();
        const selected = document.createElement('li');
        selected.setAttribute('data-ac-value', '42');
        selected.setAttribute('data-ac-label', 'John Doe');
        selected.setAttribute('role', 'option');
        selected.id = 'opt-1';

        controller.commit(selected);

        expect(controller.inputTarget.value).toBe('John Doe');
        expect(controller.hiddenTarget.value).toBe('42');
        expect(controller.hiddenTextTarget.value).toBe('John Doe');
    });

    test('commit uses textContent when no data-ac-label', () => {
        setupController();
        const selected = document.createElement('li');
        selected.setAttribute('data-ac-value', '7');
        selected.setAttribute('role', 'option');
        selected.id = 'opt-1';
        selected.textContent = '  Trimmed Text  ';

        controller.commit(selected);

        expect(controller.inputTarget.value).toBe('Trimmed Text');
        expect(controller.hiddenTarget.value).toBe('7');
    });

    test('commit skips aria-disabled elements', () => {
        setupController();
        const selected = document.createElement('li');
        selected.setAttribute('aria-disabled', 'true');
        selected.setAttribute('data-ac-value', '1');
        selected.setAttribute('role', 'option');
        selected.id = 'opt-1';

        controller.commit(selected);

        expect(controller.hiddenTarget.value).toBe('');
    });

    test('commit dispatches autocomplete.change event', () => {
        setupController();
        const handler = jest.fn();
        controller.element.addEventListener('autocomplete.change', handler);

        const selected = document.createElement('li');
        selected.setAttribute('data-ac-value', '5');
        selected.setAttribute('data-ac-label', 'Test');
        selected.setAttribute('role', 'option');
        selected.id = 'opt-1';

        controller.commit(selected);

        expect(handler).toHaveBeenCalled();
        const detail = handler.mock.calls[0][0].detail;
        expect(detail.value).toBe('5');
        expect(detail.textValue).toBe('Test');
    });

    test('commit hides results after committing', () => {
        setupController();
        controller.resultsShown = true;
        controller.resultsTarget.innerHTML = '<li role="option" id="o1">Test</li>';

        const selected = document.createElement('li');
        selected.setAttribute('data-ac-value', '1');
        selected.setAttribute('role', 'option');
        selected.id = 'opt-1';
        selected.textContent = 'Test';

        controller.commit(selected);

        expect(controller.resultsTarget.innerHTML).toBe('');
    });

    // ==================== fireChangeEvent ====================

    test('fireChangeEvent dispatches autocomplete.change and change events', () => {
        setupController();
        const acHandler = jest.fn();
        const changeHandler = jest.fn();
        controller.element.addEventListener('autocomplete.change', acHandler);
        controller.element.addEventListener('change', changeHandler);

        controller.inputTarget.value = 'Test';
        controller.fireChangeEvent('42', 'Test', null);

        expect(acHandler).toHaveBeenCalled();
        expect(changeHandler).toHaveBeenCalled();
        expect(acHandler.mock.calls[0][0].detail).toEqual({
            value: '42',
            textValue: 'Test',
            selected: null
        });
    });

    test('fireChangeEvent disables input and enables clearBtn when value present', () => {
        setupController();
        controller.inputTarget.value = 'Test';
        controller.fireChangeEvent('1', 'Test', null);

        expect(controller.inputTarget.disabled).toBe(true);
        expect(controller.clearBtnTarget.disabled).toBe(false);
    });

    test('fireChangeEvent enables input and disables clearBtn when input empty', () => {
        setupController();
        controller.inputTarget.value = '';
        controller.fireChangeEvent('', '', null);

        expect(controller.inputTarget.disabled).toBe(false);
        expect(controller.clearBtnTarget.disabled).toBe(true);
    });

    // ==================== Fetch / AJAX ====================

    test('buildURL constructs URL with query param', () => {
        setupController({ url: 'http://localhost/search' });
        const result = controller.buildURL('test query');
        expect(result).toContain('q=test+query');
        expect(result).toContain('/search');
    });

    test('buildURL uses custom queryParam', () => {
        setupController({ url: 'http://localhost/search', queryParam: 'term' });
        const result = controller.buildURL('hello');
        expect(result).toContain('term=hello');
    });

    test('doFetch calls fetch with correct headers', async () => {
        setupController({ url: '/search' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<li>Result</li>')
        });

        const html = await controller.doFetch('/search?q=test');

        expect(global.fetch).toHaveBeenCalledWith('/search?q=test', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        expect(html).toBe('<li>Result</li>');
    });

    test('doFetch throws on non-ok response', async () => {
        setupController({ url: '/search' });
        global.fetch = jest.fn().mockResolvedValue({
            ok: false,
            status: 500
        });

        await expect(controller.doFetch('/search?q=test')).rejects.toThrow('Server responded with status 500');
    });

    test('fetchResults with dataList filters locally', async () => {
        setupController({
            dataListContent: JSON.stringify([
                { value: '1', text: 'Apple' },
                { value: '2', text: 'Banana' },
                { value: '3', text: 'Avocado' }
            ])
        });
        controller._selectOptions = [
            { value: '1', text: 'Apple' },
            { value: '2', text: 'Banana' },
            { value: '3', text: 'Avocado' }
        ];
        controller.state = 'start';

        await controller.fetchResults('ap');

        const items = controller.resultsTarget.querySelectorAll('[role="option"]');
        expect(items.length).toBe(1); // "Apple" matches "ap"
        expect(items[0].getAttribute('data-ac-value')).toBe('1');
    });

    test('fetchResults with empty query shows all datalist items', async () => {
        setupController({
            dataListContent: JSON.stringify([
                { value: '1', text: 'Apple' },
                { value: '2', text: 'Banana' }
            ])
        });
        controller._selectOptions = [
            { value: '1', text: 'Apple' },
            { value: '2', text: 'Banana' }
        ];
        controller.state = 'start';

        await controller.fetchResults('');

        const items = controller.resultsTarget.querySelectorAll('[role="option"]');
        expect(items.length).toBe(2);
    });

    test('fetchResults with URL calls doFetch and replaces results', async () => {
        setupController({ url: '/members/search' });
        controller.state = 'start';

        jest.spyOn(controller, 'doFetch').mockResolvedValue(
            '<li role="option" data-ac-value="1">John</li>'
        );

        await controller.fetchResults('john');

        expect(controller.doFetch).toHaveBeenCalled();
        expect(controller.resultsTarget.innerHTML).toContain('John');
    });

    test('fetchResults with no URL and no dataList throws error', async () => {
        setupController();
        controller.hasUrlValue = false;
        controller.hasDataListTarget = false;

        await expect(controller.fetchResults('test')).rejects.toThrow(
            'You must provide a URL or a DataList target'
        );
    });

    // ==================== replaceResults ====================

    test('replaceResults sets inner HTML and identifies options', () => {
        setupController();
        controller.replaceResults('<li role="option">Option</li>');

        expect(controller.resultsTarget.innerHTML).toContain('Option');
    });

    // ==================== DataList target connected ====================

    test('dataListTargetConnected loads options from JSON', () => {
        setupController({
            dataListContent: JSON.stringify([
                { value: '1', text: 'Alpha' },
                { value: '2', text: 'Beta' }
            ])
        });

        controller.dataListTargetConnected();

        expect(controller._selectOptions).toEqual([
            { value: '1', text: 'Alpha' },
            { value: '2', text: 'Beta' }
        ]);
        expect(controller._datalistLoaded).toBe(true);
    });

    // ==================== addOption ====================

    test('addOption adds a valid option and updates dataList', () => {
        setupController({
            dataListContent: JSON.stringify([])
        });
        controller._selectOptions = [];
        const spy = jest.spyOn(controller, 'makeDataListItems');

        controller.addOption({ value: '10', text: 'New Option' });

        expect(controller._selectOptions).toEqual([{ value: '10', text: 'New Option' }]);
        expect(spy).toHaveBeenCalled();
    });

    test('addOption ignores invalid option without value/text', () => {
        setupController();
        controller._selectOptions = [];
        controller.addOption({ name: 'bad' });
        expect(controller._selectOptions).toEqual([]);
    });

    // ==================== makeDataListItems ====================

    test('makeDataListItems serializes options to dataList', () => {
        setupController({
            dataListContent: JSON.stringify([])
        });
        controller._selectOptions = [{ value: '1', text: 'One' }];
        controller.makeDataListItems();

        expect(controller.dataListTarget.textContent).toBe(JSON.stringify([{ value: '1', text: 'One' }]));
    });

    // ==================== initSelectionValueChanged ====================

    test('initSelectionValueChanged sets value when datalist loaded', () => {
        setupController({
            dataListContent: JSON.stringify([{ value: '5', text: 'Five' }])
        });
        controller._selectOptions = [{ value: '5', text: 'Five' }];
        controller._datalistLoaded = true;
        controller.hasInitSelectionValue = true;
        controller.initSelectionValue = { value: '5', text: 'Five' };

        controller.initSelectionValueChanged();

        expect(controller.hiddenTarget.value).toBe('5');
        expect(controller.inputTarget.value).toBe('Five');
    });

    test('initSelectionValueChanged early returns with null initSelectionValue when datalist loaded', () => {
        setupController();
        controller._datalistLoaded = true;
        controller.initSelectionValue = null;
        // Should not throw
        controller.initSelectionValueChanged();
        expect(controller.hiddenTarget.value).toBe('');
    });

    test('initSelectionValueChanged sets hidden fields when datalist not loaded', () => {
        setupController();
        controller._datalistLoaded = false;
        controller.initSelectionValue = { value: '3', text: 'Three' };

        controller.initSelectionValueChanged();

        expect(controller.hiddenTarget.value).toBe('3');
        expect(controller.hiddenTextTarget.value).toBe('Three');
        expect(controller.inputTarget.value).toBe('Three');
        expect(controller.inputTarget.disabled).toBe(true);
        expect(controller.clearBtnTarget.disabled).toBe(false);
    });

    // ==================== select ====================

    test('select marks target as aria-selected and adds class', () => {
        setupController();
        controller.resultsTarget.innerHTML = `
            <li id="opt-1" role="option">A</li>
            <li id="opt-2" role="option">B</li>
        `;
        const opt2 = controller.resultsTarget.querySelector('#opt-2');
        opt2.scrollIntoView = jest.fn();

        controller.select(opt2);

        expect(opt2.getAttribute('aria-selected')).toBe('true');
        expect(opt2.classList.contains('active')).toBe(true);
        expect(controller.inputTarget.getAttribute('aria-activedescendant')).toBe('opt-2');
    });

    test('select removes selection from previously selected', () => {
        setupController();
        controller.resultsTarget.innerHTML = `
            <li id="opt-1" role="option" aria-selected="true" class="active">A</li>
            <li id="opt-2" role="option">B</li>
        `;
        const opt1 = controller.resultsTarget.querySelector('#opt-1');
        const opt2 = controller.resultsTarget.querySelector('#opt-2');
        opt2.scrollIntoView = jest.fn();

        // Define selectedOption getter for the controller
        Object.defineProperty(controller, 'selectedOption', {
            get: () => controller.resultsTarget.querySelector("[aria-selected='true']"),
            configurable: true
        });

        controller.select(opt2);

        expect(opt1.getAttribute('aria-selected')).toBeNull();
        expect(opt1.classList.contains('active')).toBe(false);
        expect(opt2.getAttribute('aria-selected')).toBe('true');
    });

    test('select resolves object-backed option to rendered result item', () => {
        setupController();
        controller.resultsTarget.innerHTML = `
            <li id="opt-1" role="option" data-ac-value="1" aria-selected="false">General</li>
            <li id="opt-2" role="option" data-ac-value="2" aria-selected="false">Service</li>
        `;
        const opt2 = controller.resultsTarget.querySelector('#opt-2');
        opt2.scrollIntoView = jest.fn();

        controller.select({ value: '2', text: 'Service' });

        expect(opt2.getAttribute('aria-selected')).toBe('true');
        expect(opt2.classList.contains('active')).toBe(true);
        expect(controller.inputTarget.getAttribute('aria-activedescendant')).toBe('opt-2');
    });

    // ==================== selectedOption / sibling ====================

    test('selectedOption returns aria-selected element', () => {
        setupController();
        controller.resultsTarget.innerHTML = `
            <li id="opt-1" role="option" aria-selected="true">A</li>
            <li id="opt-2" role="option">B</li>
        `;

        // selectedOption uses activeSelector internally via get
        const selected = controller.resultsTarget.querySelector("[aria-selected='true']");
        expect(selected).not.toBeNull();
        expect(selected.id).toBe('opt-1');
    });

    // ==================== onInputBlur ====================

    test('onInputBlur closes results when state is open and mouseDown false', () => {
        setupController();
        controller.mouseDown = false;
        controller.state = 'open';
        controller.resultsShown = true;
        controller.inputTarget.value = '';
        controller.allowOtherValue = false;

        controller.onInputBlur();

        expect(controller.resultsTarget.hidden).toBe(true);
    });

    test('onInputBlur does nothing when mouseDown is true', () => {
        setupController();
        controller.mouseDown = true;
        controller.state = 'open';
        controller.resultsShown = true;

        controller.onInputBlur();

        // Results remain shown when mouse is held down (clicking result)
        expect(controller.resultsTarget.hidden).toBe(false);
    });

    // ==================== onTabKeydown ====================

    test('onTabKeydown with allowOther fires change event', () => {
        setupController({ allowOther: true });
        controller.inputTarget.value = 'Custom Value';
        const handler = jest.fn();
        controller.element.addEventListener('autocomplete.change', handler);

        controller.onTabKeydown({});

        expect(handler).toHaveBeenCalled();
    });

    test('onTabKeydown without allowOther matches existing option', () => {
        setupController({ allowOther: false });
        controller._selectOptions = [{ value: '1', text: 'Known Item' }];
        controller.inputTarget.value = 'Known Item';

        controller.onTabKeydown({});

        expect(controller.hiddenTarget.value).toBe('1');
    });

    test('onTabKeydown with empty input calls clear', () => {
        setupController({ allowOther: false });
        controller.inputTarget.value = '';
        jest.spyOn(controller, 'clear');

        controller.onTabKeydown({});

        expect(controller.clear).toHaveBeenCalled();
    });

    // ==================== Disconnect ====================

    test('disconnect removes event listeners from input', () => {
        setupController();
        const removeSpy = jest.spyOn(controller.inputTarget, 'removeEventListener');
        controller.connect();
        controller.disconnect();

        expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('blur', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('input', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('change', expect.any(Function));
    });

    test('disconnect removes event listeners from results', () => {
        setupController();
        const removeSpy = jest.spyOn(controller.resultsTarget, 'removeEventListener');
        controller.connect();
        controller.disconnect();

        expect(removeSpy).toHaveBeenCalledWith('mousedown', expect.any(Function));
        expect(removeSpy).toHaveBeenCalledWith('click', expect.any(Function));
    });

    // ==================== optionsForFetch ====================

    test('optionsForFetch returns correct headers', () => {
        setupController();
        const opts = controller.optionsForFetch();
        expect(opts.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(opts.headers['Accept']).toBe('application/json');
    });

    // ==================== shimElement ====================

    test('shimElement defines value property on element', () => {
        setupController();
        controller.shimElement();

        controller.element.value = { value: '10', text: 'Shimmed' };
        expect(controller.inputTarget.value).toBe('Shimmed');
        expect(controller.hiddenTarget.value).toBe('10');

        expect(controller.element.value).toBe('10');
    });

    test('shimElement defines options property on element', () => {
        setupController();
        controller.shimElement();

        controller.element.options = [{ value: '1', text: 'X' }];
        expect(controller._selectOptions).toEqual([{ value: '1', text: 'X' }]);
    });

    test('shimElement defines disabled property on element', () => {
        setupController();
        controller.shimElement();

        controller.element.disabled = true;
        expect(controller.hiddenTarget.disabled).toBe(true);
    });
});
