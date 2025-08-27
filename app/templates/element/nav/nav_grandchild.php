<?php

/**
 * Navigation Grandchild Element
 * 
 * @var \Cake\View\View $this
 * @var array $sublink
 * @var \App\Model\Entity\Member $user
 */

$suburl = $sublink['url'];
$suburl['plugin'] = $suburl['plugin'] ?? false;

// if (!$user->canAccessUrl($suburl)) {
//     return;
// }

$linkOptions = $sublink['linkOptions'] ?? [];
$linkLabel = __(' ' . $sublink['label']);

// Handle badge if present
if (isset($sublink['badgeResult'])) {
    // $badgeValue = $this->element('nav/badge_value', ['badgeConfig' => $sublink['badgeValue']]);
    $badgeValue = $sublink['badgeResult'];
    if ($badgeValue > 0) {
        $linkLabel .= ' ' . $this->Html->badge(strval($badgeValue), [
            'class' => $sublink['badgeClass'],
        ]);
    }
}

$linkTypeClass = 'nav-link'; // Default for sublinks
$otherClasses = '';

$linkBody = $this->Html->tag('span', $linkLabel, [
    'class' => 'fs-7 bi ' . $sublink['icon'],
    'escape' => false,
]);

$linkOptions['class'] = 'sublink ' . $linkTypeClass . ' ms-4 fs-7 mb-2 ' . $otherClasses;
$linkOptions['escape'] = false;

echo $this->Html->link($linkBody, $suburl, $linkOptions);
