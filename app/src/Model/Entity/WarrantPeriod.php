<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * WarrantPeriod Entity - Warrant Period Templates and Temporal Boundary Management
 *
 * The WarrantPeriod entity manages warrant period templates that define standard
 * temporal boundaries for warrant activation and expiration. These periods serve
 * as organizational templates for consistent warrant duration management across
 * the KMP system and integration with warrant roster approval workflows.
 *
 * ## Core Responsibilities
 *
 * ### Warrant Period Management
 * - **Period Templates**: Standardized warrant duration templates for organizational consistency
 * - **Temporal Boundaries**: Start and end date management for warrant validity periods
 * - **Organizational Constraints**: Period definitions that align with organizational requirements
 * - **Administrative Control**: Centralized management of standard warrant durations
 *
 * ### Integration with Warrant System
 * - **Warrant Roster Integration**: Period templates used in warrant roster creation
 * - **Duration Standardization**: Consistent warrant periods across different warrant types
 * - **Administrative Templates**: Pre-defined periods for common warrant scenarios
 * - **Batch Processing**: Support for applying standard periods to multiple warrants
 *
 * ### Temporal Validation Support
 * - **Date Range Validation**: Ensures valid start and end date relationships
 * - **Period Calculation**: Automatic duration calculation and validation
 * - **Future Planning**: Support for planning future warrant periods
 * - **Historical Tracking**: Maintenance of period templates for audit and analysis
 *
 * ## Warrant Period Architecture
 *
 * ### Period Template System
 * WarrantPeriod entities serve as reusable templates:
 * ```php
 * // Standard 1-year warrant period
 * $yearlyPeriod = new WarrantPeriod([
 *     'start_date' => DateTime::create(2025, 1, 1),
 *     'end_date' => DateTime::create(2025, 12, 31),
 *     'created_by' => $adminId
 * ]);
 * 
 * // Quarterly warrant period
 * $quarterlyPeriod = new WarrantPeriod([
 *     'start_date' => DateTime::create(2025, 1, 1),
 *     'end_date' => DateTime::create(2025, 3, 31),
 *     'created_by' => $adminId
 * ]);
 * ```
 *
 * ### Integration with Warrant Rosters
 * Period templates are used in warrant roster creation:
 * ```php
 * // Create warrant roster with period template
 * $warrantRoster = $warrantRostersTable->newEntity([
 *     'name' => 'Q1 2025 Officer Warrants',
 *     'period_start' => $quarterlyPeriod->start_date,
 *     'period_end' => $quarterlyPeriod->end_date,
 *     'created_by' => $adminId
 * ]);
 * 
 * // Apply period to all warrants in roster
 * foreach ($warrants as $warrant) {
 *     $warrant->start_on = $quarterlyPeriod->start_date;
 *     $warrant->expires_on = $quarterlyPeriod->end_date;
 * }
 * ```
 *
 * ## Administrative Usage Patterns
 *
 * ### Organizational Period Standards
 * Define standard periods for organizational consistency:
 * ```php
 * // Officer warrant periods (typically 6 months)
 * $officerPeriod = new WarrantPeriod([
 *     'start_date' => DateTime::parse('first day of January'),
 *     'end_date' => DateTime::parse('last day of June'),
 *     'created_by' => $adminId
 * ]);
 * 
 * // Activity authorization periods (typically 1 year)
 * $activityPeriod = new WarrantPeriod([
 *     'start_date' => DateTime::parse('first day of January'),
 *     'end_date' => DateTime::parse('last day of December'),
 *     'created_by' => $adminId
 * ]);
 * 
 * // Emergency warrant periods (30 days)
 * $emergencyPeriod = new WarrantPeriod([
 *     'start_date' => DateTime::now(),
 *     'end_date' => DateTime::now()->addDays(30),
 *     'created_by' => $adminId
 * ]);
 * ```
 *
 * ### Period Validation and Business Rules
 * ```php
 * // Validate period dates
 * public function validate(): bool
 * {
 *     // Start date must be before end date
 *     if ($this->start_date >= $this->end_date) {
 *         return false;
 *     }
 *     
 *     // Period must be at least 1 day
 *     if ($this->start_date->diffInDays($this->end_date) < 1) {
 *         return false;
 *     }
 *     
 *     // End date cannot be in the past (for new periods)
 *     if ($this->end_date < DateTime::now() && $this->isNew()) {
 *         return false;
 *     }
 *     
 *     return true;
 * }
 * ```
 *
 * ## Period Name Generation
 *
 * ### Automatic Period Display
 * The entity provides automatic period name generation:
 * ```php
 * protected function _getName(): string
 * {
 *     return $this->start_date->toDateString() . ' ~ ' . $this->end_date->toDateString();
 * }
 * 
 * // Usage examples:
 * echo $period->name;  // "2025-01-01 ~ 2025-12-31"
 * 
 * // Custom formatting
 * $period->start_date->format('M j, Y') . ' to ' . $period->end_date->format('M j, Y');
 * // "Jan 1, 2025 to Dec 31, 2025"
 * ```
 *
 * ### Period Identification Patterns
 * ```php
 * // Quarter identification
 * public function getQuarter(): string
 * {
 *     $quarter = ceil($this->start_date->month / 3);
 *     return 'Q' . $quarter . ' ' . $this->start_date->year;
 * }
 * 
 * // Duration calculation
 * public function getDurationDays(): int
 * {
 *     return $this->start_date->diffInDays($this->end_date);
 * }
 * 
 * // Period type identification
 * public function getPeriodType(): string
 * {
 *     $days = $this->getDurationDays();
 *     
 *     if ($days <= 31) return 'Monthly';
 *     if ($days <= 93) return 'Quarterly';  // ~3 months
 *     if ($days <= 186) return 'Semi-Annual';  // ~6 months
 *     if ($days <= 366) return 'Annual';
 *     
 *     return 'Multi-Year';
 * }
 * ```
 *
 * ## Integration Examples
 *
 * ### Warrant Roster Period Application
 * ```php
 * // Create warrant roster with period template
 * $warrantRoster = $warrantRostersTable->newEntity([
 *     'name' => 'Annual Officer Warrants 2025',
 *     'description' => 'Annual warrant renewal for all officers',
 *     'warrant_period_id' => $annualPeriod->id,  // Link to period template
 *     'created_by' => $adminId
 * ]);
 * 
 * // Apply period to warrants in roster
 * $warrants = $warrantsTable->find()
 *     ->where(['warrant_roster_id' => $warrantRoster->id])
 *     ->toArray();
 * 
 * foreach ($warrants as $warrant) {
 *     $warrant->start_on = $annualPeriod->start_date;
 *     $warrant->expires_on = $annualPeriod->end_date;
 *     $warrantsTable->save($warrant);
 * }
 * ```
 *
 * ### Administrative Period Management
 * ```php
 * // Create standard organizational periods
 * $periods = [
 *     // Officer terms (6 months)
 *     [
 *         'start_date' => '2025-01-01',
 *         'end_date' => '2025-06-30',
 *         'type' => 'Officer Term 1'
 *     ],
 *     [
 *         'start_date' => '2025-07-01',
 *         'end_date' => '2025-12-31',
 *         'type' => 'Officer Term 2'
 *     ],
 *     // Activity authorizations (1 year)
 *     [
 *         'start_date' => '2025-01-01',
 *         'end_date' => '2025-12-31',
 *         'type' => 'Activity Authorization'
 *     ]
 * ];
 * 
 * foreach ($periods as $periodData) {
 *     $period = $warrantPeriodsTable->newEntity($periodData);
 *     $warrantPeriodsTable->save($period);
 * }
 * ```
 *
 * ### Period Selection Interface
 * ```php
 * // Provide period options for warrant creation
 * $availablePeriods = $warrantPeriodsTable->find()
 *     ->where(['end_date >' => DateTime::now()])  // Future periods only
 *     ->order(['start_date' => 'ASC'])
 *     ->toArray();
 * 
 * $periodOptions = [];
 * foreach ($availablePeriods as $period) {
 *     $periodOptions[$period->id] = $period->name . ' (' . $period->getPeriodType() . ')';
 * }
 * 
 * // Example output:
 * // [
 * //   1 => "2025-01-01 ~ 2025-06-30 (Semi-Annual)",
 * //   2 => "2025-07-01 ~ 2025-12-31 (Semi-Annual)",
 * //   3 => "2025-01-01 ~ 2025-12-31 (Annual)"
 * // ]
 * ```
 *
 * ## Usage Examples
 *
 * ### Basic Period Operations
 * ```php
 * // Create new warrant period
 * $period = $warrantPeriodsTable->newEntity([
 *     'start_date' => DateTime::parse('2025-01-01'),
 *     'end_date' => DateTime::parse('2025-12-31'),
 *     'created_by' => $adminId
 * ]);
 * 
 * $warrantPeriodsTable->save($period);
 * 
 * // Use period in warrant creation
 * $warrant = $warrantsTable->newEntity([
 *     'member_id' => $memberId,
 *     'warrant_roster_id' => $rosterId,
 *     'entity_type' => 'Officers',
 *     'entity_id' => $officeId,
 *     'start_on' => $period->start_date,
 *     'expires_on' => $period->end_date,
 *     'status' => Warrant::PENDING_STATUS
 * ]);
 * ```
 *
 * ### Period Analysis and Reporting
 * ```php
 * // Find periods by duration
 * $annualPeriods = $warrantPeriodsTable->find()
 *     ->where(function ($exp) {
 *         return $exp->between(
 *             'DATEDIFF(end_date, start_date)',
 *             350,  // ~11.5 months
 *             375   // ~12.3 months
 *         );
 *     })
 *     ->toArray();
 * 
 * // Find overlapping periods
 * $overlappingPeriods = $warrantPeriodsTable->find()
 *     ->where([
 *         'OR' => [
 *             'AND' => [
 *                 'start_date <=' => $newPeriod->start_date,
 *                 'end_date >=' => $newPeriod->start_date
 *             ],
 *             'AND' => [
 *                 'start_date <=' => $newPeriod->end_date,
 *                 'end_date >=' => $newPeriod->end_date
 *             ]
 *         ]
 *     ])
 *     ->toArray();
 * ```
 *
 * @see \App\Model\Table\WarrantPeriodsTable For period data management and validation
 * @see \App\Model\Entity\WarrantRoster For warrant roster integration
 * @see \App\Model\Entity\Warrant For warrant entity and temporal validation
 * @see \App\Model\Entity\BaseEntity For base entity functionality
 *
 * @property int $id Unique warrant period identifier
 * @property \Cake\I18n\DateTime $start_date Period start date for warrant activation
 * @property \Cake\I18n\DateTime $end_date Period end date for warrant expiration
 * @property \Cake\I18n\DateTime $created Period creation timestamp
 * @property int|null $created_by Member who created the period template
 * @property string $name Virtual property: formatted period display name
 */
