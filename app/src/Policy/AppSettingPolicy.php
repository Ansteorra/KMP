<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\AppSetting;

/** Authorization for application settings. */
class AppSettingPolicy extends BasePolicy
{
    /** Apply the existing settings edit permission to the dedicated calendar page. */
    public function canPublicCalendar(KmpIdentityInterface $user, AppSetting $setting): bool
    {
        return $this->canEdit($user, $setting);
    }
}
