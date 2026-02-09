# What's New in KMP

Stay up to date with the latest features, improvements, and announcements for the Kingdom Management Portal.

<!-- CHANGELOG_SYNC_MARKER: This line is used by the sync-changelog prompt to track the last synced commit -->
<!-- LAST_SYNCED_COMMIT: 1f3747e56f054dab4f640aac4f6d23ba295952e1 -->
<!-- LAST_SYNCED_DATE: 2026-02-06 -->

## February 2026

### iCalendar Subscription Feeds

KMP now offers iCalendar subscription feeds so members can subscribe to gathering calendars directly in Google Calendar, Apple Calendar, Outlook, and other calendar apps. The feed updates automatically — no manual downloads needed.

- Public `/gatherings/feed` endpoint returning RFC 5545 multi-event VCALENDAR
- No authentication required — shareable subscribe URL
- Accepts the same `filter[column][]` params as the calendar grid
- Includes gatherings from 30 days ago onward, including cancelled ones (marked with `STATUS:CANCELLED`)
- Only public-safe data (name, dates, location, description)
- 6-hour refresh interval hint for calendar clients
- Subscribe button in the calendar toolbar with copy-to-clipboard URL
- Calendar display name reflects active filters
- Full OpenAPI spec for the feed endpoint

📅 February 6, 2026 · `New Feature`

---

### iCalendar Feed Uses Grid Filters

The iCalendar (.ics) subscription feed now accepts the same `filter[column][]` query parameters as the calendar grid, so the subscribe URL always matches the user's active filters — including multiple branches, gathering types, and activity filters.

- Feed URL uses `filter[branch_id][]=...&filter[gathering_type_id][]=...` format (same as the calendar grid)
- Subscribe link updates dynamically as filters are added or removed
- VEVENT UIDs use `gathering-{public_id}@host` for stable identifiers
- Branch filter across all gathering grids now uses `public_id` instead of internal database ID

📅 February 6, 2026 · `Improvement`

---

### Mobile Login Redirect

Mobile phone users are now automatically redirected to their auth card after logging in, providing a more streamlined mobile experience. Tablets continue to use the standard profile view.

- Detects mobile phones via `StaticHelpers::isMobilePhone()` (excludes tablets)
- Redirects phone users to `viewMobileCard` instead of profile
- Sets session `viewMode` to `mobile` for consistency
- Login page is now mobile-friendly with responsive CSS and touch-friendly inputs

📅 February 6, 2026 · `Improvement`

---

### Improved Cancelled Event Visibility

Cancelled gatherings are now much easier to spot in the mobile calendar and My RSVPs views, reducing confusion about event status.

- Prominent CANCELLED banner on cancelled event cards
- Red border and adjusted opacity for cancelled events
- Gathering type badge moved above the event title to prevent layout issues
- Check-circle icon only shown for non-cancelled events

📅 February 6, 2026 · `Improvement`

---

### Security Fix: fast-xml-parser

Resolved a denial-of-service vulnerability (GHSA-37qj-frw5-hhjh) in the transitive dependency `fast-xml-parser` by pinning it to ≥ 5.3.4.

- npm override added to force `fast-xml-parser` ≥ 5.3.4
- Fixes RangeError DoS via numeric entities

📅 February 6, 2026 · `Security`

---

### Bug Fixes and Code Quality

Several bug fixes and security hardening changes based on code review feedback.

- Fix XSS vulnerability in ServicePrincipals view (added `h()` escaping)
- Fix `toString()` fatal error in MembersController (use `(string)` cast)
- Prevent fatal redeclaration of `addOptions()` in assignModal template
- Replace deprecated `document.execCommand('copy')` with Clipboard API
- Validate CIDR bits range (0–32) and reject IPv6/invalid IPs in ServicePrincipal
- Add null guards on authorization service in API auth methods
- Handle token save failure in ServicePrincipalsController
- Merge OpenAPI paths at HTTP-method level instead of overwriting

📅 February 6, 2026 · `Bug Fix`

---

### Member Warrantable Status Sync Command

