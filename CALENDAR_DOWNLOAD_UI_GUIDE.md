# Calendar Download Feature - UI Integration

## Button Placement Visualization

### 1. Gathering View Page (`/gatherings/view/{id}`)

```
┌─────────────────────────────────────────────────────────────┐
│ [Gathering Name]                                    Actions: │
│                                                               │
│ ┌───────────────┐ ┌──────────────────┐ ┌──────────┐        │
│ │ 📅 Add to     │ │ 🔗 Share Event ▼ │ │ ✅ Attend │ ...   │
│ │   Calendar    │ └──────────────────┘ └──────────┘        │
│ └───────────────┘                                            │
│                                                               │
│ Event Details:                                                │
│ Date: December 15-17, 2025                                   │
│ Location: Event Center                                       │
│ ...                                                           │
└─────────────────────────────────────────────────────────────┘
```

**Button Characteristics:**
- Style: `btn btn-outline-success btn-sm`
- Icon: `bi bi-calendar-plus`
- Position: First action button, before "Share Event"
- Tooltip: "Download calendar file (.ics) for Outlook, Google Calendar, iOS, etc."

---

### 2. Calendar View - Quick View Modal

```
┌────────────────────────────── Quick View ─────────────────┐
│                                                      [X]    │
│  Multi-Day Event                                           │
│  [Festival Badge]                                          │
│                                                            │
│  📅 Date: Dec 15-17, 2025                                 │
│  📍 Location: Event Center                                │
│                                                            │
│  Description:                                              │
│  Lorem ipsum dolor sit amet...                            │
│                                                            │
│  ─────────────────────────────────────────                │
│  ┌──────────────────┐  ┌─────────────────┐               │
│  │ 📅 Add to        │  │ 👁️  Full        │               │
│  │   Calendar       │  │    Details      │               │
│  └──────────────────┘  └─────────────────┘               │
└───────────────────────────────────────────────────────────┘
```

**Button Characteristics:**
- Style: `btn btn-outline-success`
- Icon: `bi bi-calendar-plus`
- Position: First action button, left of "Full Details"
- Opens in new tab/window (via `data-turbo-frame="_top"`)

---

### 3. Public Landing Page (`/gatherings/public-landing/{publicId}`)

```
┌─────────────────────────────────────────────────────────────┐
│                    ⚜  [Event Type]  ⚜                       │
│                                                               │
│                   [Gathering Name]                           │
│                                                               │
│     📅 Dec 15-17, 2025  •  📍 Event Center  •  🏰 Branch    │
│                                                               │
│              ┌──────────────────────────┐                    │
│              │  📅  Add to Calendar     │                    │
│              └──────────────────────────┘                    │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Event Information:                                          │
│  ...                                                          │
└─────────────────────────────────────────────────────────────┘
```

**Button Characteristics:**
- Style: `btn btn-outline-light btn-lg`
- Icon: `bi bi-calendar-plus`
- Position: Hero section, prominently displayed below event meta
- Size: Large (btn-lg) for visibility
- Available to all visitors (no login required if public page enabled)

---

## User Flow

### Authenticated User Flow
```
User on Gathering View
        ↓
Clicks "Add to Calendar"
        ↓
Controller checks permissions
        ↓
ICalendarService generates .ics
        ↓
Browser downloads file
        ↓
User imports into calendar app
```

### Public User Flow (No Login)
```
Visitor on Public Landing Page
        ↓
Clicks "Add to Calendar"
        ↓
Controller verifies public_page_enabled
        ↓
ICalendarService generates .ics
        ↓
Browser downloads file
        ↓
Visitor imports into calendar app
        ↓
(Optional) Visitor registers/logs in
```

---

## Generated .ics File Preview

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//KMP//Gathering Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:gathering-123@kmp.example.com
DTSTAMP:20251104T120000Z
DTSTART;VALUE=DATE:20251215
DTEND;VALUE=DATE:20251218
SUMMARY:Winter Festival 2025
DESCRIPTION:Event Type: Festival\nHosted by: Kingdom Branch\n\nJoin us 
 for our annual winter celebration...\n\nActivities:\n- Heavy Combat\n-
  Archery\n- Arts & Sciences\n\nEvent Steward(s): Lord John Smith\n\nMo
 re information: https://kmp.example.com/gatherings/view/123
LOCATION:Event Center\, 123 Main St\, City\, State
GEO:40.7128;-74.0060
URL:https://kmp.example.com/gatherings/view/123
STATUS:CONFIRMED
ORGANIZER;CN=Kingdom Branch:noreply@kmp.example.com
CATEGORIES:Festival
END:VEVENT
END:VCALENDAR
```

---

## Implementation Details

### CSS Classes Used
- `btn` - Bootstrap button base
- `btn-outline-success` - Green outline (view/quick view)
- `btn-outline-light` - Light outline (public page)
- `btn-sm` - Small size (view page)
- `btn-lg` - Large size (public page)

### Icons Used
- `bi bi-calendar-plus` - Bootstrap Icons calendar with plus
- Consistent across all placements

### Routes
1. **Authenticated**: `/gatherings/{id}/download-calendar`
2. **Public**: `/gatherings/download-calendar/{publicId}`

### Content-Type
- `text/calendar; charset=UTF-8`
- With `Content-Disposition: attachment; filename="event-name-2025-12-15.ics"`

---

## Responsive Design

### Desktop (≥992px)
```
[📅 Add to Calendar]  [🔗 Share Event ▼]  [✅ Attend]  [✏️ Edit]
```

### Tablet (768px-991px)
```
[📅 Add to Calendar]  [🔗 Share ▼]
[✅ Attend]  [✏️ Edit]
```

### Mobile (<768px)
```
[📅 Add to Calendar]
[🔗 Share Event ▼]
[✅ Attend This Gathering]
[✏️ Edit]
```

All buttons stack vertically on mobile with full width for better touch targets.

---

## Accessibility

- ✅ Semantic HTML buttons/links
- ✅ Descriptive text labels
- ✅ Title attributes for additional context
- ✅ Keyboard accessible (tab navigation)
- ✅ Screen reader friendly
- ✅ Sufficient color contrast
- ✅ Icon + text (not icon-only)

---

*This feature seamlessly integrates with the existing KMP UI/UX patterns while providing a valuable service to users.*
