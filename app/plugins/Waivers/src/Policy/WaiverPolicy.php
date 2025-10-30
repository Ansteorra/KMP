<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * Waiver Policy (for GatheringWaiver)
 *
 * Manages authorization for waiver operations.
 * Authorization is driven by the Roles → Permissions → Policies system.
 */
class WaiverPolicy extends BasePolicy {}