New CLI command to automatically review and correct stale `warrantable` flags on member records — typically caused by expired memberships. Designed to run as a nightly cron job.

- Command: `bin/cake sync_member_warrantable_statuses`
- Scans members modified in the last 24 hours and those still marked warrantable with expired membership
- Supports `--dry-run` / `-d` flag to preview changes without saving
- Reports summary of scanned, changed, and errored records
- Example cron: `1 0 * * * ... runCakeCommand.sh --workdir /path/to/app sync_member_warrantable_statuses`

📅 February 6, 2026 · `New Feature`

---

### REST API with Service Principal Authentication

A new REST API layer enables external systems to integrate with KMP. Authenticated via service principal tokens, the API returns JSON responses with a consistent envelope format. Interactive documentation is available via Swagger UI.

- Service principal authentication (Bearer token, X-API-Key header, or query param)
- Public branch endpoints (no auth required) with parent IDs for tree reconstruction
- Officers plugin API: roster, offices, and departments
- Activities plugin API: member authorization lookup by membership#, SCA name, or email
- Swagger UI at `/api-docs/` with auto-merged plugin spec fragments
- Proper JSON error responses for all API endpoints

📅 February 6, 2026 · `New Feature`

---

### Branch Public IDs

Branches now use public IDs (8-character alphanumeric) in all URLs and API responses, replacing internal database IDs. This improves security and provides stable external references.

- Public IDs generated for all existing and new branches
- All web UI links updated to use public_id
- API responses expose public_id as the branch identifier
- Grid navigation uses public_id for branch views

📅 February 6, 2026 · `Security`

---

### Modular OpenAPI Documentation System

Plugins can now publish their own OpenAPI spec fragments that are automatically merged into a combined API specification. No changes to the core app needed when adding plugin APIs.

- Base spec at `webroot/api-docs/openapi.yaml` for core endpoints
- Plugin fragments at `plugins/{Name}/config/openapi.yaml`
- Automatic discovery and deep merge of tags, paths, and schemas
- Served at `/api-docs/openapi.json` for Swagger UI consumption

📅 February 6, 2026 · `New Feature`

---

### Plugin Data Injection for API Responses

New ApiDataRegistry allows plugins to enrich API responses from other controllers — the API equivalent of ViewCellRegistry. Plugins register providers that inject data into detail endpoints.

- Officers plugin injects current officers into branch API view
- Route-based matching ensures providers only run for relevant endpoints
- Extensible pattern for future plugin API integrations

📅 February 6, 2026 · `New Feature`

---

### Comprehensive Documentation Updates

Developer and feature documentation updated across the board to reflect recent changes including API development patterns, waiver workflow features, and gathering management.

- New API development guide (creating endpoints, OpenAPI docs, data injection)
- Complete REST API endpoint reference
- Updated waivers docs: exemptions, closure workflow, PDF download, cancel impact
- Updated gatherings docs: cancel/restore, steward editing permissions
- 44 pre-existing test failures fixed across the test suite

📅 February 6, 2026 · `Documentation`

---

### Mobile Experience Redesign

The mobile experience has been completely redesigned with a fresh new look and improved navigation.

- New Calendar page with weekly event list view
- New My RSVPs page showing your upcoming events
- Type and activity filtering for finding events
- View Details and Edit RSVP buttons on event cards
- Offline support for My RSVPs with cached data
- Visual indicators when working offline

📅 February 2, 2026 · `New Feature`

---

### Enhanced Document Upload Support

Upload images or PDFs directly as waivers - they're automatically converted to a single PDF. New PDF processing services provide better validation and handling of uploaded documents.

- Upload PDF, JPEG, PNG, GIF, BMP, and WEBP images as waivers
- Automatic image-to-PDF conversion with proper page sizing
- PDF validation with page counting and size limits
- Improved waiver upload wizard interface
- PDF merging capabilities for multi-page documents

📅 February 2, 2026 · `Improvement`

---

## January 2026

### Gathering Cancellation Management

Cancel and restore gatherings with clear visual indicators across public views and calendars. Cancelled gatherings remain in the system for record-keeping but are clearly marked.

