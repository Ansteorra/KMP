<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Event Entity - Award Ceremony and Event Management
 * 
 * Represents ceremonial events within the KMP Awards system, providing temporal
 * event management and award ceremony coordination. Events serve as the scheduled
 * occasions where award recommendations are fulfilled through formal presentation
 * and recognition ceremonies.
 * 
 * The Event entity manages the complete lifecycle of award ceremonies, from
 * initial planning through execution and closure. Events provide the temporal
 * framework that connects award recommendations to actual ceremonial presentation,
 * supporting both planning workflows and historical record keeping.
 * 
 * Award events typically include:
 * - **Royal Courts**: Formal ceremonies with Crown presence for high-level awards
 * - **Baronial Courts**: Local ceremonies for branch-level recognition
 * - **Special Events**: Tournaments, arts events, and other gatherings with award presentations
 * - **Virtual Courts**: Online ceremonies for remote recognition and accessibility
 * - **Closed Events**: Private or limited ceremonies for specific award types
 * 
 * ## Core Properties:
 * - **Event Identity**: Name and description for ceremonial identification
 * - **Temporal Management**: Start and end dates for event scheduling and planning
 * - **Organizational Scope**: Branch relationship for jurisdictional boundaries
 * - **Status Management**: Closed flag for event lifecycle control
 * - **Audit Trail**: Complete creation and modification tracking for accountability
 * 
 * ## Integration Points:
 * - **Recommendations**: Events are selected for recommendation fulfillment and presentation
 * - **Branches**: Events are scoped to specific organizational levels and jurisdictions
 * - **Members**: Events coordinate member recognition and ceremonial participation
 * - **Scheduling**: Events provide deadlines and planning frameworks for award workflows
 * 
 * ## Administrative Features:
 * The Event entity extends BaseEntity to provide comprehensive audit trail
 * support, soft deletion capabilities, and integration with the KMP security
 * framework for administrative access control and ceremony coordination.
 * 
 * @property int $id Primary key identifier for event
 * @property string $name Event name for ceremonial identification and scheduling
 * @property string $description Event description and ceremonial details
 * @property int $branch_id Foreign key to Branch for organizational scope and jurisdiction
 * @property \Cake\I18n\DateTime|null $start_date Event start date for ceremony scheduling
 * @property \Cake\I18n\DateTime|null $end_date Event end date for ceremony completion
 * @property bool|null $closed Event closure status for lifecycle management
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp for audit trail
 * @property \Cake\I18n\DateTime $created Creation timestamp for audit trail
 * @property int|null $created_by Foreign key to Member who created this event
 * @property int|null $modified_by Foreign key to Member who last modified this event
 * @property \Cake\I18n\DateTime|null $deleted Soft deletion timestamp for data preservation
 * 
 * @property \App\Model\Entity\Branch $branch Branch relationship for organizational scope
 * 
 * @package Awards\Model\Entity
 * @see \Awards\Model\Entity\Recommendation For recommendations scheduled for this event
 * @see \App\Model\Entity\Branch For event organizational scope
 * @see \Awards\Model\Table\EventsTable For event data management and scheduling
 */
class Event extends BaseEntity
{
    /**
     * Mass Assignment Protection - Define accessible fields for security
     * 
     * Configures which fields can be mass assigned through newEntity() or patchEntity()
     * operations, providing security protection against unauthorized data modification.
     * The event entity allows access to all core ceremonial configuration fields
     * for administrative management while maintaining audit trail integrity.
     * 
     * ## Accessible Fields:
     * - **Event Configuration**: name, description for ceremonial identification and planning
     * - **Temporal Management**: start_date, end_date for event scheduling and coordination
     * - **Organizational Scope**: branch_id for jurisdictional boundaries and access control
     * - **Status Management**: closed for event lifecycle control and completion tracking
     * - **Audit Fields**: created_by, modified_by for accountability tracking
     * - **Entity Relationships**: Associated branch entity for organizational integration
     * 
     * ## Security Considerations:
     * The accessible configuration allows administrative management of event
     * scheduling and ceremony coordination while ensuring that all modifications
     * are properly tracked through the audit trail system for accountability.
     * 
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'branch_id' => true,
        'start_date' => true,
        'end_date' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'branch' => true,
        'closed' => true,
    ];
}
