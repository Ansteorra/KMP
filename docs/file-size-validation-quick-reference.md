# File Size Validation - Quick Reference

## Quick Start (Copy & Paste)

### Single File Upload

```php
<?php
// Get upload limits
$uploadLimits = $this->KMP->getUploadLimits();
?>

<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    
    <!-- Warning/Error container -->
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    
    <!-- Your file input -->
    <?= $this->Form->control('file', [
        'type' => 'file',
        'data-file-size-validator-target' => 'fileInput',
        'data-action' => 'change->file-size-validator#validateFiles',
        'help' => 'Max size: ' . h($uploadLimits['formatted'])
    ]) ?>
    
    <!-- Optional: Submit button (will be auto-disabled if invalid) -->
    <button type="submit" data-file-size-validator-target="submitButton">
        Upload
    </button>
</div>
```

### Multiple File Upload

```php
<?php
$uploadLimits = $this->KMP->getUploadLimits();
?>

<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
     data-file-size-validator-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">
    
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    
    <input type="file" 
           multiple
           data-file-size-validator-target="fileInput"
           data-action="change->file-size-validator#validateFiles">
</div>
```

## What It Does

✅ Validates file sizes **before** upload  
✅ Shows clear error messages  
✅ Disables submit button for invalid files  
✅ Works with single and multiple files  
✅ Mobile-friendly (works with camera)  
✅ Bootstrap-styled alerts  

## Requirements

1. Get upload limits: `$uploadLimits = $this->KMP->getUploadLimits()`
2. Add `data-controller="file-size-validator"`
3. Add max size values to controller
4. Add warning target (optional but recommended)
5. Add fileInput target to input
6. Add validation action to input

## Data Attributes Reference

### Controller (required)
```html
data-controller="file-size-validator"
```

### Values (required)
```html
data-file-size-validator-max-size-value="26214400"
data-file-size-validator-max-size-formatted-value="25MB"
```

### Values (optional)
```html
data-file-size-validator-total-max-size-value="31457280"
data-file-size-validator-show-warning-value="true"
data-file-size-validator-warning-class-value="alert alert-warning"
data-file-size-validator-error-class-value="alert alert-danger"
```

### Targets
```html
data-file-size-validator-target="fileInput"    <!-- Required on input -->
data-file-size-validator-target="warning"      <!-- Optional but recommended -->
data-file-size-validator-target="submitButton" <!-- Optional -->
```

### Actions
```html
data-action="change->file-size-validator#validateFiles"
```

## Integration with Existing Controllers

### Multiple Controllers on Same Element

```php
<div data-controller="your-controller file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    
    <input type="file"
           data-your-controller-target="file"
           data-file-size-validator-target="fileInput"
           data-action="change->your-controller#handle change->file-size-validator#validateFiles">
</div>
```

### Listen to Events

```javascript
// In your controller
connect() {
    this.element.addEventListener('file-size-validator:invalid', (e) => {
        console.log('Invalid files:', e.detail.files)
    })
    
    this.element.addEventListener('file-size-validator:valid', (e) => {
        console.log('Valid files:', e.detail.files)
    })
}
```

## Error Messages

### Single File > Limit
```
The file "document.pdf" (30MB) exceeds the maximum upload size of 25MB.
```

### Multiple Files > Limit
```
2 file(s) exceed the maximum upload size of 25MB:

• file1.jpg (30MB)
• file2.jpg (28MB)

Please remove or replace these files before uploading.
```

### Total Size Warning
```
Warning: You are uploading 4 files with a combined size of 40MB, 
which exceeds the recommended limit of 25MB. The upload may fail 
depending on server configuration.
```

## Common Patterns

### Pattern 1: Simple Form
```php
<?php $uploadLimits = $this->KMP->getUploadLimits(); ?>
<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    <input type="file" data-file-size-validator-target="fileInput" 
           data-action="change->file-size-validator#validateFiles">
</div>
```

### Pattern 2: With CakePHP Form Helper
```php
<?php $uploadLimits = $this->KMP->getUploadLimits(); ?>
<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    <?= $this->Form->control('document', [
        'type' => 'file',
        'data-file-size-validator-target' => 'fileInput',
        'data-action' => 'change->file-size-validator#validateFiles'
    ]) ?>
</div>
```

### Pattern 3: Hidden Input (for wizards)
```php
<div data-controller="file-size-validator"
     data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
     data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>
    
    <button type="button" onclick="document.getElementById('file').click()">
        Choose Files
    </button>
    
    <input type="file" id="file" class="d-none" multiple
           data-file-size-validator-target="fileInput"
           data-action="change->file-size-validator#validateFiles">
</div>
```

## Troubleshooting

### Validation not working?
1. Check browser console for errors
2. Verify `data-controller="file-size-validator"` exists
3. Verify `data-file-size-validator-target="fileInput"` on input
4. Verify `data-action` includes `change->file-size-validator#validateFiles`
5. Clear browser cache and reload

### Warning not showing?
1. Ensure warning target exists: `data-file-size-validator-target="warning"`
2. Check that element has `d-none` class initially
3. Verify `showWarning` value is not false

### Submit button not disabling?
1. Add target: `data-file-size-validator-target="submitButton"`
2. Ensure button is inside the controller element

## Testing Checklist

- [ ] Select file < limit → No error, submit enabled
- [ ] Select file > limit → Error shown, submit disabled
- [ ] Select multiple files, all valid → No error
- [ ] Select multiple files, one invalid → Error with list, submit disabled
- [ ] Select multiple files, large total → Warning shown, submit enabled
- [ ] Test on Chrome, Firefox, Safari
- [ ] Test on mobile device
- [ ] Test with camera capture (mobile)

## Real Examples

See these files for working implementations:

1. **Single PDF:** `plugins/Waivers/templates/WaiverTypes/add.php`
2. **Multiple Images:** `plugins/Waivers/templates/element/GatheringWaivers/upload_wizard_steps.php`
3. **Member Card:** `templates/Members/register.php`

## Need Help?

- **Full Documentation:** `docs/file-size-validation.md`
- **Implementation Summary:** `docs/file-size-validation-summary.md`
- **Controller Source:** `assets/js/controllers/file-size-validator-controller.js`
- **Helper Source:** `src/View/Helper/KmpHelper.php` (getUploadLimits method)
- **Tests:** `tests/TestCase/View/Helper/KmpHelperUploadLimitsTest.php`
