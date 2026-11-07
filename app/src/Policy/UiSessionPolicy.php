<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\UiSession;

class UiSessionPolicy extends BasePolicy
{
    /** UI session metadata is self-service only. */
    public function canAccess(KmpIdentityInterface $user, UiSession $session): bool
    {
        return (string)$user->getIdentifier() === (string)$session->get('member_id');
    }
}
