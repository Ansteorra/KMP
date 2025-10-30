<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\ActiveWindowBaseEntity;

/**
 * Officer Entity - ActiveWindow integration and warrant lifecycle management
 *
 * The Officer entity represents individual officer assignments within the KMP
 * organizational hierarchy. It extends ActiveWindowBaseEntity to provide temporal
 * management of officer assignments with automatic status transitions, warrant
 * integration, and comprehensive assignment lifecycle tracking.
 *
 * ## Key Features
 * - **ActiveWindow Integration**: Automatic temporal management with status transitions
 * - **Warrant Lifecycle Management**: Integration with warrant system for requirement validation
 * - **Assignment Tracking**: Complete officer assignment history and status management
 * - **Temporal Validation**: Automatic status updates based on start/end dates
 * - **Reporting Hierarchy**: Dynamic reporting relationship calculation
 * - **Role Integration**: Automatic role assignment upon officer appointment
 * - **Deputy Management**: Support for deputy officer assignments with custom descriptions
 * - **Email Integration**: Officer contact management and reporting communication
 *
 * ## Database Schema
 * - `id`: Primary key for officer assignment identification
 * - `member_id`: Foreign key to assigned member
 * - `branch_id`: Foreign key to branch where assignment is active
 * - `office_id`: Foreign key to office being filled
 * - `granted_member_role_id`: Foreign key to role automatically granted
 * - `expires_on`: Assignment expiration date for temporal management
 * - `start_on`: Assignment start date for temporal management
 * - `status`: Current assignment status (new, current, upcoming, expired, revoked)
 * - `deputy_description`: Custom description for deputy assignments
 * - `revoked_reason`: Reason for assignment revocation
 * - `revoker_id`: User who revoked the assignment
 * - `deputy_to_branch_id`: Foreign key for deputy assignments across branches
 * - `deputy_to_office_id`: Foreign key for deputy office relationships
 * - `email_address`: Officer contact email for communication
 * - Standard audit fields: `created`, `modified`, `created_by`, `modified_by`
 *
 * ## ActiveWindow Behavior
 * The Officer entity inherits from ActiveWindowBaseEntity to provide:
 * - Automatic status transitions based on temporal windows
 * - Status management: new → upcoming → current → expired
 * - Daily status checks with automatic updates
 * - Query finder methods for current, upcoming, previous, and expired officers
 *
 * ## Warrant Integration
 * Officer assignments integrate with the warrant system through:
 * - Warrant requirement validation based on office configuration
 * - Dynamic warrant state calculation (Active, Pending, Missing, Not Required)
 * - Integration with warrant lifecycle management
 * - Automatic warrant checking upon assignment
 *
 * ## Reporting Hierarchy
 * The entity provides dynamic reporting relationship calculation:
 * - Integration with office hierarchy (deputy_to, reports_to)
 * - Cross-branch deputy assignment support
 * - Email-based contact generation for reporting chains
 * - Fallback to "Society" for top-level positions
 *
 * ## Type Identification
 * The Officer entity uses composite type identification through:
 * - `office_id`: Identifies the type of officer assignment
 * - `branch_id`: Scopes the assignment to specific organizational unit
 * - This enables proper ActiveWindow behavior for temporal management
 *
 * ## Usage Patterns
 * ```php
 * // Create a new officer assignment
 * $officer = $officersTable->newEntity([
 *     'member_id' => $memberId,
 *     'branch_id' => $branchId,
 *     'office_id' => $officeId,
 *     'start_on' => new DateTime('+1 month'),
 *     'expires_on' => new DateTime('+13 months'),
 *     'status' => 'upcoming'
 * ]);
 * 
 * // Check warrant status
 * if ($officer->warrant_state === 'Missing') {
 *     // Handle warrant requirement
 * }
 * 
 * // Get reporting hierarchy
 * $reportsTo = $officer->reports_to_list;
 * ```
 *
 * @property int $id Primary key for officer assignment identification
 * @property int $member_id Foreign key to assigned member
 * @property int $branch_id Foreign key to branch where assignment is active
 * @property int $office_id Foreign key to office being filled
 * @property int|null $granted_member_role_id Foreign key to role automatically granted
 * @property \Cake\I18n\Date|null $expires_on Assignment expiration date for temporal management
 * @property \Cake\I18n\Date|null $start_on Assignment start date for temporal management
 * @property string $status Current assignment status (new, current, upcoming, expired, revoked)
 * @property string|null $deputy_description Custom description for deputy assignments
 * @property string|null $revoked_reason Reason for assignment revocation
 * @property int|null $revoker_id User who revoked the assignment
 * @property int|null $deputy_to_branch_id Foreign key for deputy assignments across branches
 * @property int|null $deputy_to_office_id Foreign key for deputy office relationships
 * @property string $email_address Officer contact email for communication
 * @property \Cake\I18n\DateTime $created Record creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by User ID who created this record
 * @property int|null $modified_by User ID who last modified this record
 *
 * @property string $warrant_state Virtual property for warrant status calculation
 * @property bool $is_editable Virtual property indicating if assignment can be edited
 * @property string $reports_to_list Virtual property for formatted reporting hierarchy
 * @property array $effective_reports_to_currently Virtual property with smart hierarchy traversal for can_skip_report
 *
 * @property \App\Model\Entity\Member $member Assigned member entity
 * @property \App\Model\Entity\Branch $branch Branch where assignment is active
 * @property \Officers\Model\Entity\Office $office Office position being filled
 * @property \App\Model\Entity\Role $granted_member_role Role automatically granted upon assignment
 * @property \App\Model\Entity\Member $revoker User who revoked the assignment
 * @property \App\Model\Entity\Warrant $current_warrant Active warrant for this assignment
 * @property \App\Model\Entity\Warrant[] $pending_warrants Pending warrants for this assignment
 * @property \Officers\Model\Entity\Officer[] $reports_to_currently Officers this position directly reports to
 * @property \Officers\Model\Entity\Officer[] $deputy_to_currently Officers this position is deputy to
 *
 * @see \Officers\Model\Table\OfficersTable For officer data management and finders
 * @see \Officers\Model\Entity\Office For office configuration and hierarchy
 * @see \App\Model\Entity\ActiveWindowBaseEntity For temporal management features
 * @see \App\Model\Entity\Member For member profile integration
 * @see \App\Model\Entity\Branch For organizational scope
 */
