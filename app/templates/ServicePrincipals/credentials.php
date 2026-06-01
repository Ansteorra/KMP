<?php

/**
 * Service Principal Credentials Template
 * 
 * One-time display of newly created credentials.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ServicePrincipal $servicePrincipal
 * @var array $credentials
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Service Principal Credentials';
$this->KMP->endBlock();

$this->assign('title', __('Service Principal Created'));
?>

<div class="service-principals credentials content">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= __('Save These Credentials Now') ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <strong><?= __('Important:') ?></strong>
                        <?= __('These credentials will only be displayed once. Store them securely before leaving this page.') ?>
                    </div>

                    <h5><?= h($servicePrincipal->name) ?></h5>

                    <div class="mb-4">
                        <label class="form-label fw-bold"><?= __('Client ID') ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" 
                                   value="<?= h($credentials['client_id']) ?>" 
                                   id="clientId" readonly>
                            <button class="btn btn-outline-secondary" type="button"
                                    data-controller="clipboard"
                                    data-action="clipboard#copy"
                                    data-clipboard-source-selector-value="#clientId"
                                    data-clipboard-success-message-value="<?= h(__('Client ID copied to clipboard.')) ?>"
                                    title="<?= __('Copy') ?>">
                                <i class="bi bi-clipboard" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= __('Copy Client ID') ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold"><?= __('Bearer Token') ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" 
                                   value="<?= h($credentials['bearer_token']) ?>" 
                                   id="bearerToken" readonly>
                            <button class="btn btn-outline-secondary" type="button"
                                    data-controller="clipboard"
                                    data-action="clipboard#copy"
                                    data-clipboard-source-selector-value="#bearerToken"
                                    data-clipboard-success-message-value="<?= h(__('Bearer token copied to clipboard.')) ?>"
                                    title="<?= __('Copy') ?>">
                                <i class="bi bi-clipboard" aria-hidden="true"></i>
                                <span class="visually-hidden"><?= __('Copy bearer token') ?></span>
                            </button>
                        </div>
                        <div class="form-text"><?= __('Use this token in the Authorization header: Bearer {token}') ?></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold"><?= __('Example API Request') ?></label>
                        <pre class="bg-dark text-light p-3 rounded"><code>curl -H "Authorization: Bearer <?= h($credentials['bearer_token']) ?>" \
     <?= $this->Url->build('/api/v1/members', ['fullBase' => true]) ?></code></pre>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmSaved"
                                   data-controller="enable-on-check"
                                   data-action="enable-on-check#toggle"
                                   data-enable-on-check-target-selector-value="#continueBtn">
                            <label class="form-check-label" for="confirmSaved">
                                <?= __('I have saved these credentials securely') ?>
                            </label>
                        </div>
                        <?= $this->Html->link(
                            __('Continue to Service Principal'),
                            ['action' => 'view', $servicePrincipal->id],
                            ['class' => 'btn btn-primary', 'id' => 'continueBtn', 'disabled' => true]
                        ) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
