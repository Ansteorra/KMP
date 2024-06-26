<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\KMP\StaticHelpers;
use App\View\AppView;
use Cake\View\Helper;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\View\StringTemplateTrait;
use Cake\Log\Log;
use AssetMix\Mix;

/**
 * helper for KMP specific UI elements
 *
 *
 *
 *
 */
class KmpHelper extends Helper
{
    private static AppView $mainView;
    private static string $currentOpenBlock = "";

    public function beforeRender(Event $event): void
    {
        // Each cell has its own view, but the first one created is the main
        // one that we want to add scripts to, so we'll store it here…
        if (isset(self::$mainView)) {
            return;
        }
        $view = $event->getSubject();
        assert($view instanceof AppView);
        self::$mainView = $view;
    }

    public function startBlock($block): string
    {
        if (self::$currentOpenBlock != "") {
            Log::error("Block " . self::$currentOpenBlock . " was not closed before opening " . $block);
        }
        self::$mainView->start($block);
        self::$currentOpenBlock = $block;
        return self::$mainView->fetch($block);
    }

    public function endBlock()
    {
        self::$mainView->end();
        self::$currentOpenBlock = "";
    }
    /**
     * Returns a boolean icon
     *
     * @param bool $value
     * @param \Cake\View\Helper\HtmlHelper $Html
     * @return string
     */
    public function bool($value, $Html): string
    {
        return $value
            ? $Html->icon("check-circle-fill")
            : $Html->icon("x-circle");
    }
    /**
     * Builds the navigation for the app
     *
     * @param array $appNav
     * @param \Cake\Http\ServerRequest $request
     * @param \App\Model\Entity\Member $user
     * @param \Cake\View\Helper\HtmlHelper $Html
     * @return string
     */
    public function appNav($appNav, $user, $Html, $Url, $navBarState): string
    {
        if (!$navBarState) {
            $navBarState = [];
        }
        $return = "<div class='nav flex-column'>\r\n";
        foreach ($appNav as $parent) {
            $childHtml = "";
            foreach ($parent["children"] as $child) {
                $childHtml .= $this->appNavChild($child, $user, $Html);
                if ($child["active"] && isset($child["sublinks"])) {
                    $parent["active"] = true;
                    foreach ($child["sublinks"] as $sublink) {
                        $childHtml .= $this->appNavGrandchild($sublink, $user, $Html);
                    }
                }
            }
            if ($childHtml != "") {
                if (!$parent["active"]) {
                    $parent["active"] = isset($navBarState[$parent["id"]]) && $navBarState[$parent["id"]] == "true";
                }
                $return .= $this->appNavParent($parent, $childHtml, $Url);
            }
        }
        return $return . "</div>\r\n";
    }

    /**
     * Get an app setting
     *
     * @param string $key
     * @param string $fallback
     * @return mixed
     */
    public function getAppSetting(string $key, $fallback)
    {
        return StaticHelpers::getAppSetting($key, $fallback);
    }

    public function getAppSettingsStartWith(string $key): array
    {
        return StaticHelpers::getAppSettingsStartWith($key);
    }

    public function getMixScriptUrl(string $script, $Url): string
    {
        $url = $Url->script($script);
        $mixPath = (new Mix())($url);
        return $mixPath;
    }

    public function getMixStyleUrl(string $css, $Url): string
    {
        $url = $Url->css($css);
        $mixPath = (new Mix())($url);
        return $mixPath;
    }


