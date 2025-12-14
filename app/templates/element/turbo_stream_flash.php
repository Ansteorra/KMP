<?php

/**
 * Turbo Stream Flash Messages Element
 * 
 * Renders a turbo-stream that updates the flash-messages div with current flash messages.
 * Use this element in turbo-stream responses to ensure flash messages are displayed
 * even when using turbo-frame updates.
 * 
 * Usage in a turbo-stream template:
 *   <?= $this->element('turbo_stream_flash', ['flashMessages' => $flashMessages]) ?>
 * 
 * The controller should read and clear flash messages before rendering:
 *   $flashMessages = $this->request->getSession()->read('Flash');
 *   $this->request->getSession()->delete('Flash');
 * 
 * @var \App\View\AppView $this
 * @var array|null $flashMessages The flash messages array from session
 */

$flashMessages = $flashMessages ?? [];
?>
<turbo-stream action="replace" target="flash-messages">
    <template>
        <div id="flash-messages">
            <?php if (!empty($flashMessages)): ?>
                <?php foreach ($flashMessages as $key => $messages): ?>
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $text = $message['message'] ?? '';

                        // Extract type from element field (e.g., 'flash/success' -> 'success')
                        $element = $message['element'] ?? 'flash/info';
                        $type = 'info';
                        if (strpos($element, '/') !== false) {
                            $parts = explode('/', $element);
                            $type = end($parts);
                        }

                        // Map CakePHP flash types to Bootstrap alert types
                        $alertType = match ($type) {
                            'error' => 'danger',
                            'success' => 'success',
                            'warning' => 'warning',
                            'info' => 'info',
                            default => 'info'
                        };
                        ?>
                        <div class="alert alert-<?= h($alertType) ?> alert-dismissible fade show" role="alert">
                            <?= h($text) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </template>
</turbo-stream>