<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * RecommendationsStatesLog Entity - State Transition Audit Trail and Workflow Analytics
 * 
 * Represents state transition logging within the KMP Awards system, providing
 * comprehensive audit trail tracking for recommendation workflow changes and
 * analytical data for workflow optimization. This entity captures every state
 * transition in the recommendation lifecycle, supporting accountability,
 * compliance monitoring, and workflow performance analysis.
 * 
 * The RecommendationsStatesLog entity serves as the permanent audit trail for
 * the sophisticated state machine implemented in the Recommendation entity.
 * Each state transition is logged with complete context including the source
 * and destination states, timing information, and the responsible user for
 * comprehensive accountability tracking.
 * 
 * ## Audit Trail Features:
 * - **State Transition Tracking**: Complete from/to state logging for every workflow change
 * - **Temporal Analysis**: Creation timestamps for workflow timing and performance analysis
 * - **Accountability**: User tracking for administrative oversight and responsibility
 * - **Workflow Analytics**: Data foundation for process optimization and reporting
 * - **Compliance**: Permanent record for regulatory and administrative requirements
 * 
 * ## Integration Points:
 * - **Recommendations**: Each log entry is associated with a specific recommendation workflow
 * - **State Machine**: Automatic logging triggered by Recommendation entity state changes
 * - **Reporting**: Data source for workflow analytics and performance dashboards
 * - **Audit Systems**: Integration with KMP audit framework for compliance monitoring
 * 
 * ## Administrative Features:
 * The log entity provides immutable audit trail data that supports administrative
 * review, workflow optimization analysis, and compliance reporting for the
 * Awards recommendation system. Log entries are append-only to preserve
 * complete historical accuracy.
 * 
 * @property int $id Primary key identifier for log entry
 * @property int $recommendation_id Foreign key to Recommendation for workflow association
 * @property string $from_state Previous state before transition (null for initial state)
 * @property string $to_state New state after transition for workflow tracking
 * @property \Cake\I18n\DateTime $created Timestamp of state transition for temporal analysis
 * @property int|null $created_by Foreign key to Member who initiated the state transition
 * 
 * @property \Awards\Model\Entity\Recommendation $recommendation Recommendation associated with this state transition
 * 
 * @package Awards\Model\Entity
 * @see \Awards\Model\Entity\Recommendation For recommendation state machine implementation
 * @see \Awards\Model\Table\RecommendationsStatesLogsTable For audit trail data management
 */
class RecommendationsStatesLog extends BaseEntity
{
    /**
     * Mass Assignment Protection - Define accessible fields for security
     * 
     * Configures which fields can be mass assigned through newEntity() or patchEntity()
     * operations, providing security protection against unauthorized data modification.
     * The state log entity allows access to all tracking fields for automated
     * logging while maintaining audit trail integrity and immutability.
     * 
     * ## Accessible Fields:
     * - **Workflow Tracking**: recommendation_id for association with recommendation workflow
     * - **State Transition**: from_state, to_state for complete transition documentation
     * - **Audit Information**: created, created_by for accountability and temporal tracking
     * - **Entity Relationships**: Associated recommendation entity for workflow integration
     * 
     * ## Security Considerations:
     * The accessible configuration supports automated audit trail generation
     * while ensuring that log entries maintain their integrity as permanent
     * audit records. Log entries are typically append-only to preserve
     * complete historical accuracy for compliance and analysis.
     * 
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'recommendation_id' => true,
        'from_state' => true,
        'to_state' => true,
        'created' => true,
        'created_by' => true,
        'recommendation' => true,
    ];
}
