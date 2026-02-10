<?php

use Cake\Core\Configure;

$appName = $this->KMP->getAppSetting("KMP.LongSiteTitle");
$appVersion = $this->KMP->getAppSetting("App.version");

$footerLinks = $this->KMP->getAppSettingsStartWith("KMP.FooterLink.");
echo $this->KMP->startBlock("tb_footer"); ?>

<?php if (Configure::read('debug')) : ?>
<div data-controller="security-debug">
    <?php endif; ?>
    <footer class="mt-auto text-end me-3">
        <div class="row">
            <div class="col"></div>
            <ul class="navbar-nav flex-row px-3 col">
                <?php foreach ($footerLinks as $key => $value) :
                    $key = str_replace("KMP.FooterLink.", "", $key);
                    $keys = explode(".", $key);
                    $key = $keys[0];
                    if (count($keys) > 1) {
                        $key = $keys[1] == "no-label" ? "" : $keys[0];
                    }
                    $parts = explode("|", $value);
                    $url = $parts[0];
                    $css = "";
                    if (count($parts) > 1) {
                        $css = $parts[1];
                    }
                ?>
                <li class="nav-item text-nowrap mx-2">
                    <a class="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>"
                        href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></a>
                </li>
                <?php endforeach; ?>
                <li class="nav-item text-nowrap mx-2">
                    <?= $this->Html->link(
                        '<i class="bi bi-journal-text"></i> Changelog',
                        ['controller' => 'Pages', 'action' => 'changelog', 'plugin' => null],
                        ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]
                    ) ?>
                </li>
                <li class="nav-item text-nowrap mx-2">
                    <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []) ?>
                </li>
                <?php if (Configure::read('debug')) : ?>
                <li class="nav-item text-nowrap mx-2">
                    <a href="#" class="btn btn-sm btn-outline-warning" data-action="click->security-debug#toggle"
                        data-security-debug-target="toggleBtn">
                        Show Security Info
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="col"></div>
        </div>
        <div class="px-5">
            &copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?>
        </div>

        <?php if (Configure::read('debug')) : ?>
        <div data-security-debug-target="panel" style="display: none;" class="mt-3">
            <?php
                $currentUser = $this->Identity->get();
                echo $this->SecurityDebug->displaySecurityInfo($currentUser);
                ?>
        </div>
        <?php endif; ?>
    </footer>
    <?php if (Configure::read('debug')) : ?>
</div>
<?php endif; ?>
<?php
$this->KMP->endBlock();
?>