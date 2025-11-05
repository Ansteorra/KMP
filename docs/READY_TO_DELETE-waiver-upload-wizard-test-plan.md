# Waiver Upload Wizard - Test Plan

## Quick Start Testing

### Prerequisites
1. Ensure you have a gathering with activities created
2. Activities should have waiver type requirements assigned
3. User must have `canUploadWaiver` permission

### Test URL
```
/waivers/gathering-waivers/upload?gathering_id=<ID>
```

## Test Scenarios

### Scenario 1: Happy Path - Single Activity
**Steps**:
1. Navigate to waiver upload page
2. **Step 1**: Select one activity
3. Click "Next"
4. **Step 2**: Verify waiver types shown (all types required by that activity)
5. Select a waiver type
6. Click "Next"
7. **Step 3**: Click "Add Page"
8. Select 2-3 image files from file picker
9. Verify thumbnails appear with "Page 1", "Page 2", etc.
10. Click "Next"
11. **Step 4**: Review section shows:
    - Activity name in list
    - Waiver type name
    - Page count badge
    - Thumbnail grid
12. (Optional) Add notes
13. Click "Upload Waiver"
14. Wait for success confirmation
15. Verify redirect to gathering view
16. Check gathering view shows new waiver(s)

**Expected Result**: ✅ Waiver(s) created successfully with all pages

---

### Scenario 2: Multiple Activities with Common Waiver Types
**Steps**:
1. Navigate to waiver upload page
2. **Step 1**: Select 2+ activities that share common waiver types
3. Click "Next"
4. **Step 2**: Verify ONLY common waiver types shown
   - Example: Activity A requires [Type1, Type2, Type3]
   - Activity B requires [Type2, Type3, Type4]
   - Should show only [Type2, Type3]
5. Select a waiver type
6. Continue to upload

**Expected Result**: ✅ Waiver type filtering works correctly

---

### Scenario 3: Validation - No Activity Selected
**Steps**:
1. Navigate to waiver upload page
2. **Step 1**: Do NOT select any activity
3. Click "Next"

**Expected Result**: ❌ Error message appears, user cannot proceed

---

### Scenario 4: Validation - No Waiver Type Selected
**Steps**:
1. **Step 1**: Select activity
2. **Step 2**: Do NOT select waiver type
3. Click "Next"

**Expected Result**: ❌ Error message appears, user cannot proceed

---

### Scenario 5: Validation - No Pages Added
**Steps**:
1. **Step 1**: Select activity
2. **Step 2**: Select waiver type
3. **Step 3**: Do NOT add any pages
4. Click "Next"

**Expected Result**: ❌ Error message appears, user cannot proceed

---

### Scenario 6: File Validation - Invalid Type
**Steps**:
1. Navigate to Step 3 (Add Pages)
2. Click "Add Page"
3. Select a PDF file or .txt file

**Expected Result**: ❌ Error alert "Invalid file type", file not added

---

### Scenario 7: File Validation - Too Large
**Steps**:
1. Navigate to Step 3 (Add Pages)
2. Click "Add Page"
3. Select an image larger than 10MB

**Expected Result**: ❌ Error alert "File too large", file not added

---

### Scenario 8: Remove Page
**Steps**:
1. Navigate to Step 3 (Add Pages)
2. Add 3 images
3. Click the X button on "Page 2"
4. Verify "Page 3" becomes "Page 2"
5. Proceed to review

**Expected Result**: ✅ Page removed, numbering updated, review shows 2 pages

---

### Scenario 9: Previous Button Navigation
**Steps**:
1. Complete Step 1 (select activity)
2. Complete Step 2 (select waiver type)
3. Click "Previous" button
4. Verify Step 2 is shown with waiver type still selected
5. Click "Previous" again
6. Verify Step 1 is shown with activities still selected

**Expected Result**: ✅ Navigation works, state preserved

---

### Scenario 10: Cancel Button
**Steps**:
1. Complete any step
2. Click "Cancel" link

**Expected Result**: ✅ Redirects to gathering view, no waiver created

---

### Scenario 11: Mobile Camera Capture (iOS/Android)
**Device**: iPhone or Android phone

**Steps**:
1. Open waiver upload on mobile browser
2. Navigate to Step 3
3. Click "Add Page"
4. Verify camera launches (not just gallery)
5. Take photo
6. Verify photo appears in preview

**Expected Result**: ✅ Camera capture works on mobile

---

### Scenario 12: Progress Bar Updates
**Steps**:
1. Observe progress bar at top of wizard
2. Complete Step 1, verify progress ~25%
3. Complete Step 2, verify progress ~50%
4. Complete Step 3, verify progress ~75%
5. Complete Step 4, verify progress 100%

**Expected Result**: ✅ Progress bar fills correctly

---

### Scenario 13: Step Indicators Update
**Steps**:
1. Observe step indicators (badges 1-4)
2. On Step 1: Badge 1 should be blue/primary
3. Complete Step 1: Badge 1 should turn green
4. On Step 2: Badge 2 should be blue
5. Continue pattern through all steps

**Expected Result**: ✅ Active = blue, Completed = green, Upcoming = gray

---

## Edge Cases to Test

### Edge Case 1: Activities with NO Common Waiver Types
**Setup**: Select activities that have no overlapping waiver requirements

**Expected Behavior**: TBD - Currently might show empty list
- Should show error message?
- Should allow manual selection?

