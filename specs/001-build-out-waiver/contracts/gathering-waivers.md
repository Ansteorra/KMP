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
4. Calculate retention_date from gathering.end_date + waiver_type.retention_periods
5. Save to storage backend (Flysystem)
6. Create GatheringWaiver records

**Response**: Turbo Stream with progress updates

### List Waivers for Gathering
**GET** `/waivers/gathering-waivers?filter[gathering_id]=10`

Returns all waivers for gathering with download links

### Download Waiver PDF
**GET** `/waivers/gathering-waivers/:id/download`

Streams PDF file with proper headers:
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="waiver_john_smith.pdf"
```

### Search Waivers
**GET** `/waivers/gathering-waivers/search?member_id=123&date_from=2025-01-01`

Advanced search with multiple criteria

### Delete Expired Waivers
**POST** `/waivers/gathering-waivers/delete-expired`

Two-step process:
1. Mark as `status='expired'` (automatic via Queue job)
2. Compliance officer confirms batch deletion

---

## Mobile Upload Flow

1. User selects gathering → Turbo Frame loads activity selection
2. User selects activities → System determines required waiver types
3. User taps "Upload Waivers" → HTML5 camera opens
4. User captures 5 photos → Stimulus controller validates
5. User taps "Submit" → Batch POST with multipart/form-data
6. Server converts images to PDFs → Turbo Streams show progress
7. Completion → Turbo Stream shows success + waiver list
