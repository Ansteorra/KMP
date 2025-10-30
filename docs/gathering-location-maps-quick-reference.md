# Gathering Location Maps - Quick Reference

## What It Does

Provides two location features for gatherings:

1. **Address Autocomplete (Forms)**: When creating or editing a gathering, users get real-time address suggestions as they type in the location field
2. **Interactive Map (View)**: Shows an interactive Google Map for gatherings that have a location, allowing users to:
   - View the gathering location on an embedded map
   - Get directions from their current location
   - Open the location in their preferred mapping app (Google Maps or Apple Maps)
   - Copy the address to clipboard

## How It Works

1. **Automatic Display**: When a Gathering has a `location` field populated, a "Location" tab automatically appears in the Gathering view.

2. **Google Maps**: The system uses Google Maps for displaying the location with geocoding.

3. **External Integration**: Users can click to open directions in external mapping services.

## User Actions

### Use Address Autocomplete (When Creating/Editing)
- Navigate to **Add Gathering** or **Edit Gathering**
- Click in the **Location** field
- Start typing an address or place name
- Google will show real-time suggestions
- Click a suggestion to auto-fill the complete address
- Continue filling out the rest of the form

### View the Map
- Navigate to any Gathering with a location
- Click the "Location" tab (with map pin icon)
- The map loads automatically showing the location

### Get Directions
- Click **"Get Directions"** to open turn-by-turn directions in Google Maps
- Directions start from the user's current location

### Open in External App
- Click the **"Open In..."** dropdown
- Select your preferred mapping service:
  - Google Maps
  - Apple Maps (best on iOS/macOS devices)

### Copy Address
- Click **"Copy Address"** to copy the location to clipboard
- Paste anywhere you need the address

## For Administrators

### Configure Map Settings

Add this setting via **Admin > Application Settings**:

**GoogleMaps.ApiKey**
- Type: `string`
- Value: Your Google Maps API key
- Optional but recommended for production

### Get API Key

**Google Maps**: [Google Cloud Console](https://console.cloud.google.com/)
- Enable: Maps JavaScript API, Geocoding API, **Places API**
- Free tier: $200/month credit

See `docs/gathering-location-maps.md` for detailed instructions.

## For Developers

### Files Added

1. **`assets/js/controllers/gathering-location-autocomplete-controller.js`**
   - Stimulus controller for Google Places Autocomplete on location input fields
   - Uses the new **PlaceAutocompleteElement** API (latest Google Maps API)
   - Dynamically loads Google Maps Places library with `loading=async`
   - Handles place selection and form field updates
   - Replaces standard input with Google's autocomplete element

2. **`assets/js/controllers/gathering-map-controller.js`**
   - Stimulus controller handling map initialization with Google Maps
   - Handles geocoding and user interactions
   - Provides directions and external app integration

3. **`templates/element/gatherings/mapTab.php`**
   - Map tab content with embedded map
   - Direction buttons and controls
   - Responsive layout

4. **`assets/css/app.css`** (additions)
   - Map container styles
   - Loading state styles
   - Responsive map sizing

### Files Modified

1. **`templates/Gatherings/view.php`**
   - Added "Location" tab button (conditional on location presence)
   - Added map tab content include

2. **`templates/Gatherings/add.php`**
   - Added autocomplete controller to location input field
   - Includes API key configuration

3. **`templates/Gatherings/edit.php`**
   - Added autocomplete controller to location input field
   - Includes API key configuration

### How It Integrates

The feature uses the existing `Gathering.location` field (string, 255 chars) from the database. No migrations needed.

```php
// In GatheringsController::view()
// Location is already loaded with the Gathering entity
$gathering->location  // "123 Main St, City, State"
```

The map tab element checks for location:
```php
<?php if (!empty($gathering->location)): ?>
    <button class="nav-link" id="nav-location-tab">Location</button>
    <?= $this->element('gatherings/mapTab', ['gathering' => $gathering]) ?>
<?php endif; ?>
```

### Stimulus Controller Usage

```html
<div data-controller="gathering-map"
     data-gathering-map-location-value="<?= h($gathering->location) ?>"
     data-gathering-map-gathering-name-value="<?= h($gathering->name) ?>"
     data-gathering-map-api-key-value="YOUR-KEY">
  <div data-gathering-map-target="map"></div>
</div>
```

### Methods Available

**Map Display**:
- `initGoogleMap()` - Initialize Google Maps
- `loadGoogleMapsScript()` - Load Google Maps API dynamically
- `geocodeAndDisplayGoogle()` - Geocode and display location

**User Actions**:
- `getDirections(event)` - Open Google Maps directions
- `openInGoogleMaps(event)` - Open location in Google Maps
- `openInAppleMaps(event)` - Open location in Apple Maps

## Troubleshooting

**Map not showing?**
- Check that gathering.location is not empty
- Look for JavaScript errors in browser console
- Verify API key is configured correctly
- Check that CSP allows Google Maps (should be configured by default)

**Autocomplete not working?**
- Verify Places API is enabled in Google Cloud Console
- Check browser console for CSP violations
- Ensure API key is valid and not restricted
- Check API usage limits in Google Cloud Console

**"Unable to find location" error?**
- Address may be too vague or invalid
- Try more specific address with city, state, ZIP
- Test the address in Google Maps to verify it's valid

**Rate limiting?**
- Add an API key in Application Settings
- Check API usage in your provider's dashboard

## Best Practices

### For Event Stewards

When entering a location, use complete addresses:
- ✅ `123 Park Avenue, Springfield, IL 62701`
- ✅ `Lincoln Park, Chicago, IL`
- ❌ `Near the park` (too vague)
- ❌ `TBD` (not geocodable)

### For Administrators

1. Always configure API keys for production sites
2. Restrict API keys to your domain for security
3. Monitor API usage regularly
4. Don't commit API keys to version control
