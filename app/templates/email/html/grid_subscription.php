<?php
/** @var \App\View\AppView $this */
?>
<h1><?= h($name) ?></h1>
<p><?= __('Current results from {0}. Up to 50 matching rows are included.', h($report['label'])) ?></p>
<?php if ($report['rows'] === []) : ?>
<p><?= __('No rows match this view right now. Scheduled emails are skipped when there are no matches.') ?></p>
<?php else : ?>
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
<?php endif; ?>
<p><a href="<?= h($report['url']) ?>"><?= __('Open this view') ?></a></p>
<?php if (!empty($report['sample'])) : ?>
<p><?= __('This is a one-time sample. It does not create or change a subscription.') ?></p>
<?php else : ?>
<p><?= __('You subscribed to this summary. Manage or cancel email subscriptions from your profile.') ?></p>
<?php endif; ?>
<p><a href="<?= h($report['manageUrl']) ?>"><?= __('Manage email subscriptions') ?></a></p>
