<?php

/**
 * @var \App\View\AppView $this
 * @var \Queue\Model\Entity\QueueProcess[] $processes
 * @var \Queue\Model\Entity\QueueProcess[] $terminated
 * @var \Queue\Model\Entity\QueuedJob $queuedJob
 * @var string $key
 */

use Cake\I18n\DateTime;
use Cake\Core\Configure;

$this->extend("/layout/TwitterBootstrap/dashboard");




echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Queueing Processors';
$this->KMP->endBlock(); ?>

<h3>
    Queue Processors
</h3>


<ul class=" nav nav-pills">
    <li class="nav-item">
        <?= $this->Html->link(__d('queue', 'Dashboard'), ['controller' => 'Queue', 'action' => 'index'], ['class' => 'btn margin btn-secondary']) ?>
    </li>
    <li class="nav-item">
        <?php echo $this->Html->link(__d('queue', 'List {0}', __d('queue', 'Queue Processes')), ['controller' => 'QueueProcesses', 'action' => 'index'], ['class' => 'btn margin btn-primary']); ?>
    </li>
</ul>
<p><?php echo __d('queue', 'Active processes'); ?>:</p>

<ul>
    <?php
	foreach ($processes as $process) {
		echo '<li>' . $process->pid . ':';
		echo '<ul>';
		echo '<li>Current active job: ' . ($process->active_job ? $this->Html->link($process->active_job->job_task, [
			'controller' => 'QueuedJobs',
			'action' => 'view',
			$process->active_job->id
		]) : 'Currently no job is being processed by this worker') . '</li>';
		echo '<li>Last run: ' . $this->Time->nice(new DateTime($process->modified)) . '</li>';

		echo '<li>End: ' . $this->Form->postLink(__d('queue', 'Finish current job and end'), ['action' => 'processes', '?' => ['end' => $process->pid]], ['confirm' => 'Sure?', 'class' => 'button secondary btn margin btn-secondary']) . ' (next loop run)</li>';
		if ($process->workerkey === $key || !$this->Configure->read('Queue.multiserver')) {
			echo '<li>' . __d('queue', 'Kill') . ': ' . $this->Form->postLink(__d('queue', 'Soft kill'), ['action' => 'processes', '?' => ['kill' => $process->pid]], ['confirm' => 'Sure?']) . ' (termination SIGTERM = 15)</li>';
		}

		echo '</ul>';
		echo '</li>';
	}
	if (empty($processes)) {
		echo 'n/a';
	}
	?>
</ul>

<?php if (!empty($terminated)) { ?>
<h3><?php echo __d('queue', 'Terminated') ?></h3>
<p><?php echo __d('queue', 'These have been marked as to be terminated after finishing this round'); ?>:</p>
<ul>
    <?php
		foreach ($terminated as $queuedJob) {
			echo '<li>' . $queuedJob->pid;
			echo '</li>';
		}
		?>
</ul>
<?php } ?>