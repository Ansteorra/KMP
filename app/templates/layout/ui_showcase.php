<?php
declare(strict_types=1);

/** Candidate full-app shell, confined to the pattern showcase. */
$this->assign('main_content_wrapped', '1');
$siteTitle = $this->KMP->getAppSetting('KMP.ShortSiteTitle');
$identity = $this->request->getAttribute('identity');
$showcaseHome = $this->Url->build(['controller' => 'UiPatterns', 'action' => 'index']);
echo $this->KMP->startBlock('css');
echo $this->Vite->css('ui-patterns');
$this->KMP->endBlock();
echo $this->KMP->startBlock('tb_body_start');
?>
<body class="ui-showcase" data-controller="ui-patterns session-extender"
    data-session-extender-url-value="<?= h($this->Url->build('/keepalive')) ?>">
    <header class="ui-app-header">
        <button type="button" class="btn ui-icon-button d-lg-none" data-bs-toggle="offcanvas"
            data-bs-target="#ui-app-navigation" aria-controls="ui-app-navigation"
            aria-label="<?= __('Open application navigation') ?>">
            <?= $this->Icon->render('menu-2') ?>
        </button>
        <a class="ui-app-brand" href="<?= h($showcaseHome) ?>">
            <?= $this->Html->image(
                $this->KMP->assetUrl($this->KMP->getAppSetting('KMP.BannerLogo')),
                ['alt' => '', 'width' => 28, 'height' => 28],
            ) ?>
            <span><?= h($siteTitle) ?></span><span class="ui-brand-context"><?= __('Full app') ?></span>
        </a>
        <div class="dropdown ms-auto">
            <button class="btn ui-account-button dropdown-toggle" type="button" data-bs-toggle="dropdown"
                aria-expanded="false" aria-label="<?= __('Account and app options') ?>">
                <?= $this->Icon->render('user-circle') ?><span class="d-none d-sm-inline"><?= __('Account') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><?= $this->Html->link(
                    __('My profile'),
                    ['controller' => 'Members', 'action' => 'view', $identity->getIdentifier()],
                    ['class' => 'dropdown-item'],
                ) ?></li>
                <li><?= $this->Html->link(
                    __('Mobile quick access'),
                    ['controller' => 'App', 'action' => 'switchView', '?' => ['mode' => 'mobile']],
                    ['class' => 'dropdown-item'],
                ) ?></li>
                <?php foreach ($this->KMP->getAppSettingsStartWith('KMP.HeaderLink.') as $key => $value) : ?>
                    <?php $label = explode('.', str_replace('KMP.HeaderLink.', '', $key))[0]; ?>
                    <li><?= $this->Html->link($label, explode('|', $value)[0], ['class' => 'dropdown-item']) ?></li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><?= $this->Html->link(
                    __('Sign out'),
                    ['controller' => 'Members', 'action' => 'logout'],
                    ['class' => 'dropdown-item'],
                ) ?></li>
            </ul>
        </div>
    </header>
    <div class="ui-app-layout">
        <aside class="offcanvas-lg offcanvas-start ui-app-navigation" tabindex="-1" id="ui-app-navigation"
            aria-labelledby="ui-navigation-title">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="ui-navigation-title"><?= __('Application navigation') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#ui-app-navigation"
                    aria-label="<?= __('Close navigation') ?>"></button>
            </div>
            <div class="offcanvas-body">
                <nav aria-label="<?= __('Application') ?>">
                    <a class="ui-showcase-nav-link" aria-current="page" href="<?= h($showcaseHome) ?>">
                        <?= $this->Icon->render('layout-dashboard') ?> <?= __('Pattern showcase') ?>
                    </a>
                    <?= $this->cell('Navigation') ?>
                </nav>
            </div>
        </aside>
        <main id="main-content" class="ui-main" tabindex="-1">
<?php
$this->KMP->endBlock();
echo $this->KMP->startBlock('tb_body_end');
?>
        </main>
    </div>
</body>
<?php
$this->KMP->endBlock();
echo $this->fetch('content');
