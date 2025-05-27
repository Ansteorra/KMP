<?php
/**
 * Navigation Child Element
 * 
 * @var \Cake\View\View $this
 * @var array $child
 * @var \App\Model\Entity\Member $user
 */

$url = $child['url'];
$url['plugin'] = $url['plugin'] ?? false;

if (!$user->canAccessUrl($url)) {
    return;
}

$linkTypeClass = $child['linkTypeClass'] ?? 'nav-link';
$otherClasses = $child['otherClasses'] ?? '';
$activeClass = $child['active'] ? 'active' : '';

$linkLabel = __(' ' . $child['label']);

// Handle badge if present
if (isset($child['badgeValue'])) {
    $badgeValue = $this->element('nav/badge_value', ['badgeConfig' => $child['badgeValue']]);
    if ($badgeValue > 0) {
        $linkLabel .= ' ' . $this->Html->badge(strval($badgeValue), [
            'class' => $child['badgeClass'],
        ]);
    }
}

$linkBody = $this->Html->tag('span', $linkLabel, [
    'class' => 'fs-6',
    'escape' => false,
]);

$linkOptions = [
    'class' => "{$linkTypeClass} fs-6 bi {$child['icon']} mb-2 {$activeClass} {$otherClasses}",
    'escape' => false
];

echo $this->Html->link($linkBody, $url, $linkOptions);
?>
