<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Authorization policy for BestowalStatus entities.
 *
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity, mixed ...$optionalArgs)
 * @method bool canEdit(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canDelete(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canView(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity, mixed ...$optionalArgs)
 */
class BestowalStatusPolicy extends BasePolicy
{
}
