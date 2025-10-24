<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\WaiverType[] $requiredWaiverTypes
 */
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
                        <li class="breadcrumb-item"><?= $this->Html->link(__('Gatherings'), ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'index']) ?></li>
                        <li class="breadcrumb-item"><?= $this->Html->link($gathering->name, ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id]) ?></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= __('Upload Waivers') ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <h2><?= __('Upload Waivers for {0}', h($gathering->name)) ?></h2>
                <p class="lead">
                    <?= __('Upload signed waiver images (JPEG, PNG, or TIFF). Images will be automatically converted to black and white PDF format for storage.') ?>
                </p>
            </div>
        </div>

        <?php if (empty($requiredWaiverTypes)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle"></i>
                <?= __('No waiver types are required for this gathering. Please configure gathering activities with required waivers first.') ?>
            </div>
            <?= $this->Html->link(
                __('Back to Gathering'),
                ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id],
                ['class' => 'btn btn-secondary']
            ) ?>
        <?php else: ?>
            <div class="row">
                <div class="col-md-8">
                    <?= $this->Form->create(null, [
                        'type' => 'file',
                        'id' => 'waiver-upload-form',
                        'data-controller' => 'waiver-upload',
                        'data-action' => 'submit->waiver-upload#handleSubmit',
                        'url' => ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]]
                    ]) ?>

                    <fieldset>
                        <legend><?= __('Upload Waiver Images') ?></legend>

                        <div class="mb-3">
                            <?= $this->Form->control('waiver_type_id', [
                                'label' => __('Waiver Type'),
                                'options' => collection($requiredWaiverTypes)->combine('id', 'name')->toArray(),
                                'empty' => __('-- Select Waiver Type --'),
                                'required' => true,
                                'class' => 'form-select',
                                'data-waiver-upload-target' => 'waiverType'
                            ]) ?>
                            <small class="form-text text-muted">
                                <?= __('Select the type of waiver you are uploading') ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <?= __('Applicable Activities') ?>
                                <span class="text-danger">*</span>
                            </label>
                            <?php if (!empty($gathering->gathering_activities)): ?>
                                <?php foreach ($gathering->gathering_activities as $activity): ?>
                                    <div class="form-check">
                                        <?= $this->Form->checkbox('activity_ids[]', [
                                            'value' => $activity->id,
                                            'id' => 'activity-' . $activity->id,
                                            'class' => 'form-check-input'
                                        ]) ?>
                                        <label class="form-check-label" for="activity-<?= $activity->id ?>">
                                            <?= h($activity->name) ?>
                                            <?php if (!empty($activity->description)): ?>
                                                <small class="text-muted">(<?= h($activity->description) ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <small class="form-text text-muted">
                                    <?= __('Select which activities this waiver applies to') ?>
                                </small>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <?= __('No activities found for this gathering') ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <?= $this->Form->control('member_id', [
                                'label' => __('Member (Optional)'),
                                'type' => 'number',
                                'placeholder' => __('Enter member ID if applicable'),
                                'class' => 'form-control',
                                'required' => false
                            ]) ?>
                            <small class="form-text text-muted">
                                <?= __('If the waiver is for a registered member, enter their member ID') ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="waiver-images" class="form-label">
                                <?= __('Waiver Images') ?>
                                <span class="text-danger">*</span>
                            </label>

                            <!-- HTML5 file input with mobile camera capture support -->
                            <?= $this->Form->file('waiver_images[]', [
                                'id' => 'waiver-images',
                                'multiple' => true,
                                'accept' => 'image/*',
                                'capture' => 'environment',
                                'required' => true,
                                'class' => 'form-control',
                                'data-waiver-upload-target' => 'fileInput',
                                'data-action' => 'change->waiver-upload#handleFileSelect'
                            ]) ?>

                            <small class="form-text text-muted">
                                <i class="bi bi-camera"></i> <?= __('On mobile devices, you can take photos directly with your camera or select from your gallery.') ?><br>
                                <i class="bi bi-file-image"></i> <?= __('Accepted formats: JPEG, PNG, TIFF. Maximum size: 25MB per file.') ?><br>
                                <i class="bi bi-file-earmark-pdf"></i> <?= __('Images will be automatically converted to black and white PDF format.') ?>
                            </small>
                        </div>

                        <!-- File preview area -->
                        <div class="mb-3" data-waiver-upload-target="preview" style="display: none;">
                            <label class="form-label"><?= __('Selected Files') ?></label>
                            <div id="file-preview-list" class="list-group">
                                <!-- Dynamically populated by Stimulus controller -->
                            </div>
                        </div>

                        <!-- Upload progress -->
                        <div class="mb-3" data-waiver-upload-target="progress" style="display: none;">
                            <label class="form-label"><?= __('Upload Progress') ?></label>
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                    role="progressbar"
                                    data-waiver-upload-target="progressBar"
                                    aria-valuenow="0"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    style="width: 0%">
                                    <span data-waiver-upload-target="progressText">0%</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <?= $this->Form->control('notes', [
                                'type' => 'textarea',
                                'label' => __('Notes (Optional)'),
                                'placeholder' => __('Any additional notes about these waivers...'),
                                'class' => 'form-control',
                                'rows' => 3,
                                'required' => false
                            ]) ?>
                        </div>
                    </fieldset>

                    <div class="form-group">
                        <?= $this->Form->button(__('Upload Waivers'), [
                            'class' => 'btn btn-primary',
                            'data-waiver-upload-target' => 'submitButton'
                        ]) ?>
                        <?= $this->Html->link(
                            __('Cancel'),
                            ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id],
                            ['class' => 'btn btn-secondary']
                        ) ?>
                    </div>

                    <?= $this->Form->end() ?>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= __('Required Waivers') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($requiredWaiverTypes as $waiverType): ?>
                                    <li class="list-group-item">
                                        <strong><?= h($waiverType->name) ?></strong>
                                        <?php if ($waiverType->has('retention_description')): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar-check"></i>
                                                <?= h($waiverType->retention_description) ?>
                                            </small>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightbulb"></i> <?= __('Tips') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="small">
                                <li><?= __('You can upload multiple images at once') ?></li>
                                <li><?= __('Take clear, well-lit photos for best results') ?></li>
                                <li><?= __('Make sure all text is legible in the photo') ?></li>
                                <li><?= __('The system will automatically compress images to save storage space') ?></li>
                                <li><?= __('Typical upload time: 2-5 seconds per image') ?></li>
                            </ul>
                        </div>
                    </div>
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