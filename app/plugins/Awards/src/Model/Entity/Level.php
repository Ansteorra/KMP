<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Level Entity - Award Hierarchy and Precedence Management
 * 
 * Represents award levels within the KMP Awards system, providing hierarchical
 * organization and precedence management for awards. Levels establish the ranking
 * and progression order of awards within domains, supporting both ceremonial
 * precedence and member progression tracking through the award system.
 * 
 * Award levels typically represent different tiers of recognition such as:
 * - **Entry Level**: Beginning recognition awards for new achievements
 * - **Advanced**: Intermediate awards recognizing continued excellence
 * - **Master Level**: High honors for exceptional achievement and expertise
 * - **Peerages**: The highest level of recognition with special privileges
 * - **Special Recognition**: Unique levels for extraordinary circumstances
 * 
 * The Level entity implements a progression order system that supports both
 * precedence for ceremonial purposes and logical progression for member
 * advancement through the award hierarchy.
 * 
 * ## Core Properties:
 * - **Identification**: Unique name for level recognition and display
 * - **Precedence Management**: Progression order for hierarchical ranking
 * - **Audit Trail**: Complete creation and modification tracking for accountability
 * - **Soft Deletion**: Data preservation through soft deletion support
 * 
 * ## Integration Points:
 * - **Awards**: Each award belongs to a specific level for hierarchical organization
 * - **Recommendations**: Level influences recommendation workflow and approval requirements
 * - **Member Progression**: Levels support member advancement tracking through award systems
 * - **Ceremonial Protocol**: Level order determines precedence in ceremonies and recognition
 * 
 * ## Administrative Features:
 * The Level entity extends BaseEntity to provide comprehensive audit trail
 * support, soft deletion capabilities, and integration with the KMP security
 * framework for administrative access control and precedence management.
 * 
 * @property int $id Primary key identifier for level
 * @property string $name Level name for hierarchical identification and display
 * @property int|null $progression_order Numerical order for hierarchical precedence and progression
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp for audit trail
 * @property \Cake\I18n\DateTime $created Creation timestamp for audit trail
 * @property int|null $created_by Foreign key to Member who created this level
 * @property int|null $modified_by Foreign key to Member who last modified this level
 * @property \Cake\I18n\DateTime|null $deleted Soft deletion timestamp for data preservation
 * 
 * @package Awards\Model\Entity
 * @see \Awards\Model\Entity\Award For awards that belong to this level
 * @see \Awards\Model\Entity\Domain For award domain categorization
 * @see \Awards\Model\Table\LevelsTable For level data management and ordering
 */
class Level extends BaseEntity
{
    /**
     * Mass Assignment Protection - Define accessible fields for security
     * 
     * Configures which fields can be mass assigned through newEntity() or patchEntity()
     * operations, providing security protection against unauthorized data modification.
     * The level entity allows access to name and progression order fields for
     * administrative management while maintaining audit trail integrity.
     * 
     * ## Accessible Fields:
     * - **Core Configuration**: name for level identification and hierarchical display
     * - **Precedence Management**: progression_order for hierarchical ranking and ceremonial precedence
     * - **Audit Fields**: created_by, modified_by for accountability tracking
     * - **System Fields**: Standard timestamp and deletion fields for data management
     * 
     * ## Security Considerations:
     * The accessible configuration allows administrative management of level
     * hierarchy while ensuring that all modifications are properly tracked through
     * the audit trail system for administrative accountability and precedence validation.
     * 
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'name' => true,
        'progression_order' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
