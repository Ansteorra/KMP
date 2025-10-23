# Gathering Waivers API Contract (Plugin)

**Base URL**: `/waivers/gathering-waivers`  
**Controller**: `Waivers\Controller\GatheringWaiversController`  
**Authorization**: Stewards (upload), compliance officers (view/delete)

---

## Key Endpoints

### Upload Waivers (Batch)
**POST** `/waivers/gathering-waivers/upload`

**Request**: `multipart/form-data`
```
gathering_id: 10
member_id: 123 (optional)
images[]: file1.jpg
images[]: file2.jpg
images[]: file3.png
```

**Process**:
1. Validate image formats (JPEG, PNG)
2. Convert each image to black and white PDF (Imagick)
3. Compress with Group4
4. Save to storage backend (Flysystem) and calculate checksum
5. Create Document record with entity_type='Waivers.GatheringWaivers'
6. Calculate retention_date from gathering.end_date + waiver_type.retention_periods
7. Create GatheringWaiver record with document_id reference
8. Update Document.entity_id with GatheringWaiver.id (complete polymorphic link)

**Response (Success)**: Turbo Stream with progress updates
```html
<turbo-stream action="append" target="upload-progress">
  <template>
    <div class="alert alert-success">
      Successfully uploaded 3 waivers for John Smith
    </div>
  </template>
</turbo-stream>
<turbo-stream action="replace" target="waivers-list">
  <template>
    <!-- Updated waiver list HTML -->
  </template>
</turbo-stream>
```

**Response (400 Bad Request - Validation Errors)**:
```json
{
  "message": "Validation failed",
  "errors": {
    "gathering_id": ["Gathering ID is required"],
    "images": ["At least one image file is required"],
    "images.0": ["File must be JPEG or PNG format"],
    "images.1": ["File size exceeds 25MB limit"]
  }
}
```

**Turbo Stream Error Response**:
```html
<turbo-stream action="replace" target="upload-form-errors">
  <template>
    <div class="alert alert-danger">
      <h5>Validation Errors:</h5>
      <ul>
        <li>File must be JPEG or PNG format (file2.jpg)</li>
        <li>File size exceeds 25MB limit (file3.png)</li>
      </ul>
    </div>
  </template>
</turbo-stream>
```

**Response (422 Unprocessable Entity - Conversion Failure)**:
```json
{
  "message": "Image conversion failed",
  "errors": {
    "conversion": [
      "Failed to convert file1.jpg: Image appears to be corrupted",
      "Failed to convert file2.jpg: Insufficient memory for conversion"
    ]
  },
  "retry": true,
  "diagnostics": {
    "file1.jpg": {
      "error": "Image appears to be corrupted",
      "suggestion": "Please retake the photo or try a different file"
    },
    "file2.jpg": {
      "error": "Insufficient memory for conversion",
      "suggestion": "Try uploading fewer files at once or contact support"
    }
  }
}
```

**Turbo Stream Conversion Error Response**:
```html
<turbo-stream action="replace" target="upload-form-errors">
  <template>
    <div class="alert alert-warning">
      <h5>Conversion Failed for 2 files:</h5>
      <ul>
        <li><strong>file1.jpg</strong>: Image appears to be corrupted. Please retake the photo or try a different file.</li>
        <li><strong>file2.jpg</strong>: Insufficient memory for conversion. Try uploading fewer files at once.</li>
      </ul>
      <p>Your form data has been preserved. Please fix the issues above and try again.</p>
    </div>
  </template>
</turbo-stream>
```

**Response (413 Payload Too Large)**:
```json
{
  "message": "Total upload size exceeds limit",
  "errors": {
    "upload": ["Total upload size (45MB) exceeds 100MB limit"]
  }
}
```

**Response (404 Not Found)**:
```json
{
  "message": "Gathering not found",
  "errors": {
    "gathering_id": ["Gathering with ID 10 does not exist"]
  }
}
```

**Response (403 Forbidden)**:
```json
{
  "message": "Authorization failed",
  "errors": {
    "authorization": ["You do not have permission to upload waivers for this gathering"]
  }
}
```

