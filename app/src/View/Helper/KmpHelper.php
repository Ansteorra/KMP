<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\KMP\StaticHelpers;
use Cake\View\Helper;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\View\StringTemplateTrait;
use Cake\Log\Log;

/**
 * helper for KMP specific UI elements
 *
 *
 *
 *
 */
class KmpHelper extends Helper
{
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
    public function appNav($appNav, $request, $user, $Html): string
    {
        $return = "";
        foreach ($appNav as $nav) {
            if ($nav["type"] === "link") {
                if (!array_key_exists("sublinks", $nav)) {
                    $nav["sublinks"] = [];
                }
                $return .= $this->appControllerNav(
                    $nav["label"],
                    $nav["url"],
                    $request,
                    $Html,
                    $user,
                    $nav["icon"],
                    $nav["activeUrls"],
                    $nav["sublinks"],
                );
            } elseif ($nav["type"] === "parent") {
                $sectionHtml = "";
                foreach ($nav["children"] as $link) {
                    if (!array_key_exists("sublinks", $link)) {
                        $link["sublinks"] = [];
                    }
                    $sectionHtml .= $this->appControllerNav(
                        $link["label"],
                        $link["url"],
                        $request,
                        $Html,
                        $user,
                        $link["icon"],
                        $link["activeUrls"],
                        $link["sublinks"],
                    );
                }
                if ($sectionHtml !== "") {
                    $return .=
                        $this->appControllerNavSpacer(
                            $nav["label"],
                            $Html,
                            $nav["icon"],
                        ) . $sectionHtml;
                }
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

    protected function appControllerNav(
        $label,
        $url,
        $request,
        $Html,
        $user,
        $icon,
        $activeLinks = [],
        $sublinks = [],
    ): string {
        //is $urlparams a string or an array?
        $useActive = false;

        foreach ($activeLinks as $activeLink) {
            if ($this->matchingUrl($activeLink, $request)) {
                $useActive = true;
            }
        }
        if (!isset($url["plugin"])) {
            $url["plugin"] = false;
        }
        if ($user->canAccessUrl($url)) {
            $return = "";
            $activeclass = $useActive ? "active" : "";
            $return .= $Html->link(__(" " . $label), $url, [
                "class" =>
                "nav-link fs-6 bi " . $icon . " pb-0 " . $activeclass,
            ]);
            if ($useActive) {
                foreach ($sublinks as $sublink) {
                    $return .= $this->generateSubLink($sublink, $user, $Html);
                }
            }
            if (!$useActive) {
                $useActive = $this->matchingUrl($url, $request);
            }
            return $return;
        }
        return "";
    }

    protected function generateSubLink($sublink, $user, $Html): string
    {
        $return = "";
        $suburl = $sublink["url"];
        if (!isset($suburl["plugin"])) {
            $suburl["plugin"] = false;
        }
        if ($user->canAccessUrl($suburl)) {
            if (array_key_exists("linkOptions", $sublink)) {
                $linkOptions = $sublink["linkOptions"];
            } else {
                $linkOptions = [];
            }
            $linkLabel = __(" " . $sublink["label"]);
            if (array_key_exists("badgeValue", $sublink)) {
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

    protected function matchingUrl($url, $request): bool
    {
        $controller = $url["controller"];
        $action = $url["action"];
        $id = $url[0] ?? null;
        if (
            !$request->getParam("pass") ||
            !array_key_exists(0, $request->getParam("pass"))
        ) {
            $request_pass = null;
        } else {
            $request_pass = $request->getParam("pass")[0];
        }
        if ($id === "*") {
            $request_pass = "*";
        }
        $id = strval($id); //convert to string for comparison

        $id_test = strval($request_pass) === strval($id);
        //if the ID starts with 'NOT' then we are looking for the opposite
        if (substr($id, 0, 4) === "NOT ") {
            $id = substr($id, 4);
            $id_test = strval($request_pass) !== strval($id);
        }
        return $request->getParam("controller") === $controller &&
            $request->getParam("action") === $action &&
            $id_test;
    }

    protected function appControllerNavSpacer($label, $Html, $icon): string
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