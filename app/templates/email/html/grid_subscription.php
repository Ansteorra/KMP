<?php
/** @var \App\View\AppView $this */
?>
<h1><?= h($name) ?></h1>
<p><?= __('Current results from {0}. Up to 50 matching rows are included.', h($report['label'])) ?></p>
<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%">
    <thead><tr><?php foreach ($report['headers'] as $header) :
        ?><th scope="col"><?= h($header) ?></th><?php
               endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($report['rows'] as $row) : ?>
        <tr><?php foreach ($row as $value) :
            ?><td><?= h($value) ?></td><?php
            endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p><a href="<?= h($report['url']) ?>"><?= __('Open this view') ?></a></p>
<p><?= __('You subscribed to this summary. Manage or cancel email subscriptions from your profile.') ?></p>
<p><a href="<?= h($report['manageUrl']) ?>"><?= __('Manage email subscriptions') ?></a></p>
