<?php

/**
 * Browser/Turbo Back with an authorized list link for directly opened records.
 *
 * @var \App\View\AppView $this
 * @var string|null $text
 * @var string|null $class
 * @var string|null $title
 */
use App\Services\NavigationParentService;

// Set defaults
$text = $text ?? null;
$class = $class ?? '';
$title = $title ?? __('Go back');

$url = (new NavigationParentService())->url($this->getRequest());

// Build the link
$linkContent = $this->Icon->render($icon ?? 'circle-arrow-left', ['class' => $class]);
if ($text) {
    $linkContent .= ' ' . h($text);
}

$linkOptions = [
    'escape' => false,
    'title' => $title,
    'aria-label' => $title,
    'data-turbo-frame' => '_top',
    'data-turbo' => 'false',
    'data-kmp-back' => 'true',
];

$linkOptions['data-controller'] = 'history-back';
$linkOptions['data-action'] = 'history-back#go';

echo $this->Html->link($linkContent, $url, $linkOptions);
