<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Office Entity - Hierarchical office structure and warrant integration
 *
 * The Office entity represents individual officer positions within the organizational
 * hierarchy of the Officers plugin. Each office defines a specific role within a
 * department, with complex hierarchical relationships, warrant requirements, and
 * role assignments that integrate with the broader KMP authorization system.
 *
 * ## Key Features
 * - **Hierarchical Organization**: Deputy and reporting relationships between offices
 * - **Warrant Integration**: Configurable warrant requirements for role assignment
 * - **Temporal Management**: Term length configuration for automatic succession
 * - **Role Assignment**: Automatic role grants upon officer assignment
 * - **Branch Applicability**: Configurable branch type restrictions
 * - **Deputy Relationships**: Support for deputy officer positions
 * - **Administrative Control**: Required office flags and reporting controls
 *
 * ## Database Schema
 * - `id`: Primary key for office identification
 * - `name`: Human-readable office title (unique, required)
 * - `department_id`: Foreign key to parent department
 * - `requires_warrant`: Boolean flag for warrant requirement validation
 * - `required_office`: Boolean flag indicating organizational requirement
 * - `only_one_per_branch`: Boolean constraint for branch-level uniqueness
 * - `can_skip_report`: Boolean permission for reporting exemption
 * - `deputy_to_id`: Foreign key for deputy office relationships
 * - `reports_to_id`: Foreign key for reporting hierarchy
 * - `grants_role_id`: Foreign key to role automatically granted upon assignment
 * - `term_length`: Duration in months for officer terms
 * - `applicable_branch_types`: Serialized array of applicable branch types
 * - `default_contact_address`: Default email contact for the office
 * - Standard audit fields: `created`, `modified`, `created_by`, `modified_by`
 *
 * ## Relationships
 * - **belongsTo Department**: Organizational categorization within departments
 * - **belongsTo GrantsRole**: Automatic role assignment integration
 * - **belongsTo DeputyTo**: Deputy office hierarchy relationships
 * - **belongsTo ReportsTo**: Organizational reporting structure
 * - **hasMany Officers**: Current and historical officer assignments
 * - **hasMany Deputies**: Offices that report as deputies to this office
 * - **hasMany DirectReports**: Offices that report directly to this office
 *
 * ## Hierarchy Management
 * The Office entity implements complex hierarchical relationships through:
 * - Deputy relationships with automatic reporting assignment
 * - Flexible reporting structures independent of deputy relationships
 * - Branch type applicability constraints
 * - Term length management for succession planning
 *
 * ## Warrant Integration
 * Office warrant requirements integrate with the KMP warrant system:
 * - Boolean warrant requirement flags
 * - Automatic role assignment upon officer appointment
 * - Integration with warrant validation workflows
 *
 * ## Usage Patterns
 * ```php
 * // Create a new office with warrant requirements
 * $office = $officesTable->newEntity([
 *     'name' => 'Branch Officer',
 *     'department_id' => $departmentId,
 *     'requires_warrant' => true,
 *     'grants_role_id' => $roleId,
 *     'term_length' => 12,
 *     'branch_types' => ['Local', 'College']
 * ]);
 * 
 * // Set up deputy relationship
 * $deputyOffice = $officesTable->newEntity([
 *     'name' => 'Deputy Branch Officer',
 *     'deputy_to_id' => $office->id
 * ]);
 * 
 * // Check office hierarchy
 * if ($office->is_deputy) {
 *     $parentOffice = $office->deputy_to;
 * }
 * ```
 *
 * @property int $id Primary key for office identification
 * @property string $name Human-readable office title (unique, required)
 * @property int|null $department_id Foreign key to parent department
 * @property bool $requires_warrant Boolean flag for warrant requirement validation
 * @property bool $required_office Boolean flag indicating organizational requirement
 * @property bool $only_one_per_branch Boolean constraint for branch-level uniqueness
 * @property bool $can_skip_report Boolean permission for reporting exemption
 * @property int|null $deputy_to_id Foreign key for deputy office relationships
 * @property int|null $reports_to_id Foreign key for reporting hierarchy
 * @property int|null $grants_role_id Foreign key to role automatically granted upon assignment
 * @property int $term_length Duration in months for officer terms
 * @property string|null $applicable_branch_types Serialized array of applicable branch types
 * @property string $default_contact_address Default email contact for the office
 * @property \Cake\I18n\Date|null $deleted Soft deletion timestamp for archival management
 * @property \Cake\I18n\DateTime $created Record creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by User ID who created this record
 * @property int|null $modified_by User ID who last modified this record
 *
 * @property bool $is_deputy Virtual property indicating deputy office status
 * @property array $branch_types Virtual property for branch type array access
 *
 * @property \Officers\Model\Entity\Department $department Parent department organization
 * @property \App\Model\Entity\Role $grants_role Role automatically granted upon assignment
 * @property \Officers\Model\Entity\Office $deputy_to Parent office for deputy relationships
 * @property \Officers\Model\Entity\Office $reports_to Parent office for reporting hierarchy
 * @property \Officers\Model\Entity\Office[] $deputies Child deputy offices
 * @property \Officers\Model\Entity\Office[] $direct_reports Child offices in reporting hierarchy
 * @property \Officers\Model\Entity\Officer[] $officers All officer assignments (current and historical)
 * @property \Officers\Model\Entity\Officer[] $current_officers Currently active officer assignments
 * @property \Officers\Model\Entity\Officer[] $upcoming_officers Future officer assignments
 * @property \Officers\Model\Entity\Officer[] $previous_officers Historical officer assignments
 *
 * @see \Officers\Model\Table\OfficesTable For office data management operations
 * @see \Officers\Model\Entity\Department For departmental organization
 * @see \Officers\Model\Entity\Officer For officer assignment management
 * @see \App\Model\Entity\BaseEntity For audit trail and security features
 */
