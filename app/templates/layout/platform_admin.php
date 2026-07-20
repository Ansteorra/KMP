<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * Minimal layout for the separate platform-admin surface.
 *
 * @var \App\View\AppView $this
 * @var array<string, mixed> $platformAdmin
 */
$controller = (string)$this->request->getParam('controller');
$action = (string)$this->request->getParam('action');
$navItems = [
    [
        'label' => __('Overview'),
        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Dashboard', 'action' => 'index'],
        'active' => $controller === 'Dashboard',
    ],
    [
        'label' => __('Tenants'),
        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Tenants', 'action' => 'index'],
        'active' => $controller === 'Tenants',
    ],
    [
        'label' => __('Jobs'),
        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'jobs'],
        'active' => $controller === 'Operations' && $action === 'jobs',
    ],
    [
        'label' => __('Schedules'),
        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'schedules'],
        'active' => $controller === 'Operations' && $action === 'schedules',
    ],
    [
        'label' => __('Backups'),
        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups'],
        'active' => $controller === 'Operations' && $action === 'backups',
    ],
    [
        'label' => __('Health'),
        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'health'],
        'active' => $controller === 'Operations' && $action === 'health',
    ],
];
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
<body class="platform-admin">
    <a class="visually-hidden-focusable" href="#main-content"><?= __('Skip to main content') ?></a>
    <header class="navbar navbar-expand-lg navbar-dark platform-nav">
        <div class="container-fluid">
            <?= $this->Html->link(__('KMP / Platform'), ['prefix' => 'PlatformAdmin', 'controller' => 'Dashboard', 'action' => 'index'], ['class' => 'navbar-brand']) ?>
            <?php if (!empty($platformAdmin)) : ?>
                <nav class="navbar-nav flex-row flex-wrap ms-lg-4" aria-label="Platform admin navigation">
                    <?php foreach ($navItems as $item) : ?>
                        <?= $this->Html->link(
                            $item['label'],
                            $item['url'],
                            [
                                'class' => 'nav-link',
                                'aria-current' => $item['active'] ? 'page' : null,
                            ],
                        ) ?>
                    <?php endforeach; ?>
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
    <?= $this->Vite->script('controllers') ?>
    <?= $this->Vite->script('index') ?>
</body>
</html>
