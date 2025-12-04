<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Authorization policy for Level entities in the Awards plugin.
 *
 * Manages access control for award levels including precedence and hierarchical ordering.
 * All authorization methods inherited from BasePolicy.
 *
 * @see \App\Policy\BasePolicy Base authorization functionality
 * @see \Awards\Model\Entity\Level Level entity
 * @see /docs/5.2.11-awards-level-policy.md Full documentation
 */
class LevelPolicy extends BasePolicy {}
