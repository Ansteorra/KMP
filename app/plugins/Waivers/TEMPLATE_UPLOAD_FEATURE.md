# Waiver Template Upload Feature

## Overview

The Waiver Type creation and editing forms now support two methods for configuring template PDFs:

1. **Upload PDF File** - Upload a PDF file that will be stored using the shared Documents model
2. **External URL** - Provide a URL to an externally hosted PDF (e.g., SCA.org hosted documents)

## User Interface

When creating or editing a Waiver Type, users will see a "Template Source" dropdown with three options:

- **No Template** - No template is configured (or keep current template when editing)
- **Upload PDF File** - Upload a new PDF file from your computer
- **External URL** - Provide a URL to an external PDF

Based on the selection, the appropriate input field will be displayed.

## File Upload (Using Documents Model & DocumentService)

When uploading a PDF:
- Files are handled by the centralized `DocumentService` in core
- Files are stored in `/app/images/uploaded/waiver-templates/`
- A `Document` record is created with entity_type='Waivers.WaiverTypes'
- The waiver type links to the document via the `document_id` foreign key
- File metadata includes: original filename, stored filename, file size, checksum (SHA-256), mime type
- Storage adapter is set to 'local' (can be changed to 's3' in the future)
- Only PDF files are accepted (enforced by both HTML5 and server-side validation)

## DocumentService Integration

The waiver template upload feature uses the core `DocumentService` class for all document operations:

- **Upload**: `DocumentService::createDocument()` handles file validation, storage, and record creation
- **Download**: `DocumentService::getDocumentDownloadResponse()` handles file streaming
- **Update**: `DocumentService::updateDocumentEntityId()` updates entity references

This ensures consistency across the application and allows storage strategy changes (local → S3) to be made in one place.

## External URL

When using an external URL:
- The full URL is stored directly in the `template_path` field
- `document_id` remains NULL for external URLs
- URLs must be complete (including `http://` or `https://`)
- Example: `https://www.sca.org/wp-content/uploads/2019/12/rosterwaiver.pdf`

## Database Storage

The `waivers_waiver_types` table has two fields for templates:
- `document_id` (INT, nullable): FK to `documents.id` for uploaded files
- `template_path` (VARCHAR, nullable): External URL for remotely hosted templates

One and only one should be populated at a time.

## Implementation Details

### Controller Changes

**File**: `plugins/Waivers/src/Controller/WaiverTypesController.php`

- Added `_handleTemplateUpload()` method to process file uploads and URL inputs
- Modified `add()` and `edit()` actions to call the upload handler
- Added `downloadTemplate()` action to serve uploaded PDF files for download
- File validation and error handling included

### Entity Changes

**File**: `plugins/Waivers/src/Model/Entity/WaiverType.php`

- Added `template_path` to the `$_accessible` array for mass assignment
- Updated docblock to document the field

### Template Changes

**Files**: 
- `plugins/Waivers/templates/WaiverTypes/add.php`
- `plugins/Waivers/templates/WaiverTypes/edit.php`
- `plugins/Waivers/templates/WaiverTypes/view.php`
- `plugins/Waivers/templates/WaiverTypes/index.php`

- Added template source selection dropdown
- Added conditional file upload field
- Added conditional URL input field
- Modified form to use `'type' => 'file'` for multipart upload
- View template displays download buttons for uploaded files and links for external URLs
- Edit template shows current template with download option
- Index page shows template availability with quick download/view icons

### JavaScript Controller

**File**: `plugins/Waivers/assets/js/controllers/waiver-template-controller.js`

A Stimulus.js controller that:
- Shows/hides appropriate input fields based on template source selection
- Clears unused fields when switching between upload and URL modes
- Manages form state dynamically

## Usage Example

### Creating a Waiver Type with Uploaded Template

1. Navigate to Waivers → Waiver Types → Add Waiver Type
2. Fill in the required fields (Name, Retention Policy, etc.)
3. Select "Upload PDF File" from Template Source dropdown
4. Click "Choose File" and select a PDF from your computer
5. Click "Save Waiver Type"

### Creating a Waiver Type with External URL

1. Navigate to Waivers → Waiver Types → Add Waiver Type
2. Fill in the required fields (Name, Retention Policy, etc.)
3. Select "External URL" from Template Source dropdown
4. Paste the full URL in the text field
5. Click "Save Waiver Type"

## Security Considerations

- File uploads are restricted to PDF files via HTML5 `accept` attribute
- Uploaded files are stored outside the webroot for security
- Unique filenames prevent conflicts and overwrites
- File size limits are enforced by PHP configuration (upload_max_filesize, post_max_size)

## Directory Structure

```
app/
├── images/
│   └── uploaded/
│       └── waiver-templates/    # Uploaded PDF templates stored here
│           └── .gitkeep         # Ensures directory is tracked in git
```

## Browser Compatibility

The feature uses:
- HTML5 file input with `accept=".pdf"` attribute
- Stimulus.js for dynamic form behavior
- Standard form submission (no AJAX required)

All modern browsers are supported.
