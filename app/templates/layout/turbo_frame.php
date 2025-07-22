<?php

/**
 * KMP Turbo Frame Layout Template
 * 
 * Minimal layout template specifically designed for Turbo Frame responses.
 * This layout provides the absolute minimum structure needed for Turbo Drive
 * frame updates, containing only the rendered content without any wrapper elements.
 * 
 * @var \App\View\AppView $this The view instance
 * @var string $content The rendered content from the view template
 */
?>
<?= $this->fetch("content"); ?>