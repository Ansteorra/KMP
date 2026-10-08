import KMPAccessibility from '../../assets/js/KMP_accessibility.js';
import '../../assets/js/controllers/page-context-controller.js';

describe('KMP_accessibility dialogs', () => {
    class ModalMock {
        constructor(element) {
            this.element = element;
        }

        show() {
            this.element.classList.add('show');
            this.element.dispatchEvent(new Event('shown.bs.modal'));
        }

        hide() {
            this.element.classList.remove('show');
            this.element.dispatchEvent(new Event('hidden.bs.modal'));
        }

        dispose() {}
    }

    beforeEach(() => {
        document.body.innerHTML = '<button id="dialog-trigger">Open dialog</button>';
        window.bootstrap.Modal = ModalMock;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete window.bootstrap.Modal;
    });

    test('keeps forward and reverse tab focus inside the confirmation dialog', async () => {
        const trigger = document.getElementById('dialog-trigger');
        trigger.focus();
        const result = KMPAccessibility.confirm('Synchronize open work?', {
            title: 'Synchronize open work',
            confirmLabel: 'Sync Now',
        });
        const modal = document.querySelector('.modal');
        const close = modal.querySelector('.btn-close');
        const confirm = modal.querySelector('[data-dialog-confirm]');

        expect(document.activeElement).toBe(confirm);
        const forwardTab = new KeyboardEvent('keydown', {
            key: 'Tab',
            bubbles: true,
            cancelable: true,
        });
        confirm.dispatchEvent(forwardTab);
        expect(forwardTab.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(close);

        const reverseTab = new KeyboardEvent('keydown', {
            key: 'Tab',
            shiftKey: true,
            bubbles: true,
            cancelable: true,
        });
        close.dispatchEvent(reverseTab);
        expect(reverseTab.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(confirm);

        modal.querySelector('[data-dialog-cancel]').click();
        await expect(result).resolves.toBe(false);
        expect(document.activeElement).toBe(trigger);
    });

    test('confirmed Cake postLinks prepare the embedded grid URL before native submission', async () => {
        window.history.replaceState({}, '', '/members/view/member-id?tab=officers');
        document.body.innerHTML = `
            <div data-controller="grid-view" data-grid-view-sync-url-value="false">
                <turbo-frame id="member-officers-grid-table" data-grid-current-src="/officers/officers/grid-data?page=3&amp;limit=50&amp;frame_id=member-officers-grid">
                    <form name="gridPostForm" action="/officers/officers/request-warrant/officer-id"></form>
                    <a id="post-link" href="/officers/officers/request-warrant/officer-id" data-confirm-message="Request warrant?" onclick="document.gridPostForm.requestSubmit(); return false;">Request Warrant</a>
                </turbo-frame>
            </div>
        `;
        const controller = new window.Controllers['page-context']();
        controller.connect();
        const submit = jest.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(function () {
            const origin = new URL(window.location.href);
            expect(this).toBe(document.forms.gridPostForm);
            expect(origin.searchParams.get('page')).toBe('3');
            expect(origin.searchParams.get('limit')).toBe('50');
            expect(origin.searchParams.get('tab')).toBe('officers');
            expect(origin.searchParams.has('frame_id')).toBe(false);
        });
        try {
            KMPAccessibility.installCakeConfirmAdapter();
            document.getElementById('post-link').click();
            document.querySelector('[data-dialog-confirm]').click();
            await new Promise(resolve => setTimeout(resolve, 0));
            expect(submit).toHaveBeenCalledTimes(1);
        } finally {
            controller.disconnect();
            submit.mockRestore();
        }
    });
});
