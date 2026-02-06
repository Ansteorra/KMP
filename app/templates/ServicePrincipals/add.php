<?php

/**
 * Service Principal Add Template
 * 
 * Form for creating a new service principal.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ServicePrincipal $servicePrincipal
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Service Principal';
$this->KMP->endBlock();

$this->assign('title', __('Add Service Principal'));
?>

<div class="service-principals add content">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><?= __('Create Service Principal') ?></h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <?= __('A service principal allows third-party applications to authenticate with the KMP API. After creation, you will receive credentials that must be stored securely - they will only be shown once.') ?>
                    </div>

                    <?= $this->Form->create($servicePrincipal) ?>

                    <div class="mb-3">
                        <?= $this->Form->control('name', [
                            'label' => __('Name'),
                            'class' => 'form-control',
                            'placeholder' => __('e.g., External Reporting System'),
                            'required' => true,
                        ]) ?>
                        <div class="form-text"><?= __('A descriptive name to identify this integration.') ?></div>
                    </div>

                    <div class="mb-3">
                        <?= $this->Form->control('description', [
                            'type' => 'textarea',
                            'label' => __('Description'),
                            'class' => 'form-control',
                            'rows' => 3,
                            'placeholder' => __('Describe what this integration is used for...'),
                        ]) ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('IP Allowlist') ?></label>
                        <?= $this->Form->control('ip_allowlist_text', [
                            'type' => 'textarea',
                            'label' => false,
                            'class' => 'form-control font-monospace',
                            'rows' => 3,
                            'placeholder' => __("192.168.1.1\n10.0.0.0/24\n203.0.113.0/24"),
                        ]) ?>
                        <div class="form-text">
                            <?= __('Optional. Enter one IP address or CIDR range per line. Leave blank to allow all IPs.') ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?= $this->Html->link(__('Cancel'), ['action' => 'index'], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->button(__('Create Service Principal'), ['class' => 'btn btn-primary']) ?>
                    </div>

                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>
    </div>
</div>
