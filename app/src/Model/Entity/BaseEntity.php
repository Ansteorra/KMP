<?php

declare(strict_types=1);

/**
 * Base Entity for KMP Application
 *
 * Provides branch-based authorization support via getBranchId() method.
 * All KMP entities extend this class to enable consistent authorization checks.
 */

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Base Entity for KMP Application
 *
 * @property int $id Primary key identifier
 * @property \Cake\I18n\DateTime|null $created Creation timestamp
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp
 * @property int|null $branch_id Associated branch ID (when applicable)
 */
abstract class BaseEntity extends Entity
{
    /**
     * Get the branch ID for authorization checks.
     *
     * Child classes should override for complex branch relationships.
     *
     * @return int|null The branch ID, or null if no association
     */
    public function getBranchId(): ?int
    {
        // Default implementation assumes direct branch_id property
        // Child classes should override for more complex branch relationships
        return $this->branch_id ?? null;
    }
}