- Cancel gatherings while preserving history
- Restore previously cancelled gatherings
- Visual cancelled indicators on public and calendar views
- Filtering support for cancelled status

📅 January 31, 2026 · `New Feature`

---

### Branch Tree View for All Users

All authenticated members can now view the branch hierarchy with an improved tree-aware display. Branches are accessible to everyone for better organizational visibility.

- Tree-aware branch grid rendering
- Branches visible to all authenticated users
- Enhanced branch hierarchy navigation
- Added Branches to main navigation

📅 January 31, 2026 · `Improvement`

---

### Steward Edit Permissions for Gatherings

Gathering stewards can now edit the gatherings they manage. Branch selection is locked appropriately based on user permissions.

- Stewards have edit access to their gatherings
- Branch selection locked based on permissions
- Improved gathering management for event organizers

📅 January 31, 2026 · `Improvement`

---

### Waiver Ready-to-Close Status

Waiver managers can now mark gatherings as "ready to close" for the waiver secretary, streamlining the waiver closure workflow.

- Flag gatherings as ready for waiver closure
- Waiver secretary dashboard improvements
- Better waiver lifecycle tracking
- Streamlined closure workflow

📅 January 31, 2026 · `New Feature`

---

### Bulk Selection for Grid Views

Select multiple items at once in grid views for more efficient batch operations.

- Bulk selection functionality in data grids
- Works with related controllers
- Streamlined batch workflows

📅 January 24, 2026 · `Improvement`

---

### Mobile Card Token Generation

Mobile card tokens are now automatically generated when members log in if one doesn't exist, ensuring seamless mobile card access.

- Automatic token generation on login
- No manual intervention required
- Improved mobile card reliability

📅 January 16, 2026 · `Improvement`

---

### Configurable Calendar Week Start

The gatherings calendar now supports configurable week start day for different regional preferences.

- Configure which day the calendar week starts
- Better regional customization
- Consistent calendar display

📅 January 17, 2026 · `Improvement`

---

### Enhanced Gathering Timezone Handling

Improved timezone handling for multi-day gatherings ensures dates display correctly across calendar views and public pages.

- Better multi-day gathering detection
- Defensive timezone conversion fallbacks
- Consistent date display across views
- Improved calendar date accuracy

📅 January 16, 2026 · `Improvement`

---

### Officer Synchronization

New sync functionality for officers allows bulk synchronization of officer records with authorization checks.

- Sync officers across branches
- Updated authorization controls
- Better data consistency

📅 January 15, 2026 · `New Feature`

---

### Gathering Waiver Closure

Close gathering waivers when events are complete, with download authorization controls for completed waiver records.

- Close waivers for completed gatherings
- Download authorization for gathering waivers
- Improved waiver lifecycle management

📅 January 14, 2026 · `New Feature`

---

### Permission Import Tool

Bulk import and update authorization policies with a new import wizard. Preview changes before applying them and get confirmation of what will be modified.

- Upload policy configuration files
- Preview changes before applying
- Confirmation workflow for bulk updates
- Detailed change summary

📅 December 31, 2025 · `New Feature`

---

### Waiver Management Improvements

Streamlined waiver management interface with simplified displays and improved wizard steps for gathering waivers.

- Simplified gathering waivers display
- Improved wizard step navigation
- Better error handling for PDF conversions
- Enhanced preview generation

📅 December 21, 2025 · `Improvement`

---

### Enhanced Data Grid Filtering

New filtering options for data grids including "is populated" filter type for finding records with or without specific data.

- "Is populated" filter for checking field presence
- Improved dropdown filter structure
- Better grid column definitions for Offices
- Grid columns for Gathering Waivers

📅 December 14, 2025 · `Improvement`

---

### Office Reporting Structure

Comprehensive documentation and improvements for office reporting hierarchies and organizational structure.

- Office reporting structure documentation
- Improved office management views
- Better hierarchy visualization

📅 December 13, 2025 · `Improvement`

---

### Member Privacy Controls

Enhanced privacy settings give members and administrators more control over who can see personal information (PII).

