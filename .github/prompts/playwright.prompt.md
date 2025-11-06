# Playwright MCP Testing Instructions

Use the Playwright MCP server to interact with the application for end-to-end evaluation.

## Application Access

**Website URL**: `http://localhost:8080`

The application is already running and accessible at this URL. No need to start servers.

## Test User Credentials

All development users share the same password: **`TestPassword`**

Available test users with different roles and permissions:

- **admin@amp.ansteorra.org** - System super user (full administrative access)
- **agatha@ampdemo.com** - Local MoAS (Minister of Arts & Sciences)
- **bryce@ampdemo.com** - Local Seneschal
- **caroline@ampdemo.com** - Regional Seneschal
- **devon@ampdemo.com** - Regional Armored
- **eirik@ampdemo.com** - Kingdom Seneschal
- **garun@ampdemo.com** - Kingdom Rapier
- **haylee@ampdemo.com** - Kingdom MoAS
- **iris@ampdemo.com** - Basic User (minimal permissions)
- **jael@ampdemo.com** - Principality Coronet
- **kal@ampdemo.com** - Local Landed Nobility with a Canton
- **forest@ampdemo.com** - Crown
- **leonard@ampdemo.com** - Local Landed Nobility with Stronghold
- **mel@ampdemo.com** - Local Exchequer and Kingdom Social Media

Choose the appropriate user based on the permissions needed for your test scenario.

## Testing Guidelines

### Navigation and Interaction

1. **Start at the base URL**: Navigate to `http://localhost:8080`
2. **Login Process**: 
   - Click the login link/button
   - Enter the appropriate email and password
   - Submit the form
3. **Verify Navigation**: Use Playwright's navigation and assertion methods to verify page loads and content

### Screenshot Usage

**IMPORTANT**: Avoid using screenshots unless absolutely necessary, as they consume significant context space. Prefer:

- Text-based assertions (`expect(page).toHaveText(...)`)
- DOM queries and element checks
- Accessibility tree snapshots for structure verification
- Console and network request monitoring

Only use screenshots when:
- Visual regression testing is specifically required
- Debugging a visual-only issue that cannot be captured otherwise
- Documenting a bug that requires visual evidence

### Browser Context

- The application uses standard web technologies (CakePHP backend, Stimulus.js frontend)
- Session state is managed via cookies
- Some features use Turbo Drive for navigation (Hotwired)
- Forms may use CSRF tokens

### Best Practices

1. **Use semantic selectors**: Prefer `role`, `label`, and `test-id` selectors over CSS/XPath when possible
2. **Wait for navigation**: Use `waitForLoadState` or `waitForURL` after actions that trigger navigation
3. **Check authentication state**: Verify login success before proceeding with protected routes
4. **Clean state**: Consider starting each test scenario with a fresh browser context
5. **Accessibility**: Use `page.getByRole()`, `page.getByLabel()`, etc. for better maintainability

### Common Test Scenarios

- **Authentication**: Test login/logout flows with different user roles
- **Authorization**: Verify that users can only access permitted features
- **CRUD Operations**: Test create, read, update, delete operations for entities
- **Form Validation**: Test form submission with valid and invalid data
- **Navigation**: Test routing and page transitions, especially with Turbo Drive

### Debugging

If tests fail:
1. Check console logs: `page.on('console', msg => console.log(msg))`
2. Monitor network requests: `page.on('request/response', ...)`
3. Verify authentication state before protected actions
4. Check for JavaScript errors in the browser console
5. Use `page.pause()` for interactive debugging when needed

