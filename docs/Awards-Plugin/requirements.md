# Awards Plugin Requirements Document

## Overview

The Awards plugin is a modular component for the KMP project, built on CakePHP 5.x, that manages the full lifecycle of award recommendations, approvals, and tracking for an SCA (Society for Creative Anachronism) context. It provides robust data models, workflows, and user interfaces for handling awards, recommendations, domains, levels, and related event-driven processes. The plugin is designed for extensibility, security, and seamless integration with the KMP core and other plugins.

---

## 1. Business Context & Purpose

- **Purpose:**
  - To facilitate the submission, review, approval, scheduling, and historical tracking of awards and recommendations within the organization.
  - To provide a secure, auditable, and user-friendly interface for all stakeholders (members, officers, royalty, etc.).
- **Stakeholders:**
  - Members (submit recommendations, view their history)
  - Officers (review, approve, manage awards)
  - Royalty (final approval, scheduling, giving awards)
  - Administrators (configuration, reporting)

---

## 2. Core Features & User Stories

### 2.1 Award Management
- CRUD operations for awards (create, view, edit, soft-delete)
- Awards are categorized by domain, level, and branch
- Awards can have specialties, insignia, badge, and charter
- Prevent deletion if recommendations exist; soft-delete with name prefix

### 2.2 Recommendation Management
- Members can submit recommendations for awards
- Recommendations are linked to members, awards, branches, and events
- Recommendations have states and statuses (e.g., Submitted, In Progress, Scheduled, Given, No Action)
- Kanban board and table views for recommendations
- CSV export for reporting
- State change logging (audit trail)
- Bulk and quick edit features

### 2.3 Domain, Level, and Event Management
- CRUD for domains and levels (with progression order)
- Events can be linked to recommendations (scheduled, given, etc.)

### 2.4 Authorization & Policies
- Fine-grained authorization using CakePHP Authorization plugin
- Policies for Awards, Recommendations, Domains, Levels, Events
- Branch-based scoping for data access
- Approval levels for recommendations

### 2.5 UI/UX
- Stimulus.js controllers for dynamic forms, tables, and kanban boards
- Responsive, accessible, and modern UI (Bootstrap-based)
- Data-driven configuration for table/board columns, filters, and export
- Modal dialogs for add/edit/quick-edit

### 2.6 Integration & Extensibility
- Event handlers for navigation and cell rendering
- Configurable via StaticHelpers and app settings
- Plugin registration and migration order support

### 2.7 Auditing & Logging
- All state changes and critical actions are logged
- Soft-deletion (Muffin/Trash)
- Footprint behavior for created/modified by

### 2.8 Testing
- PHPUnit tests for all controllers, models, and policies
- Fixtures for all tables
- JavaScript (Jest) tests for Stimulus controllers

---

## 3. Data Model & Schema

### 3.1 Main Tables
- **awards_awards**: id, name, description, domain_id, level_id, branch_id, specialties (JSON), insignia, badge, charter, created/modified/deleted, created_by/modified_by
- **awards_domains**: id, name, description
- **awards_levels**: id, name, progression_order
- **awards_recommendations**: id, requester_id, member_id, award_id, branch_id, event_id, reason, state, status, contact info, created/modified/deleted, created_by/modified_by
- **awards_events**: id, name, date, location, etc.
- **awards_recommendations_events**: (join table for many-to-many recommendations/events)
- **awards_recommendations_states_log**: id, recommendation_id, from_state, to_state, from_status, to_status, created_by, created

### 3.2 Relationships
- Award belongsTo Domain, Level, Branch
- Recommendation belongsTo Award, Member, Branch, Event
- Recommendation hasMany RecommendationStateLogs
- Recommendation belongsToMany Events

---

## 4. Authorization & Security
- Uses CakePHP Authorization plugin with custom policies per model/table
- Branch-based scoping for data access
- Approval levels restrict actions by user role
- All actions are checked for authorization; unauthorized access is blocked
- Soft-deletion for auditability

---

## 5. UI/UX & Frontend

### 5.1 Stimulus Controllers
- Modular controllers for award forms, recommendation tables, kanban boards, quick/bulk edit
- Controllers registered globally and follow KMP conventions
- Data attributes for configuration and inter-controller communication

### 5.2 Views & Templates
- CakePHP templates for all CRUD actions (add, edit, view, index)
- Table and board views configurable via app settings
- Modal dialogs for add/edit/quick-edit
- Responsive and accessible design

---

## 6. Configuration & Extensibility
- All view/table/board configurations are stored in app settings (YAML)
- Plugin registers event handlers for navigation and cell rendering
- Migration order is configurable
- Easily extendable for new award types, states, or workflows

---

## 7. Integration Points
- Event handlers for navigation and cell rendering
- Exposes routes for awards, recommendations, domains, levels, events, and reports
- JSON endpoints for AJAX/Stimulus controllers
- CSV export for reporting

---

## 8. Error Handling & Logging
- All exceptions are caught and logged
- User-friendly error messages via Flash
- State changes are logged in a dedicated table
- Soft-deletion for recoverability

---

## 9. Testing Requirements
- PHPUnit tests for all controllers, models, and policies
- Fixtures for all tables
- JavaScript (Jest) tests for Stimulus controllers
- Test coverage for all critical workflows (submission, approval, scheduling, deletion)

---

## 10. Non-Functional Requirements
- Must follow CakePHP 5.x and KMP coding standards
- PSR-12 for PHP, ES6+ for JS
- Responsive and accessible UI
- Secure against common web vulnerabilities (XSS, CSRF, etc.)
- All configuration and state must be auditable

---

## 11. Future Enhancements (Optional)
- Notification system for state changes
- Advanced reporting and analytics
- API endpoints for external integration
- Customizable workflows per branch/domain

---

## 12. Glossary
- **Award:** A formal recognition given to a member
- **Recommendation:** A submission proposing a member for an award
- **Domain:** A category or area for awards (e.g., Arts, Service)
- **Level:** A rank or progression for awards
- **Branch:** A local group or region
- **Event:** An occurrence where awards may be given

---

*This document is auto-generated from code analysis as of May 18, 2025. Please review and update as the plugin evolves.*
