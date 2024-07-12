<?php

declare(strict_types=1);

namespace Activities\Controller;

use Cake\Log\Log;

/**
 * Reports Controller
 *
 *
 */

use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;

class ReportsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }
}