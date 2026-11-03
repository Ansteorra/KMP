import catalogData from '../../../resources/icons/catalog.json';
const aliasesById = {};
for (const [alias, id] of Object.entries(catalogData.aliases)) (aliasesById[id] ||= []).push(alias);
const catalog = Object.values(catalogData.entries).map(entry => ({...entry, aliases: aliasesById[entry.id] || []}));
import '../../../assets/js/controllers/activity-appearance-controller.js';

const ActivityAppearanceController = window.Controllers['activity-appearance'];

describe('ActivityAppearanceController', () => {
    let controller;
    beforeEach(() => {
        document.body.innerHTML = `<input id="fg" type="color" value="#ffffff">
            <input id="bg" type="color" value="#000000"><select id="source">
            <option value="catalog">KMP catalog</option><option value="upload">Upload</option></select>
            <input id="name" value="shield"><input id="search"><div id="icons"></div>
            <p id="count" role="status"></p><button id="more">More</button><input id="file" type="file">
            <span id="compact"></span><span id="full"></span><p id="contrast" role="status"></p>`;
        controller = Object.create(ActivityAppearanceController.prototype);
        for (const [target, id] of Object.entries({foreground:'fg', background:'bg', source:'source', iconName:'name',
            search:'search', icons:'icons', iconCount:'count', more:'more', file:'file', compact:'compact',
            full:'full', contrast:'contrast'})) {
            Object.defineProperty(controller, `${target}Target`, {value:document.getElementById(id)});
        }
        Object.defineProperty(controller, 'iconUrlValue', {value:'https://assets.example.test/icon.png'});
        controller.connect();
    });
    afterEach(() => controller.disconnect());

    test('picker loads on demand, exposes named buttons and selected state; more focuses the next result', async () => {
        expect(controller.iconsTarget.children).toHaveLength(0);
        global.fetch = jest.fn().mockResolvedValue({ok:true, json:async () => catalog});
        await controller.openCatalog();
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(controller.iconsTarget.children).toHaveLength(48);
        controller.showMore();
        expect(controller.iconsTarget.children).toHaveLength(96);
        expect(document.activeElement).toBe(controller.iconsTarget.children[48]);
        controller.searchTarget.value = 'shield';
        controller.searchIcons();
        const button = controller.iconsTarget.querySelector('[data-icon-name="shield"]');
        expect(button.getAttribute('aria-label')).toBe('Shield');
        controller.chooseIcon({currentTarget:button});
        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(controller.iconNameTarget.value).toBe('shield');
        expect(controller.sourceTarget.value).toBe('catalog');
        controller.searchTarget.value = 'no-such-sca-activity';
        controller.searchIcons();
        expect(controller.iconsTarget.children).toHaveLength(0);
        expect(controller.iconCountTarget).toHaveTextContent('No icons match');
    });

    test('low contrast blocks submission and announces the required ratio', () => {
        expect(controller.foregroundTarget.validity.valid).toBe(true);
        controller.backgroundTarget.value = '#ffffff';
        controller.preview();
        expect(controller.foregroundTarget.validity.customError).toBe(true);
        expect(controller.contrastTarget.textContent).toContain('1.00:1');
        expect(controller.contrastTarget.textContent).toContain('4.5:1');
    });

    test('browsing never replaces an unknown stored selection with the fallback', async () => {
        global.fetch = jest.fn().mockResolvedValue({ok:true, json:async () => catalog});
        controller.iconNameTarget.value = 'future-unknown-icon';
        await controller.openCatalog();
        expect(controller.iconNameTarget.value).toBe('future-unknown-icon');
        expect(controller.iconsTarget.querySelector('[aria-pressed="true"]')).toBeNull();
    });

    test('compact preview uses a colored alpha mask and details use the full image', () => {
        controller.sourceTarget.value = 'upload';
        controller.preview();
        expect(controller.compactTarget.querySelector('img')).toBeNull();
        expect(controller.compactTarget.querySelector('.gathering-activity-mask').style.maskImage)
            .toContain('https://assets.example.test/icon.png');
        expect(controller.fullTarget.querySelector('img').src).toBe('https://assets.example.test/icon.png');
        expect(controller.fullTarget.querySelector('img').alt).toBe('');
        expect(controller.compactTarget.getAttribute('aria-label')).toBeTruthy();
    });

    test('upload rejects wrong formats and oversized dimensions; disconnect cancels pending reads', () => {
        controller.fileTarget.reportValidity = jest.fn();
        Object.defineProperty(controller.fileTarget, 'files', {configurable:true,
            value:[new File(['<svg/>'], 'icon.svg', {type:'image/svg+xml'})]});
        controller.fileChanged();
        expect(controller.fileTarget.validity.customError).toBe(true);
        const originalImage = global.Image;
        let image;
        global.Image = class { constructor() { image = this; } };
        try {
            Object.defineProperty(controller.fileTarget, 'files', {value:[new File(['png'], 'icon.png', {type:'image/png'})]});
            controller.fileChanged();
            image.width = 129;
            image.height = 64;
            image.onload();
            expect(controller.fileTarget.validationMessage).toContain('128');
            controller.reader = {readyState:1, abort:jest.fn()};
            controller.disconnect();
            expect(controller.reader.abort).toHaveBeenCalled();
        } finally {
            global.Image = originalImage;
        }
    });
});
