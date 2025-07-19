<?php

declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\I18n\Datetime;
use Cake\ORM\Behavior;
use Cake\ORM\Query\SelectQuery;

/**
 * ActiveWindow Behavior
 * 
 * Provides temporal filtering capabilities for entities with date-bounded lifecycles.
 * This behavior is designed for entities that have start_on and expires_on fields
 * to manage time-based validity windows.
 *
 * ## Key Features
 * - **Upcoming Filter**: Find records that start in the future or expire in the future
 * - **Current Filter**: Find records active within the current time window  
 * - **Previous Filter**: Find records that have expired
 * - **Flexible Date Handling**: Support for custom effective dates or default to current time
 * - **Null-Safe Expiration**: Handle records with no expiration date (permanent records)
 *
 * ## Database Requirements
 * Tables using this behavior must have:
 * - `start_on` datetime field - When the record becomes active
 * - `expires_on` datetime field (nullable) - When the record expires (null = never expires)
 *
 * ## Usage Examples
 * ```php
 * // In a Table class
 * $this->addBehavior('ActiveWindow');
 * 
 * // Find current active records
 * $current = $this->find('current');
 * 
 * // Find upcoming records
 * $upcoming = $this->find('upcoming');
 * 
 * // Find expired records
 * $previous = $this->find('previous');
 * 
 * // Use custom effective date
 * $futureDate = new Datetime('2025-01-01');
 * $currentAtDate = $this->find('current', effectiveDate: $futureDate);
 * ```
 *
 * ## Use Cases in KMP
 * - **Officer Assignments**: Track active, upcoming, and expired officer appointments
 * - **Warrant Periods**: Manage temporal validity of member warrants
 * - **Activity Authorizations**: Handle time-bounded activity permissions
 * - **Membership Status**: Track membership validity periods
 *
 * @see \App\Model\Table\WarrantsTable Warrant temporal management
 * @see \Officers\Model\Table\OfficersTable Officer assignment lifecycle
 * @see \Activities\Model\Table\AuthorizationsTable Activity permission windows
 * @author KMP Development Team
 * @since 1.0.0
 */
class ActiveWindowBehavior extends Behavior
{
    /**
     * Find records that are upcoming (start in the future) or have not yet expired
     *
     * This finder locates records that are either:
     * 1. Starting in the future (start_on > effective date)
     * 2. Currently active but not yet expired (expires_on > effective date or null)
     *
     * ## Logic Flow
     * 1. Get table alias for proper field referencing
     * 2. Default to current datetime if no effective date provided
     * 3. Apply WHERE conditions using OR logic for flexibility
     *
     * ## Query Structure
     * ```sql
     * WHERE (table.start_on > :effectiveDate) 
     *    OR (table.expires_on > :effectiveDate OR table.expires_on IS NULL)
     * ```
     *
     * ## Usage Examples
     * ```php
     * // Find all upcoming officer assignments
     * $upcomingOfficers = $this->Officers->find('upcoming');
     * 
     * // Find assignments upcoming as of specific date
     * $futureDate = new Datetime('2025-06-01');
     * $upcomingAtDate = $this->Officers->find('upcoming', effectiveDate: $futureDate);
     * ```
     *
     * @param SelectQuery $query The query to modify
     * @param Datetime|null $effectiveDate Date to check against (defaults to now)
     * @return SelectQuery Modified query with upcoming conditions
     * @see findCurrent() For currently active records
     * @see findPrevious() For expired records
     */
    public function findUpcoming(SelectQuery $query, ?Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table

        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }

        return $query->where([$alias . '.start_on >' => $effectiveDate, 'or' => [$alias . '.expires_on >' => $effectiveDate, $alias . '.expires_on IS' => null]]);
    }

    /**
     * Find records that are currently active within their time window
     *
     * This finder locates records that are active at the specified time by checking:
     * 1. Record has already started (start_on <= effective date)
     * 2. Record has not expired (expires_on >= effective date OR expires_on is NULL)
     *
     * ## Logic Flow
     * 1. Get table alias for proper field referencing
     * 2. Default to current datetime if no effective date provided
     * 3. Apply WHERE conditions for active window validation
     *
     * ## Query Structure
     * ```sql
     * WHERE (table.start_on <= :effectiveDate) 
     *   AND (table.expires_on >= :effectiveDate OR table.expires_on IS NULL)
     * ```
     *
     * ## Usage Examples
     * ```php
     * // Find currently active officer assignments
     * $activeOfficers = $this->Officers->find('current');
     * 
     * // Find what was active on a specific historical date
     * $historicalDate = new Datetime('2024-01-01');
     * $activeAtDate = $this->Officers->find('current', effectiveDate: $historicalDate);
     * 
     * // Find current warrants for authorization check
     * $currentWarrants = $this->Warrants->find('current')
     *     ->where(['member_id' => $memberId]);
     * ```
     *
     * @param SelectQuery $query The query to modify
     * @param Datetime|null $effectiveDate Date to check against (defaults to now)
     * @return SelectQuery Modified query with current active conditions
     * @see findUpcoming() For future records
     * @see findPrevious() For expired records
     */
    public function findCurrent(SelectQuery $query, ?Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table
        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }

        return $query->where([$alias . '.start_on <=' => $effectiveDate, 'or' => [$alias . '.expires_on >=' => $effectiveDate, $alias . '.expires_on IS' => null]]);
    }

    /**
     * Find records that have already expired (are in the past)
     *
     * This finder locates records that have definitively expired by checking:
     * 1. Record has an expiration date set (expires_on IS NOT NULL)
     * 2. Expiration date is before the effective date (expires_on < effective date)
     *
     * ## Logic Flow
     * 1. Get table alias for proper field referencing
     * 2. Default to current datetime if no effective date provided  
     * 3. Apply WHERE condition for expired records only
     *
     * ## Query Structure
     * ```sql
     * WHERE table.expires_on < :effectiveDate
     * ```
     *
     * ## Important Notes
     * - Records with NULL expires_on are never considered "previous" (they never expire)
     * - Only records with explicit expiration dates can be considered expired
     * - This is useful for historical analysis and cleanup operations
     *
     * ## Usage Examples
     * ```php
     * // Find expired officer assignments for historical reporting
     * $expiredOfficers = $this->Officers->find('previous');
     * 
     * // Find assignments that expired before a specific date
     * $cutoffDate = new Datetime('2024-12-31');
     * $expiredBefore = $this->Officers->find('previous', effectiveDate: $cutoffDate);
     * 
     * // Archive expired warrants
     * $expiredWarrants = $this->Warrants->find('previous')
     *     ->where(['archived' => false]);
     * ```
     *
     * @param SelectQuery $query The query to modify
     * @param Datetime|null $effectiveDate Date to check against (defaults to now)
     * @return SelectQuery Modified query with expired conditions
     * @see findCurrent() For currently active records
     * @see findUpcoming() For future records
     */
    public function findPrevious(SelectQuery $query, ?Datetime $effectiveDate = null): SelectQuery
    {
        //get the alias of the current table

        $alias = $this->_table->getAlias();
        if ($effectiveDate == null || !$effectiveDate instanceof Datetime) {
            $effectiveDate = Datetime::now();
        }

        return $query->where([$alias . '.expires_on <' => $effectiveDate]);
    }
}
