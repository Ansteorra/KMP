<?php

/**
 * Gathering Waivers for a Specific Gathering
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\GatheringWaiver[]|\Cake\Collection\CollectionInterface $gatheringWaivers
 * @var array $countsMap
 * @var array $requiredWaiverTypes
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': ' . $gathering->name . ' - Waivers';
$this->KMP->endBlock();
?>

<div class="row align-items-start">
    <div class="col">
        <h3>
            <?= $this->element('backButton') ?><?= __('Waivers for {0}', h($gathering->name)) ?>
        </h3>
    </div>
    <div class="col text-end">
        <?= $this->Html->link(
            '<i class="bi bi-plus-circle"></i> ' . __('Upload Waiver'),
            ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]],
            ['class' => 'btn btn-primary', 'escape' => false]
        ) ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <hr>
    </div>
</div>

<?php if ($gatheringWaivers->isEmpty()): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle"></i>
        <?= __('No waivers have been uploaded for this gathering yet.') ?>
    </div>
<?php else: ?>
    <!-- Waiver Count Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> <?= __('Waiver Upload Summary') ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (empty($requiredWaiverTypes)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <?= __('No waiver requirements configured for this gathering\'s activities.') ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($requiredWaiverTypes as $activityWaiver): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title"><?= h($activityWaiver->waiver_type->name) ?></h6>
                                            <p class="display-4 mb-0">
                                                <?= isset($countsMap[$activityWaiver->waiver_type_id]) ? $countsMap[$activityWaiver->waiver_type_id] : 0 ?>
                                            </p>
                                            <p class="text-muted small"><?= __('waivers uploaded') ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Waiver List Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('waiver_type_id', __('Waiver Type')) ?></th>
                    <th><?= $this->Paginator->sort('status', __('Status')) ?></th>
                    <th><?= $this->Paginator->sort('retention_date', __('Retention Until')) ?></th>
                    <th><?= $this->Paginator->sort('created', __('Uploaded')) ?></th>
                    <th class="actions text-end"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gatheringWaivers as $waiver): ?>
                    <tr>
                        <td>
                            <strong><?= h($waiver->waiver_type->name) ?></strong>
                        </td>
                        <td>
                            <?php if ($waiver->is_declined): ?>
                                <span class="badge bg-danger"><?= __('Declined') ?></span>
                            <?php elseif ($waiver->status === 'active'): ?>
                                <span class="badge bg-success"><?= __('Active') ?></span>
                            <?php elseif ($waiver->status === 'expired'): ?>
                                <span class="badge bg-danger"><?= __('Expired') ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= h($waiver->status) ?></span>
                            <?php endif; ?>
                            <?php if ($waiver->can_be_declined): ?>
                                <br><small class="text-muted"><i class="bi bi-clock"></i> <?= __('Can be declined') ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $this->Timezone->format($waiver->retention_date, null, 'M d, Y') ?>
                            <?php
                            $today = new \Cake\I18n\Date();
                            $daysUntilExpiry = $today->diffInDays($waiver->retention_date, false);
                            if ($daysUntilExpiry < 0): ?>
                                <br><small class="text-danger"><?= __('Expired {0} days ago', abs($daysUntilExpiry)) ?></small>
                            <?php elseif ($daysUntilExpiry < 90): ?>
                                <br><small class="text-warning"><?= __('Expires in {0} days', $daysUntilExpiry) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $this->Timezone->format($waiver->created, null, 'M d, Y') ?>
                            <br>
                            <small class="text-muted"><?= $this->Timezone->format($waiver->created, null, 'g:i A') ?></small>
                        </td>
                        <td class="actions text-end">
                            <?= $this->Html->link(
                                '<i class="bi bi-binoculars-fill"></i>',
                                ['action' => 'view', $waiver->id],
                                ['class' => 'btn btn-sm btn-secondary', 'escape' => false, 'title' => __('View')]
                            ) ?>
                            <?php if ($waiver->status === 'expired'): ?>
                                <?= $this->Form->postLink(
                                    'Delete',
                                    ['action' => 'delete', $waiver->id],
                                    [
                                        'confirm' => __('Are you sure you want to delete this expired waiver?'),
                                        'class' => 'btn btn-sm btn-outline-danger',
                                        'escape' => false,
                                        'title' => __('Delete')
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
        </p>
    </div>
<?php endif; ?>