class Office extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * ## Security Configuration
     * Mass assignment protection allows controlled data modification while
     * preventing unauthorized field access. The accessible fields include:
     *
     * - `name`: Office title for identification
     * - `department_id`: Department organizational assignment
     * - `requires_warrant`: Warrant requirement configuration
     * - `can_skip_report`: Reporting exemption permission
     * - `required_office`: Organizational requirement flag
     * - `only_one_per_branch`: Branch uniqueness constraint
     * - `deputy_to_id`: Deputy relationship hierarchy
     * - `reports_to_id`: Reporting relationship hierarchy
     * - `grants_role_id`: Automatic role assignment configuration
     * - `term_length`: Officer term duration in months
     * - `branch_types`: Branch type applicability array
     * - `default_contact_address`: Default office contact email
     * - `deleted`: Soft deletion timestamp
     * - Relationship entities: `department`, `officers`
     *
     * ## Security Considerations
     * - Primary key (`id`) is intentionally excluded from mass assignment
     * - Audit fields are managed automatically through BaseEntity
     * - All accessible fields undergo validation before persistence
     * - Deputy and reporting relationships are mutually managed
     *
     * ## Usage Example
     * ```php
     * // Safe mass assignment with hierarchy
     * $office = $officesTable->patchEntity($office, [
     *     'name' => 'Updated Office Title',
     *     'requires_warrant' => true,
     *     'deputy_to_id' => $parentOfficeId,
     *     'term_length' => 24
     * ]);
     * ```
     *
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'name' => true,
        'department_id' => true,
        'requires_warrant' => true,
        'can_skip_report' => true,
        'required_office' => true,
        'only_one_per_branch' => true,
        'deputy_to_id' => true,
        'reports_to_id' => true,
        'grants_role_id' => true,
        'term_length' => true,
        'deleted' => true,
        'department' => true,
        'officers' => true,
        'branch_types' => true,
        'default_contact_address' => true,
    ];

    /**
     * Deputy-to relationship setter with automatic reporting assignment
     *
     * When a deputy relationship is established, the reporting relationship
     * is automatically set to maintain hierarchical consistency. This ensures
     * that deputy offices report to their parent office.
     *
     * ## Business Logic
     * - Setting `deputy_to_id` automatically sets `reports_to_id` to the same value
     * - Maintains hierarchical consistency between deputy and reporting relationships
     * - Supports organizational structure with clear chain of command
     *
     * ## Usage Example
     * ```php
     * // Setting deputy relationship automatically sets reporting
     * $deputyOffice->deputy_to_id = $parentOfficeId;
     * // $deputyOffice->reports_to_id is now automatically set to $parentOfficeId
     * ```
     *
     * @param int|null $deputy_to_id The office ID this office is deputy to
     * @return int|null The deputy_to_id value for storage
     */
    protected function _setDeputyToId($deputy_to_id)
    {
        $this->reports_to_id = $deputy_to_id;
        return $deputy_to_id;
    }

    /**
     * Reports-to relationship setter with deputy conflict resolution
     *
     * When a direct reporting relationship is established that differs from
     * a deputy relationship, the deputy relationship is cleared to prevent
     * conflicting hierarchical assignments.
     *
     * ## Business Logic
     * - Setting `reports_to_id` clears `deputy_to_id` to prevent conflicts
     * - Allows flexible reporting structures independent of deputy relationships
     * - Maintains data integrity in hierarchical relationships
     *
     * ## Usage Example
     * ```php
     * // Setting reporting relationship clears deputy relationship
     * $office->reports_to_id = $supervisorOfficeId;
     * // $office->deputy_to_id is now automatically cleared (null)
     * ```
     *
     * @param int|null $reports_to_id The office ID this office reports to
     * @return int|null The reports_to_id value for storage
     */
    protected function _setReportsToId($reports_to_id)
    {
        $this->deputy_to_id = null;
        return $reports_to_id;
    }

    /**
     * Virtual property to determine deputy office status
     *
     * Provides a convenient boolean check to determine if this office
     * is configured as a deputy to another office.
     *
     * ## Usage Example
     * ```php
     * if ($office->is_deputy) {
     *     echo "This is a deputy position to: " . $office->deputy_to->name;
     * }
     * ```
     *
     * @return bool True if this office is a deputy to another office
     */
    protected function _getIsDeputy(): bool
    {
        return $this->deputy_to_id !== null;
    }

    /**
     * Branch types getter with serialization handling
     *
     * Converts the serialized branch types string into a usable array
     * for programmatic access and form handling.
     *
     * ## Data Format
     * - Database storage: Comma-separated quoted strings ("Local","College")
     * - Application access: Clean string array ["Local", "College"]
     *
     * ## Usage Example
     * ```php
     * $branchTypes = $office->branch_types;
     * if (in_array('Local', $branchTypes)) {
     *     echo "This office applies to Local branches";
     * }
     * ```
     *
     * @return array Array of branch type strings
     */
    protected function _getBranchTypes(): array
    {
        if (empty($this->applicable_branch_types)) {
            return [];
        }
        $returnVals = explode(",", $this->applicable_branch_types);
        //remove quotes around each branch type
        $returnVals = array_map(function ($branchType) {
            return ltrim(rtrim($branchType, "\""), "\"");
        }, $returnVals);
        return $returnVals;
    }

    /**
     * Virtual property for department name (for grid display)
     *
     * @return string|null The department name or null if no department
     */
    protected function _getDepartmentName(): ?string
    {
        return $this->department->name ?? null;
    }

    /**
     * Virtual property for reports-to office name (for grid display)
     *
     * @return string|null The reports-to office name or null if none
     */
    protected function _getReportsToName(): ?string
    {
        return $this->reports_to->name ?? null;
    }

    /**
     * Virtual property for deputy-to office name (for grid display)
     *
     * @return string|null The deputy-to office name or null if none
     */
    protected function _getDeputyToName(): ?string
    {
        return $this->deputy_to->name ?? null;
    }

    /**
     * Virtual property for grants role name (for grid display)
     *
     * @return string|null The role name or null if none
     */
    protected function _getGrantsRoleName(): ?string
    {
        return $this->grants_role->name ?? null;
    }

    /**
     * Branch types setter with serialization handling
     *
     * Converts branch type arrays or strings into the serialized format
     * required for database storage, with proper quoting and formatting.
     *
     * ## Input Handling
     * - Array input: ["Local", "College"] → "\"Local\",\"College\""
     * - String input: "Local" → "\"Local\""
     * - Empty input: "" or [] → "" (empty string)
     *
     * ## Usage Example
     * ```php
     * // Array assignment
     * $office->branch_types = ['Local', 'College', 'Household'];
     * 
     * // String assignment
     * $office->branch_types = 'Local';
     * 
     * // Clear assignment
     * $office->branch_types = [];
     * ```
     *
     * @param array|string $branchTypes Branch types to serialize and store
     * @return void
     */
    protected function _setBranchTypes($branchTypes): void
    {
        //if branch types is not an array then make it an array
        if (!is_array($branchTypes)) {
            //if branch types is an empty string then make it an empty array
            if (empty($branchTypes)) {
                $branchTypes = [];
            } else {
                $branchTypes = [$branchTypes];
            }
        }
        //add quotes around each branch type
        $branchTypes = array_map(function ($branchType) {
            return "\"$branchType\"";
        }, $branchTypes);
        $this->applicable_branch_types = implode(",", $branchTypes);
    }
}

