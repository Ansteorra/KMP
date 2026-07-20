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
        // Top-link: render as a direct clickable link at the parent level
        if (!empty($parent['isTopLink'])):
            $url = $parent['url'];
            $url['plugin'] = $url['plugin'] ?? false;
            $activeClass = !empty($parent['active']) ? 'active' : '';
            $badgeHtml = '';
            if (isset($parent['badgeResult']) && $parent['badgeResult'] > 0) {
                $badgeHtml = ' ' . $this->Html->badge(strval($parent['badgeResult']), [
                    'class' => $parent['badgeClass'] ?? 'bg-danger',
                ]);
            }
            $linkBody = $parent['label'] . $badgeHtml;
        ?>
            <?= $this->Html->link(
                $linkBody,
                $url,
                [
                    'class' => "navheader text-start badge fs-5 mb-2 mx-1 text-bg-secondary bi {$parent['icon']} {$activeClass}",
                    'style' => 'text-decoration:none;',
                    'escape' => false,
                    'id' => $parent['id'] ?? null,
                ]
            ) ?>
        <?php continue; endif; ?>

        <?php
        $childHtml = '';
        if(!isset($parent['children']))
        {
            continue;
        }

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