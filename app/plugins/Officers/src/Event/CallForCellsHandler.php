<?php

namespace Officers\Event;

use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;

class CallForCellsHandler implements EventListenerInterface
{
    protected array $viewsToTest = [];
    public function implementedEvents(): array
    {
        return [
            // Custom event names let you design your application events
            // as required.
            \App\Controller\AppController::VIEW_CALL_EVENT => 'callForViewCells',
        ];
    }

    public function callForViewCells($event)
    {
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
        }
        foreach ($this->viewsToTest as $view) {
            $viewCells = $view::getViewConfigForRoute($event->getData()['url']);
            if ($viewCells) {
                $results[] = $viewCells;
            }
        }
        return $results;
    }
}

// From your controller, attach the UserStatistic object to the Order's event manager