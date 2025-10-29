# Security Cookie Configuration - Development vs Production

## Overview

This document outlines the conditional security settings implemented to support local development (including Safari browser and IP address access) while maintaining strict security in production environments.

## Security Changes Summary

All security-related cookie and header configurations are **conditional based on the `debug` configuration flag**:

- **Development Mode** (`debug=true`): Relaxed settings to support HTTP, localhost, and IP address access
- **Production Mode** (`debug=false`): Strict security settings enforcing HTTPS and maximum protection

## Configuration Files Modified

### 1. Application.php - CSRF Protection Middleware

**Location:** `app/src/Application.php` (Lines 406-414)

**CSRF Cookie Settings:**
```php
new CsrfProtectionMiddleware([
    'httponly' => true,    // Always prevent JavaScript access
    'secure' => !Configure::read('debug'),      // false in dev, true in prod
    'sameSite' => Configure::read('debug') ? 'Lax' : 'Strict', // Lax in dev, Strict in prod
])
```

**Behavior:**
- **Development** (`debug=true`):
  - `secure` = `false` - CSRF cookies work over HTTP
  - `sameSite` = `Lax` - Compatible with Safari and cross-origin requests
- **Production** (`debug=false`):
  - `secure` = `true` - CSRF cookies ONLY over HTTPS
  - `sameSite` = `Strict` - Maximum CSRF protection

### 2. Application.php - Security Headers

**Location:** `app/src/Application.php` (Lines 340-390)

**HTTPS Enforcement Headers:**
```php
// HSTS - Strict-Transport-Security
if (!$isDevelopment) {
    $response = $response->withHeader('Strict-Transport-Security', 'max-age=86400; includeSubDomains');
}

// CSP upgrade-insecure-requests directive
if (!$isDevelopment) {
    $csp .= "; upgrade-insecure-requests";
}
```

**Behavior:**
- **Development** (`debug=true`):
  - No HSTS header - HTTP allowed
  - No CSP upgrade directive - HTTP resources allowed
- **Production** (`debug=false`):
  - HSTS enforces HTTPS for 24 hours
  - CSP automatically upgrades HTTP requests to HTTPS

### 3. app_local.php - Session Cookie Settings

**Location:** `app/config/app_local.php` (Lines 36-49)

**Session Configuration:**
```php
'Session' => [
    'ini' => [
        'session.cookie_secure' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN) ? false : true,
        'session.cookie_httponly' => true,       // Always enabled
        'session.cookie_samesite' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN) ? 'Lax' : 'Strict',
        'session.use_strict_mode' => true,       // Always enabled
        'session.cookie_domain' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN) ? '' : null,
    ],
],
```

**Behavior:**
- **Development** (`DEBUG=true` in .env):
  - `session.cookie_secure` = `false` - Session cookies work over HTTP
  - `session.cookie_samesite` = `Lax` - Compatible with Safari
  - `session.cookie_domain` = `''` - Works with localhost and IP addresses
- **Production** (`DEBUG=false` in .env):
  - `session.cookie_secure` = `true` - Session cookies ONLY over HTTPS
  - `session.cookie_samesite` = `Strict` - Maximum CSRF protection
  - `session.cookie_domain` = `null` - Uses default domain handling

### 4. bootstrap.php - Security Validation

**Location:** `app/config/bootstrap.php` (Lines 100-144)

**Runtime Security Checks:**
- Validates configuration on application startup
- Issues warnings if production is running with insecure settings
- Logs mode changes for audit trail

**Production Warnings:**
If `debug=false` but insecure settings are detected:
```
SECURITY WARNING: Production environment detected (debug=false) but session.cookie_secure is FALSE.
This allows session cookies over HTTP which is a security risk.
```

### 5. JavaScript - PWA Controller Fallback

**Location:** `app/assets/js/controllers/member-mobile-card-pwa-controller.js`

**Changes:**
- Added fallback when Service Workers unavailable (HTTP on IP addresses)
- Dispatches `pwa-ready` event to allow app functionality without PWA
- Console warning when Service Workers disabled

