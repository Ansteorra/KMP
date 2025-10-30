# Gatherings Calendar Feature

## Overview

The Gatherings Calendar is a comprehensive calendar system that provides an interactive, user-friendly way to view and manage gatherings (events) across the kingdom. It offers multiple viewing modes, powerful filtering capabilities, and seamless integration with the existing gathering management system.

## Features

### Multiple View Modes

1. **Month View** - Traditional calendar grid showing all gatherings in a month
2. **Week View** - Focused week-by-week timeline view
3. **List View** - Detailed list format with full gathering information

### Interactive Capabilities

- **Quick View**: Click any gathering to see details in a modal without leaving the calendar
- **Attendance Tracking**: Mark yourself as attending gatherings with one click
- **Location Integration**: Quick access to gathering location maps
- **Real-time Updates**: Calendar updates reflect attendance changes immediately

### Filtering System

Filter gatherings by:
- **Branch**: View gatherings from specific branches
- **Gathering Type**: Filter by event type (tournament, practice, feast, etc.)
- **Activity**: Show only gatherings with specific activities

### Visual Indicators

The calendar uses color-coding and badges to provide quick information:

- **Gathering Type Colors**: Each type has a distinct color for easy identification
- **Attendance Badge** (Green): Shows gatherings you're attending
- **Location Badge** (Blue): Indicates gatherings with location information
- **Multi-day Badge** (Yellow): Marks events spanning multiple days
- **Activity Count Badge** (Gray): Shows number of activities at the gathering

## User Interface

### Navigation

- **Month Navigation**: Previous/Next month arrows
- **Today Button**: Jump to current month instantly
- **View Mode Toggle**: Switch between Month, Week, and List views
- **Date Display**: Clear indication of current month/year

### Filter Sidebar

Located on the left side of the calendar:
- Dropdown selectors for Branch, Gathering Type, and Activity
- Apply/Clear filter buttons
- Legend showing what each badge means

### Calendar Grid (Month View)

- **7-column layout**: Sunday through Saturday
- **Day highlighting**: Current day highlighted in yellow
- **Other months**: Days from previous/next months shown in gray
- **Gathering items**: Compact cards showing:
  - Gathering name
  - Hosting branch
  - Status badges
  - Click to view details

## Usage Scenarios

### For Members

1. **Browse Upcoming Events**
   - Open calendar to see all gatherings in current month
   - Use filters to find specific types of events
   - Click gatherings to see full details

2. **Plan Attendance**
   - Click "Mark Attendance" on gatherings you want to attend
   - See green badges on gatherings you're attending
   - Update attendance status as plans change

3. **Find Location**
   - Click gathering with location badge
   - View map showing exact venue
   - Get directions to gathering

### For Event Stewards

1. **View Event Distribution**
   - See how gatherings are spread across the month
   - Identify potential scheduling conflicts
   - Monitor branch activity levels

2. **Promote Events**
   - Share calendar view with members
   - Highlight multi-day events
   - Show activity offerings

### For Branch Officers

1. **Monitor Branch Activities**
   - Filter calendar to show only branch gatherings
   - Track attendance trends
   - Plan future gatherings

2. **Coordinate with Other Branches**
   - View kingdom-wide gathering schedule
   - Avoid date conflicts
   - Plan collaborative events

## Technical Implementation

### Controller: `GatheringsController::calendar()`

**Location**: `/app/src/Controller/GatheringsController.php`

**Query Parameters**:
- `year`: Display year (default: current year)
- `month`: Display month (default: current month)
- `view`: Display mode - month, week, or list (default: month)
- `branch_id`: Filter by branch ID
- `gathering_type_id`: Filter by gathering type ID
- `activity_id`: Filter by activity ID

**Authorization**: Uses standard gathering index authorization

**Data Loading**:
- Loads gatherings for calendar display period (including partial weeks)
- Eager loads: Branches, GatheringTypes, GatheringActivities
- Loads user's attendance records for badge display
- Applies permission-based branch filtering

### Templates

#### Main Template: `/app/templates/Gatherings/calendar.php`

**Responsibilities**:
- Header with navigation and controls
- Filter sidebar
- View mode selection
- Modal container for quick view

#### View Elements:

1. **Month View**: `/app/templates/element/Gatherings/calendar_month.php`
   - 7-column calendar grid
   - Groups gatherings by date
   - Handles multi-day event display

2. **Week View**: `/app/templates/element/Gatherings/calendar_week.php`
   - Day-by-day timeline
   - Highlights current day
   - Compact gathering display

3. **List View**: `/app/templates/element/Gatherings/calendar_list.php`
   - Detailed gathering cards
   - Full information display
   - Action buttons for each gathering

### JavaScript Controller: `gatherings-calendar-controller.js`

**Location**: `/app/assets/js/controllers/gatherings-calendar-controller.js`

**Stimulus Targets**:
- `quickViewModal`: Bootstrap modal for gathering details
- `quickViewContent`: Container for modal content

**Stimulus Values**:
- `year`: Current calendar year
- `month`: Current calendar month
- `view`: Current view mode

**Methods**:

1. `showQuickView(event)`
   - Loads gathering details via AJAX
   - Displays in modal without page reload
   - Shows loading spinner during fetch

