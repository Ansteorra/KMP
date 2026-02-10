---
layout: default
---

# KMP (Kingdom Management Portal) Developer Documentation

## Table of Contents

| Section | Description |
|---------|-------------|
| **1. [Introduction](1-introduction.md)** | |
| 1.1 About KMP | Overview of the Kingdom Management Portal project |
| 1.2 Project Purpose | Membership management system for SCA Kingdoms |
| 1.3 System Requirements | PHP 8.3+, MySQL/MariaDB, etc. |
| **2. [Getting Started](2-getting-started.md)** | |
| 2.1 Installation | Setting up development environment |
| **2.2 [Configuration](2-configuration.md)** | **NEW** Application configuration overview |
| 2.3 CakePHP Basics | Brief overview of CakePHP framework |
| **3. [Architecture](3-architecture.md)** | |
| **3.1 [Core Foundation Architecture](3.1-core-foundation-architecture.md)** | Application bootstrap, middleware stack, security architecture |
| **3.2 [Model Behaviors](3.2-model-behaviors.md)** | ActiveWindow, JsonField, and Sortable behaviors |
| **3.3 [Database Schema](3.3-database-schema.md)** | Complete database schema documentation |
| **3.4 [Migration Documentation](3.4-migration-documentation.md)** | Database migration history and patterns |
| **3.5 [ER Diagrams](3.5-er-diagrams.md)** | Entity relationship diagrams |
| **3.6 [Seed Documentation](3.6-seed-documentation.md)** | Data seeding framework and patterns |
| **3.7 [Active Window Sync](3.7-active-window-sync.md)** | Scheduled status maintenance for ActiveWindow entities |
| **3.8 [Youth Age-Up](3.8-youth-age-up.md)** | Automated transition of minor accounts to adult statuses |
| **4. [Core Modules](4-core-modules.md)** | |
| **4.1 [Member Lifecycle](4.1-member-lifecycle.md)** | Complete member lifecycle and data flow documentation |
| **4.1.1 [MembersTable API Reference](4.1.1-members-table-reference.md)** | MembersTable model API and query methods |
| **4.2 [Branch Hierarchy](4.2-branch-hierarchy.md)** | Complete organizational structure and tree management documentation |
| **4.3 [Warrant Lifecycle](4.3-warrant-lifecycle.md)** | Complete warrant state machine and approval process documentation |
| **4.4 [RBAC Security Architecture](4.4-rbac-security-architecture.md)** | Complete RBAC system with warrant temporal validation layer |
| **4.5 [View Patterns](4.5-view-patterns.md)** | Template system, helpers, and UI components |
| **4.6 [Gatherings System](4.6-gatherings-system.md)** | Event management, calendar views, and attendance tracking |
| **4.6.1 [Calendar Download Feature](4.6.1-calendar-download-feature.md)** | iCalendar (.ics) file generation for importing events |
| **4.6.2 [Gathering Staff Management](4.6.2-gathering-staff-management.md)** | Staff and steward management system |
| **4.6.3 [Gathering Schedule System](4.6.3-gathering-schedule-system.md)** | Timetables and scheduled activities for events |
| **4.6.4 [Waiver Exemption System](4.6.4-waiver-exemption-system.md)** | Attestation system for waiver exemptions |
| **4.7 [Document Management & Retention System](4.7-document-management-system.md)** | File uploads, storage, and retention policies |
| **4.9 [Impersonation Mode](4.9-impersonation-mode.md)** | Super user impersonation for troubleshooting with audit trail |
| **5. [Plugins](5-plugins.md)** | |
| **5.1 [Officers Plugin](5.1-officers-plugin.md)** | Officers management and roster system |
| **5.1.1 [Officers Services](5.1.1-officers-services.md)** | Officers plugin service layer reference |
| **5.2 [Awards Plugin](5.2-awards-plugin.md)** | Award recommendations and management system |
| **5.2.1 [EventsTable API](5.2.1-awards-events-table.md)** | Awards EventsTable model reference |
| **5.2.2 [LevelsTable API](5.2.2-awards-levels-table.md)** | Awards LevelsTable model reference |
| **5.2.3 [RecommendationsStatesLogsTable API](5.2.3-awards-recommendations-states-logs-table.md)** | Awards recommendation state logs model reference |
| **5.2.4 [RecommendationsTable API](5.2.4-awards-recommendations-table.md)** | Awards RecommendationsTable model reference |
| **5.2.5 [Award Policy](5.2.5-awards-award-policy.md)** | Award entity authorization policy |
| **5.2.6 [AwardsTable Policy](5.2.6-awards-table-policy.md)** | Awards table-level authorization policy |
| **5.2.7 [Domain Policy](5.2.7-awards-domain-policy.md)** | Awards domain entity authorization policy |
| **5.2.8 [DomainsTable Policy](5.2.8-awards-domains-table-policy.md)** | Awards domains table authorization policy |
| **5.2.9 [Event Policy](5.2.9-awards-event-policy.md)** | Awards event entity authorization policy |
| **5.2.10 [EventsTable Policy](5.2.10-awards-events-table-policy.md)** | Awards events table authorization policy |
| **5.2.11 [Level Policy](5.2.11-awards-level-policy.md)** | Awards level entity authorization policy |
| **5.2.12 [LevelsTable Policy](5.2.12-awards-levels-table-policy.md)** | Awards levels table authorization policy |
| **5.2.13 [Recommendation Policy](5.2.13-awards-recommendation-policy.md)** | Awards recommendation entity authorization policy |
| **5.2.14 [RecommendationsStatesLog Policy](5.2.14-awards-recommendations-states-log-policy.md)** | Recommendation state log authorization policy |
| **5.2.15 [RecommendationsStatesLogTable Policy](5.2.15-awards-recommendations-states-log-table-policy.md)** | Recommendation state log table authorization policy |
| **5.2.16 [RecommendationsTable Policy](5.2.16-awards-recommendations-table-policy.md)** | Recommendations table authorization policy |
| **5.2.17 [Awards Services](5.2.17-awards-services.md)** | Awards plugin service layer reference |
| **5.3 [Queue Plugin](5.3-queue-plugin.md)** | Background job processing system |
| **5.4 [GitHubIssueSubmitter Plugin](5.4-github-issue-submitter-plugin.md)** | User feedback submission to GitHub |
| **5.5 [Bootstrap Plugin](5.5-bootstrap-plugin.md)** | UI framework integration |
| **5.6 [Activities Plugin](5.6-activities-plugin.md)** | Comprehensive authorization management system |
| [Activities Quick Reference](5.6-activities-plugin-quick-reference.md) | Activities plugin quick reference card |
| [Activities Documentation Refactoring](5.6-activities-plugin-documentation-refactoring.md) | Documentation refactoring summary for Activities plugin |
| **5.6.1 [Plugin Architecture](5.6.1-activities-plugin-architecture.md)** | Activities plugin architecture and configuration |
| **5.6.2 [Activities Controller](5.6.2-activities-controller-reference.md)** | Activities controller reference |
| **5.6.3 [Activity Groups Controller](5.6.3-activity-groups-controller-reference.md)** | Activity groups controller reference |
| **5.6.4 [Activity Entity](5.6.4-activity-entity-reference.md)** | Activity entity reference |
| **5.6.5 [Security Patterns](5.6.5-activity-security-patterns.md)** | Activity authorization and security patterns |
| **5.6.6 [Activity Groups Entity](5.6.6-activity-groups-entity-reference.md)** | Activity groups entity reference |
| **5.6.7 [Authorization Entity](5.6.7-authorization-entity-reference.md)** | Authorization entity reference |
| **5.6.8 [AuthorizationApproval Entity](5.6.8-authorization-approval-entity-reference.md)** | AuthorizationApproval entity reference |
| **5.7 [Waivers Plugin](5.7-waivers-plugin.md)** | Waiver upload, tracking, and compliance management |
| **6. [Services](6-services.md)** | Service layer architecture — WarrantManager, ActiveWindowManager, Email, Navigation, CSV export, authorization, calendar, retention, and more |
| **6.2 [Authorization Helpers](6.2-authorization-helpers.md)** | getBranchIdsForAction() and permission helper methods |
| **6.3 [Email Template Management](6.3-email-template-management.md)** | Database-driven email template system with WYSIWYG editor |
| **6.4 [Caching Strategy](6.4-caching-strategy.md)** | **NEW** Multi-tier caching architecture and performance tuning |
| **7. [Development Workflow](7-development-workflow.md)** | |
| **7.1 [Security Best Practices](7.1-security-best-practices.md)** | Security configuration, testing, and audit findings |
| 7.2 Coding Standards | PHP and JavaScript coding standards |
| **7.3 [Testing Infrastructure](7.3-testing-infrastructure.md)** | Test super user fixtures, authentication helpers, and testing best practices |
| **7.4 [Security Debug Information](7.4-security-debug-information.md)** | Authorization tracking and debug display for development |
| 7.5 Git Workflow | Version control workflow |
| **7.6 [Testing Suite Overview](7.6-testing-suite.md)** | PHPUnit suite structure, seed management, and run commands |
| **7.7 [Console Commands](7.7-console-commands.md)** | CLI console commands reference for CakePHP shell tasks |
| **8. [Deployment](8-deployment.md)** | |
| **8.1 [Environment Setup](8.1-environment-setup.md)** | **NEW** Environment variables reference and configuration |
| 8.2 Migrations | Database migration handling |
| 8.3 Updates | Application update procedures |

