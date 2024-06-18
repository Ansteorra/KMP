<?php

declare(strict_types=1);

namespace App\View\Cell;

use App\KMP\StaticHelpers;
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
        $currentRequestString = $this->request->getParam("controller") . "/" . $this->request->getParam("action");
        if ($this->request->getParam("plugin")) {
            $currentRequestString = $this->request->getParam("plugin") . "/" . $currentRequestString;
        }
        if ($this->request->getParam("pass")) {
            $currentRequestString .= "/" . $this->request->getParam("pass")[0];
        }
        $currentRequestString = strtolower($currentRequestString);
        $parents = [];
        $mainLinks = [];
        $sublinks = [];
        foreach ($menuItems as &$item) {
            $item["active"] = false;
            if ($item['type'] === 'parent') {
                $parents[$item['label']] = $item;
            } elseif ($item['type'] === 'link' && count($item['mergePath']) == 1) {
                $mainLinks[] = $item;
            } elseif ($item['type'] === 'link' && count($item['mergePath']) > 1) {
                $sublinks[] = $item;
            }
        }
        $activeFound = false;
        // add mainlinks to parents
        foreach ($mainLinks as &$mainlink) {
            if (!$activeFound && $this->isActive($mainlink, $currentRequestString)) {
                $parents[$mainlink['mergePath'][0]]["active"] = true;
                $mainlink["active"] = true;
                $activeFound = true;
            }
            $parents[$mainlink['mergePath'][0]]['children'][$mainlink["label"]] = $mainlink;
        }
        //foreach sublink to mainlink
        foreach ($sublinks as &$sublink) {
            if (!$activeFound && $this->isActive($sublink, $currentRequestString)) {
                $parents[$mainlink['mergePath'][0]]["active"] = true;
                $parents[$sublink['mergePath'][0]]['children'][$sublink['mergePath'][1]]["active"] = true;
                $sublink["active"] = true;
                $activeFound = true;
            }
            $parents[$sublink['mergePath'][0]]['children'][$sublink['mergePath'][1]]['sublinks'][$sublink["label"]] = $sublink;
        }
        //sort parents by order
        uasort($parents, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        //foreach parent sort children by order
        foreach ($parents as &$parent) {
            if (!isset($parent['children'])) continue;
            uasort($parent['children'], function ($a, $b) {
                $returnval = $a['order'] <=> $b['order'];
                return $returnval;
            });
            //foreach child sort sublinks by order
            foreach ($parent['children'] as &$child) {
                if (!isset($child['sublinks'])) continue;
                uasort($child['sublinks'], function ($a, $b) {
                    return $a['order'] <=> $b['order'];
                });
            }
        }
        return $parents;
    }
    protected function isActive($link, $currentRequestString): bool
    {
        $itemPath = StaticHelpers::makePathString($link["url"]);
        if ($itemPath === $currentRequestString) {
            return true;
        }
        if (isset($link["activePaths"])) {
            foreach ($link["activePaths"] as $activePath) {
                if ($activePath === $currentRequestString) {
                    return true;
                }
                //if $activepath ends with * then we are looking for a partial match
                if (substr($activePath, -1) === "*") {
                    $activePath = substr($activePath, 0, -2);
                    $testPath = strtolower(substr($currentRequestString, 0, strlen($activePath)));
                    if ($testPath == strtolower($activePath)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}