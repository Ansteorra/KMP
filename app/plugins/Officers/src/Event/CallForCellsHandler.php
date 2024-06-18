<?php

namespace Officers\Event;

use Cake\Event\EventListenerInterface;
use App\Event\CallForCellsHandlerBase;

class CallForCellsHandler extends CallForCellsHandlerBase
{
    protected array $viewsToTest = [
        "\Officers\View\Cell\BranchOfficersCell",
        "\Officers\View\Cell\BranchRequiredOfficersCell",
        "\Officers\View\Cell\MemberOfficersCell",
    ];
}

// From your controller, attach the UserStatistic object to the Order's event manager