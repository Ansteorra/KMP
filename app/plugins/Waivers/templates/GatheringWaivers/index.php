<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\GatheringWaiver[]|\Cake\Collection\CollectionInterface $gatheringWaivers
 * @var array $countsMap
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gathering Waivers';
$this->KMP->endBlock();
?>

<div class="gathering-waivers index content">
    <div class="row">
        <div class="col-md-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><?= $this->Html->link(__('Gatherings'), ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'index']) ?></li>
                    <li class="breadcrumb-item"><?= $this->Html->link($gathering->name, ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id]) ?></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= __('Waivers') ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h2>
                <?= __('Waivers for {0}', h($gathering->name)) ?>
            </h2>
            <p class="text-muted">
                <?= __('Dates: {0} to {1}', $gathering->start_date->format('M d, Y'), $gathering->end_date->format('M d, Y')) ?>
                <span class="mx-2">|</span>
                <?= __('Branch: {0}', h($gathering->branch->name)) ?>
            </p>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <?= $this->Html->link(
                '<i class="bi bi-upload"></i> ' . __('Upload Waivers'),
                ['action' => 'upload', '?' => ['gathering_id' => $gathering->id]],
                ['class' => 'btn btn-primary', 'escape' => false]
            ) ?>
            <?= $this->Html->link(
                '<i class="bi bi-arrow-left"></i> ' . __('Back to Gathering'),
                ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id],
                ['class' => 'btn btn-secondary', 'escape' => false]
            ) ?>
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
                            <?php
                            // Get unique waiver types from gatherings's activities
                            $requiredWaiverTypes = [];
                            foreach ($gathering->gathering_activities as $activity) {
                                if (!empty($activity->gathering_activity_waivers)) {
                                    foreach ($activity->gathering_activity_waivers as $activityWaiver) {
                                        $typeId = $activityWaiver->waiver_type_id;
                                        if (!isset($requiredWaiverTypes[$typeId])) {
                                            $requiredWaiverTypes[$typeId] = $activityWaiver->waiver_type;
                                        }
                                    }
                                }
                            }
                            ?>
                            <?php foreach ($requiredWaiverTypes as $typeId => $waiverType): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="card-title"><?= h($waiverType->name) ?></h6>
                                            <p class="display-4 mb-0">
                                                <?= isset($countsMap[$typeId]) ? $countsMap[$typeId] : 0 ?>
                                            </p>
                                            <p class="text-muted small"><?= __('waivers uploaded') ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
                        <th><?= $this->Paginator->sort('member_id', __('Member')) ?></th>
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
                                <?php if ($waiver->member): ?>
                                    <?= $this->Html->link(
                                        h($waiver->member->sca_name),
                                        ['controller' => 'Members', 'action' => 'view', $waiver->member->id]
                                    ) ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= __('(No member)') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($waiver->status === 'active'): ?>
                                    <span class="badge bg-success"><?= __('Active') ?></span>
                                <?php elseif ($waiver->status === 'expired'): ?>
                                    <span class="badge bg-danger"><?= __('Expired') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= h($waiver->status) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= h($waiver->retention_date->format('M d, Y')) ?>
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
                                <?= h($waiver->created->format('M d, Y')) ?>
                                <br>
                                <small class="text-muted"><?= h($waiver->created->format('g:i A')) ?></small>
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
            <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
        </div>
    <?php endif; ?>
</div>