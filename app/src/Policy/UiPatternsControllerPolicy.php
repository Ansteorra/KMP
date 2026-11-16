<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;

/** Any authenticated member may review fictional examples, without domain access. */
class UiPatternsControllerPolicy extends BasePolicy
{
    /** Allow the read-only showcase; example capabilities never authorize real actions. */
    public function canIndex(KmpIdentityInterface $user, mixed $resource, ...$optionalArgs): bool
    {
        return true;
    }

    /** Synthetic grid rows and exports share the authenticated showcase access. */
    public function canGridData(KmpIdentityInterface $user, mixed $resource, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $resource, ...$optionalArgs);
    }

    /** The synthetic reader has the same authenticated access as the showcase. */
    public function canReader(KmpIdentityInterface $user, mixed $resource, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $resource, ...$optionalArgs);
    }
}
