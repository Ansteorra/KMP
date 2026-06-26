<?php

/**
 * Bestowal quick To-Dos modal.
 *
 * Opened from the bestowals grid "To-Dos" row action. Loads the selected
 * bestowal's preparation-checks checklist into a turbo-frame so eligible checks
 * can be completed without leaving the grid.
 *
 * @var \App\View\AppView $this
 * @var string $modalId
 */

$modalId = $modalId ?? 'bestowalTodosModal';
$turboFrameUrl = $this->URL->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'bestowalTodos',
]);
?>
<div id="bestowal_todos_root"
    data-controller="awards-bestowal-todos"
    data-awards-bestowal-todos-modal-id-value="<?= h($modalId) ?>"
    data-awards-bestowal-todos-outlet-btn-outlet=".todos-bestowal"
    data-awards-bestowal-todos-turbo-frame-url-value="<?= h($turboFrameUrl) ?>">
<?php
echo $this->Modal->create(__('Bestowal To-Dos'), [
    'id' => $modalId,
    'close' => true,
]);
?>
<turbo-frame id="bestowalTodosQuick"
    loading="eager"
    data-awards-bestowal-todos-target="turboFrame">
    <div class="text-center p-4 text-muted"><?= __('Loading...') ?></div>
</turbo-frame>
<?php
echo $this->Modal->end([
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
    ]),
]);
?>
</div>
