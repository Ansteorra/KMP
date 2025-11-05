# Timezone UI Rollout Checklist

This document tracks the systematic review and update of all templates to implement timezone-aware UI throughout the KMP application.

**Last Updated:** November 5, 2025  
**Status:** In Progress

**Gatherings Module Status:** ✅ **COMPLETE** - All 25 Gatherings-related templates have been reviewed and fully implement timezone support.

---

## Overview

- **Total Main App Templates:** 143
- **Total Plugin Templates:** 116
- **Grand Total:** 259 templates
- **Estimated Datetime Fields:** ~60+ instances

**Completed Modules:**
- ✅ **Gatherings (25 templates)** - 10 controller templates + 15 element templates
  - All date inputs use `Timezone->forInput()` with appropriate timezone context
  - All date displays use `Timezone->format()` with gathering timezone when applicable
  - Calendar views implement full UTC ↔ User Timezone conversion
  - Public pages properly display gathering-local times
  - Schedule management includes timezone notices and proper conversion

---

## Review Process

For each template file:

1. ✅ **Review** - Check if template displays or inputs date/time data
2. ✅ **Update Display** - Replace raw date output with `$this->Timezone->format()`
3. ✅ **Update Input** - Replace datetime inputs with `$this->Timezone->forInput()`
4. ✅ **Add Notice** - Add timezone notice where appropriate with `$this->Timezone->notice()`
5. ✅ **Test** - Verify timezone conversion works correctly

---

## Main Application Templates

### Controllers: AppSettings (1 template)
- [ ] `AppSettings/index.php` - Settings list page

### Controllers: Branches (3 templates)
- [ ] `Branches/add.php` - Create branch form
- [ ] `Branches/index.php` - Branch list
- [ ] `Branches/view.php` - Branch details (⚠️ **HIGH PRIORITY** - shows created/modified dates)

### Controllers: EmailTemplates (6 templates)
- [ ] `EmailTemplates/add.php` - Create template form
- [ ] `EmailTemplates/discover.php` - Discover templates
- [ ] `EmailTemplates/edit.php` - Edit template form
- [ ] `EmailTemplates/form.php` - Template form partial
- [ ] `EmailTemplates/index.php` - Template list (⚠️ shows modified dates)
- [ ] `EmailTemplates/view.php` - Template details (⚠️ shows created/modified dates)

### Controllers: GatheringActivities (4 templates)
- [ ] `GatheringActivities/add.php` - Create activity form
- [ ] `GatheringActivities/edit.php` - Edit activity form
- [ ] `GatheringActivities/index.php` - Activity list
- [ ] `GatheringActivities/view.php` - Activity details

### Controllers: Gatherings (10 templates)
- [x] `Gatherings/add.php` - Create gathering form (⚠️ **CRITICAL** - start_date, end_date inputs) ✅ Uses timezone conversion in controller
- [x] `Gatherings/all_gatherings.php` - All gatherings list (⚠️ **CRITICAL** - displays dates) ✅ Uses Timezone->format() for dates
- [x] `Gatherings/attendance_modal.php` - Attendance modal ✅ Shows dates without time (no timezone needed)
- [x] `Gatherings/calendar.php` - Calendar view (⚠️ **CRITICAL** - displays event dates/times) ✅ Full timezone conversion in controller & view
- [x] `Gatherings/edit.php` - Edit gathering form (⚠️ **CRITICAL** - start_date, end_date inputs) ✅ Uses Timezone->forInput() & notices
- [x] `Gatherings/index.php` - Gatherings list (⚠️ **CRITICAL** - displays dates) ✅ Uses turbo tabs (no direct dates)
- [x] `Gatherings/public_landing.php` - Public landing page (⚠️ **CRITICAL** - displays dates) ✅ Uses Timezone->format() with gathering timezone
- [x] `Gatherings/quick_view.php` - Quick view modal (⚠️ **CRITICAL** - displays dates) ✅ Uses Timezone->format() & getAbbreviation()
- [x] `Gatherings/view.php` - Gathering details (⚠️ **CRITICAL** - displays all date/time fields) ✅ Complete timezone implementation
- [x] `Gatherings/view_public.php` - Public gathering view (⚠️ **CRITICAL** - displays dates) ✅ Uses public_content element with timezone

