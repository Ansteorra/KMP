<?php

namespace Awards\Event;

use Cake\Event\EventListenerInterface;
use App\Event\CallForCellsHandlerBase;

class CallForCellsHandler extends CallForCellsHandlerBase
{
    protected string $pluginName = 'Awards';
    protected array $viewsToTest = [
        '\Awards\View\Cell\MemberSubmittedRecsCell',
        '\Awards\View\Cell\RecsForMemberCell',
    ];
}