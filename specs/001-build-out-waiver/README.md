# Gathering Waiver Tracking System - Planning Documents

**Feature ID**: 001-build-out-waiver  
**Branch**: `001-build-out-waiver`  
**Status**: ✅ Planning Complete - Ready for Team Review  
**Date**: October 19, 2025  
**Estimated Complexity**: 21 Story Points

---

## 📋 Executive Summary

This feature adds a comprehensive **Gathering Waiver Tracking System** to KMP, enabling:

- 📝 Configuration of gathering types, waiver types, and activity requirements
- 📱 Mobile camera capture for on-site waiver collection
- 🔄 Automated image-to-PDF conversion with 90-95% file size reduction
- 📅 Configurable retention policies with automated expiration tracking
- 🔍 Search and compliance reporting capabilities
- ☁️ Flexible storage (local filesystem or cloud S3)

**Key Architectural Decisions**:
- **Hybrid Approach**: Core gathering entities + Waivers plugin
- **Awards Migration**: Consolidate `award_gatherings` into core `gatherings` table
- **Technology Stack**: Imagick (PDF conversion), Flysystem (storage), Turbo + Stimulus (UI)

---

## 📚 Document Index

### Core Planning Documents

| Document | Size | Purpose | Review Priority |
|----------|------|---------|-----------------|
| [📄 spec.md](./spec.md) | 40KB | **Complete feature specification** - User stories, requirements, acceptance criteria | 🔴 **HIGH** |
| [📋 plan.md](./plan.md) | 15KB | **Implementation plan** - Constitution check, complexity assessment, phase completion | 🔴 **HIGH** |
| [📄 PLANNING_SESSION_SUMMARY.md](./PLANNING_SESSION_SUMMARY.md) | 8KB | **Session overview** - Quick summary of planning accomplishments | 🟡 **MEDIUM** |

### User Flow Diagrams

| Document | Size | Purpose | Review Priority |
|----------|------|---------|-----------------|
| [🔄 USER_FLOWS.md](./USER_FLOWS.md) | 18KB | **Visual user workflows** - Three main flows with Mermaid diagrams | 🔴 **HIGH** |

**Three Key Workflows Documented**:
1. **Configuring the Baseline System** - Kingdom Officer sets up types and policies
2. **Creating a Gathering** - Steward sets up a specific event with activities
3. **Uploading Waivers for a Gathering** - Mobile/desktop waiver capture and conversion

### Phase 0: Research Documents

| Document | Size | Purpose | Review Priority |
|----------|------|---------|-----------------|
| [🔬 research.md](./research.md) | 15KB | **Technical research** - 7 key decisions with rationale and alternatives | 🔴 **HIGH** |

**Key Decisions Made**:
- Image Conversion: Imagick with Group4 compression
- Mobile Camera: HTML5 `capture="environment"`
- Storage: Flysystem (local/S3 support)
- Date Handling: CakePHP FrozenTime
- Automation: Queue Plugin with two-step deletion

### Phase 1: Design Documents

| Document | Size | Purpose | Review Priority |
|----------|------|---------|-----------------|
| [🗄️ data-model.md](./data-model.md) | 22KB | **Database design** - ERD diagrams, entity schemas, relationships | 🔴 **HIGH** |
| [🔌 contracts/](./contracts/) | 12KB | **API specifications** - REST endpoints, Turbo integration patterns | 🟡 **MEDIUM** |
| [⚡ quickstart.md](./quickstart.md) | 13KB | **Developer guide** - Setup steps, code patterns, testing strategy | 🟢 **LOW** |

#### API Contract Files

| File | Size | Endpoints Covered |
|------|------|-------------------|
| [contracts/README.md](./contracts/README.md) | 3.2KB | API conventions, pagination, Turbo patterns |
| [contracts/gathering-types.md](./contracts/gathering-types.md) | 5.4KB | Gathering Types CRUD (5 endpoints) |
| [contracts/gatherings.md](./contracts/gatherings.md) | 1.4KB | Gatherings CRUD + activity management |
| [contracts/gathering-waivers.md](./contracts/gathering-waivers.md) | 2.0KB | Waiver upload, search, deletion |

### Supporting Documents

| Document | Size | Purpose |
|----------|------|---------|
| [📝 RENAME_SUMMARY.md](./RENAME_SUMMARY.md) | 5.4KB | Documentation of Event → Gathering terminology change (270+ replacements) |
| [📊 DIAGRAM_UPDATE_SUMMARY.md](./DIAGRAM_UPDATE_SUMMARY.md) | 5.1KB | Explanation of ASCII → Mermaid diagram conversion |

---

## 🎯 Review Checklist

### For Stakeholders (Non-Technical)

Review these documents to understand **what** we're building:

- [ ] **spec.md** - Read User Stories and Requirements sections
  - Are the user stories accurate?
  - Do the requirements cover all needed functionality?
  - Are the success criteria clear and measurable?