| **9. [UI Components](9-ui-components.md)** | |
| **9.1 [Dataverse Grid System](9.1-dataverse-grid-system.md)** | Modern data table system with views, filters, sorting, and export |
| **9.2 [Bootstrap Icons](9.2-bootstrap-icons.md)** | **NEW** Bootstrap Icons integration and usage |
| **9.3 [Dataverse Grid Complete Guide](9.3-dataverse-grid-complete-guide.md)** | **NEW** Comprehensive DV Grid implementation reference |
| 9.3 Layouts | Template layouts and structure |
| 9.4 View Helpers | Custom view helpers |
| 9.5 Frontend Libraries | JavaScript and CSS libraries |
| **10. [JavaScript Development](10-javascript-development.md)** | |
| **10.1 [JavaScript Framework](10.1-javascript-framework.md)** | Detailed Stimulus.JS framework implementation |
| **10.2 [QR Code Controller](10.2-qrcode-controller.md)** | QR code generation with Stimulus and npm packages |
| **10.3 [Timezone Handling](10.3-timezone-handling.md)** | Timezone conversion, display, and storage patterns |
| **10.3.1 [Timezone Utility API](10.3.1-timezone-utils-api.md)** | KMP_Timezone utility API reference |
| **10.3.2 [Timezone Input Controller](10.3.2-timezone-input-controller.md)** | Timezone input Stimulus controller reference |
| **10.4 [Asset Management](10.4-asset-management.md)** | **NEW** Asset compilation, versioning, and optimization |
| **11. [Extending KMP](11-extending-kmp.md)** | |
| 11.1 Creating Plugins | How to create plugins for extending KMP |
| 11.2 Navigation and Event System | How to add Navigation from a plugin and inject Plugin UI into Core Pages |
| 11.3 Creating UI Components | Extending the UI with custom cells |
| 11.4 Database Models | Adding custom database models to plugins |
| 11.5 Best Practices | Guidelines for effective plugin development |
| 11.6 Managing Plugin Configuration | Using AppSettings for plugin configuration |
| 11.7 Adding Public IDs to Plugin Tables | Implementing secure public identifiers in plugins |
| 11.8 Creating REST API Endpoints | API controllers, routes, authentication, and authorization |
| 11.9 OpenAPI Documentation for Plugin APIs | Plugin spec fragments and Swagger UI |
| 11.10 Injecting Data into Other API Responses | ApiDataRegistry pattern for cross-plugin data enrichment |

