# Security Debug Feature - Quick Reference

## What Was Built

A debug-mode-only security information display that shows:
1. **User Policies** - All policies the current user has, with branch scope
2. **Authorization Log** - Every authorization check performed on the page

## How to Use

### In the Browser
1. Enable debug mode (already enabled in dev environment)
2. Log in to KMP
3. Scroll to the footer
4. Click **"Show Security Info"** button (yellow/warning button)
5. View security information panel

### What You'll See

**User Policies Table:**
- Policy Class (e.g., MemberPolicy, WarrantPolicy)
- Method (e.g., canView, canEdit, canDelete)
- Scope (Global, Branch Only, Branch + Children)
- Branch IDs (which branches the policy applies to)

**Authorization Check Log:**
- Sequential number
- Action checked (e.g., 'view', 'edit')
- Resource checked (e.g., 'Member #123')
- Result (✓ Granted in green, ✗ Denied in red)
- Additional arguments count

## Debug Authorization Issues

### Step 1: Reproduce the Issue
Navigate to the page where authorization fails

### Step 2: Open Security Info
Click "Show Security Info" in the footer

### Step 3: Check Authorization Log
Look for denied (red) checks related to your issue

### Step 4: Verify Policies
Check if the user has the required policy method

### Step 5: Check Scope
If policy exists, verify branch IDs match the resource

## Key Files

### Backend
- `app/src/Services/AuthorizationService.php` - Tracks checks
- `app/src/View/Helper/SecurityDebugHelper.php` - Renders display

### Frontend
- `app/assets/js/controllers/security-debug-controller.js` - Toggle UI
- `app/templates/element/copyrightFooter.php` - Footer integration

### Documentation
- `docs/7.4-security-debug-information.md` - Full documentation
- `SECURITY_DEBUG_IMPLEMENTATION.md` - Implementation details

## Production Safety

✅ **Multiple Safeguards:**
1. Only logs when `Configure::read('debug')` is true
2. Button only renders in debug mode
3. Panel only renders in debug mode
4. Helper returns empty string if not debug
5. Zero performance impact when disabled

## Common Issues

**Button not showing?**
- Verify debug mode is enabled
- Clear cache: `bin/cake cache clear_all`
- Check browser console for errors

**No authorization checks logged?**
- Some pages don't require authorization
- Make sure you're logged in
- Try navigating to a member/warrant page

**Empty policies?**
- User may have no roles assigned
- Check user's roles in database
- Verify PermissionsLoader is working

## Quick Tips

💡 **Best Practices:**
- Use this during development to understand authorization flow
- Great for debugging "access denied" issues
- Helpful for validating new policy implementations
- Use to audit user permissions

⚠️ **Remember:**
- This is debug-only; disable debug mode in production
- Don't commit code with hardcoded debug=true
- Clear cache after permission changes

## Example Workflow

### Scenario: User can't edit a member

1. **Login as the user having issues**
2. **Navigate to member view page**
3. **Click "Show Security Info"**
4. **Check Authorization Log:**
   - Look for action='edit' with Member resource
   - If denied (red), proceed to step 5
   - If not present, edit button logic may be wrong
5. **Check User Policies:**
   - Look for MemberPolicy -> canEdit
   - If missing, user needs that permission
   - If present, check branch IDs match member's branch
6. **Fix the issue:**
   - Assign missing role/permission, OR
   - Adjust branch scope, OR
   - Fix policy logic

## Toggle Button States

**"Show Security Info"** → Panel is hidden
**"Hide Security Info"** → Panel is visible

Click to toggle between states.

## Performance Notes

- Logging has minimal overhead (~microseconds per check)
- Data stored in memory (cleared per request)
- No database queries added
- No file I/O operations
- Production: Zero impact (not enabled)

## Further Reading

- Full Documentation: `docs/7.4-security-debug-information.md`
- Authorization System: `docs/4.4-rbac-security-architecture.md`
- Policy Development: `docs/6.2-authorization-helpers.md`
