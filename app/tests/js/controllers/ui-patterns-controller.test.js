import '../../../assets/js/controllers/ui-patterns-controller.js';

const UiPatternsController = window.Controllers['ui-patterns'];

/** Bind live target getters like Stimulus, including newly inserted agenda entries. */
function mount(markup, path = '/ui-patterns?pattern=record&state=normal&record=branch') {
    window.history.replaceState({}, '', path);
    document.body.innerHTML = `<main data-controller="ui-patterns">${markup}<p role="status" data-ui-patterns-target="feedback"></p></main>`;
    const controller = new UiPatternsController();
    controller.element = document.querySelector('main');
    for (const target of UiPatternsController.targets) {
        Object.defineProperty(controller, `${target}Targets`, {
            get: () => [...controller.element.querySelectorAll(`[data-ui-patterns-target~="${target}"]`)],
        });
    }
    controller.connect();
    return controller;
}

function eventFor(node) {
    return { currentTarget: node, preventDefault: jest.fn() };
}

const task = `
    <form data-message="Waiver submitted in this preview.">
      <nav>${[1, 2, 3].map(step => `<button type="button" data-ui-patterns-target="stepButton" data-step="${step}">Step ${step}</button>`).join('')}</nav>
      <section data-ui-patterns-target="step" data-step="1"><h2 tabindex="-1" data-ui-patterns-target="stepHeading">Waiver details</h2><select required data-ui-patterns-target="waiverType"><option value="">Choose</option><option value="adult">Adult waiver</option></select></section>
      <section data-ui-patterns-target="step" data-step="2" hidden><h2 tabindex="-1" data-ui-patterns-target="stepHeading">Add file</h2><input type="file" data-ui-patterns-target="file"><p data-ui-patterns-target="fileName"></p></section>
      <section data-ui-patterns-target="step" data-step="3" hidden><h2 tabindex="-1" data-ui-patterns-target="stepHeading">Review</h2><span data-ui-patterns-target="reviewType"></span><span data-ui-patterns-target="reviewFile"></span><input type="checkbox" required id="attestation"></section>
      <button type="button" data-ui-patterns-target="previousButton">Previous</button><button type="button" data-ui-patterns-target="nextButton">Continue</button><button type="submit" data-ui-patterns-target="submitButton">Submit waiver</button>
    </form>`;

function agendaItem(id, title) {
    return `<li data-ui-patterns-target="agendaItem" data-item-id="${id}"><h3 data-ui-patterns-target="agendaTitle">${title}</h3><button data-action="ui-patterns#moveUp">Up</button><button data-action="ui-patterns#moveDown">Down</button></li>`;
}

