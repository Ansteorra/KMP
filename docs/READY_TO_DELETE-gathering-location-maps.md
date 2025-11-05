# Gathering Location Map Configuration

## Overview

Gatherings with a location can display an interactive Google Map in the "Location" tab, showing the gathering venue and providing options to get directions.

## Features

The map integration provides:
- **Address Autocomplete**: As users type a location in the gathering form, Google Places Autocomplete suggests addresses and places in real-time
- **Interactive Google Map**: Shows the gathering location with a marker
- **Marker Details**: Displays gathering name and location information
- **Get Directions**: Opens Google Maps with turn-by-turn directions from user's current location
- **External App Integration**: Open location in Google Maps or Apple Maps
- **Copy Address**: Quick clipboard copy of the location address

## Configuration

Map settings are stored in the `app_settings` database table and can be configured through the Application Settings interface.

### Required Setting

**GoogleMaps.ApiKey** (String)
- Value: Your Google Maps API key
- Default: `""` (empty - maps will still work but with limitations)
- Required for production use to avoid rate limiting

### Setting Up Google Maps API Key

1. Visit the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the following APIs:
   - **Maps JavaScript API** (for map display)
   - **Geocoding API** (for converting addresses to coordinates)
   - **Places API** (for address autocomplete)
4. Create credentials (API Key)
5. Restrict the API key to your domain for security
6. Add the key to Application Settings as `GoogleMaps.ApiKey`

**Free Tier**: Google Maps offers a generous free tier with $200 monthly credit, which is sufficient for most SCA organizations.

### Content Security Policy (CSP)

The application's CSP has been configured to allow Google Maps API:
- `https://maps.googleapis.com` is allowed in `script-src` and `connect-src`
- `https://fonts.googleapis.com` is allowed in `style-src`
- `https://fonts.gstatic.com` is allowed in `font-src`

These directives are configured in `src/Application.php` and should not need modification for Google Maps to work.

## Adding Settings via Database

If you prefer to add settings directly to the database:

```sql
-- Set API Key
INSERT INTO app_settings (name, value, type, created, modified) 
VALUES ('GoogleMaps.ApiKey', 'your-api-key-here', 'string', NOW(), NOW());
```

## Adding Settings via Application Settings Interface

1. Navigate to **Admin** > **Application Settings**
2. Click **Add Setting**
3. Add the following setting:
   - **Name**: `GoogleMaps.ApiKey`
   - **Value**: `your-api-key-here`
   - **Type**: `string`

## Features

### Address Autocomplete (Form Input)
- Real-time address suggestions as users type
- Powered by Google Places API using **PlaceAutocompleteElement** (the latest API)
- Suggests both addresses and establishment names
- Improves data quality and user experience
- Works in both Add and Edit gathering forms
- Automatically replaces the standard input field with Google's autocomplete element

### Map Display (View Page)
- Interactive Google Map showing gathering location
- Marker with gathering name
- Info window with location details
- Automatic geocoding of address

### Direction Options
- **Get Directions**: Opens Google Maps with directions from user's location
- **Open In...**: Dropdown to open location in:
  - Google Maps
  - Apple Maps (works on Apple devices)
- **Copy Address**: Copies the location address to clipboard

### Tab Behavior
The "Location" tab only appears when:
- A gathering has a location field populated
- The map can successfully geocode the address

## Usage Without API Key

The map will still function without an API key, but with limitations:
- May encounter rate limiting on busy sites
- Some advanced features may be restricted
- Not recommended for production use

## Troubleshooting

### Map Not Displaying
1. Check that the gathering has a location entered
2. Verify the location address is valid and can be geocoded
3. Check browser console for JavaScript errors
4. Ensure API key is valid and not restricted to wrong domains
5. Verify that CSP allows Google Maps (should be configured by default)

### Address Autocomplete Not Working
1. Check browser console for errors
2. Verify the API key is valid and has Places API enabled
3. Check that CSP allows `https://maps.googleapis.com` in `script-src`
4. Ensure Places API is enabled in Google Cloud Console
5. Check for API usage limits in Google Cloud Console

### "Unable to find location" Error
1. Verify the location string is a valid address
2. Try using a more specific address (include city, state, ZIP)
3. Check that the Geocoding API is enabled for your key

### Rate Limiting
1. Add an API key if not already configured
2. Check your usage in the Google Cloud Console

## Security Considerations

1. **Restrict API Keys**: Always restrict your API keys to your specific domain(s)
2. **Monitor Usage**: Regularly check your API usage to detect unauthorized use
3. **Rotate Keys**: Periodically rotate API keys as a security best practice
4. **Don't Commit Keys**: Never commit API keys to version control

## Example Location Formats

Good location formats for reliable geocoding:
- `123 Main Street, Springfield, IL 62701`
- `Central Park, New York, NY`
- `1600 Amphitheatre Parkway, Mountain View, CA 94043`

Avoid vague locations:
- `Near the park` (too vague)
- `TBD` (not geocodable)
- `See event page` (not an address)

## Development

For development/testing, you can use the APIs without a key, but be aware of rate limits. For local development, consider using mock data or a development API key.
