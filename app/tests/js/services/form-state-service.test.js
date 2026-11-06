import { installFormState, rememberForms, isDirty, dirtyWithin } from '../../../assets/js/services/form-state-service.js';

describe('draft preservation', () => {
    beforeAll(() => installFormState());
    beforeEach(() => {
        document.body.innerHTML = '<section id="region"><form method="post"><input name="note" value="Original"><input type="checkbox" name="share" value="1"></form></section><form method="get"><input name="search"></form>';
        rememberForms();
    });
    test('recognizes edits, checkbox changes, and restoration without treating searches as drafts', () => {
        const form = document.forms[0];
        expect(isDirty(form)).toBe(false);
        form.elements.note.value = 'Keep this';
        form.elements.note.dispatchEvent(new Event('input', { bubbles: true }));
        expect(dirtyWithin(document.getElementById('region'))).toBe(true);
        expect(dirtyWithin(form)).toBe(true);
        form.elements.note.value = 'Original';
        expect(isDirty(form)).toBe(false);
        form.elements.share.checked = true;
        expect(isDirty(form)).toBe(true);
        document.forms[1].elements.search.value = 'Changed search';
        expect(isDirty(document.forms[1])).toBe(false);
    });

    test('server/controller initialization and hidden context synchronization are not user edits', () => {
        const form = document.forms[0];
        form.elements.note.value = 'Server initialization';
        const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'page_context_url';
        hidden.value = '/members?page=3'; form.append(hidden);
        expect(isDirty(form)).toBe(false);
        rememberForms();
        form.elements.note.value = 'User draft';
        form.elements.note.dispatchEvent(new Event('input', { bubbles: true }));
        expect(isDirty(form)).toBe(true);
    });

    test('morphing preserves a dirty form and reset makes it eligible for reconciliation', async () => {
        const form = document.forms[0];
        form.elements.note.value = 'Unsubmitted draft';
        form.elements.note.dispatchEvent(new Event('input', { bubbles: true }));
        const morph = new CustomEvent('turbo:before-morph-element', { bubbles: true, cancelable: true });
        form.dispatchEvent(morph);
        expect(morph.defaultPrevented).toBe(true);
        const settled = jest.fn(); window.addEventListener('kmp:draft-settled', settled);
        form.reset();
        await Promise.resolve();
        expect(form.elements.note.value).toBe('Original');
        expect(isDirty(form)).toBe(false);
        expect(settled).toHaveBeenCalledTimes(1);
        const cleanMorph = new CustomEvent('turbo:before-morph-element', { bubbles: true, cancelable: true });
        form.dispatchEvent(cleanMorph);
        expect(cleanMorph.defaultPrevented).toBe(false);
        window.removeEventListener('kmp:draft-settled', settled);
    });

    test('cached restoration keeps a restored draft dirty until the user resets or saves it', async () => {
        const form = document.forms[0];
        form.elements.note.value = 'Draft retained across Back';
        form.elements.note.dispatchEvent(new Event('input', { bubbles: true }));
        document.dispatchEvent(new Event('turbo:before-cache'));
        const restored = form.cloneNode(true);
        form.replaceWith(restored);
        rememberForms();
        expect(restored.elements.note.value).toBe('Draft retained across Back');
        expect(isDirty(restored)).toBe(true);
        restored.reset(); await Promise.resolve();
        expect(isDirty(restored)).toBe(false);
        expect(restored).not.toHaveAttribute('data-kmp-preserved-draft');
    });
});
