<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use App\View\AppView;
use AssetMix\Mix;
use Cake\Event\Event;
use Cake\View\Helper;
use Cake\View\Helper\HtmlHelper;

/**
 * helper for KMP specific UI elements
 */
class KmpHelper extends Helper
{
    private static AppView $mainView;
    private static string $currentOpenBlock = '';

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

    /**
     * Convert array to CSV format
     */
    public function makeCsv(array $data): string
    {
        return StaticHelpers::arrayToCsv($data);
    }

    /**
     * Start a view block
     */
    public function startBlock(string $block): string
    {
        self::$mainView->start($block);
        self::$currentOpenBlock = $block;

        return self::$mainView->fetch($block);
    }

    /**
     * End the current view block
     */
    public function endBlock(): void
    {
        self::$mainView->end();
        self::$currentOpenBlock = '';
    }

    /**
     * Render combo box control using element
     */
    public function comboBoxControl(
        $Form,
        string $inputField,
        string $resultField,
        array $data,
        ?string $label = null,
        bool $required = false,
        bool $allowOtherValues = false,
        array $additionalAttrs = []
    ): string {
        return $this->_View->element('combo_box_control', compact(
            'Form',
            'inputField',
            'resultField',
            'data',
            'label',
            'required',
            'allowOtherValues',
            'additionalAttrs'
        ));
    }

    /**
     * Render auto complete control using element
     */
    public function autoCompleteControl(
        $Form,
        string $inputField,
        string $resultField,
        string $url,
        ?string $label = null,
        bool $required = false,
        bool $allowOtherValues = false,
        int $minLength = 1,
        array $additionalAttrs = []
    ): string {
        return $this->_View->element('auto_complete_control', compact(
            'Form',
            'inputField',
            'resultField',
            'url',
            'label',
            'required',
            'allowOtherValues',
            'minLength',
            'additionalAttrs'
        ));
    }

    /**
     * Returns a boolean icon
     *
     * @param bool $value
     * @param \Cake\View\Helper\HtmlHelper $Html
     * @return string
     */
    public function bool(bool $value, HtmlHelper $Html, array $options = []): string
    {
        return $value
            ? $Html->icon('check-circle-fill', $options)
            : $Html->icon('x-circle', $options);
    }

    /**
     * Render application navigation using cell
     */
    public function appNav(array $appNav, Member $user, array $navBarState = []): string
    {
        return (string)$this->_View->cell('AppNav', [$appNav, $user, $navBarState]);
    }

    /**
     * Get application setting
     */
    public function getAppSetting(string $key, ?string $fallback = null): mixed
    {
        return StaticHelpers::getAppSetting($key, $fallback);
    }

    /**
     * Get application settings that start with key
     */
    public function getAppSettingsStartWith(string $key): array
    {
        return StaticHelpers::getAppSettingsStartWith($key);
    }

    /**
     * Get Mix script URL with versioning
     */
    public function getMixScriptUrl(string $script, $Url): string
    {
        $url = $Url->script($script);
        return (new Mix())($url);
    }

    /**
     * Get Mix style URL with versioning
     */
    public function getMixStyleUrl(string $css, $Url): string
    {
        $url = $Url->css($css);
        return (new Mix())($url);
    }
}