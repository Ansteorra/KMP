<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\Model\Entity\Department;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Office Authorization Policy
 *
 * Controls entity-level access for Office operations including hierarchical
 * management, warrant requirements, and assignment authorization.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class OfficePolicy extends BasePolicy {}