### Controllers: GatheringStaff (2 templates)
- [ ] `GatheringStaff/add.php` - Add staff form
- [ ] `GatheringStaff/edit.php` - Edit staff form

### Controllers: GatheringTypes (4 templates)
- [ ] `GatheringTypes/add.php` - Create type form
- [ ] `GatheringTypes/edit.php` - Edit type form
- [ ] `GatheringTypes/index.php` - Type list
- [ ] `GatheringTypes/view.php` - Type details

### Controllers: Members (14 templates)
- [ ] `Members/add.php` - Create member form (add timezone selector)
- [ ] `Members/auto_complete.php` - Autocomplete results
- [ ] `Members/forgot_password.php` - Password reset request
- [ ] `Members/import_expiration_dates.php` - Import dates (⚠️ **HIGH PRIORITY** - date handling)
- [ ] `Members/index.php` - Member list (shows created/modified dates)
- [ ] `Members/login.php` - Login page
- [ ] `Members/mobile_auth_card.php` - Mobile auth card
- [ ] `Members/register.php` - Registration form (add timezone detection)
- [ ] `Members/reset_password.php` - Password reset form
- [ ] `Members/verify_queue.php` - Verify queue
- [ ] `Members/view_card_json.php` - Member card JSON
- [ ] `Members/view_card.php` - Member card view
- [ ] `Members/view_mobile_card_new.php` - Mobile card new
- [ ] `Members/view_mobile_card.php` - Mobile card
- [ ] `Members/view.php` - Member details (⚠️ **HIGH PRIORITY** - shows all dates, add timezone edit)

### Controllers: MemberRoles (1 template)
- [ ] `MemberRoles/role_member_roles.php` - Role member list

### Controllers: Pages (3 templates)
- [ ] `Pages/notfound.php` - 404 page
- [ ] `Pages/unauthorized.php` - 401 page
- [ ] `Pages/webmanifest.php` - Web manifest

### Controllers: Permissions (4 templates)
- [ ] `Permissions/add.php` - Create permission form
- [ ] `Permissions/index.php` - Permission list
- [ ] `Permissions/matrix.php` - Permission matrix
- [ ] `Permissions/view.php` - Permission details

### Controllers: Reports (2 templates)
- [ ] `Reports/permissions_warrants_roster.php` - Permissions/warrants report (⚠️ shows dates)
- [ ] `Reports/roles_list.php` - Roles list report

### Controllers: Roles (4 templates)
- [ ] `Roles/add.php` - Create role form
- [ ] `Roles/ajax/ajax.php` - AJAX handler
- [ ] `Roles/index.php` - Role list
- [ ] `Roles/view.php` - Role details (shows created/modified dates)

### Controllers: WarrantPeriods (2 templates)
- [ ] `WarrantPeriods/add.php` - Create period form (⚠️ **HIGH PRIORITY** - start/end dates)
- [ ] `WarrantPeriods/index.php` - Period list (⚠️ **HIGH PRIORITY** - displays dates)

### Controllers: WarrantRosters (4 templates)
- [ ] `WarrantRosters/all_rosters.php` - All rosters list (shows dates)
- [ ] `WarrantRosters/edit.php` - Edit roster form (⚠️ date inputs)
- [ ] `WarrantRosters/index.php` - Roster list (shows dates)
- [ ] `WarrantRosters/view.php` - Roster details (shows dates)

