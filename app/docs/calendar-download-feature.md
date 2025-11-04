# Calendar Download Feature

## Overview

The calendar download feature allows users to easily add KMP gatherings (events) to their personal calendars. The system generates iCalendar (.ics) files compatible with all major calendar applications including:

- Google Calendar
- Microsoft Outlook
- Apple Calendar (macOS/iOS)
- Android Calendar
- Any other iCalendar-compatible application

## Implementation

### Components

1. **ICalendarService** (`src/Services/ICalendarService.php`)
   - Generates RFC 5545 compliant iCalendar files
   - Handles text escaping and line folding
   - Supports both single-day and multi-day events
   - Includes event details, location, coordinates, activities, and staff information

2. **Controller Action** (`GatheringsController::downloadCalendar()`)
   - Supports both authenticated and public access
   - Uses gathering ID for authenticated users
   - Uses public_id for public/unauthenticated access
   - Returns properly formatted .ics file as download

3. **Routes** (`config/routes.php`)
   - `/gatherings/:id/download-calendar` - Authenticated access
   - `/gatherings/download-calendar/:publicId` - Public access

4. **UI Buttons**
   - Gathering view page (`templates/Gatherings/view.php`)
   - Quick view modal (`templates/Gatherings/quick_view.php`)
   - Public landing page (`templates/element/gatherings/public_content.php`)

## Usage

### For Authenticated Users

From any gathering view or quick view modal, click the "Add to Calendar" button. The system will:
1. Generate an .ics file with complete event details
2. Download the file to your device
3. Your calendar application can then import the event

### For Public/Unauthenticated Users

On public landing pages, the "Add to Calendar" button is prominently displayed in the hero section. This allows potential attendees to add the event to their calendar before registering or logging in.

## iCalendar Format Details

### Event Properties

The generated iCalendar files include:

- **SUMMARY**: Event name
- **DESCRIPTION**: Full event details including:
  - Event type
  - Hosting branch
  - Event description
  - List of activities
  - Event stewards
  - Link to event page
- **DTSTART/DTEND**: Event date/time
  - Single-day events: Specific times (9 AM - 5 PM default)
  - Multi-day events: All-day format
- **LOCATION**: Event location address
- **GEO**: Geographic coordinates (if available)
- **URL**: Link back to the event page
- **ORGANIZER**: Hosting branch
- **CATEGORIES**: Event type
- **STATUS**: CONFIRMED

### Standards Compliance

- Follows RFC 5545 (iCalendar) specification
- Uses UTC timestamps for consistency
- Implements proper line folding (max 75 octets)
- Escapes special characters (backslash, semicolon, comma, newline)

## Testing

Unit tests are provided in `tests/TestCase/Services/ICalendarServiceTest.php` covering:
- Single-day event generation
- Multi-day event generation
- Filename generation
- Text escaping and special character handling

Run tests:
```bash
vendor/bin/phpunit tests/TestCase/Services/ICalendarServiceTest.php
```

## Security Considerations

1. **Public Access**: The download action is available to unauthenticated users for public gatherings, but only if the gathering's `public_page_enabled` flag is true.

2. **Authenticated Access**: For non-public gatherings, users must be logged in and have view permissions.

3. **Data Sanitization**: All text content is properly escaped to prevent any injection attacks through calendar applications.

## Future Enhancements

Potential improvements for the future:

1. **Customizable Times**: Allow event organizers to specify exact start/end times
2. **Reminders**: Add VALARM components for pre-event reminders
3. **Recurrence**: Support for recurring events
4. **Attendee Tracking**: Use ATTENDEE property to track RSVPs
5. **Calendar Subscription**: Provide a calendar feed URL for automatic updates
6. **Multiple Events**: Allow downloading all gatherings within a date range

## Browser Compatibility

The download functionality works across all modern browsers:
- Chrome/Edge: Direct download
- Firefox: Direct download
- Safari (iOS/macOS): Direct download with option to add directly to Calendar
- Android browsers: Direct download with option to add to calendar app

## Troubleshooting

### File Not Downloading
- Check browser settings for download permissions
- Verify that the gathering exists and is accessible
- Check server logs for errors

### Calendar App Not Recognizing File
- Ensure the file has .ics extension
- Some apps require manual import (File > Import)
- Verify the calendar app supports iCalendar format

### Missing Information
- Verify all required gathering fields are populated
- Check that associated entities (branch, gathering_type) are loaded
- Review server logs for warnings during generation

## Related Files

- `src/Services/ICalendarService.php` - Core service
- `src/Controller/GatheringsController.php` - Controller action
- `config/routes.php` - URL routing
- `templates/Gatherings/view.php` - View page button
- `templates/Gatherings/quick_view.php` - Quick view button
- `templates/element/gatherings/public_content.php` - Public page button
- `tests/TestCase/Services/ICalendarServiceTest.php` - Unit tests
