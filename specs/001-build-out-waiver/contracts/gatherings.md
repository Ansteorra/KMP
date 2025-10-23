# Gatherings API Contract

**Base URL**: `/gatherings`  
**Controller**: `App\Controller\GatheringsController`  
**Authorization**: Kingdom officers (full access), gathering stewards (own gatherings)

---

## Key Endpoints

### List Gatherings
**GET** `/gatherings?filter[gathering_type_id]=1&filter[date_range]=2025-06-01,2025-06-30`

Returns paginated list with filtering by type, date range, location

### View Gathering
**GET** `/gatherings/:id`

Returns gathering with associated activities and waivers count

### Create Gathering
**POST** `/gatherings`

**Request**:
```json
{
  "gathering_type_id": 1,
  "name": "June 2025 Practice",
  "description": "Monthly fighter practice",
  "start_date": "2025-06-15",
  "end_date": "2025-06-15",
  "location": "City Park"
}
```

### Update Gathering
**PATCH** `/gatherings/:id`

### Delete Gathering
**DELETE** `/gatherings/:id`

Note: Cannot delete if waivers exist (business rule)

---

## Related Actions

### Add Activities to Gathering
**POST** `/gatherings/:id/activities`

Batch creates activities:
```json
{
  "activities": [
    {"name": "Armored Combat"},
    {"name": "Archery"}
  ]
}
```

---

## Turbo Integration

- Frame: `gatherings-list`, `gathering-{id}`
- Multi-step wizard uses nested frames for activities
