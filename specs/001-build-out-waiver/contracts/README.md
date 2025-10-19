# API Contracts - Gathering Waiver Tracking System

This directory contains API endpoint specifications for the Gathering Waiver Tracking System.

## Contract Files

### Core Entities (in core KMP)
- `gathering-types.md` - Gathering Type CRUD endpoints
- `gatherings.md` - Gathering CRUD endpoints
- `gathering-activities.md` - Gathering Activity CRUD endpoints

### Plugin Entities (in Waivers plugin)
- `waiver-types.md` - Waiver Type CRUD endpoints
- `waiver-configuration.md` - Configuration management endpoints
- `gathering-waivers.md` - Waiver upload and management endpoints
- `waiver-retention.md` - Retention policy execution endpoints

## API Conventions

All endpoints follow REST conventions and CakePHP routing:

- **Base URLs**:
  - Core: `/gathering-types`, `/gatherings`, `/gathering-activities`
  - Plugin: `/waivers/waiver-types`, `/waivers/waiver-configuration`, `/waivers/gathering-waivers`

- **HTTP Methods**:
  - `GET /resource` - List all (with pagination)
  - `GET /resource/:id` - View single
  - `POST /resource` - Create new
  - `PATCH /resource/:id` - Update existing
  - `DELETE /resource/:id` - Delete

- **Request Format**: `application/json` or `multipart/form-data` (for file uploads)
- **Response Format**: `application/json` or `text/html` (Turbo Stream/Frame responses)

- **Turbo Integration**:
  - Most endpoints support both JSON and HTML responses
  - HTML responses use Turbo Frames/Streams for partial page updates
  - Turbo-Frame header triggers frame-specific responses

- **Authentication**: All endpoints require authentication via CakePHP Authentication plugin
- **Authorization**: Endpoints enforce Policy classes (see Constitution Check in plan.md)

## Error Responses

Standard error format:
```json
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Validation error 1", "Validation error 2"]
  }
}
```

HTTP Status Codes:
- `200 OK` - Success
- `201 Created` - Resource created
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Not authorized
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Business logic error
- `500 Internal Server Error` - Server error

## Pagination

List endpoints support pagination:
- Query params: `?page=2&limit=25`
- Default limit: 20
- Max limit: 100

Response includes pagination metadata:
```json
{
  "data": [...],
  "pagination": {
    "page": 2,
    "perPage": 25,
    "count": 25,
    "totalCount": 150,
    "pageCount": 6,
    "hasNextPage": true,
    "hasPrevPage": true
  }
}
```

## Filtering & Sorting

List endpoints support filtering and sorting:
- Filter: `?filter[field]=value`
- Sort: `?sort=field&direction=asc|desc`
- Multiple filters: `?filter[field1]=value1&filter[field2]=value2`

## Turbo Stream Responses

For actions that trigger Turbo Streams (file uploads, deletions), responses use:
```html
<turbo-stream action="append|prepend|replace|update|remove" target="dom-id">
  <template>
    <!-- HTML content -->
  </template>
</turbo-stream>
```

Actions:
- `append` - Add to end of target
- `prepend` - Add to beginning of target
- `replace` - Replace entire target element
- `update` - Replace target's inner HTML
- `remove` - Remove target element
