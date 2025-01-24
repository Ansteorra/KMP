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
        $view = $event->getSubject();
        assert($view instanceof AppView);
        if (isset(self::$mainView) && $view->getTemplatePath() != 'Error') {
            return;
        }
        self::$mainView = $view;
    }

    public function makeCsv($data)
    {
        return StaticHelpers::arrayToCsv($data);
    }

    public function startBlock($block): string
    {
        self::$mainView->start($block);
        self::$currentOpenBlock = $block;
        return self::$mainView->fetch($block);
    }

    public function endBlock()
    {
        self::$mainView->end();
        self::$currentOpenBlock = "";
    }
    public function comboBoxControl($Form, $inputField, $resultField, $data, $label, $required, $allowOtherValues, $additionalAttrs)
    {
        echo "<div data-controller='ac' role='combobox'";
        echo "class='position-relative mb-3 kmp_autoComplete' data-ac-allow-other-value='" . ($allowOtherValues ? "true" : "false") . "' data-ac-min-length-value=0 ";
        if ($additionalAttrs) {
            foreach ($additionalAttrs as $key => $value) {
                echo $key . "='" . staticHelpers::makeSafeForHtmlAttribute($value) . "' ";
            }
        }
        echo ">";
        echo "<script type='application/json' data-ac-target='dataList' class='d-none'>";
        $listData = [];
        foreach ($data as $key => $value) {
            $enabled = true;
            //check if the key is a string or an int
            if (!is_int($key)) {
                //if the key includes a | then the first part is the key and the second part is the enable check
                if (strpos($key, "|") !== false) {
                    $keyParts = explode("|", $key);
                    $key = $keyParts[0];
                    $enabled = $keyParts[1] == 'true';
                }
            }
            //check if the value is a string
            if (is_string($value)) {
                $listData[] = ["value" => $key, "text" => $value, "enabled" => $enabled];
            } else {
                $listData[] = ["value" => $key, "text" => $value["text"], "data" => $value, "enabled" => $enabled];
            }
        }
        echo json_encode($listData);
        echo "</script>";
        echo $Form->control($resultField, [
            "type" => "hidden",
            "data-ac-target" => "hidden",
        ]);
        echo $Form->control($inputField, [
            "type" => "hidden",
            "data-ac-target" => "hiddenText",
        ]);
        $textEntry = $Form->control($inputField . "-Disp", [
            'required' => !$required ? false : true,
            "type" => "text",
            "label" => $label != null ? $label : null,
            "data-ac-target" => "input",
            "container" => ["style" => "margin:0 !important;",],
            "append" => ["clearBtn"]
        ]);
        //replace <span class="input-group-text">clearBtn</span>
        $textEntry = str_replace("<span class=\"input-group-text\">clearBtn</span>", "<button class='btn btn-outline-secondary' data-ac-target='clearBtn' data-action='ac#clear' disabled >Clear</button>", $textEntry);
        echo $textEntry;
        echo "<ul data-ac-target='results' class='list-group z-3 col-12 position-absolute auto-complete-list' hidden='hidden' ></ul></div>";
    }

    public function autoCompleteControl($Form, $inputField, $resultField, $url, $label, $required, $allowOtherValues, $minLength, $additionalAttrs,)
    {
        echo "<div data-controller='ac' data-ac-url-value='" . $url . "'role='combobox'";
        //if $additionalAttrs has class then add it to the div
        $class = "";
        if ($additionalAttrs && isset($additionalAttrs["class"])) {
            $class = $additionalAttrs["class"];
        }
        echo "class='position-relative mb-3 kmp_autoComplete " . $class . " ' data-ac-allow-other-value='" . ($allowOtherValues ? "true" : "false") . "' ";
        echo "data-ac-min-length-value='" . $minLength . "' ";
        if ($additionalAttrs) {
            foreach ($additionalAttrs as $key => $value) {
                echo $key . "='" . staticHelpers::makeSafeForHtmlAttribute($value) . "' ";
            }
        }
        echo ">";
        echo $Form->control($resultField, [
            "type" => "hidden",
            "data-ac-target" => "hidden",
        ]);
        echo $Form->control($inputField, [
            "type" => "hidden",
            "data-ac-target" => "hiddenText",
        ]);
        $textEntry = $Form->control($inputField . "-Disp", [
            'required' => !$required ? false : true,
            "type" => "text",
            "label" => $label != null ? $label : null,
            "data-ac-target" => "input",
            "container" => ["style" => "margin:0 !important;",],
            "append" => ["clearBtn"]
        ]);
        //replace <span class="input-group-text">clearBtn</span>
        $textEntry = str_replace("<span class=\"input-group-text\">clearBtn</span>", "<button class='btn btn-outline-secondary' data-ac-target='clearBtn' data-action='ac#clear' disabled >Clear</button>", $textEntry);
        echo $textEntry;
        echo "<ul data-ac-target='results' class='list-group z-3 col-12 position-absolute auto-complete-list' hidden='hidden' ></ul></div>";
    }
    /**
     * Returns a boolean icon
     *
     * @param bool $value
     * @param \Cake\View\Helper\HtmlHelper $Html
     * @return string
     */
    public function bool($value, $Html, array $options = []): string
    {
        return $value
            ? $Html->icon("check-circle-fill", $options)
            : $Html->icon("x-circle", $options);
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
        $return = "<div class='nav flex-column' data-controller='nav-bar'>\r\n";
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
    public function getAppSetting(string $key, $fallback = null)
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
            $linkLabel = __(" " . $link["label"]);
            if (isset($link["badgeValue"])) {
                if (
                    is_array($link["badgeValue"])
                    && isset($link["badgeValue"]["class"])
                    && isset($link["badgeValue"]["method"])
                    && isset($link["badgeValue"]["argument"])
                ) {
                    $class = $link["badgeValue"]["class"];
                    $method = $link["badgeValue"]["method"];
                    $argument = $link["badgeValue"]["argument"];
                    $badgeValue = call_user_func(array($class, $method), $argument);
                } else {
                    $badgeValue = $link["badgeValue"];
                }
                if ($badgeValue > 0) {
                    $linkLabel .= " " . $Html->badge(strval($badgeValue), [
                        "class" => $link["badgeClass"],
                    ]);
                }
            }
            $activeclass = $link["active"] ? "active" : "";

            $linkBody = $Html->tag(
                "span",
                $linkLabel,
                [
                    "class" => "fs-6",
                    "escape" => false
                ],
            );
            $linkOptions["class"] = $linkTypeClass . " fs-6 bi " . $icon . " mb-2 " . $activeclass . " " . $otherClasses;
            $linkOptions["escape"] = false;
            $return .= $Html->link(
                $linkBody,
                $url,
                $linkOptions,
            );

            //$return .= $Html->link($linkLabel, $url, [
            //    "class" =>
            //    $linkTypeClass . " fs-6 bi " . $icon . " mb-2 " . $activeclass . " " . $otherClasses,
            //]);
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
        $return = '<div data-bs-target="#' . $randomId . '" data-bs-toggle="collapse" aria-expanded="' . $expanded . '"
    id="' . $parent['id'] . '" . ' .
            ' data-collapse-url="' . $collapseUrl . '" data-expand-url="' . $expandUrl . '"' .
            ' aria-controls="' . $randomId . '" class="navheader ' . $collaped . ' text-start badge fs-5 mb-2 mx-1 text-bg-secondary bi ' .
            $parent['icon'] .
            '" data-nav-bar-target="navHeader"> ' .
            $parent['label'] .
            "</div> \r\n" .
            "<nav id='" . $randomId . "' class='appnav collapse " . $show . " nav-item ms-2 nav-underline'>" .
            $childHtml . "</nav> \r\n";
        return $return;
    }
}