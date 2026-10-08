import '../../../assets/js/controllers/confirmation-controller.js';
import '../../../assets/js/controllers/page-context-controller.js';

test('confirmed inline forms prepare their embedded grid origin before direct native submit', async () => {
    window.history.replaceState({}, '', '/members/view/member-id?tab=authorizations');
    document.body.innerHTML = `
        <div data-controller="grid-view" data-grid-view-sync-url-value="false">
            <turbo-frame id="member-auth-grid-table" data-grid-current-src="/activities/authorizations/member-authorizations-grid-data?page=3&amp;limit=50&amp;system_view=pending&amp;member_id=7">
                <form action="/activities/authorizations/retract/authorization-id"><button id="retract" type="submit">Retract</button></form>
            </turbo-frame>
        </div>
    `;
    const pageContext = new window.Controllers['page-context']();
    pageContext.connect();
    const confirmation = new window.Controllers.confirmation();
    confirmation.hasSubmitSelectorValue = false;
    confirmation.messageValue = 'Retract request?';
    const originalConfirm = window.KMP_accessibility.confirm;
    window.KMP_accessibility.confirm = jest.fn().mockResolvedValue(true);
    const submit = jest.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {
        const origin = new URL(window.location.href);
        expect(origin.searchParams.get('page')).toBe('3');
        expect(origin.searchParams.get('limit')).toBe('50');
        expect(origin.searchParams.get('tab')).toBe('authorizations');
        expect(origin.searchParams.get('system_view')).toBe('pending');
        expect(origin.searchParams.has('member_id')).toBe(false);
    });
    try {
        await confirmation.confirm({
            currentTarget: document.getElementById('retract'),
            preventDefault: jest.fn(),
            stopPropagation: jest.fn(),
        });
        expect(submit).toHaveBeenCalledTimes(1);
    } finally {
        pageContext.disconnect();
        submit.mockRestore();
        window.KMP_accessibility.confirm = originalConfirm;
        document.body.replaceChildren();
    }
});
