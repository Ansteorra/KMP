<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\ActivityGroup;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Entity-level authorization policy for ActivityGroup records.
 *
 * Inherits all CRUD authorization from BasePolicy. No activity-group-specific
 * overrides required.
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality
 * @see /docs/5.6.5-activity-security-patterns.md For security patterns
 */
class ActivityGroupPolicy extends BasePolicy {}
