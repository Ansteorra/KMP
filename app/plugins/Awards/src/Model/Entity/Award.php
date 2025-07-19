<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Award Entity - Award Configuration and Hierarchical Organization
 * 
 * Represents individual awards within the KMP Awards system, providing comprehensive
 * award configuration with hierarchical organization through Domain, Level, and Branch
 * relationships. Each award defines the specific recognition that can be recommended
 * for members, including ceremonial elements and organizational scope.
 * 
 * The Award entity implements a sophisticated hierarchical structure where awards
 * are organized by Domain (category), Level (precedence), and Branch (organizational
 * scope). This structure allows for complex award organization while maintaining
 * clear administrative boundaries and ceremonial protocols.
 * 
 * ## Core Properties:
 * - **Identification**: Unique name, abbreviation, and description for award recognition
 * - **Ceremonial Elements**: Insignia, badge, and charter configuration for physical awards
 * - **Hierarchical Organization**: Domain, Level, and Branch relationships for organizational structure
 * - **Temporal Configuration**: Open/close dates for award availability periods
 * - **Specialty Tracking**: Additional specialty categories for award classification
 * 
 * ## Hierarchical Relationships:
 * - **Domain**: Award category/type (e.g., Service, Arts & Sciences, Martial)
 * - **Level**: Award precedence and ranking within the domain hierarchy
 * - **Branch**: Organizational scope (Kingdom, Principality, Barony, Local)
 * 
 * ## Integration Points:
 * - **Recommendations**: Awards are the target of recommendation workflows
 * - **Events**: Awards are presented at specific ceremonial events
 * - **Members**: Awards are granted to members through the recommendation process
 * - **Branches**: Awards are scoped to specific organizational levels
 * 
 * @property int $id Primary key identifier
 * @property string $name Award name for recognition and ceremonial use
 * @property string|null $abbreviation Short form name for display efficiency
 * @property string|null $specialties Additional specialty categories for award classification
 * @property string|null $description Detailed award description and requirements
 * @property string|null $insignia Physical insignia specification for award creation
 * @property string|null $badge Badge design specification for ceremonial display
 * @property string|null $charter Charter text and ceremonial wording for presentations
 * @property int $domain_id Foreign key to Awards Domain for categorical organization
 * @property int $level_id Foreign key to Awards Level for precedence hierarchy
 * @property int $branch_id Foreign key to Branch for organizational scope
 * @property \Cake\I18n\DateTime|null $open_date Award availability start date for temporal control
 * @property \Cake\I18n\DateTime|null $close_date Award availability end date for temporal control
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp for audit trail
 * @property \Cake\I18n\DateTime $created Creation timestamp for audit trail
 * @property int|null $created_by Foreign key to Member who created this award configuration
 * @property int|null $modified_by Foreign key to Member who last modified this award configuration
 * @property \Cake\I18n\DateTime|null $deleted Soft deletion timestamp for data preservation
 * 
 * @property \Awards\Model\Entity\Domain $awards_domain Domain relationship for categorical organization
 * @property \Awards\Model\Entity\Level $awards_level Level relationship for precedence hierarchy
 * @property \App\Model\Entity\Branch $branch Branch relationship for organizational scope
 * 
 * @package Awards\Model\Entity
 * @see \Awards\Model\Entity\Domain For award categorization
 * @see \Awards\Model\Entity\Level For award precedence hierarchy
 * @see \App\Model\Entity\Branch For organizational scope
 * @see \Awards\Model\Entity\Recommendation For award recommendation workflow
 */
class Award extends BaseEntity
{
    /**
     * Mass Assignment Protection - Define accessible fields for security
     * 
     * Configures which fields can be mass assigned through newEntity() or patchEntity()
     * operations, providing security protection against unauthorized data modification.
     * All core award configuration fields are accessible for administrative management,
     * while system fields like timestamps are controlled through BaseEntity.
     * 
     * ## Accessible Fields:
     * - **Core Configuration**: name, abbreviation, specialties, description for award identity
     * - **Ceremonial Elements**: insignia, badge, charter for physical award specifications
     * - **Hierarchical Relationships**: domain_id, level_id, branch_id for organizational structure
     * - **Audit Fields**: created_by, modified_by for accountability tracking
     * - **Entity Relationships**: Associated domain, level, and branch entities
     * 
     * ## Security Considerations:
     * The accessible configuration allows administrative users to modify all award
     * configuration while maintaining audit trail integrity through BaseEntity
     * timestamp management and soft deletion support.
     * 
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'name' => true,
        'specialties' => true,
        'abbreviation' => true,
        'description' => true,
        'insignia' => true,
        'badge' => true,
        'charter' => true,
        'domain_id' => true,
        'level_id' => true,
        'branch_id' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'awards_domain' => true,
        'awards_level' => true,
        'branch' => true,
    ];
}
