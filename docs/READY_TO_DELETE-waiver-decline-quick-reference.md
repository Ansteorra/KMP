# Waiver Decline Feature - Quick Reference

## User Guide: How to Decline a Waiver

### Step 1: Navigate to Waiver
1. Go to the gathering's waiver list
2. Click "View" on the waiver you want to decline
3. Or access directly via URL: `/waivers/gathering-waivers/view/{waiver_id}`

### Step 2: Check Eligibility
The "Decline Waiver" button only appears if:
- You have decline permission for the gathering's branch
- The waiver was uploaded within the last 30 days
- The waiver has not already been declined
- The waiver is not expired or deleted

### Step 3: Decline the Waiver
1. Click the red "Decline Waiver" button
2. A modal dialog will open showing:
   - Waiver details (type, upload date, gathering)
   - Warning that this action cannot be undone
   - Required reason field
3. Enter a detailed reason for declining the waiver
4. Click "Decline Waiver" to confirm

### Step 4: Verification
- Success message confirms the waiver was declined
- Waiver status changes to "Declined" (red badge)
- Decline information is visible on the waiver view page

## Administrator Guide: Setting Up Decline Permissions

### Required Permission
Users need the following permission to decline waivers:
- **Permission Name**: `canDecline`
- **Resource**: `Waivers.GatheringWaivers`
- **Scope**: Branch-level (scoped to gathering's branch)

### Assigning Permission

#### Option 1: Via Role (Recommended)
1. Navigate to Roles management
2. Select or create a role (e.g., "Waiver Manager")
3. Add permission: `Waivers.GatheringWaivers` → `canDecline`
4. Assign role to users who should be able to decline waivers

#### Option 2: Via Direct Permission
1. Navigate to user's permission management
2. Add direct permission for `Waivers.GatheringWaivers` → `canDecline`
3. Scope to specific branch(es) if needed

### Recommended Roles
Consider granting decline permission to:
- Branch Seneschals
- Waiver Coordinators
- Event Coordinators
- Regional Officers (for their branches)

## Common Scenarios

### Scenario 1: Invalid Signature
**Reason Example:**
```
The signature on this waiver is illegible and cannot be verified. 
Please re-submit with a clear, legible signature.
```

### Scenario 2: Wrong Waiver Type
**Reason Example:**
```
This appears to be a Minor's Waiver but was uploaded as an Adult Waiver. 
Please re-upload under the correct waiver type.
```

### Scenario 3: Incomplete Information
**Reason Example:**
```
Critical information is missing from this waiver:
- Emergency contact information is blank
- Date field is not filled in
Please obtain a complete waiver from the participant.
```

### Scenario 4: Poor Image Quality
**Reason Example:**
```
The uploaded image is too dark/blurry to read clearly. 
The following sections are not legible:
- Participant name
- Signature
Please re-upload with better image quality.
```

### Scenario 5: Wrong Event
**Reason Example:**
```
This waiver appears to be for a different event/gathering. 
The event name listed does not match this gathering.
Please verify and upload to the correct gathering.
```

## Troubleshooting

### "Decline Waiver" Button Not Visible

**Check:**
1. Do you have decline permission?
   - Ask administrator to verify your permissions
2. Is the waiver within 30 days of upload?
   - Check upload date on waiver details page
3. Has it already been declined?
   - Look for "Declined" status badge
4. Is the waiver expired or deleted?
   - Check status field

### "Waivers can only be declined within 30 days" Error

**Cause:** More than 30 days have passed since upload

**Solution:** 
- For legitimate issues with old waivers, contact system administrator
- Consider adding a note to the waiver instead of declining
- If the waiver is expired, it can be deleted instead

### "This waiver has already been declined" Error

**Cause:** Another user already declined this waiver

**Solution:**
- View decline information on waiver details page
- Check decline reason to understand why it was declined
- If you disagree with the decline, contact the user who declined it

### Missing Decline Reason

**Cause:** Form submitted without entering a reason

**Solution:**
- Modal will show error and remain open
- Enter a reason in the text field
- Submit again

## Best Practices

### Writing Decline Reasons
✅ **DO:**
- Be specific about what is wrong
- Explain what needs to be fixed
- Provide clear instructions for resubmission
- Be professional and courteous
- Include specific details (e.g., which fields are missing)

❌ **DON'T:**
- Use vague reasons like "Invalid" or "Bad"
- Include personal opinions
- Use offensive language
- Decline without a valid reason

### Timing
- Review and decline waivers promptly after upload
- Don't wait until near the 30-day limit
- Allows time for participants to resubmit

### Communication
- After declining, notify the uploader through other channels if possible
- Provide guidance on how to resubmit correctly
- Be available to answer questions

### Documentation
- Use the decline reason field thoroughly
- Consider adding a note for additional context
- Keep records of common decline reasons for training

## Reporting

### View Declined Waivers
Currently declined waivers can be found by:
1. Viewing individual gathering waiver lists (shows "Declined" badge)
2. Viewing specific waiver details (shows full decline information)

### Future Reporting Features
Planned enhancements:
- Dashboard showing decline statistics
- List of all declined waivers across gatherings
- Decline reason analysis
- User decline activity reports

## Support

### Need Help?
- Check this quick reference guide
- Review full documentation: `/docs/waiver-decline-feature.md`
- Contact system administrator for permission issues
- Submit feedback or feature requests via GitHub Issues

### Report Issues
If you encounter bugs or unexpected behavior:
1. Note the exact error message
2. Record steps to reproduce
3. Include waiver ID and gathering ID
4. Submit via GitHub Issues or contact system administrator
