<?php
declare(strict_types=1);

use Cake\Routing\Exception\MissingRouteException;

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
$badgeHtml = '';

// Handle badge if present
if (isset($sublink['badgeResult'])) {
    // $badgeValue = $this->element('nav/badge_value', ['badgeConfig' => $sublink['badgeValue']]);
    $badgeValue = $sublink['badgeResult'];
    if ($badgeValue > 0) {
        $badgeHtml = ' ' . $this->Html->badge(strval($badgeValue), [
            'class' => $sublink['badgeClass'] . ' kmp-nav-count',
        ]);
    }
}

$linkTypeClass = 'nav-link'; // Default for sublinks
$otherClasses = '';

$linkBody = $this->Icon->render($sublink['icon'], ['class' => 'me-1 kmp-nav-icon'])
    . $this->Html->tag('span', h($linkLabel), ['class' => 'fs-7 kmp-nav-label', 'escape' => false])
    . $badgeHtml;

$linkOptions['class'] = 'sublink ' . $linkTypeClass . ' kmp-nav-item ms-4 fs-7 mb-2 ' . $otherClasses;
$linkOptions['escape'] = false;

try {
    echo $this->Html->link($linkBody, $suburl, $linkOptions);
} catch (MissingRouteException) {
    return;
}
