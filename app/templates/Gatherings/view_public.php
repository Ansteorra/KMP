<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringAttendance|null $userAttendance
 * @var bool $showPublicView
 */

// Get the authenticated user
$user = $this->request->getAttribute('identity');
?>
<?php
$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': View Gathering - ' . $gathering->name;
$this->KMP->endBlock();
$this->append(
    'css',
    implode('', [
        '<link rel="preconnect" href="https://fonts.googleapis.com">',
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">',
        $this->Vite->css('gatherings_public'),
    ]),
);

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($gathering->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordActions') ?>
<!-- Build public landing URL -->
<?php
$publicLandingUrl = $this->Url->build([
    'controller' => 'Gatherings',
    'action' => 'public-landing',
    $gathering->public_id
], ['fullBase' => true]);
?>

<a href="<?= $publicLandingUrl ?>" target="_blank" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
</a>
<a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i> Back to List
</a>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordDetails') ?>

<tr class="gathering-public-record-row">
    <td colspan="100" class="gathering-public-record-cell">
        <div class="gathering-public-content">
            <?= $this->element('gatherings/public_content', [
                'gathering' => $gathering,
                'scheduleByDate' => $scheduleByDate ?? [],
                'durationDays' => $durationDays ?? 1,
                'user' => $user ?? null,
                'userAttendance' => $userAttendance ?? null,
                'kingdomAttendances' => $kingdomAttendances ?? []
            ]) ?>
        </div>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>
