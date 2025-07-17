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
| 3.1 [Core Foundation Architecture](3.1-core-foundation-architecture.md) | Detailed architectural insights from comprehensive code documentation |
| 3.2 Application Structure | Overview of MVC pattern and directory structure |
| 3.3 Core Components | Main application components |
| 3.4 Plugin System | Overview of the plugin-based architecture |
| 3.5 Authentication & Authorization | Security implementation details |
| **4. [Core Modules](4-core-modules.md)** | |
| 4.1 [Member Management](4-core-modules.md#41-member-management) | Members registration, profiles, and management |
| └── [Member Lifecycle](4.1-member-lifecycle.md) | Complete member lifecycle and data flow documentation |
| 4.2 [Branches](4-core-modules.md#42-branches) | Branch hierarchies and management |
| └── [Branch Hierarchy](4.2-branch-hierarchy.md) | Complete organizational structure and tree management documentation |
| 4.3 [Warrants](4-core-modules.md#43-warrants) | Warrant system for officer positions |
| └── [Warrant Lifecycle](4.3-warrant-lifecycle.md) | Complete warrant state machine and approval process documentation |
| 4.4 [Permissions & Roles](4-core-modules.md#44-permissions--roles) | Security roles and permissions system |
| └── [RBAC Security Architecture](4.4-rbac-security-architecture.md) | Complete RBAC system with warrant temporal validation layer |
| 4.5 [AppSettings](4-core-modules.md#45-appsettings) | Application settings configuration system |
| **5. [Plugins](5-plugins.md)** | |
| 5.1 Activities | Activities management plugin |
| 5.2 Officers | Officers management and roster system |
| 5.3 Awards | Award recommendations and management system |
| 5.4 Queue | Background job processing system |
| 5.5 GitHubIssueSubmitter | User feedback submission to GitHub |
| 5.6 Bootstrap | UI framework integration |
| **6. [UI Components](6-ui-components.md)** | |
| 6.1 Layouts | Dashboard, signin, register, etc. |
| 6.2 View Helpers | KMP helper and other custom view helpers |
| 6.3 Frontend Libraries | JavaScript controllers, utilities, etc. |
| **7. [Services](7-services.md)** | |
| 7.1 WarrantManager | Warrant processing service |
| 7.2 ActiveWindowManager | Active window management service |
| 7.3 StaticHelpers | Application configuration helpers |
| 7.4 Email | Email notification system |
| **8. [Development Workflow](8-development-workflow.md)** | |
| 8.1 Coding Standards | PHP coding conventions used in the project |
| 8.2 Testing | How to run and write tests |
| 8.3 Debugging | Debug techniques and tools (DebugKit) |
| 8.4 Git Workflow | Branch management and contribution process |
| **9. [Deployment](9-deployment.md)** | |
| 9.1 Production Setup | Production environment considerations |
| 9.2 Migrations | Database migration management |
| 9.3 Updates | How to update the application |
| **10. [JavaScript Development with Stimulus](10-javascript-development.md)** | |
| 10.1 Introduction to Stimulus | Overview of the Stimulus framework |
| 10.2 Controller Organization | Where and how to create Stimulus controllers |
| 10.3 Development Workflow | Using npm run watch for development |
| 10.4 Asset Management | How JavaScript and CSS assets are built and served |
| **11. [Extending KMP](11-extending-kmp.md)** | |
| 11.1 Creating Plugins | How to create plugins for extending KMP |
| 11.2  Navigation and Event System | How to add Navigation from a plugin and inject Plugin UI into Core Pages |
| 11.3 Creating UI Components | Extending the UI with custom cells |
| 11.4 Database Models | Adding custom database models to plugins |
| 11.5 Best Practices | Guidelines for effective plugin development |
| **[Appendices](appendices.md)** | |
| A. Troubleshooting | Common issues and solutions |
| B. Glossary | Terms specific to KMP and SCA |
| C. Resources | Additional resources and references |
