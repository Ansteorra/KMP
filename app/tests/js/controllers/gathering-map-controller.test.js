// Mock Google Maps API
const mockInfoWindow = {
    open: jest.fn(),
};
const mockMarker = {
    addListener: jest.fn(),
};
const mockMap = {
    setCenter: jest.fn(),
};

const mockGeocoder = {
    geocode: jest.fn(),
};

global.google = {
    maps: {
        Map: jest.fn(() => mockMap),
        Geocoder: jest.fn(() => mockGeocoder),
        InfoWindow: jest.fn(() => mockInfoWindow),
        importLibrary: jest.fn(() => Promise.resolve({
            AdvancedMarkerElement: jest.fn(() => mockMarker),
        })),
    },
};

import '../../../assets/js/controllers/gathering-map-controller.js';
const GatheringMapController = window.Controllers['gathering-map'];

describe('GatheringMapController', () => {
    let controller;

    beforeEach(() => {
        // Reset mocks
        jest.clearAllMocks();

        document.body.innerHTML = `
            <div data-controller="gathering-map"
                 data-gathering-map-location-value="123 Main St, City"
                 data-gathering-map-gathering-name-value="Great Western War">
                <div data-gathering-map-target="map" id="map-container"></div>
                <div data-gathering-map-target="error" style="display: none;"></div>
            </div>
        `;

        controller = new GatheringMapController();
        controller.element = document.querySelector('[data-controller="gathering-map"]');
        controller.mapTarget = document.querySelector('[data-gathering-map-target="map"]');
        controller.errorTarget = document.querySelector('[data-gathering-map-target="error"]');
        controller.hasMapTarget = true;
        controller.hasErrorTarget = true;
        controller.locationValue = '123 Main St, City';
        controller.gatheringNameValue = 'Great Western War';
        controller.apiKeyValue = '';
        controller.zoomValue = 15;
        controller.hasLatitudeValue = false;
        controller.hasLongitudeValue = false;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    test('registers on window.Controllers', () => {
        expect(window.Controllers['gathering-map']).toBe(GatheringMapController);
    });

    test('has correct static targets', () => {
        expect(GatheringMapController.targets).toEqual(
            expect.arrayContaining(['map', 'error'])
        );
    });

    test('has correct static values', () => {
        expect(GatheringMapController.values).toHaveProperty('location', String);
        expect(GatheringMapController.values).toHaveProperty('gatheringName', String);
        expect(GatheringMapController.values).toHaveProperty('apiKey', String);
        expect(GatheringMapController.values).toHaveProperty('latitude', Number);
        expect(GatheringMapController.values).toHaveProperty('longitude', Number);
        expect(GatheringMapController.values.zoom).toEqual({ type: Number, default: 15 });
    });

    test('initialize sets map and marker to null', () => {
        controller.initialize();
        expect(controller.map).toBeNull();
        expect(controller.marker).toBeNull();
        expect(controller.geocoded).toBe(false);
    });

    test('connect shows error when no location provided', () => {
        controller.locationValue = '';
        controller.connect();
        expect(controller.errorTarget.textContent).toBe('No location provided');
        expect(controller.errorTarget.style.display).toBe('block');
    });

    test('connect calls initGoogleMap when location provided', () => {
        const initSpy = jest.spyOn(controller, 'initGoogleMap').mockResolvedValue();
        controller.connect();
        expect(initSpy).toHaveBeenCalled();
    });

    test('showError displays error message and hides map', () => {
        controller.showError('Test error');
        expect(controller.errorTarget.textContent).toBe('Test error');
        expect(controller.errorTarget.style.display).toBe('block');
        expect(controller.mapTarget.style.display).toBe('none');
    });

    test('showError creates error div when error target missing', () => {
        controller.hasErrorTarget = false;
        controller.showError('Test error');
        const errorDiv = controller.mapTarget.parentNode.querySelector('.alert-warning');
        expect(errorDiv).not.toBeNull();
    });

    test('openInGoogleMaps uses coordinates when available', () => {
        controller.hasLatitudeValue = true;
        controller.hasLongitudeValue = true;
        controller.latitudeValue = 30.123;
        controller.longitudeValue = -97.456;

        const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
        controller.openInGoogleMaps({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining('30.123,-97.456'),
            '_blank'
        );
    });

    test('openInGoogleMaps uses address when no coordinates', () => {
        const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
        controller.openInGoogleMaps({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining(encodeURIComponent('123 Main St, City')),
            '_blank'
        );
    });

    test('openInAppleMaps uses coordinates when available', () => {
        controller.hasLatitudeValue = true;
        controller.hasLongitudeValue = true;
        controller.latitudeValue = 30.123;
        controller.longitudeValue = -97.456;

        const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
        controller.openInAppleMaps({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining('maps.apple.com'),
            '_blank'
        );
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining('30.123,-97.456'),
            '_blank'
        );
    });

    test('openInAppleMaps uses address when no coordinates', () => {
        const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
        controller.openInAppleMaps({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining('maps.apple.com'),
            '_blank'
        );
    });

    test('getDirections uses coordinates when available', () => {
        controller.hasLatitudeValue = true;
        controller.hasLongitudeValue = true;
        controller.latitudeValue = 30.123;
        controller.longitudeValue = -97.456;

        const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
        controller.getDirections({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining('destination=30.123,-97.456'),
            '_blank'
        );
    });

    test('getDirections uses address when no coordinates', () => {
        const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
        controller.getDirections({ preventDefault: jest.fn() });
        expect(openSpy).toHaveBeenCalledWith(
            expect.stringContaining('destination='),
            '_blank'
        );
    });

    test('disconnect cleans up map and marker', () => {
        controller.map = mockMap;
        controller.marker = mockMarker;
        controller.disconnect();
        expect(controller.map).toBeNull();
        expect(controller.marker).toBeNull();
    });

    test('disconnect handles null map gracefully', () => {
        controller.map = null;
        controller.marker = null;
        expect(() => controller.disconnect()).not.toThrow();
    });

    test('geocodeAndDisplayGoogle uses stored coordinates when available', async () => {
        controller.map = mockMap;
        controller.hasLatitudeValue = true;
        controller.hasLongitudeValue = true;
        controller.latitudeValue = 30.123;
        controller.longitudeValue = -97.456;

        jest.spyOn(controller, 'createMarker').mockResolvedValue();

        await controller.geocodeAndDisplayGoogle();

        expect(mockMap.setCenter).toHaveBeenCalledWith({
            lat: 30.123,
            lng: -97.456,
        });
        expect(controller.createMarker).toHaveBeenCalled();
    });
});
