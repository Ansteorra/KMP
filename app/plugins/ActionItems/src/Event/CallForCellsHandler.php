<?php

namespace ActionItems\Event;

use Cake\Event\EventListenerInterface;
use App\Event\CallForCellsHandlerBase;

class CallForCellsHandler extends CallForCellsHandlerBase
{
    protected string $pluginName = 'ActionItems';
    protected array $viewsToTest = [];
}