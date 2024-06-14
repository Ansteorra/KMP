<?php

declare(strict_types=1);

namespace App\View\Cell;

use Cake\View\Cell;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Log\Log;

/**
 * Navigation cell
 */
class NavigationCell extends Cell
{
    const VIEW_CALL_EVENT = 'KMP.Nav.callForNav';
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($validationQueueCount, $myQueueCount)
    {
        $user = $this->request->getAttribute('identity');
        $params = [
            "controller" => $this->request->getParam('controller'),
            "action" => $this->request->getParam('action'),
            "plugin" => $this->request->getParam('plugin'),
            $this->request->getParam('pass')
        ];

        $event = new Event(static::VIEW_CALL_EVENT, $this, ['validationQueueCount' => $validationQueueCount, "user" => $user, "params" => $params]);
        EventManager::instance()->dispatch($event);
        if ($event->getResult()) {
            $menu = $this->organizeMenu($event->getResult());
        }
        $this->set(compact('menu'));
    }
    protected function organizeMenu($menuItems)
    {
        $parents = [];
        $mainLinks = [];
        $sublinks = [];
        foreach ($menuItems as $item) {
            if ($item['type'] === 'parent') {
                $parents[$item['label']] = $item;
            } elseif ($item['type'] === 'link' && count($item['mergePath']) == 1) {
                $mainLinks[] = $item;
            } elseif ($item['type'] === 'link' && count($item['mergePath']) > 1) {
                $sublinks[] = $item;
            }
        }
        // add mainlinks to parents
        foreach ($mainLinks as $mainlink) {
            $parents[$mainlink['mergePath'][0]]['children'][$mainlink["label"]] = $mainlink;
        }
        //foreach sublink to mainlink
        foreach ($sublinks as $sublink) {
            $parents[$sublink['mergePath'][0]]['children'][$sublink['mergePath'][1]]['sublinks'][$sublink["label"]] = $sublink;
        }
        //sort parents by order
        uasort($parents, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        //foreach parent sort children by order
        foreach ($parents as $parent) {
            uasort($parent['children'], function ($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            //foreach child sort sublinks by order
            foreach ($parent['children'] as $child) {
                if (!isset($child['sublinks'])) continue;
                uasort($child['sublinks'], function ($a, $b) {
                    return $a['order'] <=> $b['order'];
                });
            }
        }
        return $parents;
    }
}