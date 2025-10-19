# Gathering Types API Contract

**Base URL**: `/gathering-types`  
**Controller**: `App\Controller\GatheringTypesController`  
**Authorization**: Kingdom officers and branch stewards

---

## Endpoints

### 1. List All Gathering Types

**GET** `/gathering-types`

**Description**: Retrieve a list of all gathering types

**Query Parameters**:
- `page` (integer, optional): Page number for pagination (default: 1)
- `limit` (integer, optional): Items per page (default: 20, max: 100)
- `sort` (string, optional): Sort field (default: 'name')
- `direction` (string, optional): Sort direction ('asc' or 'desc', default: 'asc')

**Authorization**: All authenticated users

**Response** (200 OK):
```json
{
  "gatheringTypes": [
    {
      "id": 1,
      "name": "Practice",
      "description": "Regular fighter practice",
      "created": "2025-01-15T10:00:00+00:00",
      "modified": "2025-01-15T10:00:00+00:00"
    },
    {
      "id": 2,
      "name": "Tournament",
      "description": "Competitive fighting event",
      "created": "2025-01-15T10:00:00+00:00",
      "modified": "2025-01-15T10:00:00+00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "perPage": 20,
    "count": 2,
    "totalCount": 2,
    "pageCount": 1,
    "hasNextPage": false,
    "hasPrevPage": false
  }
}
```

**Turbo Frame Response**: Returns HTML table of gathering types in `<turbo-frame id="gathering-types-list">`

---

### 2. View Single Gathering Type

**GET** `/gathering-types/:id`

**Description**: Retrieve details of a specific gathering type

**Path Parameters**:
- `id` (integer, required): Gathering Type ID

**Authorization**: All authenticated users

**Response** (200 OK):
```json
{
  "gatheringType": {
    "id": 1,
    "name": "Practice",
    "description": "Regular fighter practice",
    "created": "2025-01-15T10:00:00+00:00",
    "modified": "2025-01-15T10:00:00+00:00",
    "_matchingData": {
      "Gatherings": [
        {
          "id": 10,
          "name": "June 2025 Practice",
          "start_date": "2025-06-15"
        }
      ]
    }
  }
}
```

**Response** (404 Not Found):
```json
{
  "message": "Gathering Type not found"
}
```

---

### 3. Create Gathering Type

**POST** `/gathering-types`

**Description**: Create a new gathering type

**Authorization**: Kingdom officers only

**Request Body**:
```json
{
  "name": "Workshop",
  "description": "Educational workshop or class"
}
```

**Validation Rules**:
- `name`: Required, max 100 characters, unique
- `description`: Optional, plain text

**Response** (201 Created):
```json
{
  "message": "Gathering Type created successfully",
  "gatheringType": {
    "id": 3,
    "name": "Workshop",
    "description": "Educational workshop or class",
    "created": "2025-06-19T14:30:00+00:00",
    "modified": "2025-06-19T14:30:00+00:00"
  }
}
```

**Response** (400 Bad Request):
```json
{
  "message": "Validation failed",
  "errors": {
    "name": ["Name is required", "Name must be unique"]
  }
}
```

**Turbo Stream Response**: Appends new row to gathering types table

---

### 4. Update Gathering Type

**PATCH** `/gathering-types/:id`

**Description**: Update an existing gathering type

**Path Parameters**:
- `id` (integer, required): Gathering Type ID

**Authorization**: Kingdom officers only

**Request Body**:
```json
{
  "name": "Advanced Workshop",
  "description": "Advanced educational workshop for experienced members"
}
```

**Validation Rules**: Same as Create

**Response** (200 OK):
```json
{
  "message": "Gathering Type updated successfully",
  "gatheringType": {
    "id": 3,
    "name": "Advanced Workshop",
    "description": "Advanced educational workshop for experienced members",
    "created": "2025-06-19T14:30:00+00:00",
    "modified": "2025-06-19T15:00:00+00:00"
  }
}
```

**Response** (404 Not Found):
```json
{
  "message": "Gathering Type not found"
}
```

**Turbo Stream Response**: Replaces updated row in gathering types table

---

### 5. Delete Gathering Type

**DELETE** `/gathering-types/:id`

**Description**: Delete a gathering type (only if no gatherings reference it)

**Path Parameters**:
- `id` (integer, required): Gathering Type ID

**Authorization**: Kingdom officers only

**Response** (200 OK):
```json
{
  "message": "Gathering Type deleted successfully"
}
```

**Response** (422 Unprocessable Entity):
```json
{
  "message": "Cannot delete Gathering Type: 5 gatherings are using this type"
}
```

**Response** (404 Not Found):
```json
{
  "message": "Gathering Type not found"
}
```

**Turbo Stream Response**: Removes deleted row from gathering types table

---

## UI Integration

### Turbo Frame IDs

- `gathering-types-list` - Main table of gathering types
- `gathering-type-form` - Create/edit form
- `gathering-type-{id}` - Individual row in table

### Stimulus Controllers

- `gathering-type-form-controller.js` - Form validation and submission
- `confirmation-controller.js` - Delete confirmation modal

### Typical User Flow

1. User navigates to `/gathering-types` → Server renders index with Turbo Frame
2. User clicks "Add Gathering Type" → Form loads in `gathering-type-form` frame
3. User fills form and submits → POST request with Turbo Stream response
4. Turbo Stream appends new row to table without full page reload
5. User clicks "Edit" on row → Form loads with existing data
6. User updates and saves → PATCH request with Turbo Stream response
7. Turbo Stream replaces row with updated data
