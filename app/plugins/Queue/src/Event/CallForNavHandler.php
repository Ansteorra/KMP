<?php

namespace Queue\Event;

use App\KMP\StaticHelpers;
use Cake\Event\EventListenerInterface;

class CallForNavHandler implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            // Custom event names let you design your application events
            // as required.
            \App\View\Cell\NavigationCell::VIEW_CALL_EVENT => 'callForNav',
        ];
    }

    public function callForNav($event)
    {
        if (StaticHelpers::pluginEnabled('Queue') == false) {
            return null;
        }
        $user = $event->getData('user');
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
        }
        $appNav = [
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Queue Engine",
                "order" => 40,
                "url" => [
                    "plugin" => "Queue",
                    "controller" => "Queue",
                    "action" => "index",
                    "model" => "Queue.QueuedJobs",
                ],
                "icon" => "bi-stack",
                "activePaths" => [
                    "queue/*",
                ]
            ],
        ];
        $results = array_merge($results, $appNav);
        return $results;
    }
}