    protected function appNavChild(
        $link,
        $user,
        $Html,
    ): string {
        $url = $link["url"];
        $label = $link["label"];
        $icon = $link["icon"];
        if (!isset($link["otherClasses"])) {
            $link["otherClasses"] = "";
        }
        if (!isset($link["linkTypeClass"])) {
            $link["linkTypeClass"] = "nav-link";
        }
        $linkTypeClass = $link["linkTypeClass"];
        $otherClasses = $link["otherClasses"];
        $return = "";
        //is $urlparams a string or an array?
        if (!isset($url["plugin"])) {
            $url["plugin"] = false;
        }
        if ($user->canAccessUrl($url)) {
            $return = "";
            $activeclass = $link["active"] ? "active" : "";
            $return .= $Html->link(__(" " . $label), $url, [
                "class" =>
                $linkTypeClass . " fs-6 bi " . $icon . " mb-2 " . $activeclass . " " . $otherClasses,
            ]);
            return $return;
        }
        return "";
    }

    protected function appNavGrandchild($sublink, $user, $Html): string
    {
        $return = "";
        $suburl = $sublink["url"];
        if (!isset($suburl["plugin"])) {
            $suburl["plugin"] = false;
        }
        if ($user->canAccessUrl($suburl)) {
            if (isset($sublink["linkOptions"])) {
                $linkOptions = $sublink["linkOptions"];
            } else {
                $linkOptions = [];
            }
            $linkLabel = __(" " . $sublink["label"]);
            if (isset($sublink["badgeValue"])) {
                if (
                    is_array($sublink["badgeValue"])
                    && isset($sublink["badgeValue"]["class"])
                    && isset($sublink["badgeValue"]["method"])
                    && isset($sublink["badgeValue"]["argument"])
                ) {
                    $class = $sublink["badgeValue"]["class"];
                    $method = $sublink["badgeValue"]["method"];
                    $argument = $sublink["badgeValue"]["argument"];
                    $badgeValue = call_user_func(array($class, $method), $argument);
                } else {
                    $badgeValue = $sublink["badgeValue"];
                }
                if ($badgeValue > 0) {
                    $linkLabel .= " " . $Html->badge(strval($badgeValue), [
                        "class" => $sublink["badgeClass"],
                    ]);
                }
            }
            if (!isset($link["otherClasses"])) {
                $link["otherClasses"] = "";
            }
            if (!isset($link["linkTypeClass"])) {
                $link["linkTypeClass"] = "nav-link";
            }
            $linkTypeClass = $link["linkTypeClass"];
            $otherClasses = $link["otherClasses"];

            $linkBody = $Html->tag(
                "span",
                $linkLabel,
                [
                    "class" => "fs-7 bi " . $sublink["icon"],
                    "escape" => false
                ],
            );
            $linkOptions["class"] =
                "sublink " . $linkTypeClass . " ms-4 fs-7 mb-2 " . $otherClasses;
            $linkOptions["escape"] = false;
            $return .= $Html->link(
                $linkBody,
                $suburl,
                $linkOptions,
            );
        }
        return $return;
    }
    protected function appNavParent($parent, $childHtml, $Url): string
    {
        $randomId = StaticHelpers::generateToken(10);
        $collaped = $parent["active"] ? "" : "collapsed";
        $show = $parent["active"] ? "show" : "";
        $expanded = $parent["active"] ? "true" : "false";
        $expandUrl = $Url->build(["controller" => "NavBar", "action" => "RecordExpand", $parent["id"], "plugin" => null]);
        $collapseUrl = $Url->build(["controller" => "NavBar", "action" => "RecordCollapse", $parent["id"], "plugin" => null]);
        $return = '<div data-bs-target="#' . $randomId . '" data-bs-toggle="collapse" aria-expanded="' . $expanded . '" id="' . $parent['id'] . '" . ' .
            'data-collapse-url="' . $collapseUrl . '" data-expand-url="' . $expandUrl . '"' .
            'aria-controls="' . $randomId . '" class="navheader ' . $collaped . ' text-start badge fs-5 mb-2 mx-1 text-bg-secondary bi ' .
            $parent['icon'] .
            '"> ' .
            $parent['label'] .
            "</div> \r\n" .
            "<nav id='" . $randomId . "' class='appnav collapse " . $show . " nav-item ms-2 nav-underline'>" .
            $childHtml . "</nav> \r\n";
        return $return;
    }
}