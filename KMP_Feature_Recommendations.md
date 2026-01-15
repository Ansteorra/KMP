# KMP Analysis & Strategic Feature Recommendations

> **Document Purpose:** Analysis of the Kingdom Management Portal (KMP) application with strategic recommendations for new plugins and features to improve SCA member experiences and reduce volunteer administrative burden.
>
> **Date:** January 2026

---

## Table of Contents

1. [Current Application Summary](#current-application-summary)
2. [Existing Plugin Capabilities](#existing-plugin-capabilities)
3. [SCA Pain Points Identified](#sca-pain-points-identified)
4. [Prioritized Feature Recommendations](#prioritized-feature-recommendations)
   - [Tier 1: High Impact, High Feasibility](#tier-1-high-impact-high-feasibility)
   - [Tier 2: High Impact, Moderate Complexity](#tier-2-high-impact-moderate-complexity)
   - [Tier 3: Future Considerations](#tier-3-future-considerations)
5. [Quick Wins](#quick-wins)
6. [Implementation Roadmap](#implementation-roadmap)
7. [Summary: Greatest Impact Features](#summary-greatest-impact-features)
8. [Sources](#sources)

---

## Current Application Summary

**Kingdom Management Portal (KMP)** is a sophisticated membership management system built on CakePHP 5.x with a modern frontend (Stimulus.js + Turbo + Bootstrap 5).

### Technology Stack

- **Backend:** CakePHP 5.x (PHP 8.1+), MySQL/MariaDB
- **Frontend:** Stimulus.js 3.2, Turbo 8.0, Bootstrap 5.3
- **Storage:** Azure Blob Storage via Flysystem
- **Architecture:** Plugin-based with 8 plugins, policy-based authorization

---

## Existing Plugin Capabilities

### Core System
| Module | Functionality |
|--------|--------------|
| **Members** | Profiles, status tracking, youth management, mobile cards, PII controls, timezone support |
| **Branches** | Hierarchical organization (Kingdom → Principality → Barony → Shire), nested set model |
| **Roles/Permissions** | Temporal RBAC with warrant requirements, background checks, policy-based authorization |
| **Gatherings** | Event management, calendars, ICS export, QR codes, public pages, staff management, scheduled activities |
| **Documents** | File management, Azure Blob Storage, retention policies |

### Activities Plugin (Marshallate/Authorization System)
**This is a complete fighter authorization system:**
- Activity definitions with age restrictions and term lengths
- Multi-level approval workflows (configurable approvers per activity)
- Authorization request lifecycle: Pending → Approved/Denied → Expired/Revoked
- Renewal system with separate approver requirements
- Mobile-optimized interfaces for requests and approvals
- Email notifications with secure token links
- Marshal roster tracking via permission-based approver discovery
- Authorization reports by activity type, branch, and validity date
- Automatic role grants on authorization approval
- Authorization cards (web and mobile cell components - partially implemented)

**Not Yet Implemented in Activities:**
- QR code verification at list tables
- Fight practice attendance linking to gatherings

### Awards Plugin
**Complete award recommendation system:**
- 3-tier hierarchy: Domain → Level → Branch
- Sophisticated state machine with 11 states and 4 status categories
- Multi-gathering support for ceremony tracking
- Court details: call into court preference, availability, person to notify
- Recommendation audit trail (immutable state change logs)
- Multiple grid views: In Progress, Scheduling, To Give, Closed
- Kanban board interface (transitioning to grid)
- Member profile integration (submitted by/for cells)
- Public submission form for guest recommendations
- Bulk edit and state update operations
- CSV export with configurable columns

**Not a Full Order of Precedence Database:**
- Tracks recommendations and ceremony scheduling
- Does NOT track: historical awards given, armorial registrations, precedence calculation

### Officers Plugin
**Complete officer management system:**
- Department → Office → Officer hierarchy
- Reporting chain management with skip-aware traversal
- Deputy relationships with cross-branch support
- Automatic role grants on officer appointment
- Term-based expiration and status transitions
- Warrant requirement flagging and state tracking
- Bulk warrant roster generation
- Hire/release email notifications
- Reports: Department roster, officers by warrant status
- Branch officer tree visualization
- Required office compliance tracking

**Not Yet Implemented in Officers:**
- Automated quarterly reporting
- Report aggregation up hierarchy

### Waivers Plugin
**Production-quality waiver management:**
- Multi-image upload with mobile camera capture
- Automatic image-to-PDF conversion
- Retention policies with JSON configuration
- Exemption/attestation system with configurable reasons
- Waiver decline capability (30-day window)
- Gathering waiver closure tracking
- Activity-level waiver requirements
- Dashboard with compliance analytics
- Soft deletes with recovery capability

---

## SCA Pain Points Identified

Based on research into SCA operations across multiple kingdoms:

| Pain Point | Description | KMP Status |
|------------|-------------|------------|
| **Fighter Authorization Tracking** | Physical cards, manual verification | ✅ Solved (Activities plugin) |
| **Award Recommendations** | Scattered across emails and forms | ✅ Solved (Awards plugin) |
| **Officer Management** | Warrant tracking, succession | ✅ Solved (Officers plugin) |
| **Event Waivers** | Paper-based, lost documents | ✅ Solved (Waivers plugin) |
| **Quarterly Reporting Burden** | Manual compilation up hierarchy | ❌ Not addressed |
| **Order of Precedence** | Separate databases, no integration | ⚠️ Partial (recommendations only) |
| **A&S Competition Tracking** | Spreadsheets, no artisan portfolios | ❌ Not addressed |
| **Volunteer Service Hours** | Invisible contributions | ❌ Not addressed |
| **Attendance Analytics** | No participation trends | ⚠️ Data exists, no analytics |
| **QR Verification at Events** | Manual card checking | ❌ Not addressed |
| **Institutional Knowledge** | Lost when officers leave | ❌ Not addressed |

---

## Prioritized Feature Recommendations

### Tier 1: High Impact, High Feasibility

*These build on existing infrastructure and address critical remaining gaps*

---

#### 1. QR Code Authorization Verification

**Impact: High** | **Effort: Low**

##### Problem
Fighters have authorization data in KMP but list tables and marshals still manually verify cards. No quick way to verify at check-in.

##### Solution
- Add QR code to existing mobile authorization card
- Create verification endpoint that returns authorization status
- Mobile-friendly verification page for list officers/marshals
- Show: member photo (if available), SCA name, active authorizations, expiration dates
- Optionally integrate with gathering attendance check-in

##### Why Now
KMP already has:
- Mobile card infrastructure with QR code generation
- Complete authorization data in Activities plugin
- Gathering attendance tracking

##### Implementation Notes
- Extend existing `MobileCardAuthorizedForCell` (test exists, implementation partial)
- Add verification controller action
- QR encodes member public_id + auth token

---

#### 2. Fight Practice Attendance Integration

**Impact: Medium-High** | **Effort: Low**

##### Problem
Practice attendance isn't linked to the authorization system. Marshals can't easily see who attended practices or track participation for renewals.

##### Solution
- Link gathering attendance to activity authorizations
- Show practice attendance on member authorization view
- Filter attendance by activity type (e.g., "Heavy fighter practices attended")
- Use as supporting data for authorization renewals

##### Why Now
KMP already has:
- Gathering attendance tracking
- Gathering activities linked to activity types
- Authorization renewal workflow

---

#### 3. Automated Officer Reporting Plugin

**Impact: Very High** | **Effort: Medium**

##### Problem
Officers spend hours compiling quarterly reports. Reports flow up through hierarchy manually (Local → Regional → Kingdom → Society). This is the biggest remaining paperwork burden.

##### Solution
- Report templates by office type (Seneschal, Exchequer, MoAS, Herald, etc.)
- Auto-populate data from KMP:
  - Membership counts by branch
  - Event/gathering counts and attendance
  - Officer roster changes
  - Authorization statistics
  - Award recommendations processed
- Hierarchical report aggregation (child reports roll up automatically)
- Deadline tracking with automated reminders via Queue system
- Report submission workflow with approval chain
- Historical report archive searchable by period/branch/office

##### Why Now
KMP already has:
- Complete branch hierarchy with nested sets
- Officer warrant tracking with reporting relationships
- Email template system
- Queue system for scheduled reminders
- All the source data (members, events, authorizations, awards)

##### Data Sources for Auto-Population
| Report Section | KMP Data Source |
|----------------|-----------------|
| Membership numbers | Members table by branch |
| Events held | Gatherings by branch and date range |
| Attendance | GatheringAttendances aggregate |
| Officers | Officers plugin roster |
| Authorizations issued | Activities authorizations |
| Awards recommended | Awards recommendations |
| Financial summary | (manual entry or future integration) |

---

#### 4. Order of Precedence (OP) Plugin

**Impact: High** | **Effort: Medium**

##### Problem
OP databases are maintained separately from award recommendations. When awards are given, they must be manually entered into a separate OP system. No single source of truth.

##### Solution
- **Award Registry**: Track awards actually given (not just recommendations)
  - Member, award, date given, event/gathering, reign
  - Import from existing OP databases
- **Precedence Calculation**: Engine following SCA rules
  - Royal peerages (Duke/Count/Viscount)
  - Patent peerages (Chivalry, Laurel, Pelican, Defense)
  - Grants of Arms, Awards of Arms
  - Kingdom-specific orders
- **Armorial Tracking**: Name, device, badge registrations
  - Submission status (local → kingdom → Laurel)
  - Registered vs pending
- **Close the Loop**: When Award recommendation state = "Given", create OP entry
- **Public OP Search**: Searchable member precedence lookup
- **Member Profile Integration**: Show OP on member view

##### Relationship to Existing Awards Plugin
```
Awards Plugin (Recommendations)     →    OP Plugin (Registry)
─────────────────────────────────────────────────────────────
Recommendation submitted
State: In Consideration
State: Scheduled for Event
State: Given                        →    Award Registry entry created
                                         Precedence recalculated
```

##### Why Now
KMP already has:
- Awards plugin with complete recommendation workflow
- Member profiles
- Gathering/event system for ceremony context

---

#### 5. Enhanced Attendance Analytics Dashboard

**Impact: High** | **Effort: Low-Medium**

##### Problem
KMP collects attendance data but provides no analytics. Officers can't see participation trends, event success metrics, or member engagement levels.

##### Solution
- **Member Participation View**:
  - Events attended over time (chart)
  - Activities participated in
  - Personal "passport" / journey timeline
- **Event Success Metrics**:
  - Attendance counts by gathering
  - Trends over time (is attendance growing?)
  - Activity popularity (which activities draw people)
- **Branch Vitality Reports**:
  - Active member counts (attended event in last 6 months)
  - Event frequency
  - New vs returning attendees
- **Export for Officer Reports**: Direct integration with Reporting plugin

##### Why Now
KMP already has:
- Gathering attendance tracking
- Activity tracking via gathering activities
- Grid/reporting infrastructure
- All data needed, just needs visualization

---

### Tier 2: High Impact, Moderate Complexity

*Strategic features requiring more development but delivering significant value*

---

#### 6. Arts & Sciences Competition Tracking Plugin

**Impact: High** | **Effort: Medium-High**

##### Problem
A&S competition results scattered across spreadsheets. No way to track an artisan's progress or find qualified judges. MoAS officers maintain manual records.

##### Solution
- **Competition Entry Registration**:
  - Categories aligned with SCA A&S structure
  - Documentation upload (using existing Document infrastructure)
  - Entry description and research summary
- **Judging System**:
  - Judge assignment per entry
  - Scoring rubrics (Lochac 5-category system as default: Documentation, Authenticity, Complexity, Workmanship, Creativity)
  - Judge comments and feedback
- **Results Tracking**:
  - Entry scores and rankings
  - Kingdom championship points
  - Competition history
- **Artisan Portfolio**:
  - Member's A&S journey on profile
  - All entries with scores over time
  - Judge qualifications tracking
- **Judge Finder**: Find qualified judges by category

##### Integration Points
- Gathering activities for A&S competitions
- Member profiles for portfolio display
- Documents for entry documentation
- Awards for A&S award recommendations

---

#### 7. Volunteer Service Hours Tracking

**Impact: Medium-High** | **Effort: Low-Medium**

##### Problem
Service to the organization is invisible. Pelican candidates need documentation of service. Event stewards want to recognize volunteers.

##### Solution
- **Service Hour Logging**:
  - Activity types: Event setup, Gate, Kitchen, Teaching, Marshaling, etc.
  - Categories aligned with peerage tracks (Service, Martial, Arts)
  - Hours worked per gathering
- **Automatic Service Calculation**:
  - Officer service from warrant periods (Officers plugin data)
  - Event staff hours from gathering_staff records
  - Authorization approvals from Activities plugin
- **Recognition System**:
  - Thank you acknowledgments
  - Service milestone badges
  - Annual service summaries
- **Reports for Award Recommendations**:
  - Service history for Pelican recommendations
  - Teaching history for Laurel recommendations
  - Marshal service for Chivalry/Defense recommendations

##### Why Now
Builds directly on existing:
- `gathering_staff` table (add hours field)
- Officer warrant data
- Authorization approval records

---

#### 8. Resource Library / Knowledge Base Plugin

**Impact: Medium** | **Effort: Medium**

##### Problem
Institutional knowledge leaves when officers change. New officers struggle to find procedures. Each officer "reinvents the wheel."

##### Solution
- **Document Repository**:
  - Organized by office/department/topic
  - Branch-specific documents (bylaws, standing policies)
  - Kingdom-wide handbooks
- **Version Control**:
  - Document history
  - Track changes between versions
  - Approval workflow for updates
- **Search**:
  - Full-text search across documents
  - Tag-based filtering
- **Access Control**:
  - Public documents
  - Officer-only documents
  - Branch-specific visibility
- **Officer Handoff**:
  - Checklist for outgoing officers
  - Required reading for new officers
  - Handoff notes

##### Why Now
KMP has Document infrastructure that can be extended for this purpose.

---

#### 9. Heraldic Submissions Tracker

**Impact: Medium-High** | **Effort: Medium**

##### Problem
Members don't know where their name/device submissions are in the process. Heralds track submissions manually in spreadsheets.

##### Solution
- **Submission Tracking**:
  - Name submissions
  - Device (arms) submissions
  - Badge submissions
- **Status Workflow**:
  - Draft → Local Herald Review → Kingdom Submission → In LoI → Laurel Decision
  - Returned for changes tracking
- **Member Integration**:
  - "My Submissions" view
  - Notifications when status changes
  - Registered items shown on profile
- **Herald Tools**:
  - Letter of Intent generation
  - Submission batch management
  - Basic conflict checking (name similarity)
- **Image Management**:
  - Device/badge image upload
  - Thumbnail generation (via Glide)

---

### Tier 3: Future Considerations

*Valuable but requiring more infrastructure or external integrations*

---

#### 10. SCA Membership Integration

**Impact: Very High** | **Effort: High (depends on SCA corporate)**

##### Problem
Membership status requires manual verification against SCA membership database.

##### Solution
- API integration with SCA membership system (if available)
- Automated membership status sync
- Membership expiration warnings
- Renewal reminders

> **Note:** This depends on SCA Corporate providing API access. Worth investigating availability.

---

#### 11. Financial Integration Plugin

**Impact: High** | **Effort: High**

##### Problem
Exchequers maintain separate financial systems. Event budgets disconnected from event management.

##### Solution
- Event budget templates
- Income/expense tracking per gathering
- Branch treasury overview (read-only from external system)
- Quarterly financial report integration
- Gate/troll integration for real-time revenue

> **Note:** Requires careful consideration of financial controls and audit requirements.

---

#### 12. Mobile App / PWA Enhancement

**Impact: Medium-High** | **Effort: High**

##### Problem
Gate staff need quick member lookup. Marshals need authorization verification in the field with spotty connectivity.

##### Solution
- Offline-capable PWA for gate/troll
- Quick member search with photo
- Authorization verification scan
- Event check-in interface
- Push notifications for event reminders
- Cached authorization data for offline verification

##### Why Defer
KMP's mobile card PWA provides foundation, but full offline capability requires significant architecture work.

---

#### 13. Inter-Kingdom Data Exchange

**Impact: Medium** | **Effort: Medium-High**

##### Problem
Kingdoms operate in silos. Visiting fighters need authorization verified. Traveling members want to connect with local groups.

##### Solution
- Standardized API for authorization verification
- Traveling member announcements
- Cross-kingdom event visibility
- Visitor welcome system

---

## Quick Wins

These require minimal new infrastructure and can be implemented quickly:

| Feature | Description | Effort | Plugin |
|---------|-------------|--------|--------|
| **QR Auth Verification** | Add QR code to mobile auth card with verification endpoint | Low | Activities |
| **Practice Attendance Link** | Show practice attendance on authorization view | Low | Activities/Gatherings |
| **Attendance Stats Widget** | Add participation stats to member profile | Low | Core |
| **Officer Term Reminders** | Queue jobs for expiring officer terms | Low | Officers |
| **Staff Hours Field** | Add hours field to gathering_staff for service tracking | Low | Core |
| **Award Given → OP Entry** | Create OP stub when recommendation marked "Given" | Low | Awards |
| **Event Success Metrics** | Attendance counts/trends on gathering view | Low | Core |

---

## Implementation Roadmap

### Phase 1: Quick Wins & Activities Enhancement
1. **QR Code Authorization Verification** - Complete the auth card system
2. **Practice Attendance Linking** - Connect gatherings to authorizations
3. **Attendance Analytics Dashboard** - Visualize existing data

### Phase 2: Reporting & Compliance
4. **Automated Officer Reporting Plugin** - Biggest paperwork reduction
5. **Service Hours Tracking** - Foundation for recognition

### Phase 3: Historical Record
6. **Order of Precedence Plugin** - Complete the award lifecycle
7. **Resource Library** - Preserve institutional knowledge

### Phase 4: Arts & Heraldry
8. **A&S Competition Tracking** - Support artisan community
9. **Heraldic Submissions Tracker** - Support herald community

---

## Summary: Greatest Impact Features

The **top 5 features** that would most transform SCA volunteer experience:

| Rank | Feature | Why It Matters |
|------|---------|----------------|
| 1 | **Automated Officer Reporting** | Biggest remaining time savings for burned-out volunteers |
| 2 | **QR Authorization Verification** | Completes the Activities plugin, modernizes list table |
| 3 | **Attendance Analytics** | Makes participation visible, helps with all reporting |
| 4 | **Order of Precedence Plugin** | Closes award recommendation loop, celebrates members |
| 5 | **Service Hours Tracking** | Recognizes volunteer contributions, supports award recommendations |

### Expected Outcomes

These five features would:

- **Reduce remaining volunteer paperwork by an estimated 50%+**
- Complete the authorization card system for field verification
- Create visibility into organizational health metrics
- Establish a single source of truth for member recognition
- Support succession planning through documented institutional knowledge

---

## Appendix: What KMP Already Solves

For reference, these pain points are **already addressed** by existing plugins:

| Pain Point | Solution |
|------------|----------|
| Fighter authorization tracking | Activities plugin - complete multi-level approval system |
| Authorization renewals | Activities plugin - renewal workflow with configurable approvers |
| Marshal roster management | Activities plugin - permission-based approver discovery |
| Award recommendations | Awards plugin - full state machine workflow |
| Court scheduling | Awards plugin - gathering integration, court details |
| Officer warrant tracking | Officers plugin - warrant state, roster generation |
| Officer succession | Officers plugin - hire/release with notifications |
| Reporting relationships | Officers plugin - hierarchical chains with skip-aware traversal |
| Event waivers | Waivers plugin - mobile upload, PDF conversion, retention |
| Event management | Core - gatherings, calendars, staff, activities |
| Member management | Core - profiles, status, mobile cards |

---

## Sources

Research was informed by:

- [SCA.org Official Site](http://www.sca.org/)
- [Drachenwald Reporting Guidelines](https://drachenwald.sca.org/offices/chatelaine/reporting/)
- [Atlantia Fighter Card System](https://mol.atlantia.sca.org/information-for-fighters/)
- [Atenveldt Online Fighter Cards](https://www.atenveldt.org/news/online-fighter-cards-accessing-them/)
- [Atlantia Order of Precedence](https://op.atlantia.sca.org/)
- [How OP Works - Poore House](https://herald.poore-house.com/protocol/op/)
- [Lochac A&S Judging Scheme](https://artsandsciences.lochac.sca.org/judging-scheme/)
- [Æthelmearc A&S Documentation Guide](https://aeans.aethelmearc.org/how-to-document/)
- [Blackthorn Event Attendance Tracking](https://blackthorn.io/content-hub/event-attendance-tracking/)
- [Donorbox Volunteer Retention Ideas](https://donorbox.org/nonprofit-blog/volunteer-retention-ideas/)

---

*Generated for the Kingdom Management Portal (KMP) project - January 2026*
