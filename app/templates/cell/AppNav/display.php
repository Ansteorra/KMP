<?php

/**
 * App Navigation Cell Template
 * 
 * @var \Cake\View\View $this
 * @var array $appNav
 * @var \App\Model\Entity\Member $user
 * @var array $navBarState
 */
?>

<div class='nav flex-column' data-controller='nav-bar'>
    <?php foreach ($appNav as $parent): ?>
        <?php
        $childHtml = '';
        foreach ($parent['children'] as $child) {
            $childHtml .= $this->element('nav/nav_child', [
                'child' => $child,
                'user' => $user
            ]);

            if ($child['active'] && isset($child['sublinks'])) {
                $parent['active'] = true;
                foreach ($child['sublinks'] as $sublink) {
                    $childHtml .= $this->element('nav/nav_grandchild', [
                        'sublink' => $sublink,
                        'user' => $user
                    ]);
                }
            }
        }
        ?>

        <?php if ($childHtml !== ''): ?>
            <?= $this->element('nav/nav_parent', [
                'parent' => $parent,
                'childHtml' => $childHtml,
                'navBarState' => $navBarState
            ]) ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>