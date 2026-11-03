<?php
declare(strict_types=1);

use Cake\Routing\Exception\MissingRouteException;

/**
 * Navigation Child Element
 *
 * @var \Cake\View\View $this
 * @var array $child
 * @var \App\Model\Entity\Member $user
 */

$url = $child['url'];
$url['plugin'] = $url['plugin'] ?? false;

// if (!$user->canAccessUrl($url)) {
//     return;
// }

$linkTypeClass = $child['linkTypeClass'] ?? 'nav-link';
$otherClasses = $child['otherClasses'] ?? '';
$activeClass = $child['active'] ? 'active' : '';

$linkLabel = __(' ' . $child['label']);
$badgeHtml = '';

// Handle badge if present
if (isset($child['badgeResult'])) {
    // $badgeValue = $this->element('nav/badge_value', ['badgeConfig' => $child['badgeValue']]);
    $badgeValue = $child['badgeResult'];

    if ($badgeValue > 0) {
        $badgeHtml = ' ' . $this->Html->badge(strval($badgeValue), [
            'class' => $child['badgeClass'] . '',
        ]);
    }
}

$linkBody = $this->Icon->render($child['icon'], ['class' => 'me-1'])
    . $this->Html->tag('span', h($linkLabel), ['class' => 'fs-6', 'escape' => false])
    . $badgeHtml;

$linkOptions = [
    'class' => "{$linkTypeClass} fs-6 mb-2 {$activeClass} {$otherClasses}",
    'escape' => false,
];

try {
    echo $this->Html->link($linkBody, $url, $linkOptions);
} catch (MissingRouteException) {
    return;
}
