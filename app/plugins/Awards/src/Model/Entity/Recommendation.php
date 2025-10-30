<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;

/**
 * Recommendation Entity - Award Recommendation State Machine and Workflow Management
 * 
 * Represents award recommendations within the KMP Awards system, implementing a
 * sophisticated state machine for managing the complete recommendation lifecycle
 * from initial submission through ceremonial presentation. The Recommendation
 * entity serves as the core workflow engine for award processing, coordinating
 * member recognition, administrative approval, and ceremonial scheduling.
 * 
 * The Recommendation entity implements a dual status/state tracking system where:
 * - **Status** represents the high-level category (In Progress, Scheduling, To Give, Closed)
 * - **State** represents the specific workflow position within the status category
 * 
 * This sophisticated state machine allows for flexible workflow management while
 * maintaining clear categorization for reporting, UI organization, and administrative
 * oversight. State transitions are controlled through configuration-driven rules
 * that automatically manage field visibility, validation requirements, and data updates.
 * 
 * ## Core Workflow States:
 * - **In Progress**: Submitted, In Consideration, Awaiting Feedback, Deferred, King/Queen Approved
 * - **Scheduling**: Need to Schedule for ceremony coordination
 * - **To Give**: Scheduled, Announced Not Given for ceremony preparation
 * - **Closed**: Given, No Action for completed recommendations
 * 
 * ## Integration Points:
 * - **Members**: Recommendations recognize specific members for achievements
 * - **Awards**: Recommendations target specific awards within the hierarchy
 * - **Events**: Recommendations are scheduled for ceremonial presentation
 * - **Branches**: Recommendations respect organizational boundaries and jurisdiction
 * - **State Logging**: All state transitions are logged for audit trail and analytics
 * 
 * ## Administrative Features:
 * The Recommendation entity provides comprehensive contact management, specialty
 * tracking, court protocol integration, and automated state rule processing for
 * complex workflow management and ceremonial coordination.
 * 
 * @property int $id Primary key identifier for recommendation
 * @property int $requester_id Foreign key to Member who submitted the recommendation
 * @property int|null $member_id Foreign key to Member being recommended for recognition
 * @property int|null $branch_id Foreign key to Branch for organizational scope
 * @property int $award_id Foreign key to Award being recommended
 * @property int|null $event_id Foreign key to Event for ceremonial scheduling
 * @property string $status High-level workflow category (In Progress, Scheduling, To Give, Closed)
 * @property string $state Specific workflow position within the status category
 * @property \Cake\I18n\DateTime|null $state_date Timestamp of last state transition for workflow tracking
 * @property \Cake\I18n\DateTime|null $given Date when award was actually presented
 * @property int|null $stack_rank Priority ranking for ceremony organization
 * @property string $requester_sca_name SCA name of the person submitting the recommendation
 * @property string $member_sca_name SCA name of the member being recommended
 * @property string $contact_number Contact phone number for coordination
 * @property string|null $contact_email Contact email address for workflow communication
 * @property string|null $reason Detailed justification for the award recommendation
 * @property string|null $specialty Specific specialty or focus area for the award
 * @property string|null $call_into_court Court protocol preferences for ceremony
 * @property string|null $court_availability Availability constraints for ceremony scheduling
 * @property string|null $person_to_notify Contact person for ceremony coordination
 * @property string|null $close_reason Reason for recommendation closure (if applicable)
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp for audit trail
 * @property \Cake\I18n\DateTime $created Creation timestamp for audit trail
 * @property int|null $created_by Foreign key to Member who created this recommendation
 * @property int|null $modified_by Foreign key to Member who last modified this recommendation
 * @property \Cake\I18n\DateTime|null $deleted Soft deletion timestamp for data preservation
 * 
 * @property \App\Model\Entity\Member $member Member being recommended for recognition
 * @property \App\Model\Entity\Member $requester Member who submitted the recommendation
 * @property \Awards\Model\Entity\Award $award Award being recommended
 * @property \Awards\Model\Entity\Event $event Event scheduled for award presentation
 * @property \App\Model\Entity\Branch $branch Branch for organizational scope
 * 
 * @package Awards\Model\Entity
 * @see \Awards\Model\Entity\RecommendationsStatesLog For state transition audit trail
 * @see \Awards\Model\Entity\Award For award hierarchy and configuration
 * @see \Awards\Model\Entity\Event For ceremony scheduling and coordination
 * @see \Awards\Model\Table\RecommendationsTable For recommendation data management
 */
class Recommendation extends BaseEntity
{

    /**
     * Mass Assignment Protection - Define accessible fields for security
     * 
     * Configures which fields can be mass assigned through newEntity() or patchEntity()
     * operations, providing security protection against unauthorized data modification.
     * The recommendation entity allows access to all workflow and contact fields
     * for comprehensive recommendation management while maintaining audit integrity.
     * 
     * ## Accessible Fields:
     * - **Core Workflow**: requester_id, member_id, award_id for recommendation identity
     * - **State Management**: status, state, state_date for workflow control
     * - **Event Coordination**: event_id, given, stack_rank for ceremony scheduling
     * - **Contact Information**: requester_sca_name, member_sca_name, contact details
     * - **Workflow Details**: reason, specialty, court protocol preferences
     * - **Administrative**: branch_id, close_reason for organizational management
     * - **Entity Relationships**: Associated member, event entities for workflow integration
     * 
     * ## Security Considerations:
     * The accessible configuration supports comprehensive recommendation workflow
     * management while ensuring that all modifications are properly tracked through
     * the audit trail system for accountability and state transition logging.
     * 
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'requester_id' => true,
        'stack_rank' => true,
        'member_id' => true,
        'branch_id' => true,
        'award_id' => true,
        'event_id' => true,  // Deprecated - kept for migration compatibility, use gathering_id instead
        'gathering_id' => true,
        'given' => true,
        'status' => true,
        'state' => true,
        'state_date' => true,
        'stack_rank' => true,
        'requester_sca_name' => true,
        'member_sca_name' => true,
        'contact_number' => true,
        'contact_email' => true,
        'reason' => true,
        'specialty' => true,
        'call_into_court' => true,
        'court_availability' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'member' => true,
        'events' => true,
        'person_to_notify' => true,
        'close_reason' => true,
    ];

    /**
     * Setter for Given Date - Handle date format conversion
     * 
     * Processes the given date field to ensure proper DateTime object creation
     * from string inputs. This setter supports flexible date input formats
     * for administrative convenience while maintaining consistent internal
     * DateTime representation for database storage and workflow processing.
     * 
     * @param mixed $value Date value in string or DateTime format
     * @return \DateTime Properly formatted DateTime object for database storage
     */
    protected function _setGiven($value)
    {
        if (is_string($value)) {
            $value = new \DateTime($value);
        }
        return $value;
    }