**Response (500 Internal Server Error - Storage Failure)**:
```json
{
  "message": "Storage error occurred",
  "errors": {
    "storage": ["Failed to save file to storage backend"]
  },
  "retry": true
}
```

### List Waivers for Gathering
**GET** `/waivers/gathering-waivers?filter[gathering_id]=10`

Returns all waivers for gathering with document info and download links

**Response**:
```json
{
  "waivers": [
    {
      "id": 1,
      "gathering_id": 10,
      "member_id": 123,
      "waiver_type_id": 1,
      "retention_date": "2032-12-15",
      "status": "active",
      "document": {
        "id": 42,
        "original_filename": "john_smith_waiver.jpg",
        "stored_filename": "john_smith_waiver_20250615_143000.pdf",
        "file_path": "waivers/2025/06/john_smith_waiver_20250615_143000.pdf",
        "mime_type": "application/pdf",
        "file_size": 245680,
        "storage_adapter": "local",
        "metadata": {
          "source": "mobile_camera",
          "converted_from": "image/jpeg",
          "compression_ratio": 0.92
        }
      },
      "waiver_type": {
        "id": 1,
        "name": "Adult General Waiver"
      },
      "member": {
        "id": 123,
        "sca_name": "John Smith"
      }
    }
  ]
}
```

### Download Waiver PDF
**GET** `/waivers/gathering-waivers/:id/download`

**Description**: Download the PDF document associated with a waiver

**Path Parameters**:
- `id` (integer, required): GatheringWaiver ID

**Authorization**: Stewards (own gatherings), compliance officers (all)