- Configure visibility for sensitive member fields
- Permission-based PII access controls
- Audit logging for data access
- Compliance with privacy requirements

📅 December 6, 2025 · `Security`

---

### Super User Impersonation Mode

Administrators can now impersonate other users for troubleshooting and support purposes, with full audit logging of all actions taken.

- Impersonate any user account
- Full audit trail of impersonation sessions
- All actions logged with impersonator identity
- Easy exit from impersonation mode

📅 December 5, 2025 · `Security`

---

### Youth Age-Up Automation

Automated system for transitioning youth members to adult status when they reach the appropriate age.

- Automatic age calculation and status updates
- Cron job for scheduled processing
- Documentation for youth transitions
- Configurable age thresholds

📅 December 5, 2025 · `New Feature`

---

### Active Window Status Synchronization

Automated synchronization of active window statuses across the system with scheduled cron job support.

- Automated status synchronization
- Cron script for scheduled runs
- Improved data consistency
- Comprehensive documentation

📅 December 5, 2025 · `Improvement`

---

### Gatherings Calendar Enhancement

Improved calendar functionality for gatherings with better date handling and grid displays.

- Enhanced calendar views
- Improved Dataverse Grid integration
- Better date and time handling
- Performance optimizations

📅 December 5, 2025 · `Improvement`

---

### Code Editor for Settings

New YAML and JSON code editor with real-time validation for editing complex configuration settings.

- Syntax highlighting for YAML and JSON
- Real-time validation with error messages
- Improved indentation handling
- Better timezone notice rendering

📅 December 1, 2025 · `New Feature`

---

### Waiver Exemption System

New system for managing waiver exemptions, allowing certain members to be exempt from specific waiver requirements.

- Create and manage waiver exemptions
- Track exemption reasons and approvals
- Integration with gathering registration
- Audit logging for exemptions

📅 November 6, 2025 · `New Feature`

---

### Authorization Request Retraction

Members can now retract pending authorization requests, with automatic notification to approvers.

- Retract pending authorization requests
- Automatic approver notifications
- Clear status tracking
- Improved request workflow

📅 November 6, 2025 · `New Feature`

---

### Comprehensive Timezone Support

Application-wide timezone improvements ensure dates and times display correctly based on gathering locations and user preferences.

- Timezone-aware date displays
- Gathering-specific timezone handling
- Improved calendar date comparisons
- User timezone preferences

📅 November 5, 2025 · `Improvement`

---

### Public ID System

Enhanced security and privacy with new public ID system for sharing gathering information without exposing internal identifiers.

- Public IDs for gatherings
- Secure sharing links
- Privacy-focused design
- Integration with waiver lookups

📅 November 3, 2025 · `Security`

---

### Gathering Public Landing Page

Beautiful new medieval-themed public landing page for gatherings with comprehensive event information.

- Medieval aesthetic design
- Detailed event information display
- Public content sections
- Mobile-responsive layout

📅 November 4, 2025 · `New Feature`

---

### Template Activities for Gatherings

Define template activities for gathering types that automatically populate when creating new gatherings.

- Template activities per gathering type
- Non-removable required activities
- Automatic population on creation
- Flexible activity management

📅 November 2, 2025 · `New Feature`

---

### Gathering Calendar Views

New calendar interface for viewing gatherings with month, week, and list view options.

- Month view calendar display
- Week view for detailed planning
- List view for quick scanning
- Calendar download feature

📅 October 30, 2025 · `New Feature`

---

### Email Template Management

Comprehensive email template management system for customizing system-generated emails.

- View and edit email templates
- Template variable documentation
- Preview before saving
- Gathering staff management integration

📅 October 30, 2025 · `New Feature`

---

### Mobile Waiver Upload Wizard

New mobile-friendly wizard for uploading waivers at gatherings with step-by-step guidance.

- Mobile-optimized interface
- Multi-step upload process
- Selection interface for waiver types
- Progress tracking

📅 October 28, 2025 · `New Feature`

---

### Gathering Attendance Management

