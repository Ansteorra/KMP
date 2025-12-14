<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Authorization policy for RecommendationsStatesLog entities in the Awards plugin.
 *
 * Manages audit trail access and transparency control for recommendation state history.
 * All authorization methods inherited from BasePolicy.
 *
 * @see \App\Policy\BasePolicy Base authorization functionality
 * @see \Awards\Model\Entity\RecommendationsStatesLog State log entity
 * @see /docs/5.2.14-awards-recommendations-states-log-policy.md Full documentation
 */
class RecommendationsStatesLogPolicy extends BasePolicy {}