**Response (200 OK)**: Binary PDF stream with headers:
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="waiver_john_smith_20250615.pdf"
Content-Length: 245680
Cache-Control: private, max-age=3600
ETag: "abc123def456"
```

**Response (404 Not Found)**:
```json
{
  "message": "Waiver not found",
  "errors": {
    "waiver_id": ["Waiver with ID 999 does not exist"]
  }
}
```

**Response (403 Forbidden)**:
```json
{
  "message": "Authorization failed",
  "errors": {
    "authorization": ["You do not have permission to download this waiver"]
  }
}
```

**Response (404 Not Found - File Missing)**:
```json
{
  "message": "File not found in storage",
  "errors": {
    "file": ["PDF file is missing from storage backend"],
    "file_path": "waivers/2025/06/john_smith_waiver_20250615_143000.pdf"
  },
  "suggestion": "Contact system administrator to investigate storage issue"
}
```

**Response (500 Internal Server Error)**:
```json
{
  "message": "Storage error occurred",
  "errors": {
    "storage": ["Failed to read file from storage backend"]
  }
}
```

### Search Waivers
**GET** `/waivers/gathering-waivers/search?member_id=123&date_from=2025-01-01`

**Description**: Advanced search for waivers with multiple criteria

**Query Parameters**:
- `member_id` (integer, optional): Filter by member
- `gathering_id` (integer, optional): Filter by gathering
- `waiver_type_id` (integer, optional): Filter by waiver type
- `date_from` (date, optional): Filter by gathering date >= value (YYYY-MM-DD)
- `date_to` (date, optional): Filter by gathering date <= value (YYYY-MM-DD)
- `status` (string, optional): Filter by status ('active', 'expired', 'deleted')
- `page` (integer, optional): Page number (default: 1)
- `limit` (integer, optional): Results per page (default: 20, max: 100)

**Authorization**: Stewards (own gatherings), compliance officers (all)

**Response (200 OK)**:
```json
{
  "waivers": [
    {
      "id": 1,
      "gathering": {
        "id": 10,
        "name": "June 2025 Practice",
        "start_date": "2025-06-15"
      },
      "member": {
        "id": 123,
        "sca_name": "John Smith"
      },
      "waiver_type": {
        "id": 1,
        "name": "Adult General Waiver"
      },
      "retention_date": "2032-06-15",
      "status": "active"
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 20,
    "count": 1,
    "totalCount": 1,
    "pageCount": 1,
    "hasNextPage": false,
    "hasPrevPage": false
  }
}
```

**Response (400 Bad Request)**:
```json
{
  "message": "Invalid search parameters",
  "errors": {
    "date_from": ["Date must be in YYYY-MM-DD format"],
    "status": ["Status must be one of: active, expired, deleted"],
    "limit": ["Limit must be between 1 and 100"]
  }
}
```

### Delete Expired Waivers
**POST** `/waivers/gathering-waivers/delete-expired`

**Description**: Two-step deletion process for expired waivers

**Authorization**: Compliance officers only (`delete_expired_waivers` permission)

**Request Body**:
```json
{
  "waiver_ids": [1, 2, 3, 4, 5],
  "confirmation": "DELETE",
  "reason": "Retention period expired, no longer required for legal compliance"
}
```

**Validation Rules**:
- `waiver_ids`: Required array of integers, all must have status='expired'
- `confirmation`: Required, must be exact string "DELETE" (case-sensitive)
- `reason`: Required, min 10 characters, max 500 characters

**Process**:
1. Validate all waiver_ids exist and have status='expired'
2. Verify confirmation string matches "DELETE"
3. Check authorization for `delete_expired_waivers` permission
4. Begin database transaction
5. Delete Document records (CASCADE deletes GatheringWaiver records)
6. Log audit trail with user_id, timestamp, reason
7. Commit transaction

**Response (200 OK)**:
```json
{
  "message": "Successfully deleted 5 expired waivers",
  "deleted": {
    "waivers": 5,
    "documents": 5
  },
  "audit": {
    "logged_by": 123,
    "timestamp": "2025-06-19T15:30:00+00:00",
    "reason": "Retention period expired, no longer required for legal compliance"
  }
}
```

**Turbo Stream Response**:
```html
<turbo-stream action="remove" target="waiver-1"></turbo-stream>
<turbo-stream action="remove" target="waiver-2"></turbo-stream>
<turbo-stream action="remove" target="waiver-3"></turbo-stream>
<turbo-stream action="remove" target="waiver-4"></turbo-stream>
<turbo-stream action="remove" target="waiver-5"></turbo-stream>
<turbo-stream action="prepend" target="flash-messages">
  <template>
    <div class="alert alert-success">Successfully deleted 5 expired waivers</div>
  </template>
</turbo-stream>
```

**Response (400 Bad Request - Validation Errors)**:
```json
{
  "message": "Validation failed",
  "errors": {
    "waiver_ids": ["At least one waiver ID is required"],
    "confirmation": ["Confirmation must be the exact string 'DELETE'"],
    "reason": ["Deletion reason must be at least 10 characters"]
  }
}
```

**Response (422 Unprocessable Entity - Status Violation)**:
```json
{
  "message": "Cannot delete waivers with non-expired status",
  "errors": {
    "waivers": [
      "Waiver ID 1 has status 'active' (must be 'expired')",
      "Waiver ID 3 has status 'active' (must be 'expired')"
    ]
  },
  "suggestion": "Only waivers marked as 'expired' can be deleted. Use the 'Mark as Expired' function first."
}
```

**Response (403 Forbidden)**:
```json
{
  "message": "Authorization failed",
  "errors": {
    "authorization": ["You do not have permission to delete expired waivers"]
  }
}
```

**Response (404 Not Found)**:
```json
{
  "message": "One or more waivers not found",
  "errors": {
    "waiver_ids": [
      "Waiver ID 999 does not exist"
    ]
  }
}
```

---

## Mobile Upload Flow

1. User selects gathering → Turbo Frame loads activity selection
2. User selects activities → System determines required waiver types
3. User taps "Upload Waivers" → HTML5 camera opens
4. User captures 5 photos → Stimulus controller validates
5. User taps "Submit" → Batch POST with multipart/form-data
6. Server converts images to PDFs → Turbo Streams show progress
7. Completion → Turbo Stream shows success + waiver list
