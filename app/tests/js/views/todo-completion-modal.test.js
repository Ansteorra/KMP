const fs = require('node:fs');
const path = require('node:path');

// Exercise the inline modal behavior shipped by the view, replacing only PHP translations.
const template = fs.readFileSync(path.join(__dirname, '../../../templates/ActionItems/my_tasks.php'), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/<\?=[\s\S]*?\?>/g, '"Label"');

describe('My To-Dos completion modal', () => {
    let modal;
    let form;
    const target = name => modal.querySelector(`[data-todo-target="${name}"]`);
    const gatheringInput = () => target('bestowalGatheringControl').querySelector('[data-ac-target="input"]');
    const eventForm = {
        fields: [{ type: 'autocomplete', url: '/gatherings', selection: null }],
    };
    const agendaForm = {
        fields: [{ type: 'select', options: { roaming: 'Roaming Court', 42: 'Evening Court' } }],
    };

    function open(completionForm = {}, mode = 'complete') {
        const button = document.createElement('button');
        button.dataset.todoCompletionForm = JSON.stringify(completionForm);
        button.dataset.todoMode = mode;
        const event = new Event('show.bs.modal');
        event.relatedTarget = button;
        modal.dispatchEvent(event);
    }

    beforeEach(() => {
        document.body.innerHTML = `<div id="todoCompleteModal" data-complete-url="/complete" data-reopen-url="/reopen">
            <form id="todoCompleteForm">
                <input data-todo-target="id">
                <h5 data-todo-target="modalTitle">Complete</h5>
                <p data-todo-target="prompt">Complete <strong data-todo-target="title"></strong></p>
                <section data-todo-target="specialForm" hidden>
                    <h6 data-todo-target="specialTitle"></h6>
                    <p data-todo-target="specialDescription" role="status"></p>
                    <div class="form-check"><input type="checkbox" data-todo-target="includePastGatherings"></div>
                    <div class="mb-3"><div data-todo-target="bestowalGatheringControl">
                        <input data-ac-target="input" name="gathering_name">
                        <input type="hidden" data-ac-target="hidden" name="bestowal_gathering_id">
                        <input type="hidden" data-ac-target="hiddenText" name="gathering_text">
                        <button type="button" data-ac-target="clearBtn">Clear</button>
                    </div></div>
                    <p data-todo-target="bestowalGatheringHelp"></p>
                    <p data-todo-target="bestowalGatheringError" hidden></p>
                    <div data-todo-target="courtSlotGroup" hidden>
                        <select data-todo-target="courtSlotSelect" name="gathering_scheduled_activity_id"></select>
                        <p data-todo-target="courtSlotHelp"></p>
                        <p data-todo-target="courtSlotError" hidden></p>
                    </div>
                </section>
                <div data-todo-target="completionOutcomeGroup" hidden>
                    <label data-todo-target="completionOutcomeLabel"></label>
                    <select data-todo-target="completionOutcomeSelect" disabled></select>
                    <p data-todo-target="completionOutcomeHelp"></p>
                </div>
                <button data-todo-target="submit" type="submit">Complete</button>
            </form>
        </div>`;
        modal = document.getElementById('todoCompleteModal');
        form = document.getElementById('todoCompleteForm');
        const ready = jest.spyOn(document, 'addEventListener').mockImplementationOnce((name, callback) => callback());
        new Function(script)();
        ready.mockRestore();
    });

    test('Add to Agenda validates the visible court picker and excludes hidden gathering controls', () => {
        open(agendaForm);
        expect(gatheringInput()).toBeDisabled();
        expect(gatheringInput()).not.toBeRequired();
        expect(target('courtSlotSelect')).toBeRequired();
        expect(form.checkValidity()).toBe(false);
        target('courtSlotSelect').value = '42';
        expect(form.checkValidity()).toBe(true);
        const data = new FormData(form);
        expect(data.get('gathering_scheduled_activity_id')).toBe('42');
        expect(data.has('bestowal_gathering_id')).toBe(false);
    });

    test('switching from scheduling to agenda clears the gathering and preserves form validity', () => {
        open({ fields: [{ ...eventForm.fields[0], selection: { value: '7', text: 'Event' } }] });
        expect(new FormData(form).get('bestowal_gathering_id')).toBe('7');
        open(agendaForm);
        expect(gatheringInput()).toBeDisabled();
        expect(gatheringInput()).not.toBeRequired();
        target('courtSlotSelect').value = 'roaming';
        expect(form.checkValidity()).toBe(true);
    });

    test('switching back to scheduling restores its visible required field', () => {
        open(agendaForm);
        open(eventForm);
        expect(gatheringInput()).toBeEnabled();
        expect(gatheringInput()).toBeRequired();
        expect(target('courtSlotSelect')).toBeDisabled();
        expect(target('courtSlotSelect')).not.toBeRequired();
        expect(form.checkValidity()).toBe(false);
    });

    test.each(['complete', 'reopen'])('ordinary %s leaves every hidden requirement disabled', mode => {
        open(eventForm);
        open({}, mode);
        expect(gatheringInput()).toBeDisabled();
        expect(gatheringInput()).not.toBeRequired();
        expect(target('courtSlotSelect')).toBeDisabled();
        expect(form.checkValidity()).toBe(true);
    });

    test('blocked agenda explains its prerequisite and cannot submit, then resets for another task', () => {
        const reason = 'Complete Event Scheduled before Added to Agenda can be completed.';
        open({ title: 'Add Bestowal to Agenda', fields: [], payload: { blockedReason: reason } });
        expect(target('specialForm').hidden).toBe(false);
        expect(target('specialDescription')).toHaveTextContent(reason);
        expect(target('submit')).toBeDisabled();
        expect(gatheringInput()).toBeDisabled();
        open(agendaForm);
        expect(target('submit')).toBeEnabled();
        expect(target('specialDescription')).not.toHaveTextContent(reason);
    });
});