Full-featured attendance tracking and management for gatherings with modal interfaces.

- Attendance check-in modals
- Member search and selection
- Attendance reports
- Integration with waiver tracking

📅 October 28, 2025 · `New Feature`

---

### Mobile Card Menu System

Enhanced mobile card interface with improved menu navigation and functionality.

- Redesigned menu system
- Better mobile responsiveness
- View mode switching
- Offline state management

📅 October 28, 2025 · `Improvement`

---

### Tab Ordering System

CSS-based tab ordering system allowing flexible arrangement of tabs across the application.

- Configurable tab order
- Plugin tab integration
- Consistent tab behavior
- JavaScript enhancements

📅 October 25, 2025 · `Improvement`

---

### Google Maps Integration

Gathering locations now integrate with Google Maps for easy navigation and location sharing.

- Google Maps embed for locations
- One-click navigation
- Address display improvements
- Copy address to clipboard

📅 October 24, 2025 · `New Feature`

---

### File Size Validation

Upload validation now includes file size limits with clear user feedback.

- Configurable size limits
- Clear error messages
- Client-side validation
- Comprehensive documentation

📅 October 24, 2025 · `Improvement`

---

### Waiver Upload Wizard

Multi-step wizard for uploading and managing waivers with guided process.

- Step-by-step upload process
- Activity and waiver type selection
- Image to PDF conversion
- Change waiver type functionality

📅 October 24, 2025 · `New Feature`

---

### Gathering Waiver Tracking System

Complete system for tracking waivers associated with gatherings and activities.

- Track waiver requirements per gathering
- Monitor waiver completion status
- User flow diagrams
- Waiver aggregation displays

📅 October 19, 2025 · `New Feature`

---

### Gathering System (Renamed from Events)

Events have been renamed to "Gatherings" throughout the application for clarity and consistency with organizational terminology.

- Consistent naming across UI
- Updated documentation
- Improved gathering management
- Better activity integration

📅 October 19, 2025 · `Announcement`

---

### Officer Reporting Structure

Improved officer management with better reporting structure visualization and branch assignment checks.

- Reporting hierarchy views
- Branch compatibility checks
- Improved officer displays
- Structure documentation

📅 October 8, 2025 · `Improvement`

---

### Events Plugin Foundation

New Events plugin providing the foundation for event and gathering management.

- Basic CRUD functionality
- Integration with core system
- Extensible plugin architecture
- Test coverage

📅 October 7, 2025 · `New Feature`

---

### Membership Card Upload

Members can now upload images of their membership cards for verification purposes.

- Image upload interface
- Verification workflow
- Pending verifications queue
- Status display improvements

📅 August 27, 2025 · `New Feature`

---

### Password Security Enhancement

Improved security with automatic reset of failed login attempts when passwords are changed or reset.

- Failed attempt counter reset
- Better security messaging
- Improved login flow
- Account protection

📅 August 14, 2025 · `Security`

---

### Action Items Plugin

New plugin for tracking action items and tasks within the organization.

- Action item creation and tracking
- Navigation integration
- Status management
- Assignment capabilities

📅 July 22, 2025 · `New Feature`

---

### RBAC Security Architecture

Comprehensive Role-Based Access Control documentation and architecture improvements.

- Detailed security documentation
- Architecture diagrams
- Best practices guide
- Implementation patterns

📅 July 17, 2025 · `Security`

---

### Award Recommendations Improvements

Enhanced recommendations system with better filtering and member visibility controls.

- Improved recommendation filtering
- Member-specific views
- Better authorization handling
- Submitted-by tracking

📅 July 14, 2025 · `Improvement`

---

### Email Queue Enhancement

Improved email processing with conditional queue handling and better job management.

- Conditional email processing
- Job creation improvements
- Queue management enhancements
- Better error handling

📅 July 9, 2025 · `Improvement`

---

### Member Officers Display

View officers associated with a member with pagination and proper authorization checks.

- Officers tab on member profile
- Pagination support
- Authorization integration
- Clear officer listings

📅 June 22, 2025 · `New Feature`

---

### Warrant Periods Management