**Action Required**: Document intended behavior

---

### Edge Case 2: Gathering with No Activities
**Setup**: Navigate to waiver upload for gathering without activities

**Expected Behavior**: Step 1 should show warning message

---

### Edge Case 3: Network Failure During Submit
**Setup**: 
1. Open browser DevTools
2. Go to Network tab
3. Enable "Offline" mode
4. Complete wizard and submit

**Expected Behavior**: Error message shown, user can retry

---

### Edge Case 4: Browser Refresh Mid-Wizard
**Setup**: Complete Step 1 and 2, then refresh browser

**Expected Behavior**: All progress lost (no auto-save yet)
- User must start over

---

## Browser Compatibility Matrix

| Browser | Desktop | Mobile | Camera Capture | Status |
|---------|---------|--------|----------------|--------|
| Chrome | ✅ | ✅ | ✅ | |
| Firefox | ✅ | ✅ | ⚠️ | |
| Safari | ✅ | ✅ | ✅ | |
| Edge | ✅ | N/A | N/A | |
| Opera | ✅ | ✅ | ✅ | |

Legend:
- ✅ Should work
- ⚠️ Limited support
- ❌ Not supported
- (blank) = Not tested

---

## Performance Testing

### Load Test: Large Images
1. Upload 10 images, each ~5MB
2. Measure time to:
   - Generate previews (FileReader)
   - Submit form (fetch POST)
   - Server processing (image → PDF)

**Target**: Complete in <30 seconds

---

### Load Test: Many Pages
1. Upload 20+ images (max allowed?)
2. Verify:
   - Preview grid scrollable
   - No browser lag/freeze
   - Submit completes successfully

**Target**: No UI freezing, max 60 seconds upload

---

## Accessibility Testing

### Keyboard Navigation
1. Use TAB to navigate through form
2. Use ENTER to select activities/waiver types
3. Use SPACE to toggle checkboxes
4. Use ARROW keys in radio groups
5. Use ENTER on buttons

**Expected**: All interactive elements reachable and operable

---

### Screen Reader Testing (NVDA/JAWS)
1. Enable screen reader
2. Navigate through wizard
3. Verify announcements:
   - Step changes announced
   - Validation errors read aloud
   - Progress updates spoken
   - Form labels read correctly

**Expected**: All content accessible to screen readers

---

## Security Testing

### File Upload Security
1. Try to upload:
   - Executable files (.exe, .sh)
   - Scripts (.php, .js, .html)
   - Very large files (>100MB)
   - Files with special characters in name
   - Files with double extensions (image.jpg.exe)

**Expected**: Only valid image types accepted, proper sanitization

---

### CSRF Protection
1. Open browser DevTools → Network
2. Submit wizard
3. Check POST request has CSRF token header

**Expected**: `X-CSRF-Token` header present

---

### Permission Checks
1. Log in as user WITHOUT `canUploadWaiver` permission
2. Try to access upload URL directly

**Expected**: 403 Forbidden or redirect to error page

---

## Regression Testing

After any code changes, re-run:
1. ✅ Scenario 1: Happy Path
2. ✅ Scenario 3: No Activity validation
3. ✅ Scenario 5: No Pages validation
4. ✅ Scenario 6: Invalid file type
5. ✅ Scenario 9: Previous button navigation

---

## Bug Tracking Template

```markdown
### Bug #XXX: [Short Description]

**Severity**: Critical / High / Medium / Low

**Steps to Reproduce**:
1. 
2. 
3. 

**Expected Behavior**:


**Actual Behavior**:


**Environment**:
- Browser: 
- OS: 
- Screen Size: 
- User Role: 

**Screenshots/Video**:
(attach files)

**Console Errors**:
```
(paste error messages)
```

**Additional Notes**:

```

---

## Test Completion Checklist

### Phase 1: Basic Functionality
- [ ] Happy path works end-to-end
- [ ] All validation works (steps 1-4)
- [ ] File upload and preview works
- [ ] Navigation (Next/Previous) works
- [ ] Submit creates waiver correctly
- [ ] Redirect after success works

### Phase 2: Edge Cases
- [ ] No activities in gathering
- [ ] No common waiver types
- [ ] Network failure handling
- [ ] Browser refresh behavior
- [ ] Duplicate file handling

### Phase 3: Cross-Browser
- [ ] Chrome (desktop)
- [ ] Firefox (desktop)
- [ ] Safari (desktop)
- [ ] Chrome (mobile)
- [ ] Safari (iOS)

### Phase 4: Mobile
- [ ] Camera capture works
- [ ] Touch interactions smooth
- [ ] Responsive layout looks good
- [ ] Performance acceptable

### Phase 5: Accessibility
- [ ] Keyboard navigation works
- [ ] Screen reader compatible
- [ ] Focus management correct
- [ ] Error announcements work

### Phase 6: Security
- [ ] File type validation enforced
- [ ] File size limits enforced
- [ ] CSRF protection active
- [ ] Permission checks working

---

## Sign-Off

**Tester**: _____________________  
**Date**: _____________________  
**Version Tested**: _____________________  
**Overall Status**: ✅ PASS / ❌ FAIL / ⚠️ PASS WITH ISSUES  

**Notes**:


---

**Next Steps After Testing**:
1. Document all bugs found
2. Prioritize fixes (critical first)
3. Retest after fixes applied
4. Update user documentation
5. Announce feature to users
6. Monitor production for issues