## API Documentation

| Reference | Description |
|-----------|-------------|
| **[API Reference Portal](api/index.md)** | REST API quick start, Swagger UI, and developer guides |
| **[PHP API Reference](api/php/index.html)** | Controllers, Services, Models, Behaviors, and Plugins |
| **[JavaScript API Reference](api/js/index.html)** | Stimulus controllers, utilities, and frontend modules |

## Appendices

| Section | Description |
|---------|-------------|
| **[Appendices](appendices.md)** | |
| A. Troubleshooting | Common issues and solutions |
| B. Glossary | Terms specific to KMP and SCA |
| C. Resources | Additional resources and references |

## Supplemental Documentation

| Document | Description |
|----------|-------------|
| **[Docker Development](docker-development.md)** | Docker multi-container setup for local and hosted development |
| **[Office Reporting Structure](office-reporting-structure.md)** | Hierarchical reporting relationships between offices |
| **[Documentation Migration Summary](DOCUMENTATION_MIGRATION_SUMMARY.md)** | Summary of documentation migration efforts |
| **[Activity Groups Controller Migration](ACTIVITY_GROUPS_CONTROLLER_MIGRATION.md)** | ActivityGroupsController documentation migration notes |
| **[Dataverse Grid Filter Audit](dataverse-grid-custom-filter-audit.md)** | Custom filter handler candidates audit |
| **[Dataverse Grid Migration Todo](dataverse-grid-migration-todo.md)** | Dataverse grid migration work-in-progress tracker |
| **[System Views Refactor Task List](system-views-refactor-task-list.md)** | DVTables system views refactor tasks |
| **[License](LICENSE.md)** | Project license information |


