<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering|null $gathering
 * @var \Waivers\Model\Entity\GatheringWaiver[]|\Cake\Collection\CollectionInterface $gatheringWaivers
 * @var array $countsMap
 * @var array $branches
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
if ($gathering) {
    echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': ' . $gathering->name . ' - Waivers';
} else {
    echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': All Waivers';
}
$this->KMP->endBlock();
?>

<div class="row align-items-start">
    <div class="col">
        <h3>
            <?php if ($gathering): ?>
                <?= $this->element('backButton') ?><?= __('Waivers for {0}', h($gathering->name)) ?>
            <?php else: ?>
                <?= __('All Waivers') ?>
            <?php endif; ?>
        </h3>
    </div>
    <div class="col text-end">
        <?php if ($gathering): ?>
            <?= $this->Html->link(
                '<i class="bi bi-plus-circle"></i> ' . __('Upload Waiver'),
                ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <hr>
    </div>
</div>

<?php if (!$gathering): ?>
    <!-- Search Form (only for all waivers view) -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-search"></i> <?= __('Search Waivers') ?></h5>
                </div>
                <div class="card-body">
                    <?= $this->Form->create(null, ['type' => 'get', 'valueSources' => 'query']) ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <?= $this->Form->control('search', [
                                'label' => __('Gathering Name'),
                                'placeholder' => __('Search by gathering name...'),
                                'class' => 'form-control',
                                'value' => $this->request->getQuery('search'),
                            ]) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $this->Form->control('branch_id', [
                                'label' => __('Branch'),
                                'options' => $branches,
                                'empty' => __('All Branches'),
                                'class' => 'form-select',
                                'value' => $this->request->getQuery('branch_id'),
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <?= $this->Form->control('start_date', [
                                'label' => __('From Date'),
                                'type' => 'date',
                                'class' => 'form-control',
                                'value' => $this->request->getQuery('start_date'),
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <?= $this->Form->control('end_date', [
                                'label' => __('To Date'),
                                'type' => 'date',
                                'class' => 'form-control',
                                'value' => $this->request->getQuery('end_date'),
                            ]) ?>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <?= $this->Form->button('', [
                                'type' => 'submit',
                                'class' => 'mb-3 btn btn-primary w-100 bi bi-search',
                                'escape' => false,
                                'title' => __('Search'),
                            ]) ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <?php if (
                                !empty($this->request->getQuery('search')) ||
                                !empty($this->request->getQuery('branch_id')) ||
                                !empty($this->request->getQuery('start_date')) ||
                                !empty($this->request->getQuery('end_date'))
                            ): ?>
                                <?= $this->Html->link(
                                    'Clear Filters',
                                    ['action' => 'index'],
                                    ['class' => 'btn btn-sm btn-outline-secondary bi bi-x-circle', 'escape' => false]
                                ) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($gatheringWaivers->isEmpty()): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle"></i>
        <?php if ($gathering): ?>
            <?= __('No waivers have been uploaded for this gathering yet.') ?>
        <?php else: ?>
            <?= __('No waivers found.') ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($gathering): ?>
        <!-- Waiver Count Summary (only for single gathering view) -->
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
    <?php endif; ?>

    <!-- Waiver List Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <?php if (!$gathering): ?>
                        <th><?= $this->Paginator->sort('Gatherings.name', __('Gathering')) ?></th>
                    <?php endif; ?>
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
                        <?php if (!$gathering): ?>
                            <td>
                                <?= $this->Html->link(
                                    h($waiver->gathering->name),
                                    ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $waiver->gathering->public_id]
                                ) ?>
                                <br>
                                <small class="text-muted">
                                    <i class="bi bi-building"></i> <?= h($waiver->gathering->branch->name) ?>
                                </small>
                                <br>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-event"></i>
                                    <?= $this->Timezone->format($waiver->gathering->start_date, $waiver->gathering, 'M d, Y') ?>
                                    <?php if ($waiver->gathering->start_date != $waiver->gathering->end_date): ?>
                                        - <?= $this->Timezone->format($waiver->gathering->end_date, $waiver->gathering, 'M d, Y') ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                        <?php endif; ?>
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
                                ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false, 'title' => __('View')]
                            ) ?>
                            <?= $this->Html->link(
                                '<i class="bi bi-download"></i>',
                                ['action' => 'download', $waiver->id],
                                ['class' => 'btn btn-sm btn-outline-success', 'escape' => false, 'title' => __('Download')]
                            ) ?>
                            <?php if ($waiver->status === 'expired'): ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-trash-fill"></i>',
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
</div>