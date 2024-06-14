<?php

namespace Activities\Event;

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
        $user = $event->getData('user');
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
        }
        $appNav = [[
            "type" => "link",
            "mergePath" => ["Members", $user->sca_name],
            "label" => "My Auth Queue",
            "url" => [
                "controller" => "AuthorizationApprovals",
                "plugin" => "Activities",
                "model" => "Activities.AuthorizationApprovals",
                "action" => "myQueue",
            ],
            "icon" => "bi-person-fill-check",
            "badgeClass" => "bg-danger",
            "badgeValue" => $event->getData('myQueueCount'),
        ]];




        $results = array_merge($results, $appNav);
        return $results;
    }
}