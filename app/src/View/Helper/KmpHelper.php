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
    public function appNav($appNav, $user, $Html): string
    {
        $return = "";
        foreach ($appNav as $parent) {
            $childHtml = "";
            foreach ($parent["children"] as $child) {
                $childHtml .= $this->appNavChild($child, $user, $Html);
                if ($child["active"] && isset($child["sublinks"])) {
                    foreach ($child["sublinks"] as $sublink) {
                        $childHtml .= $this->appNavGrandchild($sublink, $user, $Html);
                    }
                }
            }
            if ($childHtml != "") {
                $return .= $this->appNavParent($parent["label"], $Html, $parent["icon"]) . $childHtml;
            }
        }
        return $return;
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
                "nav-link fs-6 bi " . $icon . " pb-0 " . $activeclass,
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
                if ($sublink["badgeValue"] > 0) {
                    $linkLabel .= " " . $Html->badge(strval($sublink["badgeValue"]), [
                        "class" => $sublink["badgeClass"],
                    ]);
                }
            }
            $linkBody = $Html->tag(
                "span",
                $linkLabel,
                [
                    "class" => "fs-7 bi " . $sublink["icon"],
                    "escape" => false
                ],
            );
            $linkOptions["class"] =
                "sublink nav-link ms-3 fs-7 pt-0";
            $linkOptions["escape"] = false;
            $return .= $Html->link(
                $linkBody,
                $suburl,
                $linkOptions,
            );
        }
        return $return;
    }
    protected function appNavParent($label, $Html, $icon): string
    {
        $return =
            '<div class="badge fs-5 text-bg-secondary bi ' .
            $icon .
            '"> ' .
            $label .
            "</div>";
        return $return;
    }
}