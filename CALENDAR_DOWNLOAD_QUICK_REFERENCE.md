# Calendar Download Feature - Quick Reference Guide

## For End Users

### How to Add a Gathering to Your Calendar

#### From the Gathering View Page
1. Navigate to any gathering's detail page
2. Click the **"Add to Calendar"** button (with calendar icon)
3. The .ics file will download automatically
4. Open the downloaded file to import into your calendar app

#### From the Calendar View (Quick View Modal)
1. Click on any event in the calendar view
2. In the quick view modal, click **"Add to Calendar"**
3. Download and import the file

#### From the Public Landing Page (No Login Required)
1. Visit a gathering's public landing page
2. Click the **"Add to Calendar"** button in the hero section
3. Download and import the file

### Importing into Different Calendar Apps

#### Google Calendar
- **Desktop**: File > Import > Select downloaded .ics file
- **Mobile**: Downloads app usually offers "Add to Calendar" option

#### Microsoft Outlook
- **Desktop**: File > Open & Export > Import/Export > Import an iCalendar (.ics) file
- **Web**: Click calendar icon in downloaded file or drag file into Outlook calendar
- **Mobile**: Tap downloaded file and select Outlook

#### Apple Calendar (macOS/iOS)
- **macOS**: Double-click the .ics file
- **iOS**: Tap the downloaded .ics file and select "Add to Calendar"

#### Android Calendar
- **Any Android device**: Tap the downloaded file and select your calendar app

## For Developers

### Testing the Feature

#### Manual Testing
```bash
# 1. Start the development server (if not already running)
cd /workspaces/KMP/app
bin/cake server

# 2. Navigate to a gathering view page
# URL: http://localhost:8765/gatherings/view/[id]

# 3. Click "Add to Calendar" button

# 4. Check downloaded .ics file:
# - Should have proper filename (event-name-YYYY-MM-DD.ics)
# - Should contain VCALENDAR format
# - Should open in calendar application
```

#### Automated Testing
```bash
cd /workspaces/KMP/app
vendor/bin/phpunit tests/TestCase/Services/ICalendarServiceTest.php
```

### API Endpoints

#### Authenticated Access
```
GET /gatherings/{id}/download-calendar
```
- Requires: Valid user session with view permission
- Returns: application/calendar .ics file

#### Public Access
```
GET /gatherings/download-calendar/{publicId}
```
- Requires: Gathering must have `public_page_enabled = true`
- Returns: application/calendar .ics file

### Code Examples

#### Generating iCalendar in Code
```php
use App\Services\ICalendarService;

$iCalendarService = new ICalendarService();
$gathering = $this->Gatherings->get($id, [
    'contain' => ['Branches', 'GatheringTypes', 'GatheringActivities']
]);

$icsContent = $iCalendarService->generateICalendar(
    $gathering, 
    'https://example.com/event/view/123'
);

$filename = $iCalendarService->getFilename($gathering) . '.ics';
```

#### Adding Button to a Template
```php
<?= $this->Html->link(
    '<i class="bi bi-calendar-plus"></i> ' . __('Add to Calendar'),
    ['action' => 'downloadCalendar', 'id' => $gathering->id],
    [
        'class' => 'btn btn-outline-success btn-sm',
        'escape' => false,
        'title' => __('Download calendar file (.ics)')
    ]
) ?>
```

## Troubleshooting

### Button Not Appearing
- **Check**: User has view permission for the gathering
- **Check**: Template file includes the button code
- **Check**: Browser cache (hard refresh: Ctrl+F5)

### Download Not Working
- **Check**: Browser download settings/permissions
- **Check**: Server error logs for PHP errors
- **Check**: Network tab in browser dev tools for failed requests

### Calendar App Won't Import
- **Check**: File has .ics extension
- **Check**: File opens in text editor and shows VCALENDAR format
- **Check**: Calendar app supports iCalendar format (all modern apps do)

### Missing Event Details
- **Check**: Gathering has all required fields populated
- **Check**: Associated entities (branch, gathering_type) are loaded
- **Check**: Server logs for any warnings during generation

## Feature Compatibility

### Supported Calendar Applications
✅ Google Calendar (all platforms)  
✅ Microsoft Outlook (2010+)  
✅ Apple Calendar (macOS/iOS)  
✅ Android Calendar (all versions)  
✅ Mozilla Thunderbird  
✅ Yahoo Calendar  
✅ Any RFC 5545 compliant calendar app  

### Browser Support
✅ Chrome/Edge (all versions)  
✅ Firefox (all versions)  
✅ Safari (macOS/iOS)  
✅ Opera  
✅ Mobile browsers (iOS/Android)  

## Quick Tips

1. **Multi-day events** appear as all-day events in your calendar
2. **Single-day events** default to 9 AM - 5 PM (can be adjusted in calendar app)
3. **Location coordinates** enable "get directions" features in calendar apps
4. **Event description** includes all gathering details, activities, and staff
5. **Link to event page** is included for easy reference
6. Downloaded files can be **shared via email** with others

## Support

For issues or questions:
1. Check this guide's troubleshooting section
2. Review the detailed documentation: `docs/calendar-download-feature.md`
3. Check server logs: `logs/error.log`
4. Contact system administrator

---
*Last Updated: November 4, 2025*
