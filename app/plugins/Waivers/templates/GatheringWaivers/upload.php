<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\WaiverType[] $requiredWaiverTypes
 */

// Include wizard CSS
echo $this->Html->css('Waivers./css/waiver-upload-wizard', ['block' => true]);

// Build activity data for stimulus - only include activities that require waivers
$activitiesData = [];
if (!empty($gathering->gathering_activities)) {
    foreach ($gathering->gathering_activities as $activity) {
        $waiverTypeIds = [];
        if (!empty($activity->gathering_activity_waivers)) {
            foreach ($activity->gathering_activity_waivers as $activityWaiver) {
                $waiverTypeIds[] = $activityWaiver->waiver_type_id;
            }

            // Only add this activity if it has waivers
            $activitiesData[] = [
                'id' => $activity->id,
                'name' => $activity->name,
                'description' => $activity->description ?? '',
                'waiver_types' => $waiverTypeIds
            ];
        }
    }
}

$waiverTypesData = [];
if (!empty($requiredWaiverTypes)) {
    foreach ($requiredWaiverTypes as $waiverType) {
        $waiverTypesData[] = [
            'id' => $waiverType->id,
            'name' => $waiverType->name,
            'description' => $waiverType->description ?? ''
        ];
    }
}

// Get PHP upload limits for client-side validation
$uploadLimits = $this->KMP->getUploadLimits();
?>
<?php
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
        <?= h($gathering->start_date->format('F j, Y')) ?> - <?= h($gathering->end_date->format('F j, Y')) ?>
    </td>
</tr>
<?php if (!empty($gathering->location)): ?>
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
    <?= __("Upload Wizard") ?>
</button>
<?php $this->KMP->endBlock(); ?>

<?php $this->KMP->startBlock('tabContent') ?>
<div class="related tab-pane fade show active m-3" id="nav-upload-waivers" role="tabpanel"
    aria-labelledby="nav-upload-waivers-tab" data-detail-tabs-target="tabContent" data-tab-order="10"
    style="order: 10;">

    <?php if (empty($activitiesData)): ?>
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle"></i>
            <?= __('No activities requiring waivers are configured for this gathering. Please add activities with waiver requirements first.') ?>
        </div>
        <?= $this->Html->link(
            __('Back to Gathering'),
            ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id],
            ['class' => 'btn btn-secondary']
        ) ?>
    <?php else: ?>
        <!-- Waiver Upload Wizard -->
        <div class="wizard-wrapper" data-controller="waiver-upload-wizard"
            data-waiver-upload-wizard-gathering-id-value="<?= $gathering->id ?>"
            data-waiver-upload-wizard-total-steps-value="4"
            data-waiver-upload-wizard-max-file-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
            data-waiver-upload-wizard-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar"
                        data-waiver-upload-wizard-target="progressBar" style="width: 25%;" aria-valuenow="25"
                        aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
            </div>

            <!-- Step Indicators -->
            <div class="wizard-steps mb-4">
                <div class="d-flex justify-content-between">
                    <div class="wizard-step text-center flex-fill" data-waiver-upload-wizard-target="stepIndicator"
                        data-step="1" data-action="click->waiver-upload-wizard#goToStep">
                        <div class="step-number mb-2">
                            <span class="badge rounded-pill bg-primary">1</span>
                        </div>
                        <div class="step-label small">
                            <i class="bi bi-activity"></i><br>
                            <?= __('Activities') ?>
                        </div>
                    </div>
                    <div class="wizard-step text-center flex-fill" data-waiver-upload-wizard-target="stepIndicator"
                        data-step="2" data-action="click->waiver-upload-wizard#goToStep">
                        <div class="step-number mb-2">
                            <span class="badge rounded-pill bg-secondary">2</span>
                        </div>
                        <div class="step-label small">
                            <i class="bi bi-file-earmark-text"></i><br>
                            <?= __('Waiver Type') ?>
                        </div>
                    </div>
                    <div class="wizard-step text-center flex-fill" data-waiver-upload-wizard-target="stepIndicator"
                        data-step="3" data-action="click->waiver-upload-wizard#goToStep">
                        <div class="step-number mb-2">
                            <span class="badge rounded-pill bg-secondary">3</span>
                        </div>
                        <div class="step-label small">
                            <i class="bi bi-file-earmark-image"></i><br>
                            <?= __('Add Pages') ?>
                        </div>
                    </div>
                    <div class="wizard-step text-center flex-fill" data-waiver-upload-wizard-target="stepIndicator"
                        data-step="4" data-action="click->waiver-upload-wizard#goToStep">
                        <div class="step-number mb-2">
                            <span class="badge rounded-pill bg-secondary">4</span>
                        </div>
                        <div class="step-label small">
                            <i class="bi bi-check-circle"></i><br>
                            <?= __('Review') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wizard Container -->
            <div class="wizard-container card">
                <div class="card-body p-4">
                    <?php echo $this->element('GatheringWaivers/upload_wizard_steps', [
                        'activitiesData' => $activitiesData,
                        'waiverTypesData' => $waiverTypesData,
                        'gathering' => $gathering,
                        'uploadLimits' => $uploadLimits
                    ]); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock(); ?><?php
                                // Pass gathering data to JavaScript for client-side processing
                                $this->Html->scriptStart(['block' => true]);
                                echo sprintf(
                                    'window.gatheringData = %s;',
                                    json_encode([
                                        'id' => $gathering->id,
                                        'name' => $gathering->name,
                                        'requiredWaiverTypes' => collection($requiredWaiverTypes)->map(function ($type) {
                                            return [
                                                'id' => $type->id,
                                                'name' => $type->name,
                                            ];
                                        })->toArray(),
                                    ])
                                );
                                $this->Html->scriptEnd();
                                ?>