---

## Quick Start Guide

For developers new to KMP:

1. **Start with [Getting Started](2-getting-started.md)** to set up your development environment
2. **Review [Architecture](3-architecture.md)** to understand the system structure
3. **Explore [Core Modules](4-core-modules.md)** to learn about the main functionality
4. **Check [Development Workflow](7-development-workflow.md)** for coding standards and practices
5. **For configuration questions, see [Configuration](2-configuration.md)** and **[Environment Setup](8.1-environment-setup.md)**

## Recent Documentation Updates

**December 2025 - Configuration Documentation Migration**

The following documentation has been newly created or significantly expanded:

- ✅ **[2-configuration.md](2-configuration.md)** - Comprehensive application configuration guide
- ✅ **[8.1-environment-setup.md](8.1-environment-setup.md)** - Environment variables reference (complete)
- ✅ **[6.4-caching-strategy.md](6.4-caching-strategy.md)** - Multi-tier caching strategy and tuning
- ✅ **[10.4-asset-management.md](10.4-asset-management.md)** - Asset compilation and optimization
- ✅ **[9.2-bootstrap-icons.md](9.2-bootstrap-icons.md)** - Bootstrap Icons integration
- ✅ **[7.1-security-best-practices.md](7.1-security-best-practices.md)** - Expanded with session and encryption docs
- ✅ **[4.7-document-management-system.md](4.7-document-management-system.md)** - Expanded with storage configuration
- ✅ **[app/config/app.php](../app/config/app.php)** - Cleaned up, reduced documentation bloat by 45%

See **[DOCUMENTATION_MIGRATION_SUMMARY.md](DOCUMENTATION_MIGRATION_SUMMARY.md)** for complete details on the migration.

## Documentation Status

This documentation is actively maintained and reflects the current state of the KMP codebase.

## Contributing

When contributing to KMP, please:

- Follow the coding standards outlined in [Development Workflow](7-development-workflow.md)
- Update relevant documentation when making changes
- Run tests before submitting changes
- Review the [Plugin Development Guide](11-extending-kmp.md) when creating extensions

## Support

- **Issues**: Report issues through the GitHub repository
- **Documentation**: This comprehensive developer documentation
- **Code Examples**: See individual sections for implementation examples
