<?php

declare(strict_types=1);

namespace Officers\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Department Entity - Organizational structure and office categorization
 *
 * The Department entity provides organizational structure for the Officers plugin
 * by categorizing offices into logical departmental groupings. Departments serve
 * as the top-level organizational unit in the officer hierarchy system.
 *
 * ## Key Features
 * - **Organizational Categorization**: Groups offices into logical departments
 * - **Domain Integration**: Links departments to specific operational domains
 * - **Hierarchical Structure**: Provides top-level organization for offices
 * - **Administrative Management**: Supports department-wide oversight and reporting
 * - **Soft Deletion**: Maintains referential integrity with archive capability
 *
 * ## Database Schema
 * - `id`: Primary key for department identification
 * - `name`: Human-readable department name (unique, required)
 * - `domain`: Operational domain designation for department scope
 * - `deleted`: Soft deletion timestamp for archival management
 * - Standard audit fields: `created`, `modified`, `created_by`, `modified_by`
 *
 * ## Relationships
 * - **hasMany Offices**: Contains multiple office positions within the department
 * - **Integration with Domain System**: Links to operational domain structure
 * - **Member Access**: Supports permission-based department visibility
 *
 * ## Security Architecture
 * The Department entity implements secure mass assignment protection and
 * integrates with KMP's audit trail system through BaseEntity inheritance.
 *
 * ## Usage Patterns
 * ```php
 * // Create a new department
 * $department = $departmentsTable->newEntity([
 *     'name' => 'Operations',
 *     'domain' => 'administrative'
 * ]);
 * 
 * // Find departments with offices
 * $departments = $departmentsTable->find()
 *     ->contain(['Offices'])
 *     ->where(['deleted IS' => null]);
 * 
 * // Check member access to departments
 * $accessibleDepts = $departmentsTable->departmentsMemberCanWork($user);
 * ```
 *
 * @property int $id Primary key for department identification
 * @property string $name Human-readable department name (unique, required)
 * @property string $domain Operational domain designation for department scope
 * @property \Cake\I18n\Date|null $deleted Soft deletion timestamp for archival management
 * @property \Cake\I18n\DateTime $created Record creation timestamp
 * @property \Cake\I18n\DateTime $modified Last modification timestamp
 * @property int|null $created_by User ID who created this record
 * @property int|null $modified_by User ID who last modified this record
 *
 * @property \Officers\Model\Entity\Office[] $offices Associated office positions within this department
 *
 * @see \Officers\Model\Table\DepartmentsTable For department data management operations
 * @see \Officers\Model\Entity\Office For office structure within departments
 * @see \App\Model\Entity\BaseEntity For audit trail and security features
 */
class Department extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * ## Security Configuration
     * Mass assignment protection allows controlled data modification while
     * preventing unauthorized field access. The accessible fields include:
     *
     * - `name`: Department name for organizational identification
     * - `domain`: Operational domain designation for scope management
     * - `deleted`: Soft deletion timestamp for archival operations
     * - `offices`: Associated office entities for relationship management
     *
     * ## Security Considerations
     * - Primary key (`id`) is intentionally excluded from mass assignment
     * - Audit fields (`created`, `modified`, `created_by`, `modified_by`) are managed automatically
     * - All accessible fields undergo validation before persistence
     *
     * ## Usage Example
     * ```php
     * // Safe mass assignment
     * $department = $departmentsTable->patchEntity($department, [
     *     'name' => 'Updated Department Name',
     *     'domain' => 'operational'
     * ]);
     * ```
     *
     * @var array<string, bool> Field accessibility configuration for mass assignment protection
     */
    protected array $_accessible = [
        'name' => true,
        'domain' => true,
        'deleted' => true,
        'offices' => true,
    ];
}

/**
 * ## Department Entity Usage Examples
 *
 * ### Department Creation and Management
 * ```php
 * // Create a new department
 * $departmentsTable = TableRegistry::getTableLocator()->get('Officers.Departments');
 * $department = $departmentsTable->newEntity([
 *     'name' => 'Operations Department',
 *     'domain' => 'administrative'
 * ]);
 * $departmentsTable->save($department);
 * 
 * // Update department information
 * $department = $departmentsTable->patchEntity($department, [
 *     'name' => 'Updated Operations Department',
 *     'domain' => 'operational'
 * ]);
 * $departmentsTable->save($department);
 * ```
 *
 * ### Organizational Management
 * ```php
 * // Find all active departments with their offices
 * $departments = $departmentsTable->find()
 *     ->contain(['Offices' => function ($q) {
 *         return $q->where(['Offices.deleted IS' => null]);
 *     }])
 *     ->where(['Departments.deleted IS' => null])
 *     ->orderBy(['name' => 'ASC']);
 * 
 * // Count offices within a department
 * foreach ($departments as $department) {
 *     $officeCount = count($department->offices);
 *     echo "Department: {$department->name} has {$officeCount} offices";
 * }
 * ```
 *
 * ### Administrative Operations
 * ```php
 * // Check departments a member can work with (permission-based)
 * $accessibleDepartments = $departmentsTable->departmentsMemberCanWork($user);
 * 
 * // Create dropdown options for forms
 * $departmentOptions = [];
 * foreach ($accessibleDepartments as $id => $name) {
 *     $departmentOptions[$id] = $name;
 * }
 * 
 * // Soft delete a department (preserves referential integrity)
 * $department->deleted = new DateTime();
 * $departmentsTable->save($department);
 * ```
 *
 * ### Domain Integration
 * ```php
 * // Find departments by operational domain
 * $adminDepartments = $departmentsTable->find()
 *     ->where(['domain' => 'administrative'])
 *     ->where(['deleted IS' => null]);
 * 
 * // Group departments by domain
 * $departmentsByDomain = $departmentsTable->find()
 *     ->where(['deleted IS' => null])
 *     ->orderBy(['domain' => 'ASC', 'name' => 'ASC'])
 *     ->toArray();
 * 
 * $groupedDepartments = [];
 * foreach ($departmentsByDomain as $dept) {
 *     $groupedDepartments[$dept->domain][] = $dept;
 * }
 * ```
 *
 * ### Reporting and Analytics
 * ```php
 * // Department statistics for reporting
 * $departmentStats = $departmentsTable->find()
 *     ->contain(['Offices.Officers' => function ($q) {
 *         return $q->where(['Officers.deleted IS' => null]);
 *     }])
 *     ->where(['Departments.deleted IS' => null]);
 * 
 * foreach ($departmentStats as $dept) {
 *     $totalOfficers = 0;
 *     foreach ($dept->offices as $office) {
 *         $totalOfficers += count($office->officers);
 *     }
 *     echo "Department: {$dept->name} - {$totalOfficers} active officers";
 * }
 * ```
 *
 * ### Integration with Officers Plugin
 * ```php
 * // Find department for a specific office
 * $office = $officesTable->get($officeId, ['contain' => ['Departments']]);
 * $department = $office->department;
 * 
 * // Create hierarchical navigation
 * $breadcrumb = [
 *     'Department' => $department->name,
 *     'Office' => $office->name
 * ];
 * 
 * // Permission-based department access checking
 * $canViewDepartment = $user->checkCan('view', $department);
 * $canEditDepartment = $user->checkCan('edit', $department);
 * ```
 */
