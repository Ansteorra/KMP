// Controller registers on window.Controllers (no default export)
import '../../../plugins/Activities/assets/js/controllers/gw-sharing-controller.js';
const GWSharingController = window.Controllers['gw_sharing'];

describe('GWSharingController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="gw_sharing">
                <form data-gw_sharing-target="form" method="post" action="/activities/update-gw-sharing">
                    <input type="checkbox" data-action="change->gw_sharing#submit" name="gw_sharing_enabled">
                </form>
            </div>
        `;

        controller = new GWSharingController();
        controller.element = document.querySelector('[data-controller="gw_sharing"]');

        // Wire up targets
        controller.formTarget = document.querySelector('[data-gw_sharing-target="form"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    // --- Static properties ---

    test('has correct static targets', () => {
        expect(GWSharingController.targets).toEqual(
            expect.arrayContaining(['form'])
        );
    });

    test('registers on window.Controllers as gw_sharing', () => {
        expect(window.Controllers['gw_sharing']).toBe(GWSharingController);
    });

    // --- submit ---

    test('submit calls formTarget.submit()', () => {
        controller.formTarget.submit = jest.fn();
        controller.submit();
        expect(controller.formTarget.submit).toHaveBeenCalled();
    });
});
