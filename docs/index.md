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
| 1.3 System Requirements | PHP 8.0+, MySQL, etc. |
| **2. [Getting Started](2-getting-started.md)** | |
| 2.1 Installation | Setting up development environment |
| 2.2 Configuration | Configuration files and database setup |
| 2.3 CakePHP Basics | Brief overview of CakePHP framework |
| **3. [Architecture](3-architecture.md)** | |
| **3.1 [Core Foundation Architecture](3.1-core-foundation-architecture.md)** | Application bootstrap, middleware stack, security architecture |
| **3.2 [Model Behaviors](3.2-model-behaviors.md)** | ActiveWindow, JsonField, and Sortable behaviors |
| **3.3 [Database Schema](3.3-database-schema.md)** | Complete database schema documentation |
| **3.4 [Migration Documentation](3.4-migration-documentation.md)** | Database migration history and patterns |
| **3.5 [ER Diagrams](3.5-er-diagrams.md)** | Entity relationship diagrams |
| **3.6 [Seed Documentation](3.6-seed-documentation.md)** | Data seeding framework and patterns |
| **4. [Core Modules](4-core-modules.md)** | |
| **4.1 [Member Lifecycle](4.1-member-lifecycle.md)** | Complete member lifecycle and data flow documentation |
| **4.2 [Branch Hierarchy](4.2-branch-hierarchy.md)** | Complete organizational structure and tree management documentation |
| **4.3 [Warrant Lifecycle](4.3-warrant-lifecycle.md)** | Complete warrant state machine and approval process documentation |
| **4.4 [RBAC Security Architecture](4.4-rbac-security-architecture.md)** | Complete RBAC system with warrant temporal validation layer |
| **4.5 [View Patterns](4.5-view-patterns.md)** | Template system, helpers, and UI components |
| **4.6 [Gatherings System](4.6-gatherings-system.md)** | Event management, calendar views, and attendance tracking |
| **4.6.1 [Calendar Download Feature](4.6.1-calendar-download-feature.md)** | iCalendar (.ics) file generation for importing events |
| **4.6.2 [Gathering Staff Management](4.6.2-gathering-staff-management.md)** | Staff and steward management system |
| **4.7 [Document Management & Retention System](4.7-document-management-system.md)** | File uploads, storage, and retention policies |
| **5. [Plugins](5-plugins.md)** | |
| **5.1 [Officers Plugin](5.1-officers-plugin.md)** | Officers management and roster system |
| **5.2 [Awards Plugin](5.2-awards-plugin.md)** | Award recommendations and management system |
| **5.3 [Queue Plugin](5.3-queue-plugin.md)** | Background job processing system |
| **5.4 [GitHubIssueSubmitter Plugin](5.4-github-issue-submitter-plugin.md)** | User feedback submission to GitHub |
| **5.5 [Bootstrap Plugin](5.5-bootstrap-plugin.md)** | UI framework integration |
| **5.6 [Activities Plugin](5.6-activities-plugin.md)** | Comprehensive authorization management system |
| **5.7 [Waivers Plugin](5.7-waivers-plugin.md)** | Waiver upload, tracking, and compliance management |
| **6. [Services](6-services.md)** | Service layer architecture and implementations |
| **7. [Development Workflow](7-development-workflow.md)** | |
| **7.1 [Security Best Practices](7.1-security-best-practices.md)** | Security configuration, testing, and audit findings |
| 7.2 Coding Standards | PHP and JavaScript coding standards |
| 7.3 Testing | PHPUnit testing practices |
| 7.4 Debugging | Debugging tools and techniques |
| 7.5 Git Workflow | Version control workflow |
| **8. [Deployment](8-deployment.md)** | |
| 8.1 Production Setup | Server setup and configuration |
| 8.2 Migrations | Database migration handling |
| 8.3 Updates | Application update procedures |
| **8.1 [Development Workflow (Alternative)](8-development-workflow.md)** | Additional development workflow documentation |
| **9. [UI Components](9-ui-components.md)** | |
| 9.1 Layouts | Template layouts and structure |
| 9.2 View Helpers | Custom view helpers |
| 9.3 Frontend Libraries | JavaScript and CSS libraries |
| **10. [JavaScript Development](10-javascript-development.md)** | |
| **10.1 [JavaScript Framework](10.1-javascript-framework.md)** | Detailed Stimulus.JS framework implementation |
| **10.2 [QR Code Controller](10.2-qrcode-controller.md)** | QR code generation with Stimulus and npm packages |
| **11. [Extending KMP](11-extending-kmp.md)** | |
| 11.1 Creating Plugins | How to create plugins for extending KMP |
| 11.2 Navigation and Event System | How to add Navigation from a plugin and inject Plugin UI into Core Pages |
| 11.3 Creating UI Components | Extending the UI with custom cells |
| 11.4 Database Models | Adding custom database models to plugins |
| 11.5 Best Practices | Guidelines for effective plugin development |
| **[Appendices](appendices.md)** | |
| A. Troubleshooting | Common issues and solutions |
| B. Glossary | Terms specific to KMP and SCA |
| C. Resources | Additional resources and references |
| **[For Kids Documentation](for_kids/index.md)** | Child-friendly introduction to KMP concepts |

---

## Quick Start Guide

For developers new to KMP:

1. **Start with [Getting Started](2-getting-started.md)** to set up your development environment
2. **Review [Architecture](3-architecture.md)** to understand the system structure
3. **Explore [Core Modules](4-core-modules.md)** to learn about the main functionality
4. **Check [Development Workflow](7-development-workflow.md)** for coding standards and practices

## Documentation Status

This documentation is actively maintained and reflects the current state of the KMP codebase as of November 2025. Each section has been fact-checked against the actual source code to ensure accuracy.

**Recent Updates (November 2025):**
- ✅ Gatherings system expanded with staff management, calendar downloads, public pages
- ✅ Security best practices consolidated from penetration testing and configuration audits
- ✅ QR code controller documented with npm package integration
- ✅ All new features validated against source code implementation

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
