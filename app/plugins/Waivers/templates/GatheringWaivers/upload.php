<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\WaiverType[] $requiredWaiverTypes
 */

// Include wizard CSS
echo $this->Html->css('Waivers./css/waiver-upload-wizard', ['block' => true]);

// Build activity data for stimulus
$activitiesData = [];
if (!empty($gathering->gathering_activities)) {
    foreach ($gathering->gathering_activities as $activity) {
        $waiverTypeIds = [];
        if (!empty($activity->gathering_activity_waivers)) {
            foreach ($activity->gathering_activity_waivers as $activityWaiver) {
                $waiverTypeIds[] = $activityWaiver->waiver_type_id;
            }
        }
        $activitiesData[] = [
            'id' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description ?? '',
            'waiver_types' => $waiverTypeIds
        ];
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

// Debug output (check browser's View Source to see this)
// Remove after testing
echo '<!-- DEBUG: Activities Data: ' . json_encode($activitiesData) . ' -->';
echo '<!-- DEBUG: Waiver Types Data: ' . json_encode($waiverTypesData) . ' -->';
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Upload Waivers';
$this->KMP->endBlock();
?>

<turbo-frame id="waiver-upload-<?= $gathering->id ?>">
    <div class="gathering-waivers upload content">
        <div class="row">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <?= $this->Html->link(__('Gatherings'), ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'index']) ?>
                        </li>
                        <li class="breadcrumb-item">
                            <?= $this->Html->link($gathering->name, ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id]) ?>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?= __('Upload Waivers') ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <h2><?= __('Upload Waivers for {0}', h($gathering->name)) ?></h2>
                <p class="lead">
                    <?= __('Follow the steps below to upload signed waiver documents.') ?>
                </p>
            </div>
        </div>

        <?php if (empty($gathering->gathering_activities)): ?>
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle"></i>
            <?= __('No activities are configured for this gathering. Please add activities first.') ?>
        </div>
        <?= $this->Html->link(
                __('Back to Gathering'),
                ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id],
                ['class' => 'btn btn-secondary']
            ) ?>
        <?php else: ?>
        <!-- Waiver Upload Wizard -->
        <div class="row" data-controller="waiver-upload-wizard"
            data-waiver-upload-wizard-gathering-id-value="<?= $gathering->id ?>"
            data-waiver-upload-wizard-total-steps-value="4">

            <div class="col-md-12">
                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                            role="progressbar" data-waiver-upload-wizard-target="progressBar" style="width: 25%;"
                            aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
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
                                'gathering' => $gathering
                            ]); ?>

                        <?= $this->Form->end() ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
</turbo-frame>

<?php
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