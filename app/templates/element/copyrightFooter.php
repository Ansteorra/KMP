<?php
$appName = $this->KMP->appSetting("KMP Long Title", "Kingdom Management Portal");
$appVersion = $this->KMP->appSetting("App.version", "0.0.0");
$this->start("tb_footer"); ?>
<footer class="mt-auto text-end">
    <div class="inner">
        <p>&copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?></p>
    </div>
</footer>
<?php
$this->end();
?>