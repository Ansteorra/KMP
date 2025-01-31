<?php

/**
 * @var \App\View\AppView $this
 * @var iterable<\Queue\Model\Entity\QueueProcess> $queueProcesses
 */

use Queue\Queue\Config;

$this->extend("/layout/TwitterBootstrap/dashboard");




echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Queueing Processors';
$this->KMP->endBlock(); ?>

<h2><?= __d('queue', 'Queue Processes') ?></h2>

<nav class="actions" id="actions-sidebar">
    <ul class="side-nav nav nav-pills">
        <li class="nav-item heading px-1"><?= __d('queue', 'Actions') ?>:</li>
        <li class="nav-item px-1">
            <?= $this->Html->link(__d('queue', 'Dashboard'), ['controller' => 'Queue', 'action' => 'index'], ['class' => 'btn btn-primary btn-sm']) ?>
        </li>
        <li class="nav-item px-1">
            <?= $this->Html->link(__d('queue', 'Back'), ['controller' => 'Queue', 'action' => 'processes'], ['class' => 'btn btn-primary btn-sm']) ?>
        </li>
        <li class="nav-item px-1">
            <?= $this->Form->postLink(__d('queue', 'Cleanup'), ['action' => 'cleanup'], ['confirm' => 'Sure to remove all outdated ones (>' . (Config::defaultworkertimeout() * 2) . 's)?', 'class' => 'btn btn-sm btn-warning']) ?>
        </li>
    </ul>
</nav>
<div class="content">

    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('pid') ?></th>
                <th><?= $this->Paginator->sort('created', __d('queue', 'Started'), ['direction' => 'desc']) ?></th>
                <th><?= $this->Paginator->sort('modified', __d('queue', 'Last Run'), ['direction' => 'desc']) ?></th>
                <th><?= $this->Paginator->sort('terminate', __d('queue', 'Active')) ?></th>
                <th><?= $this->Paginator->sort('server') ?></th>
                <th class="actions"><?= __d('queue', 'Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($queueProcesses as $queueProcess): ?>
            <tr>
                <td>
                    <?= h($queueProcess->pid) ?>
                    <?php if ($queueProcess->workerkey && $queueProcess->workerkey !== $queueProcess->pid) { ?>
                    <div><small><?php echo h($queueProcess->workerkey); ?></small></div>
                    <?php } ?>
                </td>
                <td>
                    <?= $this->Time->nice($queueProcess->created) ?>
                    <?php if (!$queueProcess->created->addSeconds(Config::workermaxruntime())->isFuture()) {
							echo $this->Icon->render('exclamation-triangle', ['title' => 'Long running (!)']);
						} ?>
                </td>
                <td>
                    <?php
						$modified = $this->Time->nice($queueProcess->modified);
						if (!$queueProcess->created->addSeconds(Config::defaultworkertimeout())->isFuture()) {
							$modified = '<span class="disabled" title="Beyond default worker timeout!">' . $modified . '</span>';
						}
						echo $modified;
						?>
                </td>
                <td><?= $this->element('Queue.yes_no', ['value' => !$queueProcess->terminate]) ?></td>
                <td><?= h($queueProcess->server) ?></td>
                <td class="actions">
                    <?= $this->Html->link($this->Icon->render('view'), ['action' => 'view', $queueProcess->id], ['escapeTitle' => false]); ?>
                    <?php if (!$queueProcess->terminate) { ?>
                    <?= $this->Form->postLink($this->Icon->render('times', [], ['title' => __d('queue', 'Terminate')]), ['action' => 'terminate', $queueProcess->id], ['escapeTitle' => false, 'confirm' => __d('queue', 'Are you sure you want to terminate # {0}?', $queueProcess->id)]); ?>
                    <?php } ?>

                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php echo $this->element('Tools.pagination'); ?>
</div>