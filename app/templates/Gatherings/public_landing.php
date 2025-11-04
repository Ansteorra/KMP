<?php

/**
 * Public Landing Page for Gathering
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $scheduleByDate
 * @var int $durationDays
 */

use Cake\I18n\Date;

// Set page title
$this->assign('title', h($gathering->name));
?>

<?= $this->element('gatherings/public_content', [
    'gathering' => $gathering,
    'scheduleByDate' => $scheduleByDate,
    'durationDays' => $durationDays
]) ?>

<footer class="footer">
    <div class="container">
        <p>
            Hosted by <?= h($gathering->branch->name) ?>
        </p>
        <p style="margin-top: var(--space-md); font-size: 0.75rem; opacity: 0.7;">
            © <?= date('Y') ?> Kingdom Management Portal
        </p>
    </div>
</footer>