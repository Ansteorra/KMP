<?php

/**
 * Service Principal View Template
 * 
 * Detailed view with roles, tokens, and audit log.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ServicePrincipal $servicePrincipal
 * @var array $currentRoles
 * @var array $expiredRoles
 * @var \Cake\ORM\ResultSet $roles
 * @var \Cake\ORM\ResultSet $branches
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': ' . h($servicePrincipal->name);
$this->KMP->endBlock();

$this->assign('title', h($servicePrincipal->name));

// Check for new token display
$newToken = $this->request->getSession()->consume('ServicePrincipal.newToken');
?>

<div class="service-principals view content">
    <?php if ($newToken) : ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <h5><i class="bi bi-exclamation-triangle"></i> <?= __('New Token Created - Save It Now!') ?></h5>
            <p class="mb-1"><strong><?= __('Token Name:') ?></strong> <?= h($newToken['name']) ?></p>
            <div class="input-group mb-2">
                <input type="text" class="form-control font-monospace" 
                       value="<?= h($newToken['bearer_token']) ?>" id="newToken" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('newToken')">
                    <i class="bi bi-clipboard"></i> <?= __('Copy') ?>
                </button>
            </div>
            <small><?= __('This token will not be shown again.') ?></small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= h($servicePrincipal->name) ?></h5>
                    <div>
                        <?php if ($servicePrincipal->is_active) : ?>
                            <span class="badge bg-success"><?= __('Active') ?></span>
                        <?php else : ?>
                            <span class="badge bg-secondary"><?= __('Inactive') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3"><?= __('Client ID') ?></dt>
                        <dd class="col-sm-9"><code><?= h($servicePrincipal->client_id) ?></code></dd>

                        <dt class="col-sm-3"><?= __('Description') ?></dt>
                        <dd class="col-sm-9"><?= $servicePrincipal->description ? h($servicePrincipal->description) : '<em class="text-muted">' . __('No description') . '</em>' ?></dd>

                        <dt class="col-sm-3"><?= __('Last Used') ?></dt>
                        <dd class="col-sm-9">
                            <?= $servicePrincipal->last_used_at 
                                ? $servicePrincipal->last_used_at->nice() . ' (' . $servicePrincipal->last_used_at->timeAgoInWords() . ')'
                                : __('Never') ?>
                        </dd>

                        <dt class="col-sm-3"><?= __('Created') ?></dt>
                        <dd class="col-sm-9">
                            <?= $servicePrincipal->created->nice() ?>
                            <?php if ($servicePrincipal->created_by_member) : ?>
                                <?= __('by') ?> <?= h($servicePrincipal->created_by_member->sca_name) ?>
                            <?php endif; ?>
                        </dd>

                        <?php if (!empty($servicePrincipal->ip_allowlist)) : ?>
                            <dt class="col-sm-3"><?= __('IP Allowlist') ?></dt>
                            <dd class="col-sm-9">
                                <?php foreach ($servicePrincipal->ip_allowlist as $ip) : ?>
                                    <code><?= h($ip) ?></code><br>
                                <?php endforeach; ?>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php if ($user->checkCan('edit', $servicePrincipal)) : ?>
                    <div class="card-footer">
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $servicePrincipal->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink(
                            $servicePrincipal->is_active ? __('Deactivate') : __('Activate'),
                            ['action' => 'toggleActive', $servicePrincipal->id],
                            [
                                'confirm' => $servicePrincipal->is_active 
                                    ? __('Deactivate this service principal? It will no longer be able to authenticate.')
                                    : __('Activate this service principal?'),
                                'class' => $servicePrincipal->is_active ? 'btn btn-warning' : 'btn btn-success',
                            ]
                        ) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $servicePrincipal->id],
                            ['confirm' => __('Delete this service principal and all its tokens? This cannot be undone.'), 'class' => 'btn btn-danger']
                        ) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Current Roles -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= __('Assigned Roles') ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($currentRoles)) : ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?= __('Role') ?></th>
                                    <th><?= __('Scope') ?></th>
                                    <th><?= __('Dates') ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentRoles as $role) : ?>
                                    <tr>
                                        <td><?= h($role->role->name) ?></td>
                                        <td>
                                            <?= $role->branch ? h($role->branch->name) : '<em class="text-muted">' . __('Global') . '</em>' ?>
                                        </td>
                                        <td>
                                            <?= $role->start_on_to_string ?>
                                            <?= $role->expires_on ? ' - ' . $role->expires_on_to_string : '' ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($user->checkCan('edit', $servicePrincipal)) : ?>
                                                <?= $this->Form->postLink(
                                                    '<i class="bi bi-x-circle"></i>',
                                                    ['action' => 'revokeRole', $servicePrincipal->id, $role->id],
                                                    ['confirm' => __('Revoke this role?'), 'class' => 'btn btn-sm btn-outline-danger', 'escape' => false]
                                                ) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="text-muted mb-0"><?= __('No roles assigned. This service principal cannot access any resources.') ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($user->checkCan('edit', $servicePrincipal)) : ?>
                    <div class="card-footer">
                        <?= $this->Form->create(null, ['url' => ['action' => 'addRole', $servicePrincipal->id], 'class' => 'row g-2 align-items-end']) ?>
                        <div class="col-md-4">
                            <?= $this->Form->control('role_id', ['label' => __('Role'), 'options' => $roles, 'empty' => __('Select role...'), 'class' => 'form-select form-select-sm']) ?>
                        </div>
                        <div class="col-md-4">
                            <?= $this->Form->control('branch_id', ['label' => __('Scope (optional)'), 'options' => $branches, 'empty' => __('Global'), 'class' => 'form-select form-select-sm']) ?>
                        </div>
                        <div class="col-md-2">
                            <?= $this->Form->button(__('Add Role'), ['class' => 'btn btn-sm btn-primary']) ?>
                        </div>
                        <?= $this->Form->end() ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: Tokens & Recent Activity -->
        <div class="col-lg-4">
            <!-- Tokens -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><?= __('API Tokens') ?></h6>
                    <?php if ($user->checkCan('edit', $servicePrincipal)) : ?>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newTokenModal">
                            <i class="bi bi-plus"></i> <?= __('New') ?>
                        </button>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($servicePrincipal->service_principal_tokens as $token) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= h($token->name ?: __('Unnamed Token')) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?= __('Last used:') ?> <?= $token->last_used_at ? $token->last_used_at->timeAgoInWords() : __('Never') ?>
                                </small>
                                <?php if ($token->expires_at) : ?>
                                    <br>
                                    <small class="<?= $token->isExpired() ? 'text-danger' : 'text-muted' ?>">
                                        <?= $token->isExpired() ? __('Expired') : __('Expires:') ?> <?= $token->expires_at_to_string ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <?php if ($user->checkCan('edit', $servicePrincipal)) : ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-trash"></i>',
                                    ['action' => 'revokeToken', $servicePrincipal->id, $token->id],
                                    ['confirm' => __('Revoke this token? Applications using it will no longer be able to authenticate.'), 'class' => 'btn btn-sm btn-outline-danger', 'escape' => false]
                                ) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($servicePrincipal->service_principal_tokens)) : ?>
                        <li class="list-group-item text-muted text-center"><?= __('No tokens') ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><?= __('Recent API Activity') ?></h6>
                </div>
                <ul class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($servicePrincipal->service_principal_audit_logs as $log) : ?>
                        <li class="list-group-item py-2">
                            <div class="d-flex justify-content-between">
                                <span class="badge bg-<?= $log->status_category === 'success' ? 'success' : ($log->status_category === 'client_error' ? 'warning' : 'danger') ?>">
                                    <?= h($log->http_method) ?>
                                </span>
                                <small class="text-muted"><?= $log->created->timeAgoInWords() ?></small>
                            </div>
                            <small class="text-truncate d-block"><?= h($log->endpoint) ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($servicePrincipal->service_principal_audit_logs)) : ?>
                        <li class="list-group-item text-muted text-center"><?= __('No activity recorded') ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- New Token Modal -->
<div class="modal fade" id="newTokenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => ['action' => 'regenerateToken', $servicePrincipal->id]]) ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Create New API Token') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <?= $this->Form->control('name', [
                        'label' => __('Token Name'),
                        'class' => 'form-control',
                        'placeholder' => __('e.g., Production Server'),
                    ]) ?>
                </div>
                <div class="mb-3">
                    <?= $this->Form->control('expires_at', [
                        'type' => 'datetime-local',
                        'label' => __('Expires At (optional)'),
                        'class' => 'form-control',
                    ]) ?>
                    <div class="form-text"><?= __('Leave blank for no expiration.') ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <?= $this->Form->button(__('Create Token'), ['class' => 'btn btn-primary']) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const input = document.getElementById(elementId);
    input.select();
    document.execCommand('copy');
    
    const btn = input.nextElementSibling;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check"></i> <?= __("Copied!") ?>';
    setTimeout(() => {
        btn.innerHTML = originalHtml;
    }, 1500);
}
</script>