/**
 * ## Office Entity Usage Examples
 *
 * ### Office Creation and Configuration
 * ```php
 * // Create a standard office with warrant requirements
 * $officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');
 * $office = $officesTable->newEntity([
 *     'name' => 'Branch Officer',
 *     'department_id' => $operationsDepartmentId,
 *     'requires_warrant' => true,
 *     'grants_role_id' => $branchOfficerRoleId,
 *     'term_length' => 12, // 12 months
 *     'branch_types' => ['Local', 'College'],
 *     'required_office' => true,
 *     'only_one_per_branch' => true
 * ]);
 * $officesTable->save($office);
 * 
 * // Create a deputy office with automatic reporting
 * $deputyOffice = $officesTable->newEntity([
 *     'name' => 'Deputy Branch Officer',
 *     'department_id' => $operationsDepartmentId,
 *     'deputy_to_id' => $office->id, // Automatically sets reports_to_id
 *     'requires_warrant' => false,
 *     'term_length' => 12
 * ]);
 * $officesTable->save($deputyOffice);
 * ```
 *
 * ### Hierarchy Management
 * ```php
 * // Check office hierarchy relationships
 * $office = $officesTable->get($officeId, [
 *     'contain' => ['DeputyTo', 'ReportsTo', 'Deputies', 'DirectReports']
 * ]);
 * 
 * if ($office->is_deputy) {
 *     echo "Deputy to: " . $office->deputy_to->name;
 *     echo "Reports to: " . $office->reports_to->name; // Same as deputy_to
 * }
 * 
 * // List all deputy offices
 * foreach ($office->deputies as $deputy) {
 *     echo "Deputy: " . $deputy->name;
 * }
 * 
 * // List all direct reports
 * foreach ($office->direct_reports as $report) {
 *     echo "Direct Report: " . $report->name;
 * }
 * ```
 *
 * ### Branch Type Management
 * ```php
 * // Set branch types for office applicability
 * $office->branch_types = ['Local', 'College', 'Household'];
 * $officesTable->save($office);
 * 
 * // Check if office applies to specific branch type
 * $branchTypes = $office->branch_types;
 * if (in_array('Local', $branchTypes)) {
 *     echo "This office can be assigned in Local branches";
 * }
 * 
 * // Clear branch type restrictions (applies to all)
 * $office->branch_types = [];
 * $officesTable->save($office);
 * ```
 *
 * ### Officer Assignment Integration
 * ```php
 * // Find office with current officer assignments
 * $office = $officesTable->get($officeId, [
 *     'contain' => [
 *         'CurrentOfficers.Members',
 *         'UpcomingOfficers.Members',
 *         'PreviousOfficers.Members'
 *     ]
 * ]);
 * 
 * // Check current assignments
 * foreach ($office->current_officers as $officer) {
 *     echo "Current Officer: " . $officer->member->name;
 *     echo "Term expires: " . $officer->expires_on->format('Y-m-d');
 * }
 * 
 * // Check upcoming assignments
 * foreach ($office->upcoming_officers as $officer) {
 *     echo "Upcoming Officer: " . $officer->member->name;
 *     echo "Starts: " . $officer->start_on->format('Y-m-d');
 * }
 * ```
 *
 * ### Warrant and Role Integration
 * ```php
 * // Office with warrant requirements and role grants
 * $office = $officesTable->get($officeId, [
 *     'contain' => ['GrantsRole']
 * ]);
 * 
 * if ($office->requires_warrant) {
 *     echo "This office requires a warrant for assignment";
 * }
 * 
 * if ($office->grants_role) {
 *     echo "Officers assigned to this position receive role: " . $office->grants_role->name;
 * }
 * 
 * // Check term length for succession planning
 * $monthsUntilSuccession = $office->term_length;
 * echo "Officer terms last {$monthsUntilSuccession} months";
 * ```
 *
 * ### Administrative Configuration
 * ```php
 * // Configure administrative settings
 * $office = $officesTable->patchEntity($office, [
 *     'required_office' => true,        // Must be filled
 *     'can_skip_report' => false,       // Must submit reports
 *     'only_one_per_branch' => true,    // Unique per branch
 *     'default_contact_address' => 'officer@branch.example.com'
 * ]);
 * $officesTable->save($office);
 * 
 * // Check administrative flags
 * if ($office->required_office) {
 *     echo "This office must be filled in all applicable branches";
 * }
 * 
 * if (!$office->can_skip_report) {
 *     echo "Officers in this position must submit regular reports";
 * }
 * 
 * if ($office->only_one_per_branch) {
 *     echo "Only one person can hold this office per branch";
 * }
 * ```
 *
 * ### Reporting and Analytics
 * ```php
 * // Office utilization report
 * $offices = $officesTable->find()
 *     ->contain([
 *         'Departments',
 *         'CurrentOfficers.Members',
 *         'UpcomingOfficers.Members'
 *     ])
 *     ->where(['Offices.deleted IS' => null]);
 * 
 * foreach ($offices as $office) {
 *     $currentCount = count($office->current_officers);
 *     $upcomingCount = count($office->upcoming_officers);
 *     
 *     echo "Office: {$office->name}";
 *     echo "Department: {$office->department->name}";
 *     echo "Current Officers: {$currentCount}";
 *     echo "Upcoming Officers: {$upcomingCount}";
 *     
 *     if ($office->required_office && $currentCount === 0) {
 *         echo "WARNING: Required office is unfilled!";
 *     }
 * }
 * ```
 *
 * ### Hierarchical Queries
 * ```php
 * // Find all offices in a department hierarchy
 * $departmentOffices = $officesTable->find()
 *     ->where(['department_id' => $departmentId])
 *     ->contain(['DeputyTo', 'ReportsTo'])
 *     ->orderBy(['deputy_to_id' => 'ASC', 'name' => 'ASC']);
 * 
 * // Group by hierarchy level
 * $hierarchy = [
 *     'primary' => [],
 *     'deputy' => []
 * ];
 * 
 * foreach ($departmentOffices as $office) {
 *     if ($office->is_deputy) {
 *         $hierarchy['deputy'][] = $office;
 *     } else {
 *         $hierarchy['primary'][] = $office;
 *     }
 * }
 * 
 * // Find offices requiring specific branch types
 * $localOffices = $officesTable->find()
 *     ->where(['applicable_branch_types LIKE' => '%"Local"%'])
 *     ->where(['deleted IS' => null]);
 * ```
 */