**Security Impact:** None - JavaScript changes only affect UX, not security

## Production Security Guarantees

When deployed to production with `DEBUG=false` in the environment:

✅ **CSRF Protection:**
- CSRF cookies ONLY sent over HTTPS (`secure=true`)
- Strict SameSite policy (`SameSite=Strict`)
- HTTP-only cookies prevent JavaScript access

✅ **Session Security:**
- Session cookies ONLY sent over HTTPS (`session.cookie_secure=true`)
- Strict SameSite policy (`session.cookie_samesite=Strict`)
- Strict session ID validation enabled

✅ **Transport Security:**
- HSTS header enforces HTTPS for 24 hours
- CSP automatically upgrades HTTP to HTTPS
- All security headers active

✅ **Validation:**
- Runtime checks warn if misconfigured
- Logs document security mode
- Prevents accidental insecure production deployment

## Development Mode Benefits

When running locally with `DEBUG=true`:

✅ **Browser Compatibility:**
- Works in Safari (strict cookie enforcement)
- Works in all modern browsers

✅ **Network Flexibility:**
- Access via localhost (http://localhost:8080)
- Access via IP address (http://192.168.0.253:8080)
- Access via 127.0.0.1

✅ **No HTTPS Required:**
- Develop without SSL certificates
- Test on local network devices
- No mixed content warnings

## Environment Configuration

### Development (.env)
```bash
DEBUG=true
```

### Production (.env)
```bash
DEBUG=false
```

## Deployment Checklist

Before deploying to production:

- [ ] Set `DEBUG=false` in production .env
- [ ] Verify HTTPS is configured on web server
- [ ] Check application logs for security warnings
- [ ] Test that cookies are marked `Secure` in browser dev tools
- [ ] Verify HSTS header is present in responses
- [ ] Confirm login works over HTTPS only

## Testing

### Development Testing
```bash
# Should work over HTTP
curl -I http://localhost:8080/members/login

# Cookies should NOT have Secure flag
# Check browser dev tools > Application > Cookies
```

### Production Testing
```bash
# Should redirect to HTTPS or fail
curl -I http://your-domain.com/members/login

# Should work over HTTPS
curl -I https://your-domain.com/members/login

# Cookies should have Secure flag
# HSTS header should be present
```

## Browser-Specific Issues Resolved

### Safari
- **Issue:** Safari strictly enforces `Secure` cookie flag even on localhost
- **Solution:** Conditional `secure=false` in development mode
- **Issue:** Safari blocks cookies with `SameSite=Strict` in some scenarios
- **Solution:** Use `SameSite=Lax` in development, `Strict` in production

### IP Address Access
- **Issue:** Service Workers require HTTPS or localhost (not IP addresses)
- **Solution:** PWA controller gracefully degrades without Service Workers
- **Issue:** Secure cookies don't work over HTTP on IP addresses
- **Solution:** Conditional secure flags based on debug mode

## Security Audit Trail

All security mode changes are logged:

**Development Mode:**
```
Development mode active - relaxed cookie settings enabled (HTTP allowed for Safari/localhost compatibility)
```

**Production Mode:**
```
Production mode active - secure cookie settings enforced (HTTPS required)
```

**Security Warnings:**
```
SECURITY WARNING: Production environment detected but insecure settings found...
```

## Related Files

- `/app/src/Application.php` - CSRF middleware and security headers
- `/app/config/app_local.php` - Session configuration overrides
- `/app/config/bootstrap.php` - Security validation checks
- `/app/config/app.php` - Base session configuration (production defaults)
- `/app/templates/Members/login.php` - Login form
- `/app/assets/js/controllers/member-mobile-card-pwa-controller.js` - PWA fallback

## Support

For questions about security configuration:
1. Check application logs for warnings
2. Verify `debug` setting matches environment
3. Review this document for expected behavior
4. Test with browser dev tools to verify cookie flags

---

**Last Updated:** October 29, 2025  
**Related Issues:** Safari login on localhost, IP address access in development