Enhanced warrant period management with improved seeding and initialization.

- Warrant period seeding
- Better initialization logic
- Improved period tracking
- Documentation updates

📅 May 31, 2025 · `Improvement`

---

### GUI Testing Infrastructure

Playwright-based GUI testing setup with Docker support for automated testing.

- Playwright BDD tests
- Docker integration
- User authentication tests
- Test infrastructure

📅 May 30, 2025 · `Improvement`

---

### View Cell Architecture

New ViewCellRegistry system for organizing and managing view components across plugins.

- Centralized cell registration
- Plugin view cell providers
- Better component organization
- Cleaner template code

📅 May 28, 2025 · `Improvement`

---

### Application Security Audit

Security improvements with application-level checking and remediation capabilities.

- Security scanning
- Vulnerability remediation
- Improved access controls
- Security documentation

📅 May 23, 2025 · `Security`

---

### CSV Export Functionality

Export data to CSV format from various screens including Roles and Warrants.

- CSV download buttons
- Configurable exports
- Multiple format support
- Integrated export service

📅 May 2, 2025 · `New Feature`

---

### Recommendations CSV Export

Export award recommendations to CSV for external processing and reporting.

- One-click CSV export
- Filtered exports
- Format options
- Download integration

📅 May 3, 2025 · `New Feature`

---

### Select All for Checkboxes

Bulk selection capability for checkbox lists throughout the application.

- Select all buttons
- Batch operations
- Improved efficiency
- Consistent behavior

📅 April 24, 2025 · `Improvement`

---

### Officers Plugin Refactoring

Major improvements to the Officers plugin with better permission handling and reporting.

- Improved permission checks
- Enhanced reporting structure
- Better display templates
- Batch processing support

📅 April 21, 2025 · `Improvement`

---

### Documentation Portal

Comprehensive documentation system with Jekyll-based site and Mermaid diagram support.

- Documentation website
- Mermaid diagram integration
- Plugin documentation
- Architecture guides

📅 April 11, 2025 · `Announcement`

---

### Permissions Matrix

Visual permissions matrix for managing role permissions with AJAX updates.

- Interactive matrix view
- Batch permission updates
- Real-time UI updates
- Select all functionality

📅 April 10, 2025 · `New Feature`

---

### Policy-Level Permissions

Enhanced permissions system with policy-level tracking and scope management.

- Scoped role assignments
- Policy permission tracking
- UI components for management
- Database support

📅 March 28, 2025 · `New Feature`

---

### Warrant System Foundation

Complete warrant management system for tracking officer warrants, approvals, and expirations.

- Warrant creation and approval workflow
- Roster generation for batch warrants
- Warrant requirement integration with security
- Automatic expiration tracking
- Warrant release notifications

📅 January 1, 2025 · `New Feature`

---

### Office Hierarchy Refactoring

Major refactoring of office structure to support deputies, direct reports, and complex organizational hierarchies.

- Deputies can be direct reports
- Recursive deputy lookup
- Better office creation validation
- Improved hierarchy visualization

📅 January 2, 2025 · `Improvement`

---

### Email Queuing System

Background email processing with queue management for improved reliability and performance.

- Queue-based email sending
- Background cron processing
- Dev container queue support
- Queue debugging tools

📅 January 29, 2025 · `New Feature`

---

### Unwarranted Officers Report

New report for tracking officers who need warrants issued.

- View unwarranted officers
- Filter by branch and department
- Moved to Reports navigation
- Quick warrant action links

📅 January 24, 2025 · `New Feature`

---

### External Authorization API

New API endpoint for external systems (like Gulf Wars) to query member authorizations.

- REST API for authorization lookups
- Status and expiration filters
- Secure access controls
- Integration documentation

📅 February 23, 2025 · `New Feature`

---

### Branch Officers Turbo Frames

Improved branch officer displays using Turbo Frames for faster page loads and better interactivity.

- Paginated officer loading
- Search bar for officers
- Turbo Frame integration
- Better performance

📅 January 21, 2025 · `Improvement`

---

### Service Results Pattern

