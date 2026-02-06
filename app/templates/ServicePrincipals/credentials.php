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
                                    onclick="copyToClipboard('clientId')" title="<?= __('Copy') ?>">
                                <i class="bi bi-clipboard"></i>
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
                                    onclick="copyToClipboard('bearerToken')" title="<?= __('Copy') ?>">
                                <i class="bi bi-clipboard"></i>
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
                                   onchange="document.getElementById('continueBtn').disabled = !this.checked">
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

<script>
function copyToClipboard(elementId) {
    const input = document.getElementById(elementId);
    input.select();
    document.execCommand('copy');
    
    // Visual feedback
    const btn = input.nextElementSibling;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check"></i>';
    setTimeout(() => {
        btn.innerHTML = originalHtml;
    }, 1500);
}
</script>
