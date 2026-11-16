<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */

echo $this->Form->textarea('note', [
    'hidden' => true,
    'id' => false,
    'aria-label' => __('Note (optional)'),
    'data-bestowal-completion-note' => true,
]);
