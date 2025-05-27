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
$collapsed = $parent['active'] ? '' : 'collapsed';
$show = $parent['active'] ? 'show' : '';
$expanded = $parent['active'] ? 'true' : 'false';

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