class WarrantPeriod extends BaseEntity
{
    /**
     * Mass assignment configuration for warrant period management
     *
     * Configures which fields can be safely mass assigned during warrant period
     * creation and updates. This configuration balances usability with security
     * for warrant period template management.
     *
     * ### Accessible Fields
     * - **start_date**: Period start date for warrant activation
     * - **end_date**: Period end date for warrant expiration  
     * - **created**: Creation timestamp (managed by Timestamp behavior)
     * - **created_by**: Creator tracking (managed by Footprint behavior)
     *
     * ### Security Considerations
     * - **ID Protection**: Primary key cannot be mass assigned for security
     * - **Audit Trail**: Creation fields tracked by behaviors
     * - **Date Validation**: Start and end dates validated by table rules
     * - **Administrative Control**: Period creation requires proper permissions
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'start_date' => true,    // Warrant period start date
        'end_date' => true,      // Warrant period end date
        'created' => true,       // Creation timestamp
        'created_by' => true,    // Creator identification
    ];

    /**
     * Get formatted period name - Automatic period display generation
     *
     * Generates a user-friendly display name for the warrant period using
     * standardized date formatting. This virtual property provides consistent
     * period identification across the warrant management system.
     *
     * ### Display Format
     * Uses ISO date format with range separator:
     * - **Format**: "YYYY-MM-DD ~ YYYY-MM-DD"
     * - **Example**: "2025-01-01 ~ 2025-12-31"
     * - **Consistency**: Standardized format across all period displays
     * - **Sortability**: Format supports natural alphabetical sorting
     *
     * ### Usage Patterns
     * ```php
     * // Automatic display name
     * echo $period->name;  // "2025-01-01 ~ 2025-12-31"
     * 
     * // Use in select options
     * $periodOptions[$period->id] = $period->name;
     * 
     * // Custom formatting for user interface
     * $displayName = $period->start_date->format('M j, Y') . 
     *                ' to ' . 
     *                $period->end_date->format('M j, Y');
     * // "Jan 1, 2025 to Dec 31, 2025"
     * ```
     *
     * ### Integration Examples
     * ```php
     * // Period selection dropdown
     * $periods = $warrantPeriodsTable->find()->toArray();
     * $options = [];
     * foreach ($periods as $period) {
     *     $options[$period->id] = $period->name . ' (' . $period->getDurationDays() . ' days)';
     * }
     * 
     * // Warrant roster naming
     * $rosterName = 'Officer Warrants: ' . $period->name;
     * // "Officer Warrants: 2025-01-01 ~ 2025-06-30"
     * ```
     *
     * @return string Formatted period name with date range
     */
    protected function _getName(): string
    {
        return $this->start_date->toDateString() . ' ~ ' . $this->end_date->toDateString();
    }
}
