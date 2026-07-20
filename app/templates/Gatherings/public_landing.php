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
$this->append('css', $this->Vite->css('gatherings_public'));
?>
<?php
// Show mobile back button when coming from mobile app
$fromMobile = $this->request->getQuery('from') === 'mobile';
if ($fromMobile) {
    $backParams = ['controller' => 'Gatherings', 'action' => 'mobileCalendar'];
    $month = $this->request->getQuery('month');
    $year = $this->request->getQuery('year');
    if ($month && $year) {
        $backParams['?'] = ['month' => $month, 'year' => $year];
    }
}
?>
<div class="gathering-public-page">
<?php if ($fromMobile): ?>
<div class="gathering-public-mobile-back-bar">
    <a href="<?= $this->Url->build($backParams) ?>" class="gathering-public-mobile-back-link">
        <i class="bi bi-arrow-left me-2 gathering-public-mobile-back-icon"></i>
        Back to Events
    </a>
</div>
<div class="gathering-public-mobile-back-spacer"></div>
<?php endif; ?>

<?= $this->element('gatherings/public_content', [
    'gathering' => $gathering,
    'scheduleByDate' => $scheduleByDate,
    'durationDays' => $durationDays,
    'user' => $user ?? null,
    'userAttendance' => $userAttendance ?? null,
    'kingdomAttendances' => $kingdomAttendances ?? []
]) ?>

<footer class="gathering-public-footer">
    <div class="container">
        <p>
            Hosted by <?= h($gathering->branch->name) ?>
        </p>
        <p class="gathering-public-footer-meta">
            © <?= date('Y') ?> Kingdom Management Portal
        </p>
    </div>
</footer>
</div>
