# Debugging Waiver Template Upload

## Changes Made

I've updated the controller with:

1. **Better error handling** - Added validation for file objects and detailed error messages
2. **Data cleanup** - Remove `template_file`, `template_url`, and `template_source` from data before patching to entity
3. **Debug logging** - Added Log statements to track the upload process
4. **PDF validation** - Ensure only PDF files are accepted

## How to Test

### Test File Upload

1. Go to Waivers → Waiver Types → Add Waiver Type
2. Fill in required fields (Name, Retention Policy)
3. Select "Upload PDF File" from Template Source dropdown
4. Choose a PDF file
5. Click "Save Waiver Type"

### Check for Errors

**Watch for Flash Messages:**
- Success: "Template file uploaded successfully" + "The waiver type has been saved"
- Errors will show specific messages about what went wrong

**Check Debug Logs:**
```bash
tail -f /workspaces/KMP/app/logs/debug.log
```

Look for lines like:
- `Template source: upload`
- `Form data keys: name, description, template_source, template_file, ...`
- `File object type: Laminas\Diactoros\UploadedFile`

**Check Error Logs:**
```bash
tail -f /workspaces/KMP/app/logs/error.log
```

### Test External URL

1. Go to Waivers → Waiver Types → Add Waiver Type
2. Fill in required fields
3. Select "External URL" from Template Source dropdown
4. Enter a URL like: `https://www.sca.org/wp-content/uploads/2019/12/rosterwaiver.pdf`
5. Click "Save Waiver Type"

## Common Issues and Solutions

### Issue: "Invalid file upload. Please try again."
**Cause:** The file object is not being received correctly
**Check:** 
- Ensure form has `'type' => 'file'` in Form->create()
- Check browser Network tab to see if file is being sent

### Issue: File uploads but template_path is empty
**Cause:** The upload handler is not returning the path correctly
**Check:** 
- Look at debug.log for "Template source" messages
- Verify the template_source select value is being submitted

### Issue: "Only PDF files are allowed"
**Cause:** File extension validation
**Solution:** Ensure you're uploading a .pdf file

### Issue: Permission denied when creating directory
**Cause:** Directory permissions
**Solution:** 
```bash
sudo mkdir -p /workspaces/KMP/app/images/uploaded/waiver-templates
sudo chown www-data:www-data /workspaces/KMP/app/images/uploaded/waiver-templates
sudo chmod 775 /workspaces/KMP/app/images/uploaded/waiver-templates
```

## Verify Upload Directory

Check the directory exists and is writable:
```bash
ls -la /workspaces/KMP/app/images/uploaded/waiver-templates/
```

Should show:
```
drwxrwxr-x  2 www-data www-data  4096 Oct 22 14:00 .
```

## Manual Test Upload

You can test file uploads work in general by checking the member registration form which also uses file uploads.

## JavaScript Console

Open browser DevTools Console (F12) and check for any JavaScript errors related to the waiver-template controller.

## Database Check

After a successful save, check the database:
```bash
# From within the dev container
cd /workspaces/KMP/app
bin/cake console
```

Then in the console:
```php
$waiversTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Waivers.WaiverTypes');
$types = $waiversTable->find()->all();
foreach ($types as $type) {
    echo $type->name . ': ' . $type->template_path . "\n";
}
```

## Next Steps

1. Try uploading a PDF file
2. Check the flash messages for success or error
3. If there's an error, check debug.log and error.log
4. Share any error messages you see
5. Check if the file appears in `/workspaces/KMP/app/images/uploaded/waiver-templates/`
6. Verify the template_path is saved in the database
