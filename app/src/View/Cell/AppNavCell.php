<?php
declare(strict_types=1);

namespace App\View\Cell;

use App\Model\Entity\Member;
use Cake\View\Cell;

/**
 * App Navigation Cell
 */
class AppNavCell extends Cell
{
    /**
     * Display the application navigation
     *
     * @param array $appNav Navigation structure
     * @param \App\Model\Entity\Member $user Current user
     * @param array $navBarState Navigation bar state
     * @return void
     */
    public function display(array $appNav, Member $user, array $navBarState = []): void
    {
        $this->set(compact('appNav', 'user', 'navBarState'));
    }
}
