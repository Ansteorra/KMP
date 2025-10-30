<?php

/**
 * @var \App\View\AppView $this
 * @var \Waivers\Model\Entity\WaiverType $waiverType
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$this->append('css', $this->AssetMix->css('waivers'));

// Get PHP upload limits for client-side validation
$uploadLimits = $this->KMP->getUploadLimits();

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Waiver Type';
$this->KMP->endBlock(); ?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3><a href="#" onclick="window.history.back(); return false;" class="bi bi-arrow-left-circle"></a>
            Add Waiver Type</h3>
    </div>
</div>

<turbo-frame id="waiver-types-frame" target="_top">
    <div class="waiverTypes form content">
        <?= $this->Form->create($waiverType, ['type' => 'file']) ?>
        <div data-controller="waiver-template">
            <fieldset>
                <?= $this->Form->control('name', [
                    'label' => 'Waiver Type Name',
                    'class' => 'form-control',
                    'required' => true,
                    'placeholder' => 'e.g., General Liability Waiver'
                ]) ?>

                <?= $this->Form->control('description', [
                    'label' => 'Description',
                    'class' => 'form-control',
                    'type' => 'textarea',
                    'rows' => 3,
                    'placeholder' => 'Brief description of when this waiver is required'
                ]) ?>

                <div class="mb-3">
                    <label class="form-label">Template Source</label>
                    <?= $this->Form->select('template_source', [
                        'none' => 'No Template',
                        'upload' => 'Upload PDF File',
                        'url' => 'External URL'
                    ], [
                        'class' => 'form-select',
                        'data-action' => 'change->waiver-template#toggleSource',
                        'data-waiver-template-target' => 'sourceSelect',
                        'default' => 'none'
                    ]) ?>
                    <small class="form-text text-muted">
                        Choose whether to upload a PDF template or link to an external URL
                    </small>
                </div>

                <div data-waiver-template-target="uploadSection" style="display: none;"
                    data-controller="file-size-validator"
                    data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                    data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">

                    <!-- Warning message container -->
                    <div data-file-size-validator-target="warning" class="d-none mb-3"></div>

                    <?= $this->Form->control('template_file', [
                        'type' => 'file',
                        'label' => 'Upload PDF Template',
                        'class' => 'form-control',
                        'accept' => '.pdf',
                        'data-waiver-template-target' => 'fileInput',
                        'data-file-size-validator-target' => 'fileInput',
                        'data-action' => 'change->waiver-template#fileSelected change->file-size-validator#validateFiles',
                        'help' => 'Upload a blank PDF template for this waiver type (max size: ' . h($uploadLimits['formatted']) . ')'
                    ]) ?>
                </div>

                <div data-waiver-template-target="urlSection" style="display: none;">
                    <?= $this->Form->control('template_url', [
                        'type' => 'text',
                        'label' => 'External Template URL',
                        'class' => 'form-control',
                        'placeholder' => 'https://www.sca.org/wp-content/uploads/2019/12/rosterwaiver.pdf',
                        'data-waiver-template-target' => 'urlInput',
                        'help' => 'Enter the full URL to an external PDF template'
                    ]) ?>
                </div>

                <!-- Retention Policy Structured Input -->
                <div class="mb-3" data-controller="retention-policy-input">
                    <label class="form-label">Retention Policy <span class="text-danger">*</span></label>

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">Anchor Point</label>
                            <?= $this->Form->select('anchor', [
                                'gathering_end_date' => 'From Gathering End Date',
                                'upload_date' => 'From Upload Date',
                                'permanent' => 'Permanent Retention'
                            ], [
                                'class' => 'form-select',
                                'data-retention-policy-input-target' => 'anchorSelect',
                                'data-action' => 'change->retention-policy-input#updatePreview',
                                'default' => 'gathering_end_date'
                            ]) ?>
                        </div>
                    </div>

                    <div class="row" data-retention-policy-input-target="durationSection">
                        <div class="col-md-4">
                            <label class="form-label small">Years</label>
                            <?= $this->Form->number('duration_years', [
                                'class' => 'form-control',
                                'min' => 0,
                                'max' => 99,
                                'value' => 7,
                                'data-retention-policy-input-target' => 'yearsInput',
                                'data-action' => 'input->retention-policy-input#updatePreview'
                            ]) ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Months</label>
                            <?= $this->Form->number('duration_months', [
                                'class' => 'form-control',
                                'min' => 0,
                                'max' => 11,
                                'value' => 0,
                                'data-retention-policy-input-target' => 'monthsInput',
                                'data-action' => 'input->retention-policy-input#updatePreview'
                            ]) ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Days</label>
                            <?= $this->Form->number('duration_days', [
                                'class' => 'form-control',
                                'min' => 0,
                                'max' => 365,
                                'value' => 0,
                                'data-retention-policy-input-target' => 'daysInput',
                                'data-action' => 'input->retention-policy-input#updatePreview'
                            ]) ?>
                        </div>
                    </div>

                    <div class="alert alert-info mt-2" role="alert">
                        <strong>Preview:</strong>
                        <span data-retention-policy-input-target="preview">7 years from gathering end date</span>
                    </div>

                    <!-- Hidden input that stores the actual JSON value -->
                    <?= $this->Form->hidden('retention_policy', [
                        'data-retention-policy-input-target' => 'hiddenInput',
                        'value' => '{"anchor":"gathering_end_date","duration":{"years":7}}'
                    ]) ?>

                    <small class="form-text text-muted">
                        Specify how long signed waivers should be retained. This policy is captured at upload time.
                    </small>
                </div>

                <?= $this->Form->control('convert_to_pdf', [
                    'label' => 'Convert uploaded images to PDF',
                    'class' => 'form-check-input',
                    'type' => 'checkbox',
                    'checked' => true
                ]) ?>

                <?= $this->Form->control('is_active', [
                    'label' => 'Active',
                    'class' => 'form-check-input',
                    'type' => 'checkbox',
                    'checked' => true
                ]) ?>
            </fieldset>
        </div>
        <div class="mt-3">
            <?= $this->Form->button('Save Waiver Type', ['class' => 'btn btn-primary']) ?>
            <?= $this->Html->link(
                'Cancel',
                ['action' => 'index'],
                ['class' => 'btn btn-secondary']
            ) ?>
        </div>
        <?= $this->Form->end() ?>
    </div>
</turbo-frame>