class Officer extends ActiveWindowBaseEntity
{

    /**
     * Type identification fields for ActiveWindow behavior
     *
     * The Officer entity uses composite type identification to enable proper
     * ActiveWindow temporal management. This configuration defines which fields
     * uniquely identify the "type" of assignment for temporal scoping.
     *
     * ## Configuration
     * - `office_id`: Identifies the specific office position type
     * - `branch_id`: Scopes the assignment to organizational unit
     *
     * ## ActiveWindow Integration
     * This composite key enables ActiveWindow to:
     * - Manage multiple officer assignments per office across branches
     * - Prevent temporal overlaps for the same office in the same branch
     * - Support cross-branch deputy assignments
     * - Enable succession planning with future assignments
     *
     * ## Usage Example
     * ```php
     * // Multiple officers can hold the same office in different branches
     * $localOfficer = ['office_id' => 1, 'branch_id' => 1]; // Valid
     * $collegeOfficer = ['office_id' => 1, 'branch_id' => 2]; // Valid
     * 
     * // But only one active officer per office per branch
     * $duplicateLocal = ['office_id' => 1, 'branch_id' => 1]; // Temporal conflict
     * ```
     *
     * @var array Type identification fields for ActiveWindow behavior
     */
    public array $typeIdField = ['office_id', 'branch_id'];

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * ## Security Configuration
     * Mass assignment protection allows controlled data modification while
     * preventing unauthorized field access. The accessible fields include:
     *
     * ### Core Assignment Fields
     * - `member_id`: Member being assigned to office
     * - `branch_id`: Branch scope for assignment
     * - `office_id`: Office position being filled
     * - `granted_member_role_id`: Role automatically granted
     *
     * ### Temporal Management
     * - `expires_on`: Assignment expiration date
     * - `start_on`: Assignment start date
     * - `status`: Current assignment status
     *
     * ### Administrative Fields
     * - `deputy_description`: Custom deputy assignment description
     * - `email_address`: Officer contact email
     * - `revoked_reason`: Reason for assignment revocation
     * - `revoker_id`: User who revoked assignment
     *
     * ### Relationship Entities
     * - `member`, `branch`, `office`: Associated entity objects
     *
     * ## Security Considerations
     * - Primary key (`id`) is intentionally excluded from mass assignment
     * - Audit fields are managed automatically through ActiveWindowBaseEntity
     * - All accessible fields undergo validation before persistence
     * - Temporal fields are validated for consistency and business rules
     *
     * ## Usage Example
     * ```php
     * // Safe mass assignment for officer creation
     * $officer = $officersTable->patchEntity($officer, [
     *     'member_id' => $memberId,
     *     'office_id' => $officeId,
     *     'branch_id' => $branchId,
     *     'start_on' => new DateTime('+1 month'),
     *     'expires_on' => new DateTime('+13 months'),
     *     'email_address' => 'officer@branch.example.com'
     * ]);
     * ```
     *
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'member_id' => true,
        'branch_id' => true,
        'office_id' => true,
        'granted_member_role_id' => true,
        'expires_on' => true,
        'start_on' => true,
        'status' => true,
        'revoked_reason' => true,
        'revoker_id' => true,
        'member' => true,
        'branch' => true,
        'office' => true,
        'deputy_description' => true,
        'email_address' => true,
    ];

    /**
     * Warrant state calculation virtual property
     *
     * Dynamically calculates the current warrant status for this officer assignment
     * based on office requirements, active warrants, and pending warrant applications.
     *
     * ## Warrant States
     * - **"Active"**: Officer has a current, valid warrant
     * - **"Pending"**: Officer has warrant applications under review
     * - **"Missing"**: Office requires warrant but officer has none
     * - **"Not Required"**: Office does not require warrant for assignment
     * - **"Can Not Calculate"**: Unable to determine (missing office data)
     *
     * ## Business Logic
     * 1. Check if office information is available for calculation
     * 2. If current warrant exists and is not expired → "Active"
     * 3. If pending warrant applications exist → "Pending"
     * 4. If office requires warrant but none exists → "Missing"
     * 5. If office does not require warrant → "Not Required"
     *
     * ## Integration Points
     * - Office warrant requirement configuration
     * - Warrant lifecycle management system
     * - Officer assignment validation workflows
     * - Administrative reporting and compliance
     *
     * ## Usage Example
     * ```php
     * $warrantStatus = $officer->warrant_state;
     * 
     * switch ($warrantStatus) {
     *     case 'Missing':
     *         echo "Warrant required but not provided";
     *         break;
     *     case 'Pending':
     *         echo "Warrant application under review";
     *         break;
     *     case 'Active':
     *         echo "Valid warrant on file";
     *         break;
     * }
     * ```
     *
     * @param mixed $value Unused parameter (required by CakePHP getter signature)
     * @return string Warrant state description
     */
    protected function _getWarrantState($value)
    {
        if ($this->office == null) {
            return "Can Not Calculate";
        }
        if ($this->current_warrant != null && $this->current_warrant->expires_on != null) {
            return "Active";
        }
        if ($this->pending_warrants != null && count($this->pending_warrants) > 0) {
            return "Pending";
        }
        if ($this->office->requires_warrant == true) {
            return "Missing";
        }
        return "Not Required";
    }

    /**
     * Assignment editability determination virtual property
     *
     * Determines whether this officer assignment can be edited based on
     * business rules around deputy positions and contact information.
     *
     * ## Editability Rules
     * - Deputy positions are always editable (for custom descriptions)
     * - Officers with contact email addresses are editable
     * - Standard officers without email are not editable through standard interface
     *
     * ## Business Logic
     * 1. If office is a deputy position → Always editable
     * 2. If officer has email address configured → Editable
     * 3. Otherwise → Not editable through standard forms
     *
     * ## Use Cases
     * - Deputy description management
     * - Contact information updates
     * - Administrative interface control
     * - Form field accessibility determination
     *
     * ## Usage Example
     * ```php
     * if ($officer->is_editable) {
     *     echo $this->Form->button('Edit Officer');
     * } else {
     *     echo 'Assignment locked';
     * }
     * ```
     *
     * @return bool True if assignment can be edited, false otherwise
     */
    protected function _getIsEditable()
    {
        if ($this->office->is_deputy == true) {
            return true;
        }
        if ($this->email_address !== null && $this->email_address !== "") {
            return true;
        }
        return false;
    }

    /**
     * Return a formatted string of officers this position reports to, using mailto links when available.
     *
     * Uses the skip-aware effective reporting set to resolve reporting officers when an intermediate office
     * is vacant and can_skip_report is enabled, and includes direct deputies after effective reports.
     * Possible return values:
     * - "Society" when the position is top-level (no reports or deputies),
     * - "Not Filled" when reporting positions exist but no officers are available,
     * - a comma-separated list of officer names or mailto links when contact addresses are present.
     *
     * @return string Formatted reporting hierarchy list with email links
     * @see \Officers\Model\Entity\Officer::_getEffectiveReportsToCurrently() For skip-aware reporting resolution
     */
    public function _getReportsToList()
    {
        if ($this->reports_to_office_id == null && $this->deputy_to_office_id == null) {
            return "Society";
        }

        // Use effective_reports_to_currently instead of reports_to_currently
        // This automatically handles can_skip_report logic for vacant offices
        $effectiveReports = $this->effective_reports_to_currently;

        // Check if we have any effective reports or deputies
        if (empty($effectiveReports) && empty($this->deputy_to_currently)) {
            return "Not Filled";
        }

        $reportsTo = [];

        // Process effective reporting relationships (with skip logic)
        if (!empty($effectiveReports)) {
            foreach ($effectiveReports as $report) {
                if ($report->email_address !== null && $report->email_address !== "") {
                    $reportsTo[] = "<a href='mailto:{$report->email_address}'>{$report->member->sca_name}</a>";
                } else {
                    $reportsTo[] = $report->member->sca_name;
                }
            }
        }

        // Process deputy relationships (direct, no skip logic needed)
        if (!empty($this->deputy_to_currently)) {
            foreach ($this->deputy_to_currently as $report) {
                if ($report->email_address !== null && $report->email_address !== "") {
                    $reportsTo[] = "<a href='mailto:{$report->email_address}'>{$report->member->sca_name}</a>";
                } else {
                    $reportsTo[] = $report->member->sca_name;
                }
            }
        }

        // Remove duplicates
        $reportsTo = array_unique($reportsTo);
        if (count($reportsTo) > 0) {
            return implode(", ", $reportsTo);
        }
        return "Not Filled";
    }

    /**
     * Resolve the effective officers this assignment reports to using skip-aware hierarchy traversal.
     *
     * Traverses the reporting office chain honoring the office `can_skip_report` flag to find the nearest
     * current officers who should receive reports; returns an empty array for top-level positions or when
     * no effective reporting officers exist. The traversal prevents cycles.
     *
     * @return array<\Officers\Model\Entity\Officer> Array of Officer entities that effectively receive reports.
     * @see \Officers\Model\Table\OfficersTable::findEffectiveReportsTo()
     */
    protected function _getEffectiveReportsToCurrently(): array
    {
        // If we don't have a reports_to_office_id, this is top-level
        if (empty($this->reports_to_office_id)) {
            return [];
        }

        // Get the OfficersTable to call the method
        $officersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Officers.Officers');

        // Use the table method to resolve effective reporting officers
        return $officersTable->findEffectiveReportsTo($this);
    }
}

