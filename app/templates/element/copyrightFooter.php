<?php
$appName = $this->KMP->appSetting("KMP.LongSiteTitle", "Kingdom Management Portal");
$appVersion = $this->KMP->appSetting("App.version", "0.0.0");
$this->start("tb_footer"); ?>
<footer class="mt-auto text-end me-3">
    <div class="inner">
        <p>&copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?></p>
    </div>
</footer>
<?php
$this->end();
?>