Refactored service layer to return detailed ServiceResult objects instead of simple true/false values.

- Detailed error information
- Success/failure context
- Better error handling in controllers
- Improved debugging

📅 December 25, 2024 · `Improvement`

---

### Roster Approval Workflow

Complete workflow for approving or declining warrant rosters with batch operations.

- Approve/decline individual warrants
- Batch roster actions
- Non-destructive cancellation
- Approval notifications

📅 December 30, 2024 · `New Feature`

---

### Award Events Filtering

Improved filtering for award events to show only relevant events and hide closed ones.

- Filter out closed events
- Default sort by start date
- Block declined recommendations from Crowns
- Better event selection

📅 January 1, 2025 · `Improvement`

---

### Bulk Recommendation State Transitions

Process multiple award recommendations at once with bulk state and status changes.

- Bulk edit modal interface
- Multi-select recommendations
- Batch status updates
- Streamlined workflow

📅 December 6, 2024 · `New Feature`

---

### Session Extension Alert

Interactive alert warns users before session timeout and allows one-click session extension.

- 5-minute warning before timeout
- AJAX session extension
- Non-intrusive notification
- Stimulus controller implementation

📅 October 16, 2024 · `Improvement`

---

### Special Character Handling

Improved handling of special characters like Þ (Thorn) throughout the application.

- Member search handles Þ
- Authorization queues support special characters
- Autocomplete recognizes Þ as "th"
- Better Unicode support

📅 October 14-16, 2024 · `Improvement`

---

### Duplicate Authorization Prevention

System now prevents members from submitting duplicate authorization requests.

- Check for existing requests
- Clear error messaging
- Prevents accidental duplicates
- Improved request workflow

📅 October 14, 2024 · `Improvement`

---

### Title, Pronouns, and Pronunciation Fields

New member profile fields for personal preferences and proper addressing.

- Title field (Lord, Lady, etc.)
- Pronouns field
- Pronunciation guide
- Displayed on cards and exports

📅 September 21, 2024 · `New Feature`

---

### Award Recommendations Major Rewrite

Complete overhaul of the Awards functionality for better configurability and maintainability.

- More configurable award types
- DRY code principles
- Better specialization handling
- Improved recommendation workflow

📅 October 29, 2024 · `Improvement`

---

### iOS 18 Offline Mode Compatibility

Fixed Progressive Web App compatibility issues with iOS 18's offline mode changes.

- Service worker fixes
- Better offline handling
- iOS Safari compatibility
- PWA manifest updates

📅 September 21, 2024 · `Bug Fix`

---

### Person to Notify Field

Award recommendations can now specify who should be notified when the award is given.

- New field on recommendations
- Included in exports
- Better communication workflow
- Integration with notifications

📅 September 12, 2024 · `New Feature`

---

### Recommendations Permission Separation

Separated viewing recommendations from creating recommendations for better access control.

- View-only permission option
- Create permission separate
- Better role granularity
- Improved security

📅 September 9, 2024 · `Security`

---

### Award Processing Reports

New reports for tracking awards through the processing pipeline.

- "To Be Processed" report
- "To Be Scheduled" report
- "To Be Given" report
- Export capabilities

📅 August 28, 2024 · `New Feature`

---

### Branch Links System

Branches can now have associated links for external resources and websites.

- Add multiple links per branch
- URL validation
- Display on branch pages
- Useful for kingdom websites

📅 August 13, 2024 · `New Feature`

---

### Stimulus.js Migration

Major frontend refactoring from jQuery to Stimulus.js for better maintainability.

- Removed jQuery dependency
- Modern JavaScript patterns
- Better code organization
- Improved testability

📅 July 27 - August 4, 2024 · `Improvement`

---

### Password Security Pattern

Implemented password complexity requirements and security patterns.

- Password strength validation
- Clear security messaging
- Better account protection
- Compliance with best practices

📅 July 23, 2024 · `Security`

---

### Modal Edit for Award Recommendations

Quick edit capability for award recommendations directly from index and board views.

- Modal popup editor
- Quick status changes
- Inline editing
- Improved workflow efficiency

