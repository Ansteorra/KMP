<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Domain Entity - Award Categorization and Organizational Structure
 * 
 * Represents award domains within the KMP Awards system, providing categorical
 * organization for awards based on the type of recognition and area of achievement.
 * Domains serve as the top-level organizational structure for the award hierarchy,
 * allowing awards to be grouped by category for administrative management and
 * ceremonial organization.
 * 
 * Award domains typically correspond to major areas of recognition such as:
 * - **Service**: Awards recognizing service to the organization and community
 * - **Arts & Sciences**: Awards recognizing achievement in period arts and research
 * - **Martial Activities**: Awards recognizing excellence in combat and martial pursuits
 * - **Youth**: Awards specifically designed for youth member recognition
 * - **Special Recognition**: Unique awards for extraordinary circumstances
 * 
 * The Domain entity provides a simple but extensible structure that supports
 * the hierarchical organization of awards while maintaining administrative
 * flexibility for future expansion and reorganization.
 * 
 * ## Core Properties:
 * - **Identification**: Unique name for domain recognition and display
 * - **Audit Trail**: Complete creation and modification tracking for accountability
 * - **Soft Deletion**: Data preservation through soft deletion support
 * 
 * ## Integration Points:
 * - **Awards**: Each award belongs to exactly one domain for categorical organization
 * - **Recommendations**: Domain selection influences recommendation workflows
 * - **Reporting**: Domains provide grouping for award analytics and reporting
 * - **Navigation**: Domain organization supports user interface structure
 * 
 * ## Administrative Features:
 * The Domain entity extends BaseEntity to provide comprehensive audit trail
 * support, soft deletion capabilities, and integration with the KMP security
 * framework for administrative access control.
 * 
 * @property int $id Primary key identifier for domain
 * @property string $name Domain name for categorical identification and display
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp for audit trail
 * @property \Cake\I18n\DateTime $created Creation timestamp for audit trail
 * @property int|null $created_by Foreign key to Member who created this domain
 * @property int|null $modified_by Foreign key to Member who last modified this domain
 * @property \Cake\I18n\DateTime|null $deleted Soft deletion timestamp for data preservation
 * 
 * @package Awards\Model\Entity
 * @see \Awards\Model\Entity\Award For awards that belong to this domain
 * @see \Awards\Model\Entity\Level For award level hierarchy within domains
 * @see \Awards\Model\Table\DomainsTable For domain data management
 */
class Domain extends BaseEntity
{
    /**
     * Mass Assignment Protection - Define accessible fields for security
     * 
     * Configures which fields can be mass assigned through newEntity() or patchEntity()
     * operations, providing security protection against unauthorized data modification.
     * The domain entity allows access to the name field for administrative management
     * while maintaining audit trail integrity through BaseEntity.
     * 
     * ## Accessible Fields:
     * - **Core Configuration**: name for domain identification and categorization
     * - **Audit Fields**: created_by, modified_by for accountability tracking
     * - **System Fields**: Standard timestamp and deletion fields for data management
     * 
     * ## Security Considerations:
     * The minimal accessible configuration reflects the simple nature of domain
     * entities while ensuring that all modifications are properly tracked through
     * the audit trail system for administrative accountability.
     * 
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'name' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
    ];
}