### Controllers: Warrants (4 templates)
- [ ] `Warrants/add.php` - Create warrant form (⚠️ **HIGH PRIORITY** - start/end/expires dates)
- [ ] `Warrants/all_warrants.php` - All warrants list (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Warrants/index.php` - Warrant list (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Warrants/view.php` - Warrant details (⚠️ **HIGH PRIORITY** - displays all date fields)

### Cells (3 templates)
- [ ] `cell/AppNav/display.php` - App navigation
- [ ] `cell/Navigation/display.php` - Navigation cell
- [ ] `cell/Notes/display.php` - Notes cell (may show created/modified dates)

### Elements: Core (9 templates)
- [ ] `element/activeWindowTabs.php` - Tab system
- [ ] `element/autoCompleteControl.php` - Autocomplete control
- [ ] `element/backButton.php` - Back button
- [ ] `element/comboBoxControl.php` - Combobox control
- [ ] `element/copyrightFooter.php` - Footer
- [ ] `element/pluginDetailBodies.php` - Plugin detail bodies
- [ ] `element/pluginTabBodies.php` - Plugin tab bodies
- [ ] `element/pluginTabButtons.php` - Plugin tab buttons
- [ ] `element/timezone_examples.php` - Timezone examples (✅ **REFERENCE**)
- [ ] `element/turboActiveTabs.php` - Turbo tabs
- [ ] `element/turboSubTable.php` - Turbo sub-table

### Elements: Branches (1 template)
- [ ] `element/branches/editModal.php` - Edit branch modal

### Elements: Gatherings (15 templates)
- [x] `element/gatherings/addActivityModal.php` - Add activity modal ✅ No dates
- [x] `element/gatherings/addScheduleModal.php` - Add schedule modal (⚠️ **CRITICAL** - datetime inputs) ✅ Shows timezone notice, uses forInput
- [x] `element/gatherings/attendanceTab.php` - Attendance tab ✅ No dates displayed
- [x] `element/gatherings/attendGatheringModal.php` - Attend modal ✅ No dates displayed
- [x] `element/gatherings/calendar_list.php` - Calendar list view (⚠️ **CRITICAL** - displays dates) ✅ Uses timezone conversion
- [x] `element/gatherings/calendar_month.php` - Calendar month view (⚠️ **CRITICAL** - displays dates) ✅ Full timezone conversion for day assignment
- [x] `element/gatherings/calendar_week.php` - Calendar week view (⚠️ **CRITICAL** - displays dates) ✅ Uses timezone conversion
- [x] `element/gatherings/cloneModal.php` - Clone gathering modal (⚠️ date inputs) ✅ Uses Timezone->forInput() with notices
- [x] `element/gatherings/editActivityDescriptionModal.php` - Edit activity description ✅ No dates
- [x] `element/gatherings/editScheduleModal.php` - Edit schedule modal (⚠️ **CRITICAL** - datetime inputs) ✅ Shows timezone notice, uses forInput
- [x] `element/gatherings/mapTab.php` - Map tab ✅ No dates
- [x] `element/gatherings/public_content.php` - Public content (displays dates) ✅ Uses Timezone->format() with gathering timezone
- [x] `element/gatherings/scheduleTab.php` - Schedule tab (⚠️ **CRITICAL** - displays schedule times) ✅ Complete timezone implementation
- [x] `element/gatherings/staffTab.php` - Staff tab ✅ No dates
- [x] `element/gatherings/waivers.php` - Waivers display ✅ No dates

### Elements: Members (6 templates)
- [ ] `element/members/changePasswordModal.php` - Change password modal
- [ ] `element/members/editModal.php` - Edit member modal (add timezone selector)
- [ ] `element/members/gatheringAttendanceModals.php` - Attendance modals
- [ ] `element/members/gatheringAttendances.php` - Attendance list (shows dates)
- [ ] `element/members/memberDetails.php` - Member details (shows dates, add timezone display)
- [ ] `element/members/submitMemberCard.php` - Submit member card
- [ ] `element/members/verifyMembershipModal.php` - Verify membership modal

### Elements: Nav (4 templates)
- [ ] `element/nav/badge_value.php` - Badge value
- [ ] `element/nav/nav_child.php` - Nav child
- [ ] `element/nav/nav_grandchild.php` - Nav grandchild
- [ ] `element/nav/nav_parent.php` - Nav parent

### Elements: Roles (3 templates)
- [ ] `element/roles/addMemberModal.php` - Add member modal
- [ ] `element/roles/addPermissionModal.php` - Add permission modal
- [ ] `element/roles/editModal.php` - Edit role modal

### Email Templates (13 templates)
- [ ] `email/html/default.php` - HTML email layout
- [ ] `email/text/default.php` - Text email layout
- [ ] `email/text/mobile_card.php` - Mobile card email
- [ ] `email/text/new_registration.php` - New registration email
- [ ] `email/text/notify_approver.php` - Notify approver email
- [ ] `email/text/notify_of_hire.php` - Notify of hire email
- [ ] `email/text/notify_of_release.php` - Notify of release email
- [ ] `email/text/notify_of_warrant.php` - Notify of warrant email (⚠️ may show dates)
- [ ] `email/text/notify_requester.php` - Notify requester email
- [ ] `email/text/notify_secretary_of_new_member.php` - Notify secretary email
- [ ] `email/text/notify_secretary_of_new_minor_member.php` - Notify secretary minor email
- [ ] `email/text/reset_password.php` - Reset password email

### Error Pages (4 templates)
- [ ] `Error/error400_default.php` - Default 400 error
- [ ] `Error/error400.php` - 400 error page
- [ ] `Error/error500_default.php` - Default 500 error
- [ ] `Error/error500.php` - 500 error page

### Layouts (12 templates)
- [ ] `layout/ajax.php` - AJAX layout
- [ ] `layout/default.php` - Default layout
- [ ] `layout/email/html/default.php` - HTML email layout
- [ ] `layout/email/text/default.php` - Text email layout
- [ ] `layout/error.php` - Error layout
- [ ] `layout/mobile_app.php` - Mobile app layout
- [ ] `layout/public_event.php` - Public event layout
- [ ] `layout/turbo_frame.php` - Turbo frame layout
- [ ] `layout/TwitterBootstrap/cover.php` - Cover layout
- [ ] `layout/TwitterBootstrap/dashboard.php` - Dashboard layout
- [ ] `layout/TwitterBootstrap/register.php` - Register layout
- [ ] `layout/TwitterBootstrap/signin.php` - Sign-in layout
- [ ] `layout/TwitterBootstrap/view_record.php` - View record layout

---

## Plugin Templates

### Plugin: Activities (23 templates)

#### Controllers
- [ ] `Activities/Activities/add.php` - Create activity form
- [ ] `Activities/Activities/index.php` - Activity list
- [ ] `Activities/Activities/view.php` - Activity details

#### Activity Groups
- [ ] `Activities/ActivityGroups/add.php` - Create group form
- [ ] `Activities/ActivityGroups/index.php` - Group list
- [ ] `Activities/ActivityGroups/view.php` - Group details

#### Authorization Approvals
- [ ] `Activities/AuthorizationApprovals/index.php` - Approval list (⚠️ shows dates)
- [ ] `Activities/AuthorizationApprovals/mobile_approve_authorizations.php` - Mobile approve
- [ ] `Activities/AuthorizationApprovals/mobile_approve.php` - Mobile approve single
- [ ] `Activities/AuthorizationApprovals/mobile_deny.php` - Mobile deny
- [ ] `Activities/AuthorizationApprovals/view.php` - Approval details (⚠️ shows dates)

#### Authorizations
- [ ] `Activities/Authorizations/activity_authorizations.php` - Activity auth list (⚠️ **HIGH PRIORITY** - shows expiry dates)
- [ ] `Activities/Authorizations/member_authorizations.php` - Member auth list (⚠️ **HIGH PRIORITY** - shows expiry dates)
- [ ] `Activities/Authorizations/mobile_request_authorization.php` - Mobile request

#### Cells
- [ ] `Activities/cell/MemberAuthorizationDetailsJSON/display.php` - Auth details JSON
- [ ] `Activities/cell/MemberAuthorizations/display.php` - Member auth cell (⚠️ shows dates)
- [ ] `Activities/cell/PermissionActivities/display.php` - Permission activities

#### Elements
- [ ] `Activities/element/renewAuthorizationModal.php` - Renew modal (⚠️ date input)
- [ ] `Activities/element/requestAuthorizationModal.php` - Request modal
- [ ] `Activities/element/revokeAuthorizationModal.php` - Revoke modal

#### Email
- [ ] `Activities/email/html/default.php` - HTML email
- [ ] `Activities/email/text/default.php` - Text email
- [ ] `Activities/email/text/notify_approver.php` - Notify approver
- [ ] `Activities/email/text/notify_requester.php` - Notify requester

#### Reports
- [ ] `Activities/Reports/authorizations.php` - Authorizations report (⚠️ **HIGH PRIORITY** - shows dates)

### Plugin: Awards (26 templates)

#### Controllers
- [ ] `Awards/Awards/add.php` - Create award form
- [ ] `Awards/Awards/index.php` - Award list
- [ ] `Awards/Awards/turbo_frame_cell.php` - Turbo frame cell
- [ ] `Awards/Awards/view.php` - Award details

#### Cells
- [ ] `Awards/cell/ActivityAwards/display.php` - Activity awards cell
- [ ] `Awards/cell/MemberSubmittedRecs/display.php` - Submitted recs cell (shows dates)
- [ ] `Awards/cell/RecsForMember/display.php` - Recs for member cell (shows dates)

#### Domains
- [ ] `Awards/Domains/add.php` - Create domain form
- [ ] `Awards/Domains/index.php` - Domain list
- [ ] `Awards/Domains/view.php` - Domain details

#### Elements
- [ ] `Awards/element/recommendationEditModal.php` - Edit rec modal (⚠️ date inputs)
- [ ] `Awards/element/recommendationQuickEditModal.php` - Quick edit modal
- [ ] `Awards/element/recommendationsBulkEditModal.php` - Bulk edit modal

#### Email
- [ ] `Awards/email/html/default.php` - HTML email
- [ ] `Awards/email/text/default.php` - Text email

#### Levels
- [ ] `Awards/Levels/add.php` - Create level form
- [ ] `Awards/Levels/index.php` - Level list
- [ ] `Awards/Levels/view.php` - Level details

#### Recommendations
- [ ] `Awards/Recommendations/add.php` - Create rec form (⚠️ **HIGH PRIORITY** - date inputs)
- [ ] `Awards/Recommendations/board.php` - Rec board (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Awards/Recommendations/index.php` - Rec list (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Awards/Recommendations/submit_recommendation.php` - Submit rec (⚠️ date inputs)
- [ ] `Awards/Recommendations/table.php` - Rec table (displays dates)
- [ ] `Awards/Recommendations/turbo_bulk_edit_form.php` - Bulk edit form
- [ ] `Awards/Recommendations/turbo_edit_form.php` - Edit form (⚠️ date inputs)
- [ ] `Awards/Recommendations/turbo_quick_edit_form.php` - Quick edit form
- [ ] `Awards/Recommendations/view.php` - Rec details (⚠️ **HIGH PRIORITY** - displays dates)

### Plugin: GitHubIssueSubmitter (2 templates)
- [ ] `GitHubIssueSubmitter/cell/IssueSubmitter/display.php` - Issue submitter cell
- [ ] `GitHubIssueSubmitter/Issues/submit.php` - Submit issue form

### Plugin: Officers (23 templates)

#### Cells
- [ ] `Officers/cell/BranchOfficers/display.php` - Branch officers cell (shows dates)
- [ ] `Officers/cell/BranchRequiredOfficers/display.php` - Required officers cell
- [ ] `Officers/cell/MemberOfficers/display.php` - Member officers cell (shows dates)

#### Departments
- [ ] `Officers/Departments/add.php` - Create department form
- [ ] `Officers/Departments/index.php` - Department list
- [ ] `Officers/Departments/view.php` - Department details

#### Elements
- [ ] `Officers/element/assignModal.php` - Assign modal (⚠️ **HIGH PRIORITY** - start/end date inputs)
- [ ] `Officers/element/editModal.php` - Edit modal (⚠️ **HIGH PRIORITY** - start/end date inputs)
- [ ] `Officers/element/releaseModal.php` - Release modal (⚠️ date input)

#### Email
- [ ] `Officers/email/html/default.php` - HTML email
- [ ] `Officers/email/text/default.php` - Text email
- [ ] `Officers/email/text/notify_of_hire.php` - Notify of hire (shows dates)
- [ ] `Officers/email/text/notify_of_release.php` - Notify of release (shows dates)

#### Officers
- [ ] `Officers/Officers/auto_complete.php` - Autocomplete
- [ ] `Officers/Officers/branch_officers.php` - Branch officers list (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Officers/Officers/index.php` - Officer list (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Officers/Officers/member_officers.php` - Member officers list (⚠️ **HIGH PRIORITY** - displays dates)
- [ ] `Officers/Officers/officers_by_warrant_status.php` - Officers by warrant status (shows dates)

#### Offices
- [ ] `Officers/Offices/add.php` - Create office form
- [ ] `Officers/Offices/index.php` - Office list
- [ ] `Officers/Offices/view.php` - Office details

#### Reports
- [ ] `Officers/Reports/department_officers_roster.php` - Department roster (⚠️ shows dates)

#### Rosters
- [ ] `Officers/Rosters/add.php` - Add roster form

### Plugin: Queue (14 templates)

#### Elements
- [ ] `Queue/element/ok.php` - OK element
- [ ] `Queue/element/search.php` - Search element
- [ ] `Queue/element/yes_no.php` - Yes/no element

#### Queue
- [ ] `Queue/Queue/index.php` - Queue index
- [ ] `Queue/Queue/processes.php` - Queue processes

#### Queued Jobs
- [ ] `Queue/QueuedJobs/data.php` - Job data (⚠️ shows timestamps)
- [ ] `Queue/QueuedJobs/edit.php` - Edit job
- [ ] `Queue/QueuedJobs/execute.php` - Execute job
- [ ] `Queue/QueuedJobs/import.php` - Import jobs
- [ ] `Queue/QueuedJobs/index.php` - Job list (⚠️ **HIGH PRIORITY** - displays created/notbefore/fetched times)
- [ ] `Queue/QueuedJobs/migrate.php` - Migrate jobs
- [ ] `Queue/QueuedJobs/stats.php` - Job stats (⚠️ shows timestamps)
- [ ] `Queue/QueuedJobs/test.php` - Test jobs
- [ ] `Queue/QueuedJobs/view.php` - Job details (⚠️ **HIGH PRIORITY** - displays all timestamps)

#### Queue Processes
- [ ] `Queue/QueueProcesses/edit.php` - Edit process
- [ ] `Queue/QueueProcesses/index.php` - Process list (⚠️ shows timestamps)
- [ ] `Queue/QueueProcesses/view.php` - Process details (⚠️ shows timestamps)

### Plugin: Template (4 templates)
- [ ] `Template/HelloWorld/add.php` - Example add form
- [ ] `Template/HelloWorld/edit.php` - Example edit form
- [ ] `Template/HelloWorld/index.php` - Example index
- [ ] `Template/HelloWorld/view.php` - Example view

### Plugin: Waivers (14 templates)

#### Cells
- [ ] `Waivers/cell/GatheringActivityWaivers/display.php` - Activity waivers cell
- [ ] `Waivers/cell/GatheringWaivers/display.php` - Gathering waivers cell

#### Elements
- [ ] `Waivers/element/addWaiverRequirementModal.php` - Add requirement modal
- [ ] `Waivers/element/GatheringWaivers/changeTypeActivitiesModal.php` - Change type modal
- [ ] `Waivers/element/GatheringWaivers/mobile_wizard_steps.php` - Mobile wizard steps
- [ ] `Waivers/element/GatheringWaivers/upload_wizard_steps.php` - Upload wizard steps

#### Gathering Waivers
- [ ] `Waivers/GatheringWaivers/dashboard.php` - Waiver dashboard
- [ ] `Waivers/GatheringWaivers/index.php` - Waiver list
- [ ] `Waivers/GatheringWaivers/mobile_select_gathering.php` - Mobile select gathering
- [ ] `Waivers/GatheringWaivers/mobile_upload.php` - Mobile upload
- [ ] `Waivers/GatheringWaivers/needing_waivers.php` - Needing waivers
- [ ] `Waivers/GatheringWaivers/upload.php` - Upload waiver
- [ ] `Waivers/GatheringWaivers/view.php` - Waiver details

#### Waiver Types
- [ ] `Waivers/WaiverTypes/add.php` - Create type form
- [ ] `Waivers/WaiverTypes/edit.php` - Edit type form
- [ ] `Waivers/WaiverTypes/index.php` - Type list
- [ ] `Waivers/WaiverTypes/view.php` - Type details

---

## Priority Groups

### 🔴 CRITICAL - Immediate Priority (User-Facing Date/Time Heavy)
These templates display or input event dates/times and should be updated first:

1. **Gatherings Module** (10 templates)
   - `Gatherings/add.php` - Gathering creation
   - `Gatherings/edit.php` - Gathering editing
   - `Gatherings/view.php` - Gathering details
   - `Gatherings/view_public.php` - Public view
   - `Gatherings/calendar.php` - Calendar display
   - `Gatherings/index.php` - Gathering list
   - `Gatherings/all_gatherings.php` - All gatherings
   - `Gatherings/quick_view.php` - Quick view
   - `Gatherings/public_landing.php` - Public landing
   - `element/gatherings/scheduleTab.php` - Schedule tab

2. **Gathering Scheduling** (4 templates)
   - `element/gatherings/addScheduleModal.php` - Add schedule
   - `element/gatherings/editScheduleModal.php` - Edit schedule
   - `element/gatherings/calendar_list.php` - Calendar list
   - `element/gatherings/calendar_month.php` - Calendar month
   - `element/gatherings/calendar_week.php` - Calendar week

3. **Warrants** (4 templates)
   - `Warrants/add.php` - Create warrant
   - `Warrants/view.php` - Warrant details
   - `Warrants/index.php` - Warrant list
   - `Warrants/all_warrants.php` - All warrants

### 🟠 HIGH PRIORITY - Secondary Priority
Templates with important date/time fields:

1. **Member Management** (3 templates)
   - `Members/view.php` - Member details (add timezone selector)
   - `Members/add.php` - Member creation (add timezone selector)
   - `element/members/editModal.php` - Member edit modal (add timezone selector)

2. **Officers Plugin** (6 templates)
   - `Officers/Officers/index.php` - Officer list
   - `Officers/Officers/branch_officers.php` - Branch officers
   - `Officers/Officers/member_officers.php` - Member officers
   - `Officers/element/assignModal.php` - Assign officer
   - `Officers/element/editModal.php` - Edit officer
   - `Officers/element/releaseModal.php` - Release officer

3. **Activities Plugin** (5 templates)
   - `Activities/Authorizations/activity_authorizations.php` - Activity authorizations
   - `Activities/Authorizations/member_authorizations.php` - Member authorizations
   - `Activities/Reports/authorizations.php` - Authorization report
   - `Activities/AuthorizationApprovals/index.php` - Approval list
   - `Activities/element/renewAuthorizationModal.php` - Renew authorization

4. **Awards Plugin** (5 templates)
   - `Awards/Recommendations/add.php` - Create recommendation
   - `Awards/Recommendations/index.php` - Recommendation list
   - `Awards/Recommendations/board.php` - Recommendation board
   - `Awards/Recommendations/view.php` - Recommendation details
   - `Awards/element/recommendationEditModal.php` - Edit recommendation

5. **Queue Plugin** (3 templates)
   - `Queue/QueuedJobs/index.php` - Job list
   - `Queue/QueuedJobs/view.php` - Job details
   - `Queue/QueuedJobs/stats.php` - Job stats

6. **Other Core** (3 templates)
   - `WarrantPeriods/add.php` - Create period
   - `WarrantPeriods/index.php` - Period list
   - `Members/import_expiration_dates.php` - Import dates

### 🟡 MEDIUM PRIORITY - Review & Update
Templates with created/modified timestamps:

1. **Standard Views** (~20 templates)
   - All `view.php` files showing created/modified
   - All `index.php` files showing timestamps
   - Email templates with date references

### 🟢 LOW PRIORITY - Review Only
Templates unlikely to have date/time fields:

1. **Forms without dates** (~50 templates)
2. **Navigation/Layout** (~20 templates)
3. **Error pages** (4 templates)
4. **Template plugin examples** (4 templates)

---

## Testing Strategy

### Phase 1: Critical Templates (Gatherings)
1. Update all Gathering-related templates
2. Test gathering creation with timezone
3. Test gathering calendar display
4. Test gathering public view
5. Verify schedule times display correctly

### Phase 2: High Priority (Warrants, Officers, Activities)
1. Update warrant templates
2. Update officer assignment templates
3. Update authorization templates
4. Test each module's date/time handling

### Phase 3: Medium Priority (All Views)
1. Systematically update all view/index pages
2. Test timestamp displays
3. Verify timezone notice appears appropriately

### Phase 4: Low Priority (Cleanup)
1. Review remaining templates
2. Update any missed date/time fields
3. Final comprehensive testing

---

## Notes

- **Timezone Notice**: Add `<?= $this->Timezone->notice() ?>` to pages with significant date/time displays
- **Form Inputs**: Always use `$this->Timezone->forInput()` for datetime-local inputs
- **Display**: Always use `$this->Timezone->format()` for displaying dates/times
- **Testing**: Test with different user timezones (Chicago, New York, Los Angeles, UTC)
- **Documentation**: Update inline comments when making changes

---

## Completion Tracking

- **Main App:** 0/143 (0%)
- **Plugins:** 0/116 (0%)
- **Overall:** 0/259 (0%)

**Critical Path:** 0/28 (0%)  
**High Priority:** 0/25 (0%)  
**Medium Priority:** 0/~40 (0%)  
**Low Priority:** 0/~166 (0%)
