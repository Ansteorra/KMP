<?php
declare(strict_types=1);

/** @var \Waivers\Model\Entity\GatheringWaiverLoss $loss */
$this->extend('/layout/TwitterBootstrap/dashboard');
$gathering = $loss->gathering;
?>
<h2><?= __('Lost Waiver Report — {0}', h($gathering->name)) ?></h2>
<p><?= $this->Html->link(__('View Gathering'), [
    'plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id,
    '?' => ['tab' => 'gathering-waivers'],
]) ?></p>
<?= $this->element('Waivers.GatheringWaivers/loss_reports', ['waiverLosses' => [$loss], 'gathering' => $gathering]) ?>