describe('UI pattern showcase interactions', () => {
    let controller;

    afterEach(() => {
        controller?.disconnect();
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        window.bootstrap = { Tooltip: jest.fn() };
    });

    test('record section state preserves other parameters and removes its listener on disconnect', () => {
        controller = mount('<button data-bs-toggle="tab" id="branch-members">Members</button>', '/ui-patterns?pattern=record&q=active&state=restricted');
        document.querySelector('#branch-members').dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
        expect(new URL(window.location.href).searchParams.get('section')).toBe('branch-members');
        expect(new URL(window.location.href).searchParams.get('q')).toBe('active');
        controller.disconnect();
        window.history.replaceState({}, '', '/ui-patterns?pattern=record');
        document.querySelector('#branch-members').dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));
        expect(new URL(window.location.href).searchParams.has('section')).toBe(false);
    });

    test('focusing clipped record tabs reveals either edge without scrolling the page vertically', () => {
        controller = mount(`<div role="tablist">
            <button data-bs-toggle="tab" id="record-overview">Overview</button>
            <button data-bs-toggle="tab" id="record-gatherings">Gatherings</button></div>`);
        const list = document.querySelector('[role="tablist"]');
        const first = document.querySelector('#record-overview');
        const last = document.querySelector('#record-gatherings');
        Object.defineProperties(list, { clientWidth: { value: 296 }, scrollWidth: { value: 333 } });
        list.getBoundingClientRect = () => ({ left: 12, right: 308 });
        first.getBoundingClientRect = () => ({ left: 12 - list.scrollLeft, right: 122 - list.scrollLeft });
        last.getBoundingClientRect = () => ({ left: 223 - list.scrollLeft, right: 345 - list.scrollLeft });
        list.scrollTop = 64;
        const scrollPage = jest.spyOn(window, 'scrollTo').mockImplementation(() => {});

        last.focus({ preventScroll: true });
        expect(list.scrollLeft).toBe(37);
        expect(last).toHaveFocus();
        first.focus({ preventScroll: true });
        expect(list.scrollLeft).toBe(0);
        expect(list.scrollTop).toBe(64);
        expect(scrollPage).not.toHaveBeenCalled();

        controller.disconnect();
        last.focus({ preventScroll: true });
        expect(list.scrollLeft).toBe(0);
    });

    test('shown record tabs are revealed without taking focus and preserve other URL state', () => {
        controller = mount(`<input aria-label="Other control"><div role="tablist">
            <button data-bs-toggle="tab" id="record-gatherings">Gatherings</button></div>`,
        '/ui-patterns?pattern=record&record=branch&view_id=royal#results');
        const list = document.querySelector('[role="tablist"]');
        const tab = document.querySelector('#record-gatherings');
        Object.defineProperties(list, { clientWidth: { value: 296 }, scrollWidth: { value: 333 } });
        list.getBoundingClientRect = () => ({ left: 12, right: 308 });
        tab.getBoundingClientRect = () => ({ left: 223, right: 345 });
        document.querySelector('input').focus();

        tab.dispatchEvent(new Event('shown.bs.tab', { bubbles: true }));

        expect(list.scrollLeft).toBe(37);
        expect(document.querySelector('input')).toHaveFocus();
        const url = new URL(window.location.href);
        expect(url.searchParams.get('section')).toBe('record-gatherings');
        expect(url.searchParams.get('view_id')).toBe('royal');
        expect(url.hash).toBe('#results');
    });

    test('URL-restored sections reveal even an already active tab that emits no shown event', () => {
        window.bootstrap.Tab = { getOrCreateInstance: jest.fn(tab => ({ show: () => {
            const list = tab.closest('[role="tablist"]');
            Object.defineProperties(list, { clientWidth: { value: 296 }, scrollWidth: { value: 333 } });
            list.getBoundingClientRect = () => ({ left: 12, right: 308 });
            tab.getBoundingClientRect = () => ({ left: 223, right: 345 });
        } })) };
        controller = mount('<div role="tablist"><button class="active" data-bs-toggle="tab" id="record-gatherings">Gatherings</button></div>',
            '/ui-patterns?pattern=record&section=record-gatherings');
        expect(document.querySelector('[role="tablist"]').scrollLeft).toBe(37);
        expect(window.bootstrap.Tab.getOrCreateInstance).toHaveBeenCalledWith(document.querySelector('#record-gatherings'));
    });

    test('preview form submission stays local and treats entered text as text', () => {
        const hide = jest.fn();
        window.bootstrap.Modal = { getInstance: jest.fn(() => ({ hide })) };
        controller = mount('<h1>Original</h1><div class="modal"><form data-update-target="h1" data-update-field="name"><input name="name" required><button>Save changes</button></form></div>');
        document.querySelector('input').value = '<img src=x onerror=alert(1)>';
        const form = document.querySelector('form');
        const event = eventFor(form);
        const nativeSubmit = jest.spyOn(form, 'submit');
        controller.savePreview(event);
        expect(event.preventDefault).toHaveBeenCalled();
        expect(nativeSubmit).not.toHaveBeenCalled();
        expect(document.querySelector('h1').textContent).toBe('<img src=x onerror=alert(1)>');
        expect(document.querySelector('h1 img')).toBeNull();
        expect(hide).toHaveBeenCalled();
        expect(controller.feedbackTargets[0]).toHaveTextContent('Changes saved in this preview.');
    });

    test('task validates each step, focuses its heading, and never uploads the sample file', () => {
        controller = mount(task, '/ui-patterns?pattern=task');
        const next = eventFor(controller.nextButtonTargets[0]);
        controller.nextStep(next);
        expect(controller.currentStep).toBe(1);
        expect(controller.waiverTypeTargets[0]).toHaveFocus();
        controller.waiverTypeTargets[0].value = 'adult';
        controller.nextStep(next);
        expect(controller.currentStep).toBe(2);
        expect(controller.stepHeadingTargets[1]).toHaveFocus();
        expect(controller.stepButtonTargets[1]).not.toBeDisabled();
        expect(controller.stepButtonTargets[2]).toBeDisabled();
        expect(controller.waiverTypeTargets[0]).toBeDisabled();
        controller.nextStep(next);
        expect(controller.currentStep).toBe(2);
        expect(controller.fileTargets[0]).toHaveFocus();
        controller.useSampleFile(eventFor(document.createElement('button')));
        controller.nextStep(next);
        expect(controller.currentStep).toBe(3);
        expect(controller.reviewTypeTargets[0]).toHaveTextContent('Adult waiver');
        expect(controller.reviewFileTargets[0]).toHaveTextContent('gate-waivers-sample.pdf');
        expect(controller.fileTargets[0].files).toHaveLength(0);
        expect(controller.submitButtonTargets[0]).not.toHaveAttribute('hidden');
        expect(controller.nextButtonTargets[0]).toHaveAttribute('hidden');
        const submit = eventFor(document.querySelector('form'));
        controller.savePreview(submit);
        expect(controller.feedbackTargets[0]).not.toHaveTextContent('Waiver submitted');
        document.querySelector('#attestation').checked = true;
        controller.savePreview(submit);
        expect(controller.feedbackTargets[0]).toHaveTextContent('Waiver submitted in this preview.');
        expect(submit.preventDefault).toHaveBeenCalledTimes(2);
    });

    test('agenda keyboard controls preserve focus and cannot cross court boundaries', () => {
        controller = mount(`<select data-ui-patterns-target="court"><option value="kingdom">Kingdom court</option><option value="baronial">Baronial court</option></select><h2 data-ui-patterns-target="courtName"></h2><ol data-ui-patterns-target="agendaList" data-court="kingdom">${agendaItem('a', 'Welcome')}${agendaItem('b', 'Award')}</ol><ol data-ui-patterns-target="agendaList" data-court="baronial">${agendaItem('c', 'Thanks')}</ol>`);
        const button = controller.agendaItemTargets[1].querySelector('button');
        button.focus();
        controller.moveUp(eventFor(button));
        expect([...controller.agendaListTargets[0].children].map(row => row.dataset.itemId)).toEqual(['b', 'a']);
        expect(button).toHaveFocus();
        expect(button).toHaveAttribute('aria-disabled', 'true');
        controller.courtTargets[0].value = 'baronial';
        controller.selectCourt();
        expect(controller.agendaListTargets[0]).toHaveAttribute('hidden');
        expect(controller.agendaListTargets[1]).not.toHaveAttribute('hidden');
        expect(controller.courtNameTargets[0]).toHaveTextContent('Baronial court');
    });

    test('a bestowal can be added only once across courts and the empty state recovers', () => {
        controller = mount('<select data-ui-patterns-target="court"><option value="kingdom">Kingdom</option><option value="baronial">Baronial</option></select><ol data-ui-patterns-target="agendaList" data-court="kingdom"></ol><ol data-ui-patterns-target="agendaList" data-court="baronial"></ol><p data-ui-patterns-target="agendaEmpty">Empty</p><button data-item-id="rowan" data-title="Rowan" data-detail="Award of arms">Add</button>');
        const button = document.querySelector('button');
        button.focus();
        controller.addAgendaItem(eventFor(button));
        expect(controller.agendaItemTargets).toHaveLength(1);
        expect(controller.agendaEmptyTargets[0]).toHaveAttribute('hidden');
        expect(button).toHaveFocus();
        expect(button).toHaveAttribute('aria-disabled', 'true');
        controller.courtTargets[0].value = 'baronial';
        controller.selectCourt();
        controller.addAgendaItem(eventFor(button));
        expect(controller.agendaItemTargets).toHaveLength(1);
        expect(controller.agendaListTargets[1].children).toHaveLength(0);
        expect(controller.agendaEmptyTargets[0]).not.toHaveAttribute('hidden');
    });

    test.each([
        ['oakmere', 'oakmere', '1'],
        ['unknown-branch', 'all', '2'],
    ])('restores supported dashboard scope from %s without rewriting the shared URL', (query, scope, count) => {
        const path = `/ui-patterns?pattern=dashboard&state=normal&branch=${query}#needs-attention`;
        controller = mount('<select data-ui-patterns-target="dashboardScope"><option value="all">All</option><option value="oakmere">Oakmere</option></select><article data-ui-patterns-target="dashboardItem" data-branch="oakmere">Missing waiver</article><article data-ui-patterns-target="dashboardItem" data-branch="willowbank">Lost waiver</article><strong data-ui-patterns-target="dashboardMetric" data-count-all="2" data-count-oakmere="1">2</strong>', path);
        expect(controller.dashboardScopeTargets[0].value).toBe(scope);
        expect(controller.dashboardMetricTargets[0]).toHaveTextContent(count);
        expect(controller.dashboardItemTargets[1].hidden).toBe(scope === 'oakmere');
        expect(window.location.pathname + window.location.search + window.location.hash).toBe(path);
    });

    test('dashboard scope changes the displayed exceptions and sample counts together', () => {
        controller = mount('<select data-ui-patterns-target="dashboardScope"><option value="all">All</option><option value="oakmere">Oakmere</option></select><article data-ui-patterns-target="dashboardItem" data-branch="oakmere">Missing waiver</article><article data-ui-patterns-target="dashboardItem" data-branch="willowbank">Lost waiver</article><strong data-ui-patterns-target="dashboardMetric" data-count-all="2" data-count-oakmere="1">2</strong>', '/ui-patterns?pattern=dashboard&state=normal');
        controller.dashboardScopeTargets[0].value = 'oakmere';
        controller.filterDashboard();
        expect(controller.dashboardItemTargets[1]).toHaveAttribute('hidden');
        expect(controller.dashboardMetricTargets[0]).toHaveTextContent('1');
        expect(new URL(window.location.href).searchParams.get('pattern')).toBe('dashboard');
        expect(new URL(window.location.href).searchParams.get('branch')).toBe('oakmere');
    });

    test('permission lookup validates before adding a local relationship row', () => {
        controller = mount('<table><tbody data-ui-patterns-target="permissions"></tbody></table><form><select name="permission" required><option value="">Choose</option><option value="Manage gatherings" data-scope="Assigned branch">Manage gatherings</option></select></form>');
        const form = document.querySelector('form');
        const event = eventFor(form);
        controller.addPermission(event);
        expect(controller.permissionsTargets[0].children).toHaveLength(0);
        form.querySelector('[name="permission"]').value = 'Manage gatherings';
        controller.addPermission(event);
        expect(controller.permissionsTargets[0]).toHaveTextContent('Manage gatherings');
        expect(controller.permissionsTargets[0]).toHaveTextContent('Assigned branch');
        expect(controller.permissionsTargets[0].querySelector('th')).toHaveAttribute('scope', 'row');
        expect(event.preventDefault).toHaveBeenCalledTimes(2);
    });
});