- [ ] **plan.md** - Review Summary and Complexity sections
  - Does the feature summary match expectations?
  - Is the complexity assessment reasonable (21 story points)?
  - Are the risk areas acceptable?

### For Technical Team (Developers)

Review these documents to understand **how** we're building it:

- [ ] **research.md** - Validate technical decisions
  - Agree with Imagick for PDF conversion?
  - Comfortable with Flysystem for storage abstraction?
  - Any concerns with Queue Plugin for automation?

- [ ] **data-model.md** - Review database design
  - Are the entity relationships correct?
  - Any missing fields or indexes?
  - Is the Awards migration strategy sound?

- [ ] **contracts/** - Review API design
  - Do the endpoints follow REST conventions?
  - Is Turbo integration pattern appropriate?
  - Any missing endpoints or use cases?

- [ ] **plan.md** - Constitution compliance
  - All 7 KMP principles verified?
  - Plugin vs Core decision justified?
  - Any architectural concerns?

### For Architects/Lead Developers

Review the overall architecture:

- [ ] **Hybrid approach** (Core entities + Plugin) makes sense?
- [ ] **Awards migration** plan is safe and complete?
- [ ] **Technology choices** align with KMP standards?
- [ ] **Complexity estimate** (21 points) seems reasonable?
- [ ] **Risk mitigation** strategies are adequate?

---

## 📊 Key Metrics

### Documentation Coverage

| Category | Files | Total Size | Status |
|----------|-------|------------|--------|
| Planning | 3 files | 63KB | ✅ Complete |
| Research | 1 file | 15KB | ✅ Complete |
| Design | 5 files | 47KB | ✅ Complete |
| Supporting | 2 files | 10KB | ✅ Complete |
| **Total** | **11 files** | **135KB** | ✅ **100% Complete** |

### Entity Breakdown

| Location | Entities | Purpose |
|----------|----------|---------|
| Core (`src/Model/`) | 3 entities | GatheringTypes, Gatherings, GatheringActivities (reusable) |
| Plugin (`plugins/Waivers/`) | 4 entities | WaiverTypes, GatheringActivityWaivers, GatheringWaivers, WaiverConfiguration |
| Existing | 1 entity | Members (reference only) |
| **Total** | **8 entities** | Complete system |

### API Coverage

| Category | Endpoints | Status |
|----------|-----------|--------|
| Gathering Types | 5 endpoints | ✅ Documented |
| Gatherings | 5+ endpoints | ✅ Documented |
| Gathering Waivers | 5+ endpoints | ✅ Documented |
| **Total** | **15+ endpoints** | ✅ Fully Specified |

---

## 🎨 Visual Overview

### User Workflows

The system has **three primary user workflows**, each optimized for its specific role and use case:

#### 1. 🔧 [Configuring the Baseline System](./USER_FLOWS.md#flow-1-configuring-the-baseline-system)
**Role**: Kingdom Officer  
**Frequency**: Initial setup (1-2x per year)  
**Steps**:
```
Configure Gathering Types → Configure Waiver Types → Link Activities to Waivers
```
Sets up the types and policies that define how the system operates.

#### 2. 📅 [Creating a Gathering](./USER_FLOWS.md#flow-2-creating-a-gathering)
**Role**: Gathering Steward or Kingdom Officer  
**Frequency**: Before each event  
**Steps**:
```
Enter Basic Info → Add Activities → System Auto-Links Waivers → Publish
```
Creates a specific gathering and automatically determines required waivers.

#### 3. 📱 [Uploading Waivers](./USER_FLOWS.md#flow-3-uploading-waivers-for-a-gathering)
**Role**: Gathering Steward  
**Frequency**: During/after each event  
**Steps**:
```
Select Gathering → Capture Images (Mobile Camera) → Auto-Convert to PDF → Store
```
Mobile-optimized workflow with automatic image-to-PDF conversion (90-95% size reduction).

**👉 See [USER_FLOWS.md](./USER_FLOWS.md) for complete visual diagrams of all three workflows**

### System Architecture

The system uses a **hybrid architecture**:

```
┌─────────────────────────────────────────────────────┐
│                   KMP Core                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────┐ │
│  │ Gathering    │  │ Gathering    │  │ Gathering│ │
│  │ Types        │→ │ Entities     │→ │ Activities│ │
│  └──────────────┘  └──────────────┘  └──────────┘ │
└─────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────┐
│              Waivers Plugin                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────┐ │
│  │ Waiver       │  │ Activity     │  │ Gathering│ │
│  │ Types        │→ │ Waivers      │  │ Waivers  │ │
│  └──────────────┘  └──────────────┘  └──────────┘ │
└─────────────────────────────────────────────────────┘
```

**See `data-model.md` for full Mermaid diagrams** (ERD, workflow, relationships)

### Key Technologies

| Layer | Technology | Purpose |
|-------|------------|---------|
| Backend | CakePHP 5.x | MVC framework |
| Database | MySQL 5.7+ / MariaDB 10.2+ | Data persistence |
| Image Processing | Imagick (ImageMagick) | PDF conversion |
| Storage | Flysystem | Local/S3 abstraction |
| Frontend | Hotwired (Turbo + Stimulus) | Interactive UI |
| UI Framework | Bootstrap 5 | Responsive design |
| Background Jobs | Queue Plugin | Retention automation |
| Testing | PHPUnit | Unit & integration tests |

---

## ⚠️ Important Considerations

### Risk Areas (from plan.md)

1. **Image Conversion Quality** - Balancing file size vs. readability
2. **Mobile Camera Compatibility** - Cross-device testing required (iOS Safari, Android Chrome)
3. **Awards Migration** - Must not break existing Awards plugin functionality
4. **Retention Policy Bugs** - Critical for legal compliance (date calculations must be accurate)
5. **Storage Backend Switching** - Graceful handling when changing from local to S3
6. **Performance** - Batch upload of 20+ images needs optimization

### Dependencies

**System Requirements**:
- ImageMagick library installed
- PHP `imagick` extension enabled
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.2+

**Composer Packages** (new):
- `league/flysystem-cakephp` - Storage abstraction

### Constitutional Compliance

✅ **All 7 KMP Constitution principles verified** (see plan.md)
- CakePHP Conventions ✅
- Plugin Architecture ✅
- Hotwired Stack ✅
- Test Coverage ✅
- Security & Authorization ✅
- Service Layer ✅
- Code Quality ✅

**Deviations**: None

---

## 🚀 Next Steps

### After Team Review

1. **Approve Planning Documents**
   - [ ] Stakeholder sign-off on spec.md
   - [ ] Technical team sign-off on plan.md, research.md, data-model.md
   - [ ] Architecture team sign-off on overall design

2. **Update Agent Context**
   ```bash
   .specify/scripts/bash/update-agent-context.sh copilot
   ```

3. **Generate Implementation Tasks**
   ```bash
   # Run in chat: /speckit.tasks
   ```
   This will create `tasks.md` with granular development tasks

4. **Begin Development**
   - Follow tasks.md milestone by milestone
   - Use quickstart.md for code patterns
   - Reference data-model.md for database schemas
   - Follow contracts/ for API implementations

### Development Milestones

| Milestone | Description | Story Points |
|-----------|-------------|--------------|
| M1 | Database migrations & seed data | 2 |
| M2 | Core entities (CRUD) | 3 |
| M3 | Waivers plugin setup | 2 |
| M4 | Image conversion service | 5 |
| M5 | Mobile UI (Stimulus) | 3 |
| M6 | Retention policies | 3 |
| M7 | Testing (PHPUnit) | 2 |
| M8 | Awards migration | 1 |
| **Total** | | **21 points** |

---

## 📞 Questions or Feedback?

### For Specification Questions
- Review `spec.md` for complete requirements
- Check User Stories (US-001 through US-014)
- Review Functional Requirements (FR-001 through FR-040)

### For Technical Questions
- Review `research.md` for decision rationale
- Check `data-model.md` for database design
- Review `contracts/` for API specifications
- Check `quickstart.md` for implementation patterns

### For Architecture Questions
- Review `plan.md` for architectural decisions
- Check Constitution Check section for compliance
- Review `.specify/memory/constitution.md` for KMP principles

### For Clarifications
Please add comments or questions to the specific document, noting:
- Document name
- Section or line number
- Specific question or concern

---

## 📝 Review Status

| Reviewer Role | Reviewer Name | Status | Date | Notes |
|---------------|---------------|--------|------|-------|
| Product Owner | | ⏳ Pending | | |
| Technical Lead | | ⏳ Pending | | |
| Senior Developer | | ⏳ Pending | | |
| QA Lead | | ⏳ Pending | | |
| Security Review | | ⏳ Pending | | |

**Status Legend**: ⏳ Pending | ✅ Approved | ❌ Changes Requested | 🔄 In Review

---

## 📄 Document Change Log

| Date | Document | Change | Author |
|------|----------|--------|--------|
| 2025-10-19 | All documents | Initial planning phase complete | Copilot |
| 2025-10-19 | data-model.md | Converted ASCII to Mermaid diagrams | Copilot |
| 2025-10-19 | README.md | Created review document (this file) | Copilot |

---

**Ready for Review**: ✅ Yes  
**Planning Phase**: ✅ Complete (Phase 0 & Phase 1)  
**Next Phase**: Phase 2 - Task Generation (after approval)

---

*This document serves as the entry point for team review. All planning documents are complete and ready for stakeholder and technical team evaluation.*
