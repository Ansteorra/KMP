<?php
$appName = $this->KMP->getAppSetting("KMP.LongSiteTitle", "Kingdom Management Portal");
$appVersion = $this->KMP->getAppSetting("App.version", "0.0.0");
$this->start("tb_footer"); ?>
<footer class="mt-auto text-end me-3">
    <div style="display:inline">
        <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', [], ['rootView' => $this]) ?>
    </div>
    <div style="display:inline">
        &copy;<?= h(date("Y")) ?> <?= h($appName) ?> : <?= h($appVersion) ?>
    </div>
</footer>
<?php
$this->end();
?>