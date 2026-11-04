import PublicCalendarController from '../../../assets/js/controllers/public-calendar-controller.js';

describe('Public calendar rows and activity picker', () => {
    let controller;
    beforeEach(() => {
        document.body.innerHTML = `<table><tbody><tr id="row"><td><a href="/event">Event</a><span id="address">Address</span>
            <button id="more">…</button><details data-panel="panel"><summary aria-expanded="false">Details</summary></details></td></tr>
            <tr id="panel" hidden><td>All activities</td></tr></tbody></table>
            <input id="search"><details id="martial" data-activity-type="Martial"><summary>Martial<span id="martial-count"></span></summary>
            <label id="a"><input name="activities[]" value="1" type="checkbox" checked><span class="kc-choice-name">Archery</span></label></details>
            <details id="arts" data-activity-type="Arts & Sciences"><summary>Arts & Sciences</summary>
            <label id="b"><input name="activities[]" value="2" type="checkbox"><span class="kc-choice-name">Bardic</span></label></details>
            <p id="status"></p><span id="count"></span>
            <div id="selection"><ul id="selection-labels"></ul></div><p id="selection-status" role="status"></p>`;
        controller = new PublicCalendarController();
        controller.activitySearchTarget = document.getElementById('search');
        controller.activityOptionTargets = [document.getElementById('a'), document.getElementById('b')];
        controller.activityGroupTargets = [document.getElementById('martial'), document.getElementById('arts')];
        controller.groupCountTargets = [document.getElementById('martial-count')];
        controller.activityStatusTarget = document.getElementById('status');
        controller.activityCountTarget = document.getElementById('count');
        controller.hasSelectedLabelsTarget = true;
        controller.selectedSummaryTarget = document.getElementById('selection');
        controller.selectedLabelsTarget = document.getElementById('selection-labels');
        controller.selectionStatusTarget = document.getElementById('selection-status');
        controller.pendingMessageValue = 'Selection changed. Apply filters to update events.';
    });
    afterEach(() => { document.body.innerHTML = ''; });

    test('selected names remain visible through searching and distinguish pending changes from applied filters', () => {
        controller.connect();
        expect(controller.selectedLabelsTarget).toHaveTextContent('Archery');
        expect(controller.selectionStatusTarget).toBeEmptyDOMElement();
        controller.activityOptionTargets[1].querySelector('input').checked = true;
        controller.updateCount();
        expect(Array.from(controller.selectedLabelsTarget.children, item => item.textContent)).toEqual(['Archery', 'Bardic']);
        expect(controller.selectionStatusTarget).toHaveTextContent('Apply filters to update events');
        controller.activitySearchTarget.value = 'no match';
        controller.filterActivities();
        expect(controller.selectedLabelsTarget).toHaveTextContent('Bardic');
        controller.activityOptionTargets[1].querySelector('input').checked = false;
        controller.updateCount();
        expect(controller.selectionStatusTarget).toBeEmptyDOMElement();
        controller.activityOptionTargets[0].querySelector('input').checked = false;
        controller.updateCount();
        expect(controller.selectedSummaryTarget).toHaveAttribute('hidden');
        expect(controller.selectionStatusTarget).toHaveTextContent('Apply filters');
    });

    test('selected names are rendered as text and restoring an applied empty selection hides the reminder', () => {
        controller.activityOptionTargets[0].querySelector('input').checked = false;
        controller.connect();
        controller.activityOptionTargets[1].querySelector('.kc-choice-name').textContent = '<img src=x onerror=alert(1)>';
        controller.activityOptionTargets[1].querySelector('input').checked = true;
        controller.updateCount();
        expect(controller.selectedLabelsTarget).toHaveTextContent('<img src=x onerror=alert(1)>');
        expect(controller.selectedLabelsTarget.querySelector('img')).toBeNull();
        controller.activityOptionTargets[1].querySelector('input').checked = false;
        controller.updateCount();
        expect(controller.selectedSummaryTarget).toHaveAttribute('hidden');
        expect(controller.selectionStatusTarget).toBeEmptyDOMElement();
    });

    test('keyboard focus is revealed below pinned filters without moving focus inside them', () => {
        const bar = document.createElement('form');
        const input = document.createElement('input');
        bar.append(input);
        document.body.prepend(bar);
        controller.hasFilterBarTarget = true;
        controller.filterBarTarget = bar;
        bar.getBoundingClientRect = () => ({ top: 0, bottom: 160 });
        const link = document.querySelector('#row a');
        link.getBoundingClientRect = () => ({ top: 80, bottom: 100 });
        const scroll = jest.spyOn(window, 'scrollBy').mockImplementation(() => {});
        controller.revealFocus({ target: link });
        expect(scroll).toHaveBeenCalledWith({ top: -92, behavior: 'instant' });
        scroll.mockClear();
        controller.revealFocus({ target: input });
        expect(scroll).not.toHaveBeenCalled();
        bar.getBoundingClientRect = () => ({ top: 200, bottom: 360 });
        controller.revealFocus({ target: link });
        expect(scroll).not.toHaveBeenCalled();
        scroll.mockRestore();
    });

    test('row clicks expand but event navigation and interactive controls remain independent', () => {
        const row = document.getElementById('row');
        const details = row.querySelector('details');
        controller.toggleRow({ target: document.getElementById('address'), currentTarget: row });
        expect(details.open).toBe(true);
        controller.toggleRow({ target: row.querySelector('a'), currentTarget: row });
        expect(details.open).toBe(true);
        controller.toggleRow({ target: row.querySelector('summary'), currentTarget: row });
        expect(details.open).toBe(true);
        controller.toggleRow({ target: document.getElementById('address'), currentTarget: row });
        expect(details.open).toBe(false);
    });

    test('overflow exposes full details, focuses its summary and synchronizes accessible state', () => {
        controller.expand({ currentTarget: document.getElementById('more'), stopPropagation: jest.fn() });
        const details = document.querySelector('details');
        controller.sync({ currentTarget: details });
        expect(document.getElementById('panel')).not.toHaveAttribute('hidden');
        expect(details.querySelector('summary')).toHaveAttribute('aria-expanded', 'true');
        expect(details.querySelector('summary')).toHaveFocus();
        details.open = false;
        controller.sync({ currentTarget: details });
        expect(document.getElementById('panel')).toHaveAttribute('hidden');
    });

    test('searching a large picker keeps hidden choices checked and reports empty matches', () => {
        controller.activitySearchTarget.value = 'bard';
        controller.filterActivities();
        expect(controller.activityOptionTargets[0]).toHaveAttribute('hidden');
        expect(controller.activityOptionTargets[0].querySelector('input')).toBeChecked();
        expect(controller.activityStatusTarget).toHaveTextContent('1 option shown');
        expect(controller.activityGroupTargets[0]).toHaveAttribute('hidden');
        expect(controller.activityGroupTargets[1]).not.toHaveAttribute('hidden');
        expect(controller.activityGroupTargets[1].open).toBe(true);
        controller.updateCount();
        expect(controller.activityCountTarget).toHaveTextContent('(1)');
        controller.activitySearchTarget.value = 'no match';
        controller.filterActivities();
        expect(controller.activityStatusTarget).toHaveTextContent('0 options shown');
    });

    test('search matches type headings and restores groups without clearing selected choices', () => {
        controller.activitySearchTarget.value = ' MARTIAL ';
        controller.filterActivities();
        expect(controller.activityOptionTargets[0]).not.toHaveAttribute('hidden');
        expect(controller.activityGroupTargets[0]).not.toHaveAttribute('hidden');
        expect(controller.activityGroupTargets[1]).toHaveAttribute('hidden');
        controller.activitySearchTarget.value = '';
        controller.filterActivities();
        expect(controller.activityGroupTargets.every(group => !group.hidden)).toBe(true);
        expect(controller.activityStatusTarget).toHaveTextContent('2 options shown');
        expect(controller.activityOptionTargets[0].querySelector('input')).toBeChecked();
        expect(controller.activityGroupTargets.every(group => !group.open)).toBe(true);
    });

    test('the compact summary counts royal, circle, and individual activity selections together', () => {
        document.body.insertAdjacentHTML('beforeend', `<label id="royal"><input name="royal_progress[]" checked type="checkbox"><span class="kc-choice-name">Crown</span></label>
            <label id="circle"><input name="circles" checked type="checkbox"><span class="kc-choice-name">Any order circle</span></label>`);
        controller.activityOptionTargets.push(document.getElementById('royal'), document.getElementById('circle'));
        controller.updateCount();
        expect(controller.activityCountTarget).toHaveTextContent('(3)');
        expect(controller.groupCountTargets[0]).toHaveTextContent('(1)');
    });
});
