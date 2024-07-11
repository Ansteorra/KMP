<?php

namespace App\Event;

use Cake\Event\EventListenerInterface;
use App\KMP\StaticHelpers;

class CallForCellsHandlerBase implements EventListenerInterface
{
    protected string $pluginName = '';
    protected array $viewsToTest = [];

    public function implementedEvents(): array
    {
        return [
            // Custom event names let you design your application events
            // as required.
            \App\Controller\AppController::VIEW_PLUGIN_EVENT => 'callForViewCells',
        ];
    }

    public function callForViewCells($event)
    {
        if ($this->pluginName && !StaticHelpers::pluginEnabled($this->pluginName)) {
            return [];
        }
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
        }
        foreach ($this->viewsToTest as $view) {
            $viewCells = $view::getViewConfigForRoute($event->getData()['url'], $event->getData()['currentUser']);
            if ($viewCells) {
                $results[] = $viewCells;
            }
        }
        return $results;
    }
}