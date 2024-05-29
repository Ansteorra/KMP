<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Log\Log;

/**
 * Reports Controller
 *
 *
 */
class ReportsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }

    public function rolesList()
    {
        $this->authorize->can($this);
    }

    public function warrentsRoster($hide = "no")
    {
        $this->authorize->can($this);
    }

    public function authorizations()
    {
        $this->authorize->can($this);
    }
}
