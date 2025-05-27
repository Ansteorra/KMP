<?php

/**
 * Navigation Parent Element
 * 
 * @var \Cake\View\View $this
 * @var array $parent
 * @var string $childHtml
 * @var array $navBarState
 */

$randomId = \App\KMP\StaticHelpers::generateToken(10);

// Check navbar state first (user's manual expand/collapse preference)
$isExpanded = false;
if (isset($navBarState[$parent['id']])) {
    $isExpanded = $navBarState[$parent['id']];
} else {
    // Fall back to active state if no user preference is stored
    $isExpanded = $parent['active'];
}

$collapsed = $isExpanded ? '' : 'collapsed';
$show = $isExpanded ? 'show' : '';
$expanded = $isExpanded ? 'true' : 'false';

$expandUrl = $this->Url->build([
    'controller' => 'NavBar',
    'action' => 'RecordExpand',
    $parent['id'],
    'plugin' => null
]);

$collapseUrl = $this->Url->build([
    'controller' => 'NavBar',
    'action' => 'RecordCollapse',
    $parent['id'],
    'plugin' => null
]);
?>

<div data-bs-target="#<?= $randomId ?>"
    data-bs-toggle="collapse"
    aria-expanded="<?= $expanded ?>"
    id="<?= $parent['id'] ?>"
    data-collapse-url="<?= $collapseUrl ?>"
    data-expand-url="<?= $expandUrl ?>"
    aria-controls="<?= $randomId ?>"
    class="navheader <?= $collapsed ?> text-start badge fs-5 mb-2 mx-1 text-bg-secondary bi <?= $parent['icon'] ?>"
    data-nav-bar-target="navHeader">
    <?= $parent['label'] ?>
</div>

<nav id='<?= $randomId ?>' class='appnav collapse <?= $show ?> nav-item ms-2 nav-underline'>
    <?= $childHtml ?>
</nav>