/**
 * ## Officer Entity Usage Examples
 *
 * ### Officer Assignment Creation
 * ```php
 * // Create a new officer assignment with temporal management
 * $officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');
 * $officer = $officersTable->newEntity([
 *     'member_id' => $memberId,
 *     'branch_id' => $branchId,
 *     'office_id' => $officeId,
 *     'start_on' => new DateTime('+1 month'),
 *     'expires_on' => new DateTime('+13 months'),
 *     'status' => 'upcoming',
 *     'email_address' => 'officer@branch.example.com'
 * ]);
 * $officersTable->save($officer);
 * 
 * // Create a deputy assignment with custom description
 * $deputyOfficer = $officersTable->newEntity([
 *     'member_id' => $deputyMemberId,
 *     'branch_id' => $branchId,
 *     'office_id' => $deputyOfficeId,
 *     'deputy_description' => 'Deputy for Special Events',
 *     'start_on' => new DateTime('now'),
 *     'expires_on' => new DateTime('+12 months'),
 *     'status' => 'current'
 * ]);
 * $officersTable->save($deputyOfficer);
 * ```
 *
 * ### ActiveWindow Temporal Management
 * ```php
 * // Find current officers using ActiveWindow finder
 * $currentOfficers = $officersTable->find('current')
 *     ->contain(['Members', 'Offices', 'Branches']);
 * 
 * // Find upcoming officer assignments
 * $upcomingOfficers = $officersTable->find('upcoming')
 *     ->where(['start_on <=' => new DateTime('+30 days')])
 *     ->contain(['Members', 'Offices']);
 * 
 * // Find previous officers for succession planning
 * $previousOfficers = $officersTable->find('previous')
 *     ->where(['office_id' => $officeId, 'branch_id' => $branchId])
 *     ->orderBy(['expires_on' => 'DESC'])
 *     ->limit(5);
 * 
 * // Check officer status transitions
 * foreach ($currentOfficers as $officer) {
 *     echo "Officer: {$officer->member->sca_name}";
 *     echo "Status: {$officer->status}";
 *     echo "Expires: {$officer->expires_on->format('Y-m-d')}";
 * }
 * ```
 *
 * ### Warrant Integration and Validation
 * ```php
 * // Check warrant status for officer assignments
 * $officer = $officersTable->get($officerId, [
 *     'contain' => ['Offices', 'CurrentWarrant', 'PendingWarrants']
 * ]);
 * 
 * $warrantStatus = $officer->warrant_state;
 * switch ($warrantStatus) {
 *     case 'Missing':
 *         echo "WARNING: Warrant required but not provided";
 *         // Trigger warrant application workflow
 *         break;
 *     case 'Pending':
 *         echo "Warrant application under review";
 *         break;
 *     case 'Active':
 *         echo "Valid warrant on file until: " . $officer->current_warrant->expires_on->format('Y-m-d');
 *         break;
 *     case 'Not Required':
 *         echo "No warrant required for this position";
 *         break;
 * }
 * 
 * // Validate warrant requirements before assignment
 * if ($officer->office->requires_warrant && $warrantStatus === 'Missing') {
 *     throw new Exception('Cannot assign officer without required warrant');
 * }
 * ```
 *
 * ### Reporting Hierarchy Navigation
 * ```php
 * // Display reporting relationships
 * $officer = $officersTable->get($officerId, [
 *     'contain' => [
 *         'ReportsToCurrently.Members',
 *         'DeputyToCurrently.Members',
 *         'Office'
 *     ]
 * ]);
 * 
 * $reportsTo = $officer->reports_to_list;
 * echo "This officer reports to: " . $reportsTo;
 * 
 * // Generate organizational chart data
 * $organizationalData = [];
 * if ($reportsTo !== 'Society' && $reportsTo !== 'Not Filled') {
 *     $organizationalData['reports_to'] = $reportsTo;
 * }
 * 
 * // Check if position can be edited
 * if ($officer->is_editable) {
 *     echo "Officer assignment can be modified";
 *     if ($officer->office->is_deputy) {
 *         echo "Can update deputy description: " . $officer->deputy_description;
 *     }
 * }
 * ```
 *
 * ### Assignment Management Workflows
 * ```php
 * // Succession planning - find replacement officers
 * $expiringOfficers = $officersTable->find('current')
 *     ->where(['expires_on <=' => new DateTime('+60 days')])
 *     ->contain(['Members', 'Offices', 'Branches'])
 *     ->orderBy(['expires_on' => 'ASC']);
 * 
 * foreach ($expiringOfficers as $officer) {
 *     echo "Officer {$officer->member->sca_name} expires on {$officer->expires_on->format('Y-m-d')}";
 *     
 *     // Check for upcoming replacement
 *     $replacement = $officersTable->find('upcoming')
 *         ->where([
 *             'office_id' => $officer->office_id,
 *             'branch_id' => $officer->branch_id
 *         ])
 *         ->first();
 *     
 *     if ($replacement) {
 *         echo "Replacement: {$replacement->member->sca_name} starts {$replacement->start_on->format('Y-m-d')}";
 *     } else {
 *         echo "WARNING: No replacement scheduled!";
 *     }
 * }
 * ```
 *
 * ### Cross-Branch Deputy Management
 * ```php
 * // Create cross-branch deputy assignment
 * $crossBranchDeputy = $officersTable->newEntity([
 *     'member_id' => $memberId,
 *     'branch_id' => $currentBranchId,
 *     'office_id' => $deputyOfficeId,
 *     'deputy_to_branch_id' => $parentBranchId,
 *     'deputy_to_office_id' => $parentOfficeId,
 *     'deputy_description' => 'Cross-branch support deputy',
 *     'start_on' => new DateTime('now'),
 *     'expires_on' => new DateTime('+6 months')
 * ]);
 * $officersTable->save($crossBranchDeputy);
 * 
 * // Find all deputies for a specific office across branches
 * $allDeputies = $officersTable->find('current')
 *     ->where(['deputy_to_office_id' => $parentOfficeId])
 *     ->contain(['Members', 'Branches', 'Offices']);
 * 
 * foreach ($allDeputies as $deputy) {
 *     echo "Deputy: {$deputy->member->sca_name}";
 *     echo "From Branch: {$deputy->branch->name}";
 *     echo "Description: {$deputy->deputy_description}";
 * }
 * ```
 *
 * ### Role Assignment Integration
 * ```php
 * // Officer assignment with automatic role granting
 * $officer = $officersTable->get($officerId, [
 *     'contain' => ['GrantedMemberRole', 'Office.GrantsRole']
 * ]);
 * 
 * if ($officer->granted_member_role_id) {
 *     echo "Officer has been granted role: " . $officer->granted_member_role->name;
 * }
 * 
 * if ($officer->office->grants_role_id) {
 *     echo "This office automatically grants: " . $officer->office->grants_role->name;
 * }
 * 
 * // Handle role assignment during officer appointment
 * if ($officer->office->grants_role_id && !$officer->granted_member_role_id) {
 *     // Trigger automatic role assignment workflow
 *     $officer->granted_member_role_id = $officer->office->grants_role_id;
 *     $officersTable->save($officer);
 * }
 * ```
 *
 * ### Administrative Reporting and Analytics
 * ```php
 * // Generate officer assignment statistics
 * $assignmentStats = $officersTable->find()
 *     ->select([
 *         'status',
 *         'count' => $officersTable->find()->func()->count('*')
 *     ])
 *     ->group(['status'])
 *     ->toArray();
 * 
 * foreach ($assignmentStats as $stat) {
 *     echo "Status {$stat->status}: {$stat->count} officers";
 * }
 * 
 * // Find unfilled required offices
 * $unfilledOffices = $officersTable->Offices->find()
 *     ->where(['required_office' => true])
 *     ->leftJoinWith('CurrentOfficers')
 *     ->where(['CurrentOfficers.id IS' => null])
 *     ->contain(['Departments']);
 * 
 * foreach ($unfilledOffices as $office) {
 *     echo "UNFILLED REQUIRED OFFICE: {$office->name} in {$office->department->name}";
 * }
 * 
 * // Email contact list generation
 * $officerContacts = $officersTable->find('current')
 *     ->where(['email_address IS NOT' => null])
 *     ->where(['email_address !=' => ''])
 *     ->contain(['Members', 'Offices', 'Branches'])
 *     ->orderBy(['Branches.name' => 'ASC', 'Offices.name' => 'ASC']);
 * 
 * foreach ($officerContacts as $officer) {
 *     echo "{$officer->member->sca_name} ({$officer->office->name}) - {$officer->email_address}";
 * }
 * ```
 */