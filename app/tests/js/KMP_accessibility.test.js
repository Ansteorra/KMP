import KMPAccessibility from '../../assets/js/KMP_accessibility.js';

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
});
