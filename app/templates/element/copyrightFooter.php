<?php
$appName = $this->KMP->getAppSetting("KMP.LongSiteTitle");
$appVersion = $this->KMP->getAppSetting("App.version");

$footerLinks = $this->KMP->getAppSettingsStartWith("KMP.FooterLink.");
echo $this->KMP->startBlock("tb_footer"); ?>

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
                    <a class="nav-link <?= $css ?>" href="<?= $url ?>"><?= $key ?></a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item text-nowrap mx-2">
                <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []) ?>
            </li>
        </ul>

        <div class="col"></div>
    </div>
    <div class="px-5">
        &copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?>
    </div>
</footer>
<?php
$this->KMP->endBlock();
?>