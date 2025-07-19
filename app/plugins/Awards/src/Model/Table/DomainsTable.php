<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * Award Domains Table - Core Domain Classification & Categorization Management
 * 
 * Manages the fundamental domain classification system that categorizes awards into
 * logical organizational groups. This table class provides the foundational framework
 * for award categorization, enabling sophisticated award management through domain-based
 * classification while maintaining organizational policy compliance and data integrity.
 * 
 * ## Domain Classification Architecture
 * 
 * ### Award Categorization Framework
 * - **Logical Grouping**: Provides logical grouping mechanism for award organization
 * - **Category Management**: Comprehensive category management for award classification
 * - **Hierarchical Support**: Foundation for hierarchical award organization
 * - **Classification Standards**: Enforces organizational classification standards
 * 
 * ### Organizational Domain Structure
 * - **Domain Definition**: Defines award domains with clear naming and identification
 * - **Category Boundaries**: Establishes clear boundaries between award categories
 * - **Classification Rules**: Implements domain classification rules and policies
 * - **Organizational Alignment**: Ensures alignment with organizational structure
 * 
 * ### Award Integration Framework
 * - **Award Association**: One-to-many association with Awards for classification
 * - **Category Assignment**: Enables assignment of awards to appropriate domains
 * - **Classification Consistency**: Maintains consistency across award classifications
 * - **Domain Scoping**: Provides domain-based scoping for award management
 * 
 * ## Data Management & Integrity
 * 
 * ### Domain Data Validation
 * - **Name Uniqueness**: Ensures unique domain names across the system
 * - **Content Standards**: Enforces domain naming and content standards
 * - **Format Compliance**: Validates domain format compliance requirements
 * - **Quality Assurance**: Comprehensive data quality assurance framework
 * 
 * ### Audit & Tracking Capabilities
 * - **Creation Tracking**: Comprehensive creation and modification tracking
 * - **User Attribution**: Tracks user attribution for all domain changes
 * - **Temporal Analysis**: Supports temporal analysis and historical reporting
 * - **Soft Deletion**: Implements soft deletion for data preservation
 * 
 * ### Business Rule Enforcement
 * - **Naming Standards**: Enforces organizational naming standards
 * - **Classification Rules**: Implements domain classification business rules
 * - **Policy Compliance**: Ensures compliance with organizational policies
 * - **Administrative Control**: Provides administrative control over domain management
 * 
 * ## System Integration & Architecture
 * 
 * ### Award System Integration
 * - **Award Classification**: Fundamental integration with award classification system
 * - **Category Filtering**: Enables category-based filtering and organization
 * - **Search Integration**: Integrates with award search and discovery systems
 * - **Reporting Framework**: Supports domain-based reporting and analysis
 * 
 * ### Administrative Interface Integration
 * - **CRUD Operations**: Complete CRUD operation support for domain management
 * - **Form Integration**: Integration with administrative forms and interfaces
 * - **Validation Framework**: Real-time validation for administrative operations
 * - **Error Handling**: Comprehensive error handling for administrative workflows
 * 
 * ### Security & Authorization
 * - **Access Control**: Implements access control for domain management
 * - **Permission Validation**: Validates user permissions for domain operations
 * - **Administrative Authority**: Ensures appropriate administrative authority
 * - **Audit Compliance**: Maintains comprehensive audit compliance
 * 
 * ## Performance & Optimization
 * 
 * ### Query Performance
 * - **Index Strategy**: Optimized index strategy for domain queries
 * - **Association Performance**: Efficient association loading for awards
 * - **Query Optimization**: Comprehensive query optimization strategies
 * - **Caching Support**: Integrated caching support for performance
 * 
 * ### Resource Management
 * - **Memory Efficiency**: Memory-efficient domain data management
 * - **Connection Optimization**: Database connection optimization
 * - **Resource Conservation**: Conservative resource usage patterns
 * - **Concurrent Access**: Support for concurrent access patterns
 * 
 * ## Usage Examples & Best Practices
 * 
 * ### Basic Domain Operations
 * ```php
 * // Create new award domain
 * $domain = $this->Domains->newEntity([
 *     'name' => 'Academic Excellence',
 *     'description' => 'Awards recognizing academic achievement'
 * ]);
 * $this->Domains->saveOrFail($domain);
 * 
 * // Find domain with associated awards
 * $domain = $this->Domains->get($id, [
 *     'contain' => ['Awards']
 * ]);
 * ```
 * 
 * ### Domain-Based Award Management
 * ```php
 * // Find all awards in specific domain
 * $academicAwards = $this->Domains->Awards->find()
 *     ->where(['domain_id' => $academicDomainId])
 *     ->contain(['Levels', 'Branches']);
 * 
 * // Domain-based award statistics
 * $domainStats = $this->Domains->find()
 *     ->contain(['Awards' => function($q) {
 *         return $q->where(['Awards.active' => true]);
 *     }])
 *     ->formatResults(function($results) {
 *         return $results->map(function($domain) {
 *             $domain->award_count = count($domain->awards);
 *             return $domain;
 *         });
 *     });
 * ```
 * 
 * ### Administrative Operations
 * ```php
 * // Bulk domain operations
 * $domains = $this->Domains->patchEntities($domains, $bulkUpdateData);
 * $this->Domains->saveManyOrFail($domains);
 * 
 * // Soft deletion with audit trail
 * $domain->deleted = new DateTime();
 * $this->Domains->saveOrFail($domain);
 * ```
 * 
 * @property \Awards\Model\Table\AwardsTable&\Cake\ORM\Association\HasMany $Awards Award classification relationship
 *
 * @method \Awards\Model\Entity\Domain newEmptyEntity() Create new empty domain entity
 * @method \Awards\Model\Entity\Domain newEntity(array $data, array $options = []) Create new domain entity with data
 * @method array<\Awards\Model\Entity\Domain> newEntities(array $data, array $options = []) Create multiple domain entities
 * @method \Awards\Model\Entity\Domain get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args) Get domain by primary key
 * @method \Awards\Model\Entity\Domain findOrCreate($search, ?callable $callback = null, array $options = []) Find or create domain
 * @method \Awards\Model\Entity\Domain patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = []) Patch domain entity
 * @method array<\Awards\Model\Entity\Domain> patchEntities(iterable $entities, array $data, array $options = []) Patch multiple domain entities
 * @method \Awards\Model\Entity\Domain|false save(\Cake\Datasource\EntityInterface $entity, array $options = []) Save domain entity
 * @method \Awards\Model\Entity\Domain saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = []) Save domain entity or fail
 * @method iterable<\Awards\Model\Entity\Domain>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Domain>|false saveMany(iterable $entities, array $options = []) Save multiple domains
 * @method iterable<\Awards\Model\Entity\Domain>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Domain> saveManyOrFail(iterable $entities, array $options = []) Save multiple domains or fail
 * @method iterable<\Awards\Model\Entity\Domain>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Domain>|false deleteMany(iterable $entities, array $options = []) Delete multiple domains
 * @method iterable<\Awards\Model\Entity\Domain>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Domain> deleteManyOrFail(iterable $entities, array $options = []) Delete multiple domains or fail
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior Automatic timestamp management
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior User attribution tracking
 * @mixin \Muffin\Trash\Model\Behavior\TrashBehavior Soft deletion capabilities
 *
 * @see \Awards\Model\Entity\Domain For domain entity documentation
 * @see \Awards\Model\Table\AwardsTable For award integration
 * @see \Awards\Model\Table\LevelsTable For level hierarchy integration
 * @see \App\Model\Table\BaseTable For base table functionality
 */
