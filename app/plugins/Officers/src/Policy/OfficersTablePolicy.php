<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\DepartmentsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Officers Table Authorization Policy
 *
 * Provides table-level authorization control for Officers table operations.
 * Extends BasePolicy with SKIP_BASE to allow unrestricted table access
 * while entity-level controls are handled by OfficerPolicy.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficersTablePolicy extends BasePolicy
{
    public const SKIP_BASE = 'true';
}
