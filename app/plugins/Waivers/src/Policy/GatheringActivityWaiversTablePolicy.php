<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * GatheringActivityWaivers Table Authorization Policy
 *
 * Provides table-level authorization for GatheringActivityWaivers operations
 * including query scoping and bulk operations. Inherits standard authorization
 * methods from BasePolicy.
 *
 * @see /docs/5.7-waivers-plugin.md
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringActivityWaiversTablePolicy extends BasePolicy {}