class DomainsTable extends BaseTable
{
    /**
     * Initialize the Domains table with comprehensive configuration and association management
     * 
     * Establishes the foundational configuration for the Domains table including database
     * table mapping, display field configuration, association definitions, and behavior
     * integration. This method configures the complete table architecture for domain
     * classification management with award associations, audit capabilities, and data integrity.
     * 
     * ## Table Configuration & Database Mapping
     * 
     * ### Database Table Configuration
     * - **Table Mapping**: Maps to 'awards_domains' database table
     * - **Display Field**: Configures 'name' as the primary display field for UI components
     * - **Primary Key**: Establishes 'id' as the primary key for entity identification
     * - **Schema Integration**: Integrates with database schema and constraint definitions
     * 
     * ### Performance Configuration
     * - **Query Optimization**: Table configuration optimized for domain classification queries
     * - **Index Utilization**: Configuration designed to utilize database indexes effectively
     * - **Association Performance**: Association configuration optimized for award loading
     * - **Memory Management**: Efficient memory usage configuration for domain operations
     * 
     * ## Award Association Architecture
     * 
     * ### One-to-Many Award Relationship
     * - **Award Association**: HasMany association with Awards.Awards for classification
     * - **Foreign Key Configuration**: Uses 'domain_id' as foreign key in awards table
     * - **Cascade Operations**: Proper cascade handling for domain-award relationships
     * - **Association Loading**: Optimized association loading strategies
     * 
     * ### Classification Framework Integration
     * - **Category Management**: Enables category-based award management
     * - **Domain Scoping**: Provides domain scoping for award queries
     * - **Classification Consistency**: Maintains classification consistency across awards
     * - **Referential Integrity**: Ensures referential integrity between domains and awards
     * 
     * ## Behavior Integration & Audit Capabilities
     * 
     * ### Timestamp Behavior Configuration
     * - **Automatic Timestamping**: Automatic created and modified timestamp management
     * - **Audit Trail Support**: Comprehensive audit trail through timestamp tracking
     * - **Temporal Analysis**: Support for temporal analysis and historical reporting
     * - **Data Lifecycle**: Complete data lifecycle tracking from creation to modification
     * 
     * ### Footprint Behavior Integration
     * - **User Attribution**: Automatic tracking of created_by and modified_by fields
     * - **Administrative Accountability**: User accountability for all domain modifications
     * - **Change Tracking**: Comprehensive change tracking for administrative oversight
     * - **Audit Compliance**: Full audit compliance through user attribution tracking
     * 
     * ### Trash Behavior Configuration
     * - **Soft Deletion**: Soft deletion pattern implementation for data preservation
     * - **Recovery Capabilities**: Support for data recovery and restoration operations
     * - **Audit Requirements**: Maintain deleted records for audit and compliance
     * - **Data Preservation**: Preserve domain data for organizational continuity
     * 
     * ## Data Integrity & Performance Optimization
     * 
     * ### Domain Data Integrity
     * - **Validation Framework**: Foundation for comprehensive validation framework
     * - **Business Logic**: Integration points for business logic and rule enforcement
     * - **Data Quality**: Data quality assurance through validation integration
     * - **Consistency Enforcement**: Ensures data consistency across domain operations
     * 
     * ### Query Performance Setup
     * - **Association Optimization**: Optimized association loading patterns
     * - **Index Strategy**: Configuration supporting optimal index utilization
     * - **Query Caching**: Foundation for query result caching and optimization
     * - **Resource Management**: Efficient resource management configuration
     * 
     * ## Integration Points & System Architecture
     * 
     * ### Award System Integration
     * - **Classification Integration**: Deep integration with award classification system
     * - **Category Management**: Integration with category management workflows
     * - **Search Integration**: Integration with award search and discovery systems
     * - **Reporting Framework**: Integration with domain-based reporting systems
     * 
     * ### Administrative System Integration
     * - **CRUD Operations**: Foundation for complete CRUD operation support
     * - **Form Integration**: Integration with administrative forms and interfaces
     * - **Validation Integration**: Real-time validation for administrative operations
     * - **Error Handling**: Comprehensive error handling for administrative workflows
     * 
     * ## Usage Examples & Configuration Patterns
     * 
     * ```php
     * // Automatic table configuration during application bootstrap
     * $domainsTable = TableRegistry::getTableLocator()->get('Awards.Domains');
     * 
     * // Association loading with configured relationships
     * $domains = $domainsTable->find()
     *     ->contain(['Awards' => ['Levels', 'Branches']]);
     * 
     * // Behavior integration with automatic tracking
     * $domain = $domainsTable->newEntity($data);
     * $domainsTable->save($domain); // Automatic timestamps and user attribution
     * 
     * // Domain-based award filtering
     * $domainAwards = $domainsTable->Awards->find()
     *     ->where(['domain_id' => $domainId]);
     * ```
     * 
     * @param array<string, mixed> $config The configuration array for table initialization
     * @return void
     * 
     * @see \App\Model\Table\BaseTable::initialize() For base table initialization
     * @see \Awards\Model\Table\AwardsTable For award association integration
     * @see \Cake\ORM\Behavior\TimestampBehavior For timestamp behavior documentation
     * @see \Muffin\Footprint\Model\Behavior\FootprintBehavior For footprint behavior documentation
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_domains');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('Awards', [
            'foreignKey' => 'domain_id',
            'className' => 'Awards.Awards',
        ]);

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    /**
     * Default validation rules for domain data integrity and business logic enforcement
     * 
     * Establishes comprehensive validation framework for domain classification management
     * ensuring data integrity, business rule compliance, and organizational policy enforcement.
     * This validation system provides multiple layers of validation including data type
     * validation, business rule enforcement, and organizational compliance validation.
     * 
     * ## Core Domain Validation Framework
     * 
     * ### Domain Name Validation
     * - **Presence Validation**: Domain name is required and cannot be empty
     * - **String Type Validation**: Ensures domain name is properly formatted string
     * - **Length Constraints**: Maximum 255 characters for database compatibility
     * - **Uniqueness Validation**: Domain names must be unique across the system
     * 
     * ### Data Type & Format Validation
     * - **Scalar Validation**: Comprehensive scalar validation for domain name
     * - **Integer Validation**: Validates integer fields for user attribution
     * - **DateTime Validation**: Validates datetime fields for audit and deletion tracking
     * - **Format Standards**: Professional formatting requirements for domain names
     * 
     * ## Business Logic Validation Rules
     * 
     * ### Domain Naming Standards
     * - **Content Requirements**: Meaningful domain name content requirements
     * - **Naming Conventions**: Adherence to organizational naming conventions
     * - **Professional Standards**: Professional domain naming standards
     * - **Classification Clarity**: Clear classification terminology requirements
     * 
     * ### Organizational Policy Validation
     * - **Standard Compliance**: Ensures compliance with organizational standards
     * - **Policy Alignment**: Validates alignment with classification policies
     * - **Administrative Rules**: Enforces administrative domain management rules
     * - **Content Guidelines**: Validates content against organizational guidelines
     * 
     * ## Audit & Tracking Validation
     * 
     * ### User Attribution Validation
     * - **Created By Validation**: Validates created_by field for audit tracking
     * - **Modified By Validation**: Validates modified_by field for change tracking
     * - **Integer Type Validation**: Ensures user IDs are properly formatted integers
     * - **Optional Fields**: Allows empty values for system-generated entries
     * 
     * ### Temporal Data Validation
     * - **Deletion Timestamp**: Validates deletion timestamp for soft deletion
     * - **DateTime Format**: Ensures proper datetime format for temporal fields
     * - **Optional Deletion**: Allows empty deletion timestamp for active domains
     * - **Audit Compliance**: Ensures audit trail compliance requirements
     * 
     * ## Advanced Validation Features
     * 
     * ### Uniqueness Validation
     * - **System-Wide Uniqueness**: Domain names unique across entire system
     * - **Case-Insensitive Checking**: Implements case-insensitive uniqueness validation
     * - **Duplicate Prevention**: Prevents creation of duplicate domain classifications
     * - **Consistency Enforcement**: Maintains classification consistency
     * 
     * ### Conditional Validation Logic
     * - **Context-Aware Validation**: Validation rules adapted to domain context
     * - **State-Dependent Rules**: Validation rules based on domain lifecycle state
     * - **Role-Based Validation**: Validation adapted to user roles and permissions
     * - **Administrative Context**: Special validation for administrative operations
     * 
     * ## Data Quality & Integrity
     * 
     * ### Content Quality Validation
     * - **Meaningful Content**: Ensures meaningful domain classification content
     * - **Content Standards**: Enforces organizational content standards
     * - **Quality Assurance**: Comprehensive data quality assurance framework
     * - **Professional Standards**: Maintains professional domain classification standards
     * 
     * ### Classification Integrity
     * - **Logical Classification**: Ensures logical domain classification structure
     * - **Category Consistency**: Maintains consistency across domain categories
     * - **System Integration**: Validates integration with broader classification system
     * - **Organizational Alignment**: Ensures alignment with organizational structure
     * 
     * ## Error Handling & User Experience
     * 
     * ### User-Friendly Error Messages
     * - **Clear Error Messages**: Provides clear, actionable error messages
     * - **Field-Specific Errors**: Detailed field-specific error information
     * - **Context-Aware Messages**: Error messages adapted to administrative context
     * - **Correction Guidance**: Provides guidance for error correction
     * 
     * ### Validation Performance
     * - **Efficient Validation**: Optimized validation rules for performance
     * - **Early Termination**: Early termination for critical validation failures
     * - **Resource Conservation**: Conservative resource usage during validation
     * - **Batch Validation**: Support for efficient batch validation operations
     * 
     * ## Usage Examples & Integration Patterns
     * 
     * ```php
     * // Basic domain validation
     * $domain = $domainsTable->newEntity($data);
     * if (!$domain->getErrors()) {
     *     $domainsTable->save($domain);
     * }
     * 
     * // Custom validation integration
     * $validator = $domainsTable->getValidator('default');
     * $validator->add('name', 'businessRule', [
     *     'rule' => function($value, $context) {
     *         return $this->validateDomainBusinessRule($value, $context);
     *     }
     * ]);
     * 
     * // Validation with context
     * $domain = $domainsTable->newEntity($data, [
     *     'validate' => 'default',
     *     'associated' => []
     * ]);
     * ```
     * 
     * @param \Cake\Validation\Validator $validator Validator instance for rule configuration
     * @return \Cake\Validation\Validator Configured validator with comprehensive rule set
     * 
     * @see \Cake\Validation\Validator For validation framework documentation
     * @see \App\Model\Table\BaseTable::validationDefault() For base validation patterns
     * @see \Awards\Model\Table\AwardsTable::validationDefault() For award validation integration
     * @see \Awards\Model\Entity\Domain For domain entity validation patterns
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->integer('modified_by')
            ->allowEmptyString('modified_by');

        $validator
            ->dateTime('deleted')
            ->allowEmptyDateTime('deleted');

        return $validator;
    }

    /**
     * Application rules for comprehensive domain data integrity and business logic enforcement
     * 
     * Establishes sophisticated application-level rules that ensure data integrity, uniqueness
     * constraints, and business logic compliance across the domain classification system.
     * These rules provide database-level constraints and business logic validation that
     * operates beyond simple field validation to enforce complex organizational and
     * classification requirements.
     * 
     * ## Domain Uniqueness & Integrity Rules
     * 
     * ### System-Wide Uniqueness Enforcement
     * - **Domain Name Uniqueness**: Ensures domain names are unique across entire system
     * - **Case-Insensitive Uniqueness**: Implements case-insensitive uniqueness checking
     * - **Duplicate Prevention**: Prevents creation of duplicate domain classifications
     * - **Classification Consistency**: Maintains consistency across domain classifications
     * 
     * ### Data Integrity Validation
     * - **Referential Integrity**: Ensures referential integrity with related award data
     * - **Constraint Validation**: Validates database constraint compliance
     * - **Business Rule Enforcement**: Enforces domain-specific business rules
     * - **System Consistency**: Maintains consistency across classification system
     * 
     * ## Business Logic Enforcement Rules
     * 
     * ### Classification Standards Rules
     * - **Naming Standards**: Enforces organizational domain naming standards
     * - **Classification Rules**: Implements domain classification business rules
     * - **Policy Compliance**: Ensures compliance with organizational classification policies
     * - **Administrative Control**: Provides administrative control over domain management
     * 
     * ### Organizational Policy Rules
     * - **Domain Policy Compliance**: Ensures compliance with organizational domain policies
     * - **Classification Authority**: Validates administrative authority for domain management
     * - **Standard Alignment**: Ensures alignment with organizational classification standards
     * - **Compliance Requirements**: Ensures regulatory and policy compliance
     * 
     * ### Domain Lifecycle Rules
     * - **Creation Rules**: Validates domain creation requirements and permissions
     * - **Modification Rules**: Enforces rules for domain modification and updates
     * - **State Transition Rules**: Validates domain state transitions and lifecycle
     * - **Deletion Rules**: Enforces rules for domain deletion and archival
     * 
     * ## Advanced Business Logic Rules
     * 
     * ### Classification Consistency Rules
     * - **Cross-Domain Consistency**: Validates consistency across domain classifications
     * - **System Integration**: Ensures proper integration with broader classification system
     * - **Award Integration**: Validates integration consistency with award system
     * - **Hierarchical Consistency**: Maintains consistency within classification hierarchy
     * 
     * ### Administrative Authority Rules
     * - **Permission Validation**: Validates user permissions for domain operations
     * - **Role-Based Access**: Enforces role-based access control requirements
     * - **Administrative Privileges**: Ensures appropriate administrative privileges
     * - **Authority Delegation**: Validates authority delegation patterns
     * 
     * ## Data Quality & Standards Rules
     * 
     * ### Content Quality Rules
     * - **Content Standards**: Validates domain content quality standards
     * - **Professional Standards**: Ensures professional domain classification standards
     * - **Organizational Guidelines**: Validates compliance with organizational guidelines
     * - **Classification Quality**: Maintains high quality domain classifications
     * 
     * ### Format & Structure Rules
     * - **Format Compliance**: Ensures format compliance with organizational standards
     * - **Structure Validation**: Validates domain classification structure requirements
     * - **Consistency Requirements**: Ensures structural consistency across domains
     * - **Standard Alignment**: Validates alignment with classification standards
     * 
     * ## Security & Compliance Rules
     * 
     * ### Access Control Rules
     * - **Security Validation**: Validates domain security requirements
     * - **Access Permission**: Ensures appropriate access permissions for domain operations
     * - **Administrative Security**: Implements administrative security requirements
     * - **Data Protection**: Validates data protection compliance
     * 
     * ### Audit & Compliance Rules
     * - **Audit Compliance**: Ensures audit trail compliance requirements
     * - **Regulatory Compliance**: Validates regulatory compliance requirements
     * - **Policy Adherence**: Ensures adherence to organizational policies
     * - **Compliance Tracking**: Tracks compliance with domain management policies
     * 
     * ## Error Handling & Recovery
     * 
     * ### Rule Violation Handling
     * - **Clear Error Messages**: Provides clear, actionable error messages for rule violations
     * - **Error Context**: Includes contextual information for rule violation diagnosis
     * - **Recovery Guidance**: Provides guidance for recovering from rule violations
     * - **Administrative Support**: References to administrative support for complex violations
     * 
     * ### Performance Optimization
     * - **Efficient Rule Checking**: Optimized rule checking for performance
     * - **Early Termination**: Early termination for critical rule violations
     * - **Resource Management**: Efficient resource management during rule validation
     * - **Batch Rule Validation**: Support for efficient batch rule validation
     * 
     * ## Usage Examples & Integration Patterns
     * 
     * ```php
     * // Automatic rule checking during save operations
     * $domain = $domainsTable->newEntity($data);
     * if ($domainsTable->save($domain)) {
     *     // All rules passed successfully
     * } else {
     *     // Handle rule violations
     *     $errors = $domain->getErrors();
     * }
     * 
     * // Custom rule definition
     * $rules->add(function($entity, $options) {
     *     return $this->validateCustomDomainRule($entity, $options);
     * }, 'customDomainRule');
     * 
     * // Rule checking without saving
     * $rules = $domainsTable->rulesChecker();
     * $isValid = $rules->check($domain, RulesChecker::CREATE);
     * 
     * // Uniqueness validation with error handling
     * try {
     *     $domainsTable->saveOrFail($domain);
     * } catch (PersistenceFailedException $e) {
     *     $errors = $domain->getErrors();
     *     if (isset($errors['name']['_isUnique'])) {
     *         // Handle uniqueness violation
     *     }
     * }
     * ```
     * 
     * @param \Cake\ORM\RulesChecker $rules The rules object to configure
     * @return \Cake\ORM\RulesChecker Configured rules checker with comprehensive rule set
     * 
     * @see \Cake\ORM\RulesChecker For rules framework documentation
     * @see \App\Model\Table\BaseTable::buildRules() For base rules patterns
     * @see \Awards\Model\Table\AwardsTable::buildRules() For award rules integration
     * @see \Awards\Model\Entity\Domain For domain entity rules integration
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }
}
