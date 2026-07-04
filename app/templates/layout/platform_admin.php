<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * Minimal layout for the separate platform-admin surface.
 *
 * @var \App\View\AppView $this
 * @var array<string, mixed> $platformAdmin
 */
?>
<!doctype html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($this->fetch('title') ?: __('Platform Admin')) ?></title>
    <?= $this->Html->meta('csrf-token', $this->request->getAttribute('csrfToken')) ?>
    <?= $this->Vite->css('app') ?>
</head>
<body class="platform-admin bg-light">
    <a class="visually-hidden-focusable" href="#main-content"><?= __('Skip to main content') ?></a>
    <header class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <?= $this->Html->link(__('Platform Admin'), ['prefix' => 'PlatformAdmin', 'controller' => 'Dashboard', 'action' => 'index'], ['class' => 'navbar-brand']) ?>
            <?php if (!empty($platformAdmin)) : ?>
                <nav class="navbar-nav flex-row flex-wrap gap-2" aria-label="Platform admin navigation">
                    <?= $this->Html->link(__('Dashboard'), ['prefix' => 'PlatformAdmin', 'controller' => 'Dashboard', 'action' => 'index'], ['class' => 'nav-link px-2']) ?>
                    <?= $this->Html->link(__('Tenants'), ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'], ['class' => 'nav-link px-2']) ?>
                    <?= $this->Html->link(__('Backups'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups'], ['class' => 'nav-link px-2']) ?>
                    <?= $this->Html->link(__('Health'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'health'], ['class' => 'nav-link px-2']) ?>
                </nav>
                <span class="navbar-text small ms-lg-auto">
                    <?= h($platformAdmin['email'] ?? __('platform admin')) ?>
                    <?= $this->Html->link(__('Logout'), ['prefix' => 'PlatformAdmin', 'controller' => 'Auth', 'action' => 'logout'], ['class' => 'link-light ms-3']) ?>
                </span>
            <?php endif; ?>
        </div>
    </header>
    <main id="main-content" class="container-fluid py-4" tabindex="-1">
        <div id="flash-messages">
            <?= $this->Flash->render() ?>
        </div>
        <?= $this->fetch('content') ?>
    </main>
</body>
</html>
