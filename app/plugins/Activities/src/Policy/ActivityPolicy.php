<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\Activity;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Entity-level authorization policy for Activity records.
 *
 * Inherits all CRUD authorization from BasePolicy. No activity-specific
 * overrides required.
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality
 * @see /docs/5.6.5-activity-security-patterns.md For security patterns
 */
class ActivityPolicy extends BasePolicy {}
