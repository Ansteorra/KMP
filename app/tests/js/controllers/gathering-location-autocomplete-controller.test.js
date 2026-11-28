describe('Gathering location autocomplete', () => {
    let controller;
    let Autocomplete;
    let widget;
    let subscription;
    let address;

    const mapsScript = () => document.querySelector('script[src^="https://maps.googleapis.com/maps/api/js"]');

    beforeEach(() => {
        jest.isolateModules(() => require('../../../assets/js/controllers/gathering-location-autocomplete-controller.js'));

        document.body.innerHTML = `
            <div>
                <label for="location">Location</label>
                <input id="location" name="location" value="Original venue" aria-describedby="location-help">
                <input type="hidden" name="latitude" value="32">
                <input type="hidden" name="longitude" value="-96">
                <div id="location-help" role="status">Enter a venue name or street address.</div>
            </div>
        `;
        address = document.getElementById('location');
        subscription = { remove: jest.fn() };
        widget = { addListener: jest.fn(() => subscription), getPlace: jest.fn() };
        Autocomplete = jest.fn(() => widget);
        window.google = { maps: { places: { Autocomplete } } };

        const Controller = window.Controllers['gathering-location-autocomplete'];
        controller = new Controller();
        controller.element = address.parentElement;
        controller.inputTarget = address;
        controller.latitudeTarget = document.querySelector('[name="latitude"]');
        controller.longitudeTarget = document.querySelector('[name="longitude"]');
        controller.helpTarget = document.getElementById('location-help');
        controller.apiKeyValue = 'test-key';
        controller.fallbackMessageValue = 'Address suggestions are unavailable. Enter a venue name or street address.';
    });

    afterEach(() => {
        controller.disconnect();
        mapsScript()?.remove();
        delete window.google;
        delete window.gm_authFailure;
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    test('keeps an editable native field and preserves an unchanged address with no Maps key', async () => {
        controller.apiKeyValue = '  ';
        delete window.google;
        await controller.connect();

        expect(mapsScript()).toBeNull();
        expect(address).toBeEnabled();
        expect(controller.latitudeTarget).toHaveValue('32');

        address.value = 'Manually entered venue';
        controller.addressChanged();
        expect(controller.latitudeTarget).toHaveValue('');
        expect(controller.longitudeTarget).toHaveValue('');
    });

    test('script load errors leave the original field editable and announce manual entry', async () => {
        delete window.google;
        const connection = controller.connect();
        mapsScript().dispatchEvent(new Event('error'));
        await connection;

        expect(address).toBeEnabled();
        expect(address).toHaveValue('Original venue');
        expect(controller.helpTarget).toHaveTextContent(controller.fallbackMessageValue);
    });

    test('falls back if the script stalls or the callback never runs', async () => {
        jest.useFakeTimers();
        delete window.google;
        const connection = controller.connect();

        expect(address).toBeEnabled();
        jest.advanceTimersByTime(10_000);
        await connection;

        expect(mapsScript()).toBeNull();
        expect(address).toBeEnabled();
        expect(controller.helpTarget).toHaveTextContent(controller.fallbackMessageValue);
    });

    test('Maps authentication failure restores native semantics, text and focus', async () => {
        const previousCallback = jest.fn();
        window.gm_authFailure = previousCallback;
        await controller.connect();
        address.focus();
        address.value = 'Venue entered before failure';
        controller.addressChanged();
        address.setSelectionRange(5, 5);
        address.readOnly = true;
        address.placeholder = 'Maps error';
        window.gm_authFailure();

        const fallback = document.getElementById('location');
        expect(fallback).toHaveFocus();
        expect(fallback).toBeEnabled();
        expect(fallback.readOnly).toBe(false);
        expect(fallback.placeholder).toBe('');
        expect(fallback).toHaveValue('Venue entered before failure');
        expect(fallback.selectionStart).toBe(5);
        expect(fallback).toHaveAccessibleName('Location');
        expect(fallback).toHaveAccessibleDescription(controller.fallbackMessageValue);
        expect(subscription.remove).toHaveBeenCalled();
        expect(previousCallback).toHaveBeenCalled();

        // The widget may finish failing after the fallback is already active.
        address.disabled = true;
        expect(fallback).toBeEnabled();
    });

    test('recovers when the widget disables the field without reporting an authentication error', async () => {
        await controller.connect();
        address.disabled = true;
        await Promise.resolve();

        expect(document.getElementById('location')).toBeEnabled();
        expect(controller.helpTarget).toHaveTextContent(controller.fallbackMessageValue);
    });

    test('restores the field if widget initialization fails after mutating the input', async () => {
        Autocomplete.mockImplementation(() => {
            address.readOnly = true;
            throw new Error('Places unavailable');
        });
        await controller.connect();

        const fallback = document.getElementById('location');
        expect(fallback.readOnly).toBe(false);
        expect(fallback).toHaveValue('Original venue');
        expect(controller.helpTarget).toHaveTextContent(controller.fallbackMessageValue);
    });

    test('saves map coordinates for a selected suggestion and clears them after manual edits', async () => {
        widget.getPlace.mockReturnValue({
            formatted_address: '123 Main St, Dallas, TX',
            geometry: { location: { lat: () => 32.7767, lng: () => -96.797 } },
        });
        await controller.connect();
        expect(Autocomplete).toHaveBeenCalledWith(address, { types: ['geocode'] });
        expect(widget.addListener).toHaveBeenCalledWith('place_changed', expect.any(Function));
        widget.addListener.mock.calls[0][1]();

        expect(address).toHaveValue('123 Main St, Dallas, TX');
        expect(controller.latitudeTarget).toHaveValue('32.7767');
        expect(controller.longitudeTarget).toHaveValue('-96.797');

        address.value = 'Other venue';
        controller.addressChanged();
        expect(controller.latitudeTarget).toHaveValue('');
        expect(controller.longitudeTarget).toHaveValue('');
    });

    test('disconnect prevents a late Maps response from enhancing a departed form', async () => {
        delete window.google;
        const connection = controller.connect();
        controller.disconnect();
        window.google = { maps: { places: { Autocomplete } } };
        window.initGatheringLocationAutocomplete();
        await connection;

        expect(Autocomplete).not.toHaveBeenCalled();
        expect(address).toBeEnabled();
    });
});
