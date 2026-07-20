import '../../../assets/js/controllers/public-gathering-controller.js';

const PublicGatheringController = window.Controllers['public-gathering'];

describe('PublicGatheringController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="public-gathering">
                <a class="email-link" data-email="dGVzdEBleGFtcGxlLmNvbQ==">Email</a>
                <a class="email-link" data-email="aW5mb0BleGFtcGxlLmNvbQ=="><i class="bi bi-envelope"></i></a>
            </div>
        `;

        controller = new PublicGatheringController();
        controller.element = document.querySelector('[data-controller="public-gathering"]');
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['public-gathering']).toBe(PublicGatheringController);
    });

    test('connect decodes email links and upgrades hrefs', () => {
        controller.connect();

        const [textLink, iconLink] = controller.element.querySelectorAll('.email-link');
        expect(textLink.href).toContain('mailto:test@example.com');
        expect(textLink.textContent).toBe('test@example.com');
        expect(textLink.hasAttribute('data-email')).toBe(false);

        expect(iconLink.href).toContain('mailto:info@example.com');
        expect(iconLink.textContent).toBe('info@example.com');
        expect(iconLink.hasAttribute('data-email')).toBe(false);
    });
});
