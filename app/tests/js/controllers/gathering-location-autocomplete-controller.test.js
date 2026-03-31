// Mock Google Maps API
const mockAddListener = jest.fn();
const mockGetPlace = jest.fn(() => ({
    formatted_address: '123 Main St, Dallas, TX',
    geometry: {
        location: {
            lat: () => 32.7767,
            lng: () => -96.7970
        }
    }
}));

global.google = {
    maps: {
        places: {
            Autocomplete: jest.fn().mockImplementation(() => ({
                addListener: mockAddListener,
                getPlace: mockGetPlace
            }))
        },
        event: {
            clearInstanceListeners: jest.fn()
        }
    }
};

import '../../../assets/js/controllers/gathering-location-autocomplete-controller.js';

const GatheringLocationAutocompleteController = window.Controllers['gathering-location-autocomplete'];

describe('GatheringLocationAutocompleteController', () => {
    let controller;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="gathering-location-autocomplete"
                 data-gathering-location-autocomplete-api-key-value="test-key">
                <input type="text" data-gathering-location-autocomplete-target="input" value="">
                <input type="hidden" data-gathering-location-autocomplete-target="latitude" value="">
                <input type="hidden" data-gathering-location-autocomplete-target="longitude" value="">
            </div>
        `;

        controller = new GatheringLocationAutocompleteController();
        controller.element = document.querySelector('[data-controller="gathering-location-autocomplete"]');
        controller.inputTarget = document.querySelector('[data-gathering-location-autocomplete-target="input"]');
        controller.latitudeTarget = document.querySelector('[data-gathering-location-autocomplete-target="latitude"]');
        controller.longitudeTarget = document.querySelector('[data-gathering-location-autocomplete-target="longitude"]');
        controller.hasLatitudeTarget = true;
        controller.hasLongitudeTarget = true;
        controller.hasInputTarget = true;
        controller.apiKeyValue = 'test-key';

        jest.clearAllMocks();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['gathering-location-autocomplete']).toBe(GatheringLocationAutocompleteController);
    });

    test('has correct static targets', () => {
        expect(GatheringLocationAutocompleteController.targets).toEqual(
            expect.arrayContaining(['input', 'latitude', 'longitude'])
        );
    });

    test('has correct static values', () => {
        expect(GatheringLocationAutocompleteController.values).toHaveProperty('apiKey', String);
    });

    test('initialize sets default properties', () => {
        controller.initialize();
        expect(controller.autocompleteElement).toBeNull();
        expect(controller.isGoogleMapsLoaded).toBe(false);
        expect(controller.isInitialized).toBe(false);
        expect(controller.lastSelectedAddress).toBeNull();
        expect(controller.lastSelectedPlace).toBeNull();
    });

    test('initAutocomplete creates Autocomplete instance', () => {
        controller.isGoogleMapsLoaded = true;
        controller.initAutocomplete();
        expect(google.maps.places.Autocomplete).toHaveBeenCalledWith(
            controller.inputTarget,
            { types: ['geocode'] }
        );
    });

    test('initAutocomplete adds place_changed listener', () => {
        controller.isGoogleMapsLoaded = true;
        controller.initAutocomplete();
        expect(mockAddListener).toHaveBeenCalledWith('place_changed', expect.any(Function));
    });

    test('initAutocomplete does nothing when Google Maps not loaded', () => {
        controller.isGoogleMapsLoaded = false;
        controller.initAutocomplete();
        expect(google.maps.places.Autocomplete).not.toHaveBeenCalled();
    });

    test('place_changed callback sets input value and lat/lng', () => {
        controller.isGoogleMapsLoaded = true;
        controller.initAutocomplete();

        // Get the callback passed to addListener
        const callback = mockAddListener.mock.calls[0][1];
        callback();

        expect(controller.inputTarget.value).toBe('123 Main St, Dallas, TX');
        // Input .value is always a string
        expect(parseFloat(controller.latitudeTarget.value)).toBeCloseTo(32.7767);
        expect(parseFloat(controller.longitudeTarget.value)).toBeCloseTo(-96.7970);
        expect(controller.lastSelectedAddress).toBe('123 Main St, Dallas, TX');
    });

    test('place_changed handles place without geometry', () => {
        mockGetPlace.mockReturnValueOnce({
            formatted_address: 'Some Place',
            geometry: null
        });

        controller.isGoogleMapsLoaded = true;
        controller.initAutocomplete();

        const callback = mockAddListener.mock.calls[0][1];
        callback();

        expect(controller.inputTarget.value).toBe('Some Place');
        expect(controller.latitudeTarget.value).toBe('');
    });

    test('loadGoogleMapsPlaces resolves if already loaded', async () => {
        controller.isGoogleMapsLoaded = false;
        // google is already defined globally in our mock
        await controller.loadGoogleMapsPlaces();
        expect(controller.isGoogleMapsLoaded).toBe(true);
    });

    test('disconnect cleans up autocomplete listeners', () => {
        const autocompleteInstance = { some: 'instance' };
        controller.autocomplete = autocompleteInstance;
        controller.isInitialized = true;
        controller.disconnect();
        expect(google.maps.event.clearInstanceListeners).toHaveBeenCalledWith(autocompleteInstance);
        expect(controller.autocomplete).toBeNull();
        expect(controller.isInitialized).toBe(false);
    });

    test('disconnect handles null autocomplete', () => {
        controller.autocomplete = null;
        expect(() => controller.disconnect()).not.toThrow();
    });

    test('connect initializes autocomplete and marks as initialized', async () => {
        controller.initialize();
        await controller.connect();
        expect(controller.isInitialized).toBe(true);
    });

    test('connect skips if already initialized', async () => {
        controller.initialize();
        controller.isInitialized = true;
        const initSpy = jest.spyOn(controller, 'initAutocomplete');
        await controller.connect();
        expect(initSpy).not.toHaveBeenCalled();
    });
});
