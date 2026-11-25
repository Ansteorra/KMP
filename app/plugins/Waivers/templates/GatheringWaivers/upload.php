<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array<\Waivers\Model\Entity\WaiverType> $requiredWaiverTypes
 * @var array $waiverStatusSummary
 * @var string|null $pageContextUrl
 */

// Include the built waiver upload stylesheet via Vite.
$this->append('css', $this->Vite->css('waiver-upload'));

$waiverTypesData = [];
if (!empty($requiredWaiverTypes)) {
    foreach ($requiredWaiverTypes as $waiverType) {
        $waiverTypesData[] = [
            'id' => $waiverType->id,
            'name' => $waiverType->name,
            'description' => $waiverType->description ?? '',
            'exemption_reasons' => $waiverType->exemption_reasons_parsed ?? [],
        ];
    }
}

// Get PHP upload limits for client-side validation
$uploadLimits = $this->KMP->getUploadLimits();

$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Upload Waivers - ' . $gathering->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle');
?>
<?= __('Upload Waivers for {0}', h($gathering->name)) ?>
<?php $this->KMP->endBlock(); ?>

<?= $this->KMP->startBlock('recordActions') ?>
<?php $this->KMP->endBlock(); ?>

<?php $this->KMP->startBlock('recordDetails') ?>
<tr scope="row">
    <th class="col"><?= __('Gathering') ?></th>
    <td class="col-10"><?= h($gathering->name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __('Date Range') ?></th>
    <td class="col-10">
        <?= $this->Timezone->format($gathering->start_date, $gathering, 'F j, Y') ?> -
        <?= $this->Timezone->format($gathering->end_date, $gathering, 'F j, Y') ?>
    </td>
</tr>
<?php if (!empty($gathering->location)) : ?>
    <tr scope="row">
        <th class="col"><?= __('Location') ?></th>
        <td class="col-10"><?= h($gathering->location) ?></td>
    </tr>
<?php endif; ?>
<?php $this->KMP->endBlock(); ?>

<?php $this->KMP->startBlock('tabButtons') ?>
<!-- Waiver upload wizard tab -->
<button class="nav-link active" id="nav-upload-waivers-tab" data-bs-toggle="tab" data-bs-target="#nav-upload-waivers"
    type="button" role="tab" aria-controls="nav-upload-waivers" aria-selected="true" data-detail-tabs-target='tabBtn'
    data-tab-order="10" style="order: 10;">
    <?= __('Upload Wizard') ?>
</button>
<?php $this->KMP->endBlock(); ?>

<?php $this->KMP->startBlock('tabContent') ?>
<div class="related tab-pane fade show active m-3" id="nav-upload-waivers" role="tabpanel"
    aria-labelledby="nav-upload-waivers-tab" data-detail-tabs-target="tabContent" data-tab-order="10"
    style="order: 10;">
    <?= $this->element('GatheringWaivers/upload_wizard', [
        'mobile' => false,
        'waiverTypesData' => $waiverTypesData,
        'gathering' => $gathering,
        'uploadLimits' => $uploadLimits,
        'waiverStatusSummary' => $waiverStatusSummary,
        'pageContextUrl' => $pageContextUrl ?? null,
        'preSelectedWaiverTypeId' => $preSelectedWaiverTypeId ?? null,
    ]) ?>
</div>
<?php $this->KMP->endBlock(); ?>
