// Controller registers on window.Controllers (no default export)
import '../../../plugins/GitHubIssueSubmitter/assets/js/controllers/github-submitter-controller.js';
const GitHubSubmitter = window.Controllers['github-submitter'];

describe('GitHubSubmitter', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="github-submitter"
                 data-github-submitter-url-value="/api/issues">
                <div data-github-submitter-target="modal" class="modal">
                    <div data-github-submitter-target="formBlock" style="display: block;">
                        <form data-github-submitter-target="form">
                            <input name="title" value="Bug Report">
                            <textarea name="body">Description</textarea>
                        </form>
                    </div>
                    <button data-github-submitter-target="submitBtn" style="display: block;">Submit</button>
                    <div data-github-submitter-target="success" style="display: none;">
                        <a data-github-submitter-target="issueLink" href="">View Issue</a>
                    </div>
                </div>
            </div>
        `;

        controller = new GitHubSubmitter();
        controller.element = document.querySelector('[data-controller="github-submitter"]');

        // Wire up targets
        controller.modalTarget = document.querySelector('[data-github-submitter-target="modal"]');
        controller.formBlockTarget = document.querySelector('[data-github-submitter-target="formBlock"]');
        controller.formTarget = document.querySelector('[data-github-submitter-target="form"]');
        controller.submitBtnTarget = document.querySelector('[data-github-submitter-target="submitBtn"]');
        controller.successTarget = document.querySelector('[data-github-submitter-target="success"]');
        controller.issueLinkTarget = document.querySelector('[data-github-submitter-target="issueLink"]');

        // Wire up has* checks
        controller.hasModalTarget = true;

        // Wire up values
        controller.urlValue = '/api/issues';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
        delete global.fetch;
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(GitHubSubmitter.targets).toEqual(
            expect.arrayContaining(['success', 'formBlock', 'submitBtn', 'issueLink', 'form', 'modal'])
        );
    });

    test('has correct static values', () => {
        expect(GitHubSubmitter.values).toHaveProperty('url', String);
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['github-submitter']).toBe(GitHubSubmitter);
    });

    // --- connect ---

    test('connect sets initial display states', () => {
        controller.connect();
        expect(controller.formBlockTarget.style.display).toBe('block');
        expect(controller.successTarget.style.display).toBe('none');
        expect(controller.submitBtnTarget.style.display).toBe('block');
    });

    // --- submit ---

    test('submit sends form data via fetch and shows success', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ url: 'https://github.com/issue/1' })
        }));

        const event = { preventDefault: jest.fn() };
        await controller.submit(event);

        // Wait for .then chain
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(event.preventDefault).toHaveBeenCalled();
        expect(global.fetch).toHaveBeenCalledWith('/api/issues', expect.objectContaining({
            method: 'POST',
            body: expect.any(FormData)
        }));
        expect(controller.issueLinkTarget.href).toContain('https://github.com/issue/1');
        expect(controller.formBlockTarget.style.display).toBe('none');
        expect(controller.submitBtnTarget.style.display).toBe('none');
        expect(controller.successTarget.style.display).toBe('block');
    });

    test('submit announces on server error message', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ message: 'Rate limited' })
        }));

        const event = { preventDefault: jest.fn() };
        await controller.submit(event);
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith('Error: Rate limited', { assertive: true });
    });

    test('submit announces on fetch failure', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: false
        }));

        const event = { preventDefault: jest.fn() };
        await controller.submit(event);
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'An error occurred while creating the issue.',
            { assertive: true }
        );
    });

    test('submit announces on network error', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        const event = { preventDefault: jest.fn() };
        await controller.submit(event);
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(window.KMP_accessibility.announce).toHaveBeenCalledWith(
            'An error occurred while creating the issue.',
            { assertive: true }
        );
    });

    // --- modalTargetConnected ---

    test('modalTargetConnected registers hidden.bs.modal event listener', () => {
        const addSpy = jest.spyOn(controller.modalTarget, 'addEventListener');
        controller.modalTargetConnected();
        expect(addSpy).toHaveBeenCalledWith('hidden.bs.modal', expect.any(Function));
    });

    // --- modalTargetDisconnected ---

    test('modalTargetDisconnected removes event listener', () => {
        const removeSpy = jest.spyOn(controller.modalTarget, 'removeEventListener');
        controller.modalTargetDisconnected();
        expect(removeSpy).toHaveBeenCalledWith('hidden.bs.modal', expect.any(Function));
    });
});
