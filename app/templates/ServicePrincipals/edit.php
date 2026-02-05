<?php

/**
 * Service Principal Edit Template
 * 
 * Form for editing service principal details.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ServicePrincipal $servicePrincipal
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Edit ' . h($servicePrincipal->name);
$this->KMP->endBlock();

$this->assign('title', __('Edit Service Principal'));
?>

<div class="service-principals edit content">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><?= __('Edit Service Principal') ?></h4>
                </div>
                <div class="card-body">
                    <?= $this->Form->create($servicePrincipal) ?>

                    <div class="mb-3">
                        <label class="form-label"><?= __('Client ID') ?></label>
                        <input type="text" class="form-control font-monospace" value="<?= h($servicePrincipal->client_id) ?>" disabled>
                        <div class="form-text"><?= __('Client ID cannot be changed.') ?></div>
                    </div>

                    <div class="mb-3">
                        <?= $this->Form->control('name', [
                            'label' => __('Name'),
                            'class' => 'form-control',
                            'required' => true,
                        ]) ?>
                    </div>

                    <div class="mb-3">
                        <?= $this->Form->control('description', [
                            'type' => 'textarea',
                            'label' => __('Description'),
                            'class' => 'form-control',
                            'rows' => 3,
                        ]) ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('IP Allowlist') ?></label>
                        <?= $this->Form->control('ip_allowlist_text', [
                            'type' => 'textarea',
                            'label' => false,
                            'class' => 'form-control font-monospace',
                            'rows' => 3,
                            'value' => !empty($servicePrincipal->ip_allowlist) ? implode("\n", $servicePrincipal->ip_allowlist) : '',
                            'placeholder' => __("192.168.1.1\n10.0.0.0/24"),
                        ]) ?>
                        <div class="form-text">
                            <?= __('Enter one IP address or CIDR range per line. Leave blank to allow all IPs.') ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?= $this->Form->control('is_active', [
                            'type' => 'checkbox',
                            'label' => __('Active'),
                            'class' => 'form-check-input',
                        ]) ?>
                        <div class="form-text"><?= __('Inactive service principals cannot authenticate.') ?></div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?= $this->Html->link(__('Cancel'), ['action' => 'view', $servicePrincipal->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->button(__('Save Changes'), ['class' => 'btn btn-primary']) ?>
                    </div>

                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>
    </div>
</div>
