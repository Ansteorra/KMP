<?php
$appName = $this->KMP->getAppSetting("KMP.LongSiteTitle", "Kingdom Management Portal");
$appVersion = $this->KMP->getAppSetting("App.version", "0.0.0");
echo $this->KMP->startBlock("tb_footer"); ?>
<footer class="mt-auto text-end me-3">
    <div style="display:inline">
        <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []) ?>
    </div>
    <div style="display:inline">
        &copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?>
    </div>
</footer>
<?php
$this->KMP->endBlock();
?>