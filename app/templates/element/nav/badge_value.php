<?php
/**
 * Badge Value Processing Element
 * 
 * @var \Cake\View\View $this
 * @var mixed $badgeConfig
 */

if (is_array($badgeConfig) 
    && isset($badgeConfig['class'], $badgeConfig['method'], $badgeConfig['argument'])
) {
    echo call_user_func(
        [$badgeConfig['class'], $badgeConfig['method']], 
        $badgeConfig['argument']
    );
} else {
    echo (int)$badgeConfig;
}
?>
