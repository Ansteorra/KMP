<?php

/**
 * @var \App\View\AppView $this
 * @var \Waivers\Model\Entity\WaiverType[]|\Cake\Collection\CollectionInterface $waiverTypes
 * @var bool $showInactive
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$this->append('css', $this->AssetMix->css('waivers'));

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Waiver Types';
$this->KMP->endBlock(); ?>

<turbo-frame id="waiver-types-frame" target="_top">
    <div class="row align-items-start">
        <div class="col">
            <h3>Waiver Types</h3>
        </div>
        <div class="col text-end">
            <?php
            $waiverTypesTable = \Cake\ORM\TableRegistry::getTableLocator()->get("Waivers.WaiverTypes");
            $tempWaiverType = $waiverTypesTable->newEmptyEntity();
            if ($user->checkCan("add", $tempWaiverType)) :
            ?>
                <?= $this->Html->link(
                    ' Add Waiver Type',
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-3">
        <?php if ($showInactive): ?>
            <?= $this->Html->link(
                'Hide Inactive',
                ['action' => 'index'],
                ['class' => 'btn btn-secondary btn-sm']
            ) ?>
        <?php else: ?>
            <?= $this->Html->link(
                'Show Inactive',
                ['action' => 'index', '?' => ['show_inactive' => 1]],
                ['class' => 'btn btn-secondary btn-sm']
            ) ?>
        <?php endif; ?>
    </div>

    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('name') ?></th>
                <th><?= $this->Paginator->sort('description') ?></th>
                <th>Template</th>
                <th>Retention Policy</th>
                <th class="text-center"><?= $this->Paginator->sort('convert_to_pdf', 'Convert to PDF') ?></th>
                <th class="text-center"><?= $this->Paginator->sort('is_active', 'Status') ?></th>
                <th class="actions text-end"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($waiverTypes->count() === 0): ?>
                <tr>
                    <td colspan="7" class="text-center">
                        <em>No waiver types found.</em>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($waiverTypes as $waiverType): ?>
                    <tr>
                        <td><?= h($waiverType->name) ?></td>
                        <td><?= h($waiverType->description) ?></td>
                        <td>
                            <?php if (!empty($waiverType->template_path)): ?>
                                <a href="<?= h($waiverType->template_path) ?>" target="_blank" rel="noopener"
                                    class="btn btn-sm btn-outline-primary" title="View External Template">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            <?php elseif (!empty($waiverType->document_id)): ?>
                                <?= $this->Html->link(
                                    '<i class="bi bi-download"></i>',
                                    ['action' => 'downloadTemplate', $waiverType->id],
                                    [
                                        'class' => 'btn btn-sm btn-success',
                                        'escape' => false,
                                        'title' => 'Download Template'
                                    ]
                                ) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= h($waiverType->retention_description) ?></small></td>
                        <td class="text-center">
                            <?php if ($waiverType->convert_to_pdf): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($waiverType->is_active): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions text-end text-nowrap">
                            <?= $this->Html->link(
                                '',
                                ['action' => 'view', $waiverType->id],
                                [
                                    'class' => 'btn btn-secondary btn-sm bi bi-binoculars-fill',
                                    'title' => __('View'),
                                    'escape' => false
                                ]
                            ) ?>
                            <?= $this->Html->link(
                                '',
                                ['action' => 'edit', $waiverType->id],
                                [
                                    'class' => 'btn btn-primary btn-sm bi bi-pencil-fill',
                                    'title' => __('Edit'),
                                    'escape' => false
                                ]
                            ) ?>
                            <?= $this->Form->postLink(
                                '',
                                ['action' => 'toggleActive', $waiverType->id],
                                [
                                    'class' => 'btn btn-warning btn-sm bi bi-' . ($waiverType->is_active ? 'pause-circle-fill' : 'play-circle-fill'),
                                    'title' => __($waiverType->is_active ? 'Deactivate' : 'Activate'),
                                    'confirm' => __(
                                        'Are you sure you want to {0} this waiver type?',
                                        $waiverType->is_active ? 'deactivate' : 'activate'
                                    ),
                                    'escape' => false
                                ]
                            ) ?>
                            <?= $this->Form->postLink(
                                '',
                                ['action' => 'delete', $waiverType->id],
                                [
                                    'class' => 'btn btn-danger btn-sm bi bi-trash-fill',
                                    'title' => __('Delete'),
                                    'confirm' => __('Are you sure you want to delete "{0}"?', $waiverType->name),
                                    'escape' => false
                                ]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

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
</turbo-frame>