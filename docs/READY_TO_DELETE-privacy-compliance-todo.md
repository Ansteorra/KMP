# Privacy Compliance TODO List
## GDPR & CCPA/CPRA Requirements for KMP

**Generated:** October 31, 2025  
**Status:** Planning Phase  
**Priority:** High - Legal Compliance Required

---

## Overview

This document contains a comprehensive list of items needed to achieve compliance with:
- **GDPR** (General Data Protection Regulation - EU)
- **CCPA/CPRA** (California Consumer Privacy Act / California Privacy Rights Act)

The analysis was conducted on the KMP application to identify gaps in current privacy implementation and required enhancements.

---

## Quick Start - High Priority Items

Start with these critical items first:

1. **Cookie Consent Banner** (#1) - Legal requirement before any data collection
2. **Privacy Policy & Terms** (#2) - Must-have documentation
3. **Data Export** (#3) - Core GDPR right
4. **Account Deletion** (#4) - Core GDPR right
5. **Privacy Settings** (#8) - Many existing TODOs in codebase point to this

---

## 1. User Rights & Consent

### ☐ 1. Implement Cookie Consent Banner & Management
**Priority:** Critical  
**GDPR Articles:** 7, Recital 32  
**CCPA Sections:** 1798.100, 1798.135

**Requirements:**
- Cookie consent banner on first visit
- Cookie preferences management page
- Granular consent tracking (essential vs analytics vs marketing)
- Ability to withdraw consent
- Consent mechanism for session cookies, CSRF tokens, and any tracking cookies

**Files to Create:**
- `templates/element/cookie_consent.php`
- `src/Controller/CookieConsentController.php`
- Migration: `cookie_consents` table

**Dependencies:** None (start here)

---

### ☐ 2. Create Privacy Policy & Terms of Service Pages
**Priority:** Critical  
**GDPR Articles:** 13, 14  
**CCPA Sections:** 1798.100(b)

**Requirements:**
- Privacy Policy page explaining data collection, processing, retention
- Terms of Service page
- Data Processing Agreement if applicable
- Cookie Policy separate page

**Content to Include:**
- What personal data is collected (members table has extensive PII)
- Why it's collected (purpose)
- How long it's retained
- Who has access
- Third-party processors (Azure storage, email SMTP)

**Files to Create:**
- `templates/Pages/privacy_policy.php`
- `templates/Pages/terms_of_service.php`
- `templates/Pages/cookie_policy.php`

**Dependencies:** None

---

### ☐ 3. Implement Right to Access (Data Export)
**Priority:** Critical  
**GDPR Article:** 15  
**CCPA Section:** 1798.100, 1798.110

**Requirements:**
- Members self-service data export endpoint
- Generate comprehensive JSON/CSV with all personal data
- Include metadata about data processing

**Data to Export:**
- Members table data
- Notes
- Documents
- Waivers
- Officer assignments
- Awards
- Activities
- Authorizations

**Files to Create/Modify:**
- `MembersController::exportMyData()` action
- Consider using existing `CsvExportService` as foundation

**Dependencies:** Privacy Policy (#2) should reference this right

---

### ☐ 4. Implement Right to Erasure (Account Deletion)
**Priority:** Critical  
**GDPR Article:** 17  
**CCPA Section:** 1798.105

**Current State:** Has soft-delete (`deleted` field)

**Requirements:**
- User-initiated account deletion request workflow
- Admin review queue for deletion requests
- Data anonymization/pseudonymization for retained records (awards history, officer records)
- Cascade deletion/anonymization of related data (notes, documents, waivers)
- Deletion confirmation and audit logging

**Important:** Some data may need retention for legal/historical reasons - document exceptions

**Files to Create/Modify:**
- `MembersController::requestDeletion()` action
- Admin deletion queue view
- Data anonymization service

**Dependencies:** Privacy Policy (#2), Audit Logging (#9)

---

### ☐ 5. Implement Right to Rectification UI
**Priority:** High  
**GDPR Article:** 16  
**CCPA Section:** 1798.106

**Current State:** Members can edit profiles

**Requirements:**
- Clear UI indicating users can request corrections
- Admin notification system for correction requests if user can't self-edit certain fields
- Audit trail of data corrections (who changed what when)

**Fields That May Need Correction Workflow:**
- `membership_number`
- `verified_by`
- `verified_date`
- Background check fields

**Files to Modify:**
- Enhance existing edit profile functionality
- Create correction request workflow for locked fields

**Dependencies:** Audit Logging (#9)

---

### ☐ 6. Implement Data Portability Features
**Priority:** High  
**GDPR Article:** 20  
**CCPA Section:** 1798.100

**Requirements:**
- Extend data export (TODO #3) to include structured format (JSON preferred)
- Document data schema/structure
- Enable export of data to another controller if technically feasible

**Data to Include:**
- Profile
- Roles
- Warrants
- Awards
- Activities
- Authorizations
- Waivers
- Documents
- Notes

**Files to Modify:**
- Extend export functionality from #3

**Dependencies:** Right to Access (#3)

---

### ☐ 39. Implement Right to Object & Restrict Processing
**Priority:** Medium  
**GDPR Articles:** 18, 21

**Requirements:**
- UI for members to object to processing or request restriction
- Processing restriction flags in members table
- Admin workflow for reviewing objection requests
- System enforcement of restrictions (block emails, hide from searches, etc.)
- Notification when restrictions lifted
- Audit trail of restrictions

**Files to Create:**
- `processing_restrictions` table migration
- Restriction management UI

**Dependencies:** Audit Logging (#9)

---

## 2. Data Governance & Documentation

### ☐ 7. Enhance Data Retention Policies & Automated Deletion
**Priority:** High  
**GDPR Article:** 5(1)(e)

**Current State:** `retention_date` exists for waivers via `RetentionPolicyService`

**Requirements:**
- Extend retention policies to all personal data types
- Document retention periods for each data category (members, notes, documents, logs)
- Implement automated cleanup jobs for expired data
- Create retention policy configuration UI for admins
- Implement retention policy exceptions for legal holds

**Data Categories to Address:**
- Members
- Notes
- Documents
- Logs
- Waivers (already has retention)
- Activity records

**Files to Create/Modify:**
- Extend `RetentionPolicyService`
- Queue plugin job for scheduled cleanup
- Admin retention policy UI

**Dependencies:** None

---

### ☐ 12. Document Data Processing Activities (ROPA)
**Priority:** High  
**GDPR Article:** 30

**Requirements:**
- Create ROPA document listing all personal data processing
- Document purposes, legal basis, data categories, recipients, transfers, retention periods
- Document third-party processors (Azure storage per .env, email SMTP)
- Regular ROPA review process

**Processing Activities to Document:**
- Member registration
- Login/authentication
- Officer assignments
- Awards management
- Waiver collection
- Document storage
- Email communications
- Background checks

**Files to Create:**
- `docs/privacy-ropa.md`
- Consider creating admin UI for ROPA maintenance

**Dependencies:** None

---

### ☐ 13. Implement Data Processing Agreements (DPAs)
**Priority:** High  
**GDPR Article:** 28

**Requirements:**
- Identify all third-party data processors
- Obtain signed DPAs from each processor
- Document processor security measures
- Regular processor audits/reviews
- Store DPAs in secure location
- Create processor registry in admin area

**Known Processors:**
- Azure Storage (from `.env` `AZURE_STORAGE_CONNECTION_STRING`)
- Email provider
- Any analytics providers

**Files to Create:**
- Processor registry table migration
- Processor registry UI

**Dependencies:** ROPA (#12)

---

### ☐ 17. Create Data Protection Impact Assessment (DPIA)
**Priority:** Medium  
**GDPR Article:** 35

**Requirements:**
- Conduct DPIA for high-risk activities
- Document risks and mitigation measures
- Identify when DPIA is needed for new features
- Regular DPIA reviews
- Consult DPO if appointed

**High-Risk Areas:**
- Minor data processing
- Large-scale personal data
- Sensitive categories
- Automated decisions
- Waivers plugin
- Background checks

**Files to Create:**
- `docs/privacy-dpia.md`

**Dependencies:** ROPA (#12)

---

### ☐ 29. Create Privacy Team & DPO Designation
**Priority:** Medium  
**GDPR Article:** 37

**Requirements:**
- Determine if DPO required (public authority, large-scale processing of sensitive data)
- Designate DPO if required
- Create privacy team roles and responsibilities
- DPO contact information in privacy policy
- Regular privacy team meetings
- Privacy training for staff

**Considerations:**
- Organization size
- Data processing scale
- Nature of processing activities

**Dependencies:** Privacy Policy (#2)

---

### ☐ 30. Implement Vendor Risk Assessment Process
**Priority:** Medium  
**GDPR Article:** 28

**Requirements:**
- Vendor assessment questionnaire (security, privacy, compliance)
- Vendor approval workflow
- Regular vendor reviews
- Vendor incident notification requirements
- Vendor termination and data return procedures
- Vendor registry in admin area

**Files to Create:**
- `vendor_assessments` table migration
- Assessment workflow UI

**Dependencies:** DPAs (#13)

---

## 3. Privacy Settings & Consent Management

### ☐ 8. Implement Privacy Settings & Granular Consent
**Priority:** Critical  
**GDPR Articles:** 7, 6(1)(a)

**Current State:** Members table has TODOs for privacy settings integration

**Requirements:**
- Create `privacy_settings` table/field for granular consent tracking
- Implement UI for members to control visibility of each field
- Track consent for specific processing activities
- Version consent records for audit trail

**Consent Types:**
- Email communications
- Photo usage
- Directory listings
- Public profile visibility
- Data sharing with branches

**Files to Create:**
- `member_privacy_settings` migration
- `PrivacySettings` entity
- Privacy settings UI component

**Code References:**
- `Member::publicData()` has many TODO comments for privacy settings

**Dependencies:** None

---

### ☐ 20. Implement Email Opt-Out & Communication Preferences
**Priority:** High  
**CCPA Section:** 1798.120

**Requirements:**
- Communication preferences UI
- Unsubscribe links in all emails
- Opt-out tracking and enforcement
- Clear distinction between transactional vs marketing emails
- Preference center for granular control

**Email Categories:**
- Marketing emails
- Newsletters
- Event notifications
- Administrative emails (must-send)

**Files to Create:**
- `email_preferences` table migration
- Preferences UI
- Unsubscribe controller

**Code to Review:**
- `QueuedMailerAwareTrait` usage

**Dependencies:** Privacy Settings (#8)

---

### ☐ 21. Create 'Do Not Sell My Personal Information' (CCPA)
**Priority:** Medium (if selling occurs)  
**CCPA Section:** 1798.120, 1798.135

**Requirements:**
- Determine if any data 'selling' occurs (data sharing with third parties for value)
- If yes: implement 'Do Not Sell' link in footer and privacy policy
- Track Do Not Sell requests
- Honor opt-outs within 15 days
- Annual reporting on data sales

**Action Items:**
- Review all data sharing agreements
- Review third-party integrations

**Dependencies:** Privacy Policy (#2)

---

## 4. Minor Protection & Age Verification

### ☐ 10. Enhance Minor Protection & Parental Consent
**Priority:** High  
**GDPR Article:** 8  
**Related:** COPPA (US), CCPA

**Current State:** Age-based restrictions in `publicData()`

**Requirements:**
- Implement explicit parental consent tracking
- Parental consent forms and workflows
- Age verification at registration
- Enhanced data minimization for minors
- Parental access to minor's data
- Age-up workflow when minor turns 18

**Database Fields:**
- `consent_given_by` (member_id or separate table)
- `consent_date`
- `birth_month`, `birth_year` (already exist)

**Files to Create:**
- Parental consent tracking table/fields
- Parental consent workflow
- Age-up automation

**Dependencies:** Age Verification (#22), Privacy Settings (#8)

---

### ☐ 22. Implement Age Verification at Registration
**Priority:** High  
**GDPR Article:** 8  
**CCPA Section:** 1798.120(c)

**Current State:** `birth_month`, `birth_year` fields exist

**Requirements:**
- Age verification during registration
- Different registration flows for minors vs adults
- Parental consent capture for minors
- Age gate for certain features
- Document age verification methods

**Considerations:**
- May need `birth_day` field for accurate age calculation
- Age thresholds: 13 (COPPA), 16 (GDPR), 18 (adult)

**Files to Modify:**
- Registration form
- Age verification service

**Dependencies:** None

---

### ☐ 42. Implement Children's Privacy Compliance (COPPA)
**Priority:** High (if US-based with children under 13)  
**US Law:** COPPA

**Requirements:**
- Determine if system collects data from children under 13
- Implement COPPA-compliant parental consent
- Parental notification requirements
- Parental access to child's data
- Data minimization for children
- No behavioral advertising to children

**Action Items:**
- Review minor member handling
- Review age thresholds

**Dependencies:** Minor Protection (#10), Age Verification (#22)

---

## 5. Security & Data Protection

### ☐ 9. Implement Comprehensive Audit Logging
**Priority:** Critical  
**GDPR Article:** 30

**Requirements:**
- Create `audit_logs` table for tracking all data access/modifications
- Log who accessed what data when
- Log data exports, deletions, corrections
- Log administrative actions on member data
- Implement audit log retention policy
- Create audit log review UI for admins

**Sensitive Fields to Log:**
- email
- phone
- address
- birth date
- password changes
- permission changes

**Files to Create:**
- `audit_logs` table migration
- Audit logging service
- Audit log UI

**Dependencies:** None

---

### ☐ 11. Implement Data Breach Notification System
**Priority:** High  
**GDPR Articles:** 33, 34  
**CCPA Section:** 1798.150

**Requirements:**
- Create incident response plan documentation
- Breach detection mechanisms (failed login monitoring exists, expand)
- Breach notification workflow
- Breach log and tracking system
- Notification templates

**Notification Types:**
- Internal notification
- Supervisory authority notification (72 hours)
- Affected user notification

**Files to Create:**
- `security_incidents` table migration
- Incident response controller/views
- `docs/incident-response-plan.md`

**Dependencies:** Audit Logging (#9)

---

### ☐ 14. Enhance Encryption & Data Security
**Priority:** High  
**GDPR Article:** 32

**Current State:** Passwords hashed, sessions secured

**Requirements:**
- Review and document all encryption (at-rest, in-transit)
- Implement field-level encryption for highly sensitive data
- Document encryption keys management
- Regular security audits
- Penetration testing schedule
- Document security measures in privacy policy

**Fields for Encryption:**
- Addresses
- Phone numbers
- Birth dates
- Background check data

**Code to Review:**
- `Security.salt` usage in `app.php`

**Dependencies:** Privacy Policy (#2)

---

### ☐ 26. Implement Password & Security Token Management
**Priority:** Medium  
**GDPR Article:** 32

**Requirements:**
- Review `password_token`, `password_token_expires_on` usage
- Implement token expiration cleanup
- Secure token generation and storage
- Rate limiting on password reset
- Password breach checking (HaveIBeenPwned API)
- MFA/2FA implementation for sensitive accounts

**Current Features:**
- `failed_login_attempts` tracking exists

**Files to Modify:**
- Enhance authentication in `Application.php`

**Dependencies:** None

---

### ☐ 35. Implement Session Security Enhancements
**Priority:** Medium  
**GDPR Article:** 32

**Current State:** Session config documented in `app_local.php`

**Requirements:**
- Review session configuration
- Implement session timeout warnings
- Secure session storage
- Session fixation prevention
- Concurrent session limits
- Session activity logging for sensitive accounts

**Files to Review:**
- Session config in `app.php`
- `Application.php` authentication setup

**Dependencies:** Audit Logging (#9)

---

### ☐ 36. Create Privacy Incident Response Plan
**Priority:** Medium  
**GDPR Articles:** 33, 34

**Requirements:**
- Privacy incident classification
- Incident response team and roles
- Investigation procedures
- Notification decision tree
- Incident documentation templates
- Post-incident review process

**Incident Types:**
- Breach
- Unauthorized access
- Data loss

**Files to Create:**
- `docs/incident-response.md`
- Incident tracking table

**Dependencies:** Data Breach Notification (#11)

---

## 6. Access Control & Data Minimization

### ☐ 15. Implement Purpose Limitation Controls
**Priority:** Medium  
**GDPR Article:** 5(1)(b)

**Requirements:**
- Document purpose for each data field collected
- Implement access controls based on purpose
- Prevent secondary use without consent
- Purpose declaration at collection time
- Audit data usage against stated purposes

**Action Items:**
- Review all data access in controllers and policies
- Document purpose for each field in members table

**Dependencies:** ROPA (#12)

---

### ☐ 16. Implement Data Minimization Review
**Priority:** Medium  
**GDPR Article:** 5(1)(c)

**Requirements:**
- Audit all collected fields in members table
- Justify necessity of each field
- Remove/make optional unnecessary fields
- Review `additional_info` JSON collection
- Document minimization rationale

**Fields to Review:**
- first_name, middle_name, last_name
- street_address, city, state, zip
- phone_number, email_address
- membership_number
- birth_month, birth_year
- pronouns
- additional_info JSON

**Dependencies:** ROPA (#12)

---

### ☐ 23. Review & Enhance Access Control Policies
**Priority:** High  
**GDPR Article:** 5(1)(f)

**Requirements:**
- Audit all Policy classes for appropriate data access restrictions
- Ensure officers only access data necessary for their role
- Review public data exposure
- Implement attribute-based access control for sensitive fields
- Least privilege review for all roles

**Files to Review:**
- All `*Policy.php` files
- Especially `MembersPolicy`
- `publicData()`, `memberData()`, `officerData()` methods

**Dependencies:** None

---

### ☐ 44. Implement Data Quality & Accuracy Measures
**Priority:** Medium  
**GDPR Article:** 5(1)(d)

**Requirements:**
- Regular data accuracy prompts (annual profile review)
- Data verification workflows
- Stale data detection and cleanup
- User-initiated correction process
- Accuracy metrics and reporting
- Automated data validation at input

**Files to Create:**
- Data quality service
- Verification workflows

**Dependencies:** Right to Rectification (#5)

---

## 7. Automated Decisions & Profiling

### ☐ 18. Implement Automated Decision-Making Notices
**Priority:** Low  
**GDPR Article:** 22

**Requirements:**
- Review system for automated decisions
- If automated decisions exist: implement human review option
- Explain logic
- Allow contestation
- Document in privacy policy

**Areas to Review:**
- Automated warrant eligibility (`warrantable` field, `getNonWarrantableReasons()`)
- Automated role assignments
- Activity authorization approvals
- Any scoring/profiling systems

**Dependencies:** Privacy Policy (#2)

---

## 8. Third-Party & Cross-Border Transfers

### ☐ 19. Review & Secure Third-Party Integrations
**Priority:** High  
**GDPR Articles:** 28, 44-50

**Requirements:**
- Review all third-party services for GDPR compliance
- Verify DPAs
- Document data flows to third parties
- Implement Standard Contractual Clauses (SCCs) for non-EU transfers

**Services to Review:**
- Azure Storage (`AZURE_STORAGE_CONNECTION_STRING` in `.env`)
- Email SMTP (`EMAIL_SMTP_HOST`)
- Any analytics/tracking tools in JavaScript
- GitHub integration (`GitHubIssueSubmitter` plugin)

**Dependencies:** DPAs (#13)

---

### ☐ 28. Implement Cross-Border Data Transfer Mechanisms
**Priority:** High  
**GDPR Chapter:** V

**Requirements:**
- Identify all data storage locations
- If data leaves EU/EEA: implement appropriate safeguards
- Document transfer mechanisms in privacy policy
- Member consent for transfers if required
- Annual review of transfer mechanisms

**Action Items:**
- Review `AZURE_STORAGE_CONNECTION_STRING` for storage location
- Implement SCCs, BCRs, or adequacy decisions

**Dependencies:** Privacy Policy (#2), DPAs (#13)

---

## 9. Specific Features & Modules

### ☐ 27. Document Waiver Processing & Legal Basis
**Priority:** Medium  
**GDPR Articles:** 6, 9

**Current State:** `RetentionPolicyService` exists

**Requirements:**
- Document legal basis for waiver collection
- Waiver retention justification
- Who can access waiver documents
- Waiver anonymization after retention period
- Parent/guardian consent for minor waivers

**Legal Bases:**
- Consent
- Legitimate interest
- Legal obligation

**Files to Review:**
- Waivers plugin privacy documentation
- `GatheringWaiverPolicy`

**Dependencies:** ROPA (#12)

---

### ☐ 31. Review Notes System for Privacy Compliance
**Priority:** Medium

**Current State:** `private` flag exists

**Requirements:**
- Review note privacy controls
- Note access restrictions based on role
- Note retention policies
- Include notes in data export/deletion
- Audit who can create private vs public notes

**Files to Review:**
- `Note` entity
- `NotesTable`
- Note access in policies

**Dependencies:** Data Export (#3), Audit Logging (#9)

---

### ☐ 32. Review Document Management for Privacy
**Priority:** Medium

**Current State:** `uploaded_by` tracking exists

**Requirements:**
- Review document access controls
- Document encryption at rest
- Document retention and deletion
- Include documents in member data export
- Secure file download authorization
- Document metadata privacy

**Files to Review:**
- `Document` entity
- `DocumentsTable`
- File storage service

**Dependencies:** Data Export (#3), Encryption (#14)

---

### ☐ 33. Implement Privacy-Preserving Search & Discovery
**Priority:** Medium

**Requirements:**
- Review search functionality in `MembersController`
- Implement rate limiting on search to prevent scraping
- Log search queries for abuse detection
- Limit search results based on requester authorization
- Consider CAPTCHA for public search
- Anonymize search analytics

**Methods to Review:**
- `autoComplete`
- `searchMembers`

**Dependencies:** Audit Logging (#9)

---

### ☐ 34. Review Mobile Card & QR Code Privacy
**Priority:** Medium

**Requirements:**
- Review data in mobile cards
- Time-limited `mobile_card_token` implementation
- Token revocation capability
- Privacy controls for QR code generation
- Email distribution privacy
- Consider PII minimization in mobile cards

**Methods to Review:**
- `viewMobileCard` action
- `emailTaken` endpoint

**Dependencies:** Privacy Settings (#8)

---

### ☐ 38. Review Background Check Data Handling
**Priority:** Medium  
**GDPR Article:** 9 (special categories)

**Requirements:**
- Review `background_check_expires_on` field usage
- Document legal basis for background check requirement
- Background check data retention justification
- Access restrictions for background check info
- Background check provider DPA
- Member notification and consent for background checks

**Files to Review:**
- Members table background check fields

**Dependencies:** DPAs (#13), ROPA (#12)

---

### ☐ 41. Review Logging for Sensitive Data Exposure
**Priority:** High

**Requirements:**
- Audit all logging
- Ensure no passwords, tokens, or sensitive PII in logs
- Log retention policies
- Log access controls
- Log anonymization for privacy
- Secure log storage and transmission

**Files to Review:**
- `logs/` directory
- Logging configuration
- All `Log::write()` calls
- `FileLog` in `app.php`

**Dependencies:** Audit Logging (#9)

---

### ☐ 45. Review Email Communications for Privacy
**Priority:** Medium

**Requirements:**
- Review `QueuedMailerAwareTrait` usage and all email sending
- Email encryption (TLS/STARTTLS)
- BCC for bulk emails (privacy protection)
- Email content data minimization
- Tracking pixel opt-out if used
- Email logs retention and access controls

**Files to Review:**
- Mailer classes
- Queue plugin email jobs

**Dependencies:** Email Opt-Out (#20)

---

## 10. Analytics & Tracking

### ☐ 24. Implement Privacy-Preserving Analytics
**Priority:** Medium  
**GDPR Articles:** 6, 7

**Requirements:**
- Review any analytics tracking in JavaScript
- Implement IP anonymization
- Cookie consent integration with analytics
- Data retention limits for analytics
- Consider privacy-focused analytics alternatives
- Document analytics in privacy policy

**Files to Review:**
- `core.js`
- Stimulus controllers

**Alternatives:**
- Plausible Analytics
- Matomo (self-hosted)

**Dependencies:** Cookie Consent (#1), Privacy Policy (#2)

---

## 11. Transparency & User Experience

### ☐ 25. Create Member Data Dashboard & Transparency
**Priority:** Medium  
**GDPR Article:** 5(1)(a)

**Requirements:**
- Member dashboard showing all data collected about them
- When data was collected, who can access it
- Data source information (user-provided vs derived)
- Data accuracy verification prompts
- Link to privacy controls, export, deletion options

**Implementation:**
- Extend existing profile view with privacy transparency section

**Dependencies:** Data Export (#3), Privacy Settings (#8)

---

### ☐ 43. Create Accessibility & Privacy Notice Integration
**Priority:** Medium  
**GDPR Recital:** 58

**Requirements:**
- Ensure privacy policy is accessible (WCAG 2.1 AA compliance)
- Plain language privacy notices
- Multi-language privacy notices if applicable
- Privacy notice for users with disabilities
- Visual privacy indicators in UI
- Screen reader friendly privacy controls

**UI Elements:**
- Locked icons
- Privacy badges

**Dependencies:** Privacy Policy (#2)

---

## 12. Staff Training & Process

### ☐ 37. Implement Staff Privacy Training Program
**Priority:** Medium  
**GDPR Article:** 39(1)(b)

**Requirements:**
- Privacy training curriculum for all staff
- Role-specific training (admins, officers, developers)
- Training completion tracking
- Annual refresher training
- Privacy onboarding for new staff
- Training materials and documentation

**Delivery:**
- Online training platform or documentation

**Dependencies:** ROPA (#12), DPO designation (#29)

---

### ☐ 40. Create Privacy Policy Update Notification System
**Priority:** Low

**Requirements:**
- Privacy policy versioning system
- Change notification mechanism
- Track user acknowledgment of new policy
- Archive old policy versions
- Material changes highlighted in notifications
- Re-consent trigger for significant changes

**Files to Create:**
- `privacy_policy_versions` table
- Notification system

**Notification Methods:**
- Email
- Banner on login

**Dependencies:** Privacy Policy (#2), Email System (#45)

---

## Current System Strengths

✅ **Already Implemented:**
- Password hashing with secure methods
- Session security configuration (documented in `SECURITY_COOKIE_CONFIGURATION.md`)
- CSRF protection middleware
- Age-based privacy controls for minors (`publicData()` method)
- Soft deletion system (`deleted` field)
- Failed login attempt tracking
- Data filtering layers (public/member/officer data access)
- Waiver retention policies via `RetentionPolicyService`
- Document management with upload tracking
- Footprint behavior for created_by/modified_by tracking
- Branch-scoped authorization

## Personal Data Inventory

### Members Table PII:
- Legal names (first, middle, last)
- Contact info (email, phone, address)
- Birth dates (month/year)
- Membership numbers
- Background check information
- Pronouns and pronunciation
- Additional info JSON field
- Login credentials and tokens

### Related Data:
- Documents (waivers, files)
- Notes (potentially sensitive)
- Activity authorizations
- Officer assignments
- Awards recommendations
- Gathering waivers
- Audit trails

### Third-Party Processors:
- Azure Storage (`AZURE_STORAGE_CONNECTION_STRING`)
- Email SMTP provider
- GitHub (GitHubIssueSubmitter plugin)

---

## Implementation Strategy

### Phase 1: Critical Compliance (Weeks 1-4)
1. Cookie Consent Banner (#1)
2. Privacy Policy & Terms (#2)
3. Data Export (#3)
4. Account Deletion (#4)
5. Privacy Settings (#8)
6. Audit Logging (#9)

### Phase 2: User Rights (Weeks 5-8)
7. Right to Rectification (#5)
8. Data Portability (#6)
9. Email Opt-Out (#20)
10. Age Verification (#22)
11. Minor Protection (#10)

### Phase 3: Documentation & Governance (Weeks 9-12)
12. ROPA (#12)
13. DPAs (#13)
14. Data Retention (#7)
15. DPIA (#17)
16. Access Control Review (#23)

### Phase 4: Security Enhancements (Weeks 13-16)
17. Encryption Review (#14)
18. Breach Notification (#11)
19. Password Management (#26)
20. Third-Party Review (#19)
21. Cross-Border Transfers (#28)

### Phase 5: Feature-Specific Reviews (Weeks 17-20)
22. Waivers (#27)
23. Notes (#31)
24. Documents (#32)
25. Search (#33)
26. Mobile Cards (#34)
27. Background Checks (#38)
28. Email Communications (#45)

### Phase 6: Optimization & Training (Weeks 21-24)
29. Purpose Limitation (#15)
30. Data Minimization (#16)
31. Analytics (#24)
32. Member Dashboard (#25)
33. Staff Training (#37)
34. Incident Response (#36)

---

## Legal Basis Reference

### GDPR Legal Bases (Article 6):
- **(a) Consent** - Freely given, specific, informed agreement
- **(b) Contract** - Necessary for contract performance
- **(c) Legal Obligation** - Compliance with legal requirements
- **(d) Vital Interests** - Protection of life
- **(e) Public Interest** - Public task performance
- **(f) Legitimate Interests** - Balancing test with data subject rights

### CCPA Consumer Rights:
- Right to know
- Right to delete
- Right to opt-out of sale
- Right to non-discrimination
- Right to correct (CPRA)
- Right to limit use of sensitive PI (CPRA)

---

## Resources

### GDPR Resources:
- [Official GDPR Text](https://gdpr-info.eu/)
- [ICO GDPR Guide](https://ico.org.uk/for-organisations/guide-to-data-protection/guide-to-the-general-data-protection-regulation-gdpr/)
- [EDPB Guidelines](https://edpb.europa.eu/our-work-tools/general-guidance/gdpr-guidelines-recommendations-best-practices_en)

### CCPA/CPRA Resources:
- [California AG CCPA Page](https://oag.ca.gov/privacy/ccpa)
- [IAPP CCPA Resource Center](https://iapp.org/resources/topics/california-consumer-privacy-act/)

### Implementation Tools:
- [CakePHP Security Docs](https://book.cakephp.org/5/en/security.html)
- [OWASP Privacy Resources](https://owasp.org/www-community/privacy)

---

## Notes

- This list was generated through comprehensive analysis of the KMP codebase
- Priority levels are suggestions based on legal requirements and implementation dependencies
- Estimated timelines assume dedicated resources and may need adjustment
- Some items may be optional depending on jurisdiction and organization type
- Regular legal review recommended throughout implementation

**Last Updated:** October 31, 2025  
**Next Review:** To be scheduled after Phase 1 completion
