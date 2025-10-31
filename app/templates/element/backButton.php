<?php

/**
 * Back Button Element
 *
 * Reusable back button component that intelligently handles navigation.
 * Uses the pageStack session variable when available for accurate back navigation,
 * falls back to browser history when pageStack is unavailable.
 *
 * Features:
 * - Smart navigation using pageStack history tracking
 * - Fallback to browser history when pageStack unavailable
 * - Bootstrap icon integration
 * - Customizable text and CSS classes
 * - Accessible markup with proper titles
 *
 * Usage:
 * ```php
 * // Basic usage (icon only)
 * <?= $this->element('backButton') ?>
 *
 * // With custom text
 * <?= $this->element('backButton', ['text' => 'Back to List']) ?>
 *
 * // With custom CSS classes
 * <?= $this->element('backButton', ['class' => 'btn btn-secondary']) ?>
 *
 * // Icon only with custom class
 * <?= $this->element('backButton', ['class' => 'text-primary fs-4']) ?>
 * ```
 *
 * @var \App\View\AppView $this
 * @var string|null $text Optional text to display after the icon (default: none)
 * @var string|null $class Optional CSS classes (default: 'bi bi-arrow-left-circle')
 * @var string|null $title Optional title attribute (default: 'Go back')
 */

// Set defaults
$text = $text ?? null;
$class = $class ?? 'bi bi-arrow-left-circle';
$title = $title ?? __('Go back');

// Get pageStack from session if available
$pageStack = $this->request->getSession()->read('pageStack') ?? [];
$historyCount = count($pageStack);

// Determine the URL to navigate to
if ($historyCount < 2) {
    // No pageStack or insufficient history - use browser back
    $url = '#';
    $onclick = 'window.history.back(); return false;';
} else {
    // Use pageStack for accurate navigation
    $url = $pageStack[$historyCount - 2];
    $onclick = null;
}

// Build the link
$linkContent = '<i class="' . h($class) . '"></i>';
if ($text) {
    $linkContent .= ' ' . h($text);
}

$linkOptions = [
    'escape' => false,
    'title' => $title,
];

if ($onclick) {
    $linkOptions['onclick'] = $onclick;
}

echo $this->Html->link($linkContent, $url, $linkOptions);