📅 July 20, 2024 · `Improvement`

---

### Kanban Board for Awards

Visual kanban board interface for managing award recommendations through workflow stages.

- Drag-and-drop interface
- Status columns
- Quick actions
- Visual workflow management

📅 July 12, 2024 · `New Feature`

---

### Feature Flags System

Consistent feature flag implementation for enabling/disabling plugins and features.

- Per-plugin feature flags
- Configuration-based toggles
- Clean enable/disable logic
- Better deployment control

📅 July 11, 2024 · `Improvement`

---

### Member-Editable Additional Information

Members can now edit certain additional information fields themselves.

- Configurable editable fields
- Self-service updates
- Admin-controlled permissions
- Better data accuracy

📅 July 9, 2024 · `New Feature`

---

### View Your Submitted Awards

Members can view award recommendations they have submitted.

- Submitted awards tab
- View recommendations about you
- Better transparency
- Improved user experience

📅 July 9, 2024 · `New Feature`

---

### Email Debugging with Mailpit

Local development email testing using Mailpit for debugging email flows.

- Local SMTP capture
- Email preview interface
- Debug email templates
- Improved developer experience

📅 July 7, 2024 · `Improvement`

---

### Back Button Navigation Fix

Fixed browser back button behavior when using subdomains and AJAX navigation.

- Server-side history management
- Better state handling
- Cleaner navigation
- Improved user experience

📅 July 4-7, 2024 · `Bug Fix`

---

### Member Search Enhancement

Added search functionality to the members index for easier member lookup.

- Search input on members list
- Real-time filtering
- Multiple field search
- Improved performance

📅 May 2024 · `Improvement`

---

### Password Reset Flow

Complete password reset functionality with email integration.

- Reset request workflow
- Email notifications
- Secure token handling
- User-friendly process

📅 May 2024 · `New Feature`

---

### Multi-Step Approval Workflow

Support for authorization requests requiring multiple approvals before completion.

- Configurable approval counts
- Approval tracking
- Notification system
- Workflow visualization

📅 May 2024 · `New Feature`

---

### Member Registration Verification

Workflow for verifying newly registered users' memberships.

- Verification queue
- Admin review interface
- Status tracking
- Email notifications

📅 June 2024 · `New Feature`

---

### Mobile Member Cards

Progressive web app support for member cards on mobile devices.

- Mobile card interface
- PWA manifest support
- Offline-capable design
- QR code integration

📅 June 2024 · `New Feature`

---

### Officers Plugin

New plugin for managing organizational officers and their assignments.

- Officer CRUD operations
- Reporting structure
- Deputy management
- Term tracking

📅 June 2024 · `New Feature`

---

### Activities Plugin

Converted activities management into a standalone plugin for better organization.

- Independent plugin structure
- Activity management
- Integration with core
- Improved maintainability

📅 June 2024 · `Improvement`

---

### Reports System

Comprehensive reporting functionality with feature parity to legacy system.

- Multiple report types
- PDF generation
- Warrant reports
- Activity reports

📅 June 2024 · `New Feature`

---

### Asset Compilation Pipeline

Modern JavaScript and CSS build pipeline with webpack integration.

- JS/CSS compilation
- Asset versioning
- Lazy loading
- Build automation

📅 June 2024 · `Improvement`

---

### Tab Memory

Persistent tab selection memory so users return to their last viewed tab.

- Remember selected tabs
- Per-page memory
- Session persistence
- Better navigation

📅 June 2024 · `Improvement`

---

### Active Window Behavior

Database behavior for tracking active windows on records with start and end dates.

- Automatic status tracking
- Date-based activation
- Consistent behavior
- Multiple table support

📅 June 2024 · `New Feature`

---

### Initial Release

First production release of the Kingdom Management Portal with core functionality.

- Member management
- Role-based permissions
- Branch hierarchy
- Authorization workflows
- Award tracking
- Warrant management

📅 May 2024 · `Announcement`

---

*For technical details and complete commit history, see the [GitHub repository](https://github.com/Ansteorra/KMP).*
