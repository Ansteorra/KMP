# File Size Validation Feature

## Overview

The file size validation feature provides client-side pre-upload validation to warn users when their file uploads exceed PHP-configured limits. This prevents failed uploads and improves user experience by providing immediate feedback before form submission.

## Architecture

### Components

1. **KmpHelper::getUploadLimits()** - PHP helper method that retrieves server upload limits
2. **file-size-validator-controller.js** - Reusable Stimulus controller for client-side validation
3. **Template Integration** - Data attributes pass PHP limits to JavaScript

### How It Works

```
┌─────────────────┐
│  PHP Template   │
│  (View Layer)   │
└────────┬────────┘
         │ Gets upload limits via KmpHelper
         │ $limits = $this->KMP->getUploadLimits()
         ▼
┌─────────────────────────────────────────┐
│  HTML with data-* attributes            │
│  data-file-size-validator-max-size      │
│  data-file-size-validator-max-size-...  │
└────────┬────────────────────────────────┘
         │
         │ Stimulus connects controller
         ▼
┌─────────────────────────────────────────┐
│  file-size-validator-controller.js      │
│  - Monitors file input change events    │
│  - Validates file sizes                 │
│  - Shows warnings/errors                │
│  - Disables submit if invalid           │
└─────────────────────────────────────────┘
```

## PHP Helper Method

### KmpHelper::getUploadLimits()

Located in: `app/src/View/Helper/KmpHelper.php`

**Returns:**
```php
[
    'maxFileSize' => 26214400,        // bytes (smaller of upload_max_filesize and post_max_size)
    'formatted' => '25MB',            // human-readable string
    'uploadMaxFilesize' => 26214400,  // upload_max_filesize in bytes
    'postMaxSize' => 31457280,        // post_max_size in bytes
]
```

**Usage in Templates:**
```php
<?php
// Get limits
$uploadLimits = $this->KMP->getUploadLimits();
?>

<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
```

## JavaScript Controller

### FileSizeValidatorController

Located in: `app/assets/js/controllers/file-size-validator-controller.js`

**Targets:**
- `fileInput` - File input element(s) to monitor (required)
- `warning` - Container for warning messages (optional)
- `submitButton` - Submit button to disable when invalid (optional)

**Values:**
- `maxSize` (Number) - Maximum single file size in bytes (required)
- `maxSizeFormatted` (String) - Human-readable max size, e.g., "25MB" (optional)
- `totalMaxSize` (Number) - Maximum total size for multiple files (optional, defaults to maxSize)
- `showWarning` (Boolean) - Whether to show warning messages (default: true)
- `warningClass` (String) - CSS class for warning alerts (default: 'alert alert-warning')
- `errorClass` (String) - CSS class for error alerts (default: 'alert alert-danger')

**Events Dispatched:**
- `file-size-validator:valid` - All files are valid
- `file-size-validator:invalid` - One or more files exceed limits
- `file-size-validator:warning` - Warning displayed to user

## Implementation Examples

### Example 1: Single File Upload (PDF Template)

```php
<?php
// In template (e.g., WaiverTypes/add.php)
$uploadLimits = $this->KMP->getUploadLimits();
?>

<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    
    <!-- Warning container -->
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    
    <!-- File input -->
    <?= $this->Form->control('template_file', [
        'type' => 'file',
        'label' => 'Upload PDF Template',
        'class' => 'form-control',
        'accept' => '.pdf',
        'data-file-size-validator-target' => 'fileInput',
        'data-action' => 'change->file-size-validator#validateFiles',
        'help' => 'Max size: ' . h($uploadLimits['formatted'])
    ]) ?>
    
    <!-- Submit button (will be disabled if file is too large) -->
    <button type="submit" data-file-size-validator-target="submitButton">
        Save
    </button>
</div>
```

### Example 2: Multiple File Upload (Waiver Images)

```php
<?php
// In template (e.g., GatheringWaivers/upload.php)
$uploadLimits = $this->KMP->getUploadLimits();
?>

<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
     data-file-size-validator-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">
    
    <!-- Warning container -->
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    
    <!-- Multiple file input -->
    <input type="file"
           multiple
           accept="image/*"
           data-file-size-validator-target="fileInput"
           data-action="change->file-size-validator#validateFiles">
    
    <small class="text-muted">
        Max per file: <?= h($uploadLimits['formatted']) ?><br>
        Recommended total: <?= h($uploadLimits['formatted']) ?>
    </small>
</div>
```

### Example 3: Integration with Existing Controllers

For existing upload controllers, add the file-size-validator as an additional controller:

```php
<div data-controller="waiver-template file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    
    <input type="file"
           data-waiver-template-target="fileInput"
           data-file-size-validator-target="fileInput"
           data-action="change->waiver-template#fileSelected change->file-size-validator#validateFiles">
</div>
```

## Updated Templates

The following templates have been updated with file size validation:

### Waiver Templates
1. **WaiverTypes/add.php** - PDF template upload (single file)
2. **WaiverTypes/edit.php** - PDF template upload (single file)
3. **GatheringWaivers/upload.php** - Waiver image uploads (multiple files)
4. **element/GatheringWaivers/upload_wizard_steps.php** - Wizard step 3 (multiple files)

### Member Templates
5. **Members/register.php** - Membership card upload (single file)

## User Experience

### Valid Files
- No warnings shown
- Submit button remains enabled
- Form submission proceeds normally

### Single File Too Large
- **Error alert** displayed immediately
- Submit button **disabled**
- Clear error message: "The file 'document.pdf' (30MB) exceeds the maximum upload size of 25MB."

### Multiple Files - Individual File Too Large
- **Error alert** displayed with list of invalid files
- Submit button **disabled**
- Message: "2 file(s) exceed the maximum upload size of 25MB: • file1.jpg (30MB) • file2.jpg (28MB)"

### Multiple Files - Total Size Warning
- **Warning alert** displayed (not an error)
- Submit button **remains enabled** (user can still try)
- Message: "Warning: You are uploading 5 files with a combined size of 30MB, which exceeds the recommended limit of 25MB. The upload may fail depending on server configuration."

## Event Handling

Listen for validation events:

```javascript
// In another Stimulus controller or custom script
document.addEventListener('file-size-validator:invalid', (event) => {
    console.log('Invalid files:', event.detail.files)
    console.log('Error message:', event.detail.message)
    
    // Custom handling
    // e.g., show custom UI, log to analytics, etc.
})

document.addEventListener('file-size-validator:valid', (event) => {
    console.log('All files valid:', event.detail.files)
    console.log('Total size:', event.detail.totalSize)
})

document.addEventListener('file-size-validator:warning', (event) => {
    console.log('Warning - total size:', event.detail.totalSize)
})
```

## Configuration

### PHP Upload Limits

Configure in `php.ini` or `.htaccess`:

```ini
upload_max_filesize = 25M
post_max_size = 30M
```

The helper method automatically reads these values and provides them to the client.

### Custom Styling

Override default alert classes:

```php
<div data-controller="file-size-validator"
     data-file-size-validator-warning-class-value="custom-warning-class"
     data-file-size-validator-error-class-value="custom-error-class">
```

## Testing

### Manual Testing Checklist

1. **Single file under limit**
   - Select file < max size
   - ✓ No warnings
   - ✓ Submit enabled

2. **Single file over limit**
   - Select file > max size
   - ✓ Error message shown
   - ✓ Submit disabled
   - ✓ Clear error message with file name and sizes

3. **Multiple files - all valid**
   - Select multiple files, all < max size
   - ✓ No warnings
   - ✓ Submit enabled

4. **Multiple files - one invalid**
   - Select multiple files, one > max size
   - ✓ Error message with list
   - ✓ Submit disabled

5. **Multiple files - total size warning**
   - Select multiple files, each valid but total > post_max_size
   - ✓ Warning message shown
   - ✓ Submit remains enabled (warning only)

6. **Browser compatibility**
   - Test on Chrome, Firefox, Safari, Edge
   - Test on mobile devices (iOS Safari, Android Chrome)

## Future Enhancements

Potential improvements:

1. **Progressive upload** - Show per-file upload progress for multiple files
2. **Client-side compression** - Compress images before upload
3. **Drag & drop integration** - Validate files on drop
4. **File type validation** - Extend to validate MIME types client-side
5. **Configurable validation rules** - Allow per-form custom limits

## Troubleshooting

### Warning not showing
- Check that `warning` target exists in HTML
- Verify `showWarning` value is true
- Check browser console for errors

### Submit button not disabling
- Ensure `submitButton` target is set
- Check that button has `data-file-size-validator-target="submitButton"`

### Limits not matching PHP
- Clear template cache: `bin/cake cache clear_all`
- Verify PHP ini settings: `php -i | grep upload_max_filesize`
- Check for `.htaccess` overrides

## Related Documentation

- [JavaScript Development](10-javascript-development.md)
- [View Patterns](4.5-view-patterns.md)
- [Waiver Upload Wizard](waiver-upload-wizard-implementation.md)

## References

- [CakePHP View Helpers](https://book.cakephp.org/5/en/views/helpers.html)
- [Stimulus.js Controllers](https://stimulus.hotwired.dev/reference/controllers)
- [PHP File Uploads](https://www.php.net/manual/en/features.file-upload.php)
