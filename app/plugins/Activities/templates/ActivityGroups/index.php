<?php

/**
 * Activity Groups Index - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Activity Groups';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3>Activity Groups</h3>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Activities.ActivityGroups")): ?>
        <?= $this->Html->link(
                ' Add Activity Group',
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Activities.ActivityGroups.index.main',
    'frameId' => 'activity-groups-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>