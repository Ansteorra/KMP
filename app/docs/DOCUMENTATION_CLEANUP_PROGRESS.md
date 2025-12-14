# Documentation Cleanup Progress

This file tracks progress on trimming inline documentation across the codebase.
Usage examples and patterns are being moved to `/docs` folder.

## Guidelines Applied
- Class docblocks: max 20-30 lines (purpose, dependencies, key behaviors)
- Method docblocks: max 5-10 lines (what it does, parameters, return)
- No `## Usage Examples/Patterns` inline - moved to docs/
- Focus on maintenance: "what does this do" and "how to modify" not "how to use"

---

## Phase 1: Inline Documentation Cleanup

### app/src/Controller/ (24 files)

- [x] AppController.php
- [ ] AppSettingsController.php
- [x] BranchesController.php
- [x] DataverseGridTrait.php
- [x] EmailTemplatesController.php (already clean)
- [ ] ErrorController.php
- [ ] GatheringActivitiesController.php
- [ ] GatheringAttendancesController.php
- [ ] GatheringStaffController.php
- [x] GatheringTypesController.php (already clean)
- [x] GatheringsController.php (already clean)
- [x] GridViewsController.php
- [ ] MemberRolesController.php
- [x] MembersController.php
- [ ] NavBarController.php
- [ ] NotesController.php
- [ ] PagesController.php
- [x] PermissionsController.php
- [ ] ReportsController.php
- [x] RolesController.php
- [ ] SessionsController.php
- [x] WarrantPeriodsController.php
- [x] WarrantRostersController.php
- [x] WarrantsController.php

### app/src/Services/ 

- [x] AuthorizationService.php
- [ ] AuthorizationPolicyService.php
- [x] CsvExportService.php
- [ ] DefaultPermissionsManager.php
- [ ] DocumentService.php
- [x] GridViewService.php
- [ ] ICalendarService.php (already clean)
- [ ] ImageToPdfConversionService.php
- [x] NavigationRegistry.php
- [ ] NavigationProviderInterface.php
- [ ] NavigationService.php (already clean)
- [x] ServiceResult.php
- [ ] ViewCellProviderInterface.php
- [ ] ViewCellRegistry.php
- [ ] WarrantManager.php
- [ ] WarrantManagerInterface.php

### app/src/View/Helper/

- [ ] TimezoneHelper.php
- [ ] Other helpers as needed

### app/src/Model/ (Tables and Entities)

- [ ] Review and clean as needed

### app/src/Policy/

- [ ] Review and clean as needed

### app/src/KMP/

- [ ] Review and clean as needed

---

### app/plugins/Awards/

#### Controllers
- [ ] AppController.php
- [ ] AwardsController.php
- [ ] DomainsController.php
- [ ] LevelsController.php
- [ ] RecommendationsController.php
- [ ] ReportsController.php

#### Policies
- [ ] AwardPolicy.php
- [ ] AwardsTablePolicy.php
- [ ] DomainPolicy.php
- [ ] DomainsTablePolicy.php
- [ ] EventPolicy.php
- [ ] EventsTablePolicy.php
- [ ] LevelPolicy.php
- [ ] LevelsTablePolicy.php
- [ ] RecommendationPolicy.php
- [ ] RecommendationsTablePolicy.php
- [ ] RecommendationsStatesLogPolicy.php
- [ ] RecommendationsStatesLogTablePolicy.php

#### Models
- [ ] RecommendationsTable.php
- [ ] LevelsTable.php
- [ ] Recommendation.php (Entity)
- [ ] Other tables/entities as needed

#### Services
- [ ] AwardsViewCellProvider.php
- [ ] AwardsNavigationProvider.php

---

### app/plugins/Officers/

#### Controllers
- [ ] AppController.php
- [ ] DepartmentsController.php
- [ ] OfficesController.php
- [ ] OfficersController.php
- [ ] ReportsController.php
- [ ] RostersController.php

#### Policies
- [ ] All policy files

#### Services
- [ ] OfficersViewCellProvider.php
- [ ] OfficersNavigationProvider.php
- [ ] DefaultOfficerManager.php
- [ ] OfficerManagerInterface.php

---

### app/plugins/Activities/

- [ ] ActivitiesPlugin.php
- [ ] Controllers (all)
- [ ] Policies (ActivityGroupsTablePolicy, ActivityGroupPolicy, ReportsControllerPolicy, etc.)
- [ ] Services (AuthorizationManagerInterface, etc.)

---

### app/plugins/Waivers/

- [ ] Controllers
- [ ] Policies
- [ ] Services

---

### app/plugins/Queue/

- [ ] Controllers
- [ ] Services

---

### app/plugins/GitHubIssueSubmitter/

- [ ] Controllers
- [ ] Services

---

### app/plugins/Template/ - SKIP (keep verbose for developer reference)

---

### JavaScript Controllers

#### app/assets/js/controllers/
- [ ] dataverse-grid-controller.js
- [ ] delayed-forward-controller.js
- [ ] gathering-location-autocomplete-controller.js
- [ ] gathering-map-controller.js
- [ ] gatherings-calendar-controller.js
- [ ] member-unique-email-controller.js
- [ ] permission-add-role-controller.js
- [ ] revoke-form-controller.js
- [ ] role-add-member-controller.js
- [ ] security-debug-controller.js
- [ ] Other controllers as needed

#### Plugin JavaScript
- [ ] GitHubIssueSubmitter: github-submitter-controller.js
- [ ] Officers plugin controllers
- [ ] Awards plugin controllers
- [ ] Activities plugin controllers
- [ ] Waivers plugin controllers

---

### Config/Migrations (lower priority)

- [ ] 20251119000000_CreateGridViews.php
- [ ] 20251105000000_AddTimezoneToMembers.php
- [ ] 20251103140000_AddPublicIdToMembersAndGatherings.php
- [ ] 20251121090000_CreateGridViewPreferences.php
- [ ] Other migrations as needed

---

## Phase 2: Docs Folder Integration

### New Usage Documentation Files
- [ ] docs/4.8-controller-patterns.md
- [ ] docs/6.4-policy-patterns.md
- [ ] docs/10.4-stimulus-controller-reference.md

### Dataverse Grid Documentation
- [ ] docs/9.1-dataverse-grid-system.md
- [ ] docs/9.2-dataverse-grid-columns.md
- [ ] docs/9.3-dataverse-grid-features.md

### Fact-Check Existing Docs
- [ ] docs/4-core-modules.md and children
- [ ] docs/5-plugins.md and children
- [ ] docs/6-services.md
- [ ] docs/10-javascript-development.md
- [ ] docs/index.md (update navigation)

---

## Notes

- Template plugin is excluded from cleanup (kept verbose for developer reference)
- Migrations may keep more documentation if it helps understand schema changes
- JavaScript Stimulus controllers: move HTML structure examples to docs, keep target/value documentation
