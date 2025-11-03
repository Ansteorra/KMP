# CSP Configuration for OpenStreetMap Integration - Quick Reference

## Content Security Policy Update

To support the OpenStreetMap Nominatim autocomplete feature, the Content Security Policy (CSP) was updated to allow API requests to the Nominatim service.

## Changes Made

### File: `/app/src/Application.php`

**Updated CSP directive:**

```php
"connect-src 'self' https://maps.googleapis.com https://places.googleapis.com https://nominatim.openstreetmap.org https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org; "
```

**What was added:**
- `https://nominatim.openstreetmap.org` - Allows fetch requests for geocoding/autocomplete

## Complete CSP Configuration

The full CSP policy now allows:

| Directive | Allowed Sources | Purpose |
|-----------|----------------|---------|
| `default-src` | `'self'` | Default to same origin |
| `script-src` | `'self' 'unsafe-inline' 'unsafe-eval'`<br>`https://cdn.jsdelivr.net`<br>`https://unpkg.com`<br>`https://maps.googleapis.com` | Application scripts, CDN, Leaflet, Google Maps |
| `style-src` | `'self' 'unsafe-inline'`<br>`https://cdn.jsdelivr.net`<br>`https://unpkg.com`<br>`https://fonts.googleapis.com` | Stylesheets, Leaflet CSS, Google Fonts |
| `font-src` | `'self' data:`<br>`https://fonts.gstatic.com`<br>`https://cdn.jsdelivr.net` | Font files |
| `img-src` | `'self' data: https:` | All HTTPS images and data URIs |
| `connect-src` | `'self'`<br>`https://maps.googleapis.com`<br>`https://places.googleapis.com`<br>`https://nominatim.openstreetmap.org` ⭐<br>`https://tile.openstreetmap.org`<br>`https://a.tile.openstreetmap.org`<br>`https://b.tile.openstreetmap.org`<br>`https://c.tile.openstreetmap.org` | AJAX/fetch API calls |
| `frame-src` | `'self'`<br>`https://www.google.com` | iframes (Google Maps embeds) |
| `object-src` | `'none'` | Disable plugins |
| `base-uri` | `'self'` | Prevent base tag attacks |
| `form-action` | `'self'` | Form submissions |
| `frame-ancestors` | `'self'` | Embedding restrictions |

⭐ = **New addition for OSM autocomplete**

## Why This Is Needed

The `connect-src` directive controls which URLs the browser can connect to using:
- `fetch()`
- `XMLHttpRequest`
- WebSockets
- EventSource

Our OSM autocomplete uses `fetch()` to call:
```
https://nominatim.openstreetmap.org/search?q=...&format=json&...
```

Without adding this to `connect-src`, the browser will block the request with an error like:
```
Refused to connect to 'https://nominatim.openstreetmap.org/search' 
because it violates the following Content Security Policy directive: 
"connect-src 'self'"
```

## Testing CSP Configuration

### 1. Check Browser Console
Open DevTools (F12) and look for CSP errors:
```
✅ No errors = CSP configured correctly
❌ CSP errors = Need to add domain to appropriate directive
```

### 2. Test Address Autocomplete
1. Go to Gatherings → Add Gathering
2. Type an address in the Location field
3. Open Network tab in DevTools
4. You should see a successful request to `nominatim.openstreetmap.org`

### 3. Verify CSP Header
Check the response headers in DevTools:
```
Content-Security-Policy: default-src 'self'; script-src 'self' ... connect-src 'self' https://nominatim.openstreetmap.org ...
```

## Common CSP Issues and Solutions

### Issue: "Refused to connect to nominatim.openstreetmap.org"

**Solution:** Add to `connect-src`:
```php
"connect-src 'self' https://nominatim.openstreetmap.org; "
```

### Issue: "Refused to load the script 'https://unpkg.com/leaflet'"

**Solution:** Add to `script-src`:
```php
"script-src 'self' https://unpkg.com; "
```

### Issue: "Refused to load the stylesheet 'https://unpkg.com/leaflet.css'"

**Solution:** Add to `style-src`:
```php
"style-src 'self' https://unpkg.com; "
```

### Issue: Map tiles not loading from OpenStreetMap

**Solution:** Add to `connect-src`:
```php
"connect-src 'self' https://tile.openstreetmap.org https://a.tile.openstreetmap.org https://b.tile.openstreetmap.org https://c.tile.openstreetmap.org; "
```

## Development vs Production

The CSP configuration includes environment-specific settings:

```php
// Add upgrade-insecure-requests only in production/UAT
if (!$isDevelopment) {
    $csp .= "; upgrade-insecure-requests";
}
```

- **Development:** More lenient (no auto-HTTPS upgrade)
- **Production:** Stricter security (auto-upgrade HTTP to HTTPS)

## Security Best Practices

✅ **Keep it restrictive:** Only add domains you actually need
✅ **Use HTTPS only:** All external sources use HTTPS
✅ **Avoid 'unsafe-inline':** We use it for compatibility, but minimize inline scripts
✅ **No wildcards:** Don't use `*` or `https:` unless absolutely necessary
✅ **Test thoroughly:** Check all features after CSP changes

## Related Documentation

- [MDN: Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [CSP Evaluator](https://csp-evaluator.withgoogle.com/)
- [OpenStreetMap Nominatim Usage Policy](https://operations.osmfoundation.org/policies/nominatim/)

## Rollback

If CSP changes cause issues, revert this file:

```bash
git checkout main -- app/src/Application.php
```

Or manually remove `https://nominatim.openstreetmap.org` from the `connect-src` directive.

---

**Updated:** November 3, 2025
**Related Feature:** OpenStreetMap Location Autocomplete
**Status:** ✅ Production Ready
