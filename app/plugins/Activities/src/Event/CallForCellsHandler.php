<?php

namespace Activities\Event;

use Cake\Event\EventListenerInterface;
use App\Event\CallForCellsHandlerBase;

class CallForCellsHandler extends CallForCellsHandlerBase
{
    protected string $pluginName = 'Activities';
    protected array $viewsToTest = [
        "\Activities\View\Cell\PermissionActivitiesCell",
        "\Activities\View\Cell\MemberAuthorizationsCell",
        "\Activities\View\Cell\MemberAuthorizationDetailsJSONCell"
    ];
}