2. `toggleAttendance(event)`
   - Marks/updates attendance for a gathering
   - Updates UI with success/failure
   - Reloads calendar to show changes

3. `showLocation(event)`
   - Navigates to gathering view with location tab active
   - Deep links to specific tab

4. `showToast(title, message, type)`
   - Displays Bootstrap toast notifications
   - Auto-dismisses after 3 seconds
   - Multiple toast support

### Database Schema

**New Field**: `gathering_types.color`
- Type: VARCHAR(7)
- Default: '#0d6efd' (Bootstrap primary blue)
- Purpose: Hex color code for calendar display
- Migration: `20251030000001_AddColorToGatheringTypes.php`

## Styling

### CSS Classes

**Calendar Grid**:
- `.calendar-grid`: Main grid container
- `.calendar-day-header`: Day name headers
- `.calendar-day`: Individual day cell
- `.calendar-day.today`: Current day highlighting
- `.calendar-day.other-month`: Days from adjacent months

**Gathering Items**:
- `.gathering-item`: Individual gathering card
- `.gathering-item.multi-day`: Border for multi-day events
- `.gathering-item.attending`: Border for attending status
- `.gathering-badges`: Badge container
- Hover effects for better interactivity

### Responsive Design

- **Desktop** (>768px): Sidebar + calendar grid
- **Mobile** (<768px): Stacked layout, sidebar collapses
- Touch-friendly buttons and links
- Optimized spacing for small screens

## Integration Points

### Existing Features

1. **Gathering Management**
   - Links to full gathering view
   - Edit/delete permissions respected
   - Uses existing authorization system

2. **Attendance System**
   - Reads from GatheringAttendances table
   - Creates attendance records via existing controller
   - Displays user's attendance status

3. **Location/Maps**
   - Integrates with Google Maps functionality
   - Uses existing location autocomplete
   - Links to location tab on gathering view

4. **Branch Permissions**
   - Respects branch-level access control
   - Filters gatherings based on user permissions
   - Shows only authorized branches in filters

### Navigation Links

- From Gatherings index: "Calendar View" button
- From Calendar: "List View" button
- From any gathering: Links to full view

## Best Practices

### For Administrators

1. **Set Gathering Type Colors**
   - Assign distinct colors to each gathering type
   - Use colors that are visually distinguishable
   - Consider color-blind friendly palettes
   - Test on both light and dark backgrounds

2. **Encourage Attendance Tracking**
   - Promote calendar use among members
   - Use attendance data for planning
   - Monitor popular gathering types

### For Users

1. **Mark Attendance Early**
   - Register for gatherings as soon as you know you're going
   - Update if plans change
   - Check attendance before gathering starts

2. **Use Filters Effectively**
   - Filter by your local branch for nearby events
   - Filter by activity to find specific event types
   - Clear filters to see kingdom-wide calendar

3. **Check Locations**
   - Click location badge to see venue details
   - Get directions before traveling
   - Verify location if unfamiliar

## Future Enhancements

Potential additions to consider:

1. **iCal Export**: Export gatherings to calendar applications
2. **Print View**: Printer-friendly calendar format
3. **Notifications**: Reminders for gatherings you're attending
4. **Weather Integration**: Show weather forecast for gathering dates
5. **Carpooling**: Connect with others attending same gathering
6. **Social Sharing**: Share specific gatherings on social media
7. **Mobile App**: Native mobile calendar application
8. **Recurring Events**: Support for regular practice schedules

## Troubleshooting

### Common Issues

**Calendar doesn't show all gatherings**:
- Check filter settings (may be accidentally filtered)
- Verify branch permissions (may not have access to some branches)
- Clear filters and try again

**Attendance button doesn't work**:
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify CSRF token is present
- Try refreshing the page

**Colors not showing**:
- Verify gathering types have colors assigned
- Check CSS is loading properly
- Clear browser cache

**Quick view modal empty**:
- Check network connectivity
- Verify gathering ID is valid
- Check browser console for errors

## Accessibility

The calendar is designed with accessibility in mind:

- **Keyboard Navigation**: All interactive elements accessible via keyboard
- **Screen Reader Support**: Proper ARIA labels and roles
- **Color Contrast**: Meets WCAG AA standards
- **Focus Indicators**: Clear focus states for all controls
- **Alternative Text**: Icons have descriptive labels

## Security

Security measures implemented:

- **Authorization**: All actions check user permissions
- **CSRF Protection**: Forms protected against CSRF attacks
- **Input Validation**: All user input validated
- **SQL Injection Prevention**: Parameterized queries used
- **XSS Protection**: Output properly escaped

## Performance Considerations

Optimizations for fast performance:

- **Efficient Queries**: Single query loads all calendar data
- **Eager Loading**: Associations loaded efficiently
- **Client-side Grouping**: Gatherings grouped by date in template
- **Modal Loading**: Details loaded on-demand, not upfront
- **CSS Grid**: Hardware-accelerated calendar layout

## Conclusion

The Gatherings Calendar provides a powerful, user-friendly way to view and interact with gatherings across the kingdom. Its intuitive interface, flexible filtering, and seamless integration with existing features make it an essential tool for members, event stewards, and branch officers alike.