    /**
     * State Setter - Complex state machine management with automatic transitions
     * 
     * Implements the sophisticated state machine logic for recommendation workflow
     * management. This setter automatically handles:
     * - State validation against configured state lists
     * - Automatic status updates based on state categorization
     * - State transition logging with timestamp tracking
     * - Configuration-driven field updates through state rules
     * 
     * The state machine integrates with the Awards plugin configuration system
     * to provide flexible workflow management that can be adjusted through
     * administrative settings without code changes.
     * 
     * ## State Machine Features:
     * - **Validation**: Ensures state transitions are valid according to configuration
     * - **Status Synchronization**: Automatically updates status based on state categorization
     * - **Timestamp Management**: Records state transition timing for audit trail
     * - **Rule Processing**: Applies configuration-driven field updates and validation
     * - **Audit Trail**: Preserves previous state/status for transition logging
     * 
     * @param string $value New state value for workflow transition
     * @return string Validated and processed state value
     * @throws \InvalidArgumentException When state value is not valid according to configuration
     * 
     * @see StaticHelpers::getAppSetting() For state configuration management
     * @see getStates() For valid state list retrieval
     * @see getStatuses() For status categorization mapping
     */
    protected function _setState($value)
    {
        $this->beforeState = $this->state;
        $this->beforeStatus = $this->status;

        $states = self::getStates();
        if (!in_array($value, $states)) {
            throw new \InvalidArgumentException("Invalid State");
        }
        $statuses = self::getStatuses();
        $nextStatus = $this->status;
        foreach ($statuses as $statusKey => $status) {
            if (in_array($value, $status)) {
                $nextStatus = $statusKey;
                break;
            }
        }
        if ($nextStatus != $this->status) {
            $this->status = $nextStatus;
        }
        $this->state_date = new DateTime();
        $stateRules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");
        if (isset($stateRules[$value])) {
            $rule = $stateRules[$value];
            if (isset($rule['Set'])) {
                $fieldsToSet = $rule['Set'];
                foreach ($fieldsToSet as $field => $fieldValue) {
                    $this->$field = $fieldValue;
                }
            }
        }
        return $value;
    }

    /**
     * Get Status Configuration - Retrieve status categories and state mappings
     * 
     * Returns the complete status configuration from the Awards plugin settings,
     * providing the hierarchical mapping of status categories to their constituent
     * states. This configuration drives the state machine behavior and UI organization.
     * 
     * ## Status Categories:
     * - **In Progress**: Active workflow states requiring attention or action
     * - **Scheduling**: States focused on ceremony coordination and event planning
     * - **To Give**: States for recommendations ready for ceremonial presentation
     * - **Closed**: Final states for completed or terminated recommendations
     * 
     * @return array Status configuration with state categorization mapping
     * 
     * @see StaticHelpers::getAppSetting() For configuration retrieval
     * @see getStates() For flattened state list retrieval
     */
    public static function getStatuses(): array
    {
        $statusList = StaticHelpers::getAppSetting("Awards.RecommendationStatuses");
        return $statusList;
    }

    /**
     * Get State Configuration - Retrieve valid states with optional status filtering
     * 
     * Returns either all valid states (flattened from status categories) or states
     * within a specific status category. This method supports both comprehensive
     * state validation and status-specific workflow management.
     * 
     * ## Usage Patterns:
     * - **All States**: `getStates()` returns complete flattened state list for validation
     * - **Status States**: `getStates('In Progress')` returns states within specific status
     * 
     * @param string|null $status Optional status filter for category-specific state retrieval
     * @return array Valid states list, either all states or states within specified status
     * 
     * @see getStatuses() For status category configuration
     * @see _setState() For state validation and transition processing
     */
    public static function getStates($status = null): array
    {
        if ($status) {
            $statusList = self::getStatuses();
            return $statusList[$status];
        }
        $statuses = self::getStatuses();
        $states = [];
        foreach ($statuses as $status) {
            foreach ($status as $state) {
                $states[] = $state;
            }
        }
        return $states;
    }

    /**
     * Determine the organizational branch ID associated with this recommendation's award.
     *
     * @return int|null The branch ID associated with the recommendation's award, or null if it cannot be determined.
     * @see \Awards\Model\Entity\Award
     * @see \App\Model\Entity\Branch
     */
    public function getBranchId(): ?int
    {
        if ($this->award)
            return $this->award->branch_id;

        if ($this->award_id == null) {
            return null;
        }
        $awardTbl = TableRegistry::getTableLocator()->get('Awards.Awards');
        $award = $awardTbl->find()
            ->where(['id' => $this->award_id])
            ->select('branch_id')
            ->first();
        if ($award) {
            return $award->branch_id;
        }
        return null;
    }
}