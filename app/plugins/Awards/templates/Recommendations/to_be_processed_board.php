<?php

use Cake\Utility\Inflector;

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Award Recommendations';
$recommendation = [];
$this->KMP->endBlock(); ?>
<h3>
    Award Recommendations
    <?php if ($viewAction != "Index") : ?>
        : <?= Inflector::humanize($viewAction) ?>
    <?php endif; ?>
</h3>
<div class="overflow-x-auto table-responsive">
    <div class="text-end">
        <?php if ($ShowDeclined == false): ?>
            <a href="<?= $this->Url->build(['action' => 'ToBeProcessedBoard', '?' => ['includeDeclined' => 'true']]) ?>"
                class="btn btn-primary btn-sm end">Show Declined In last 30 days</a>
        <?php else: ?>
            <a href="<?= $this->Url->build(['action' => 'ToBeProcessedBoard']) ?>" class="btn btn-primary btn-sm end">Hide
                Declined In last 30 days</a>
        <?php endif; ?>
    </div>
    <table class="table table-striped-columns" width="100%" style="min-width:1020px" data-controller="kanban"
        data-kanban-csrf-token-value="<?= $this->request->getAttribute('csrfToken') ?>"
        data-kanban-url-value="<?= $this->Url->build(['action' => 'kanbanUpdate']) ?>">
        <thead>
            <tr>
                <?php foreach ($statuses as $statusName => $status) : ?>
                    <th scope="col" width="14.28%"><?= h($statusNames[$statusName]) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <?php
                foreach ($statuses as $statusName => $status) : ?>
                    <td class="sortable" width="14.28%" data-kanban-target="column" data-status="<?= h($statusName) ?>"
                        data-action="dragstart->kanban#grabCard dragover->kanban#cardDrag drop->kanban#dropCard ">

                        <?php
                        if (is_array($status)) :
                            foreach ($status as $recommendation) : ?>
                                <div class="card m-1" style="cursor: pointer;" draggable="true"
                                    data-stack-rank="<?= $recommendation->stack_rank ?>" data-rec-id="<?= $recommendation->id ?>"
                                    id="card_<?= $recommendation->id ?>" data-kanban-target="card">
                                    <div class="card-body">
                                        <div class="card-title">
                                            <?= $this->Html->link($recommendation->award->abbreviation, ['action' => 'view', $recommendation->id]) ?>
                                            <button type="button" class="btn btn-primary btn-sm float-end edit-rec"
                                                data-bs-toggle="modal" data-bs-target="#editModal" data-controller="grid-btn"
                                                data-action="click->grid-btn#fireNotice"
                                                data-grid-btn-row-data-value='{ "id":<?= $recommendation->id ?>}'>
                                                Edit</button>
                                        </div>
                                        <h6 class="card-subtitle mb-2 text-body-secondary"><?= $recommendation->member_sca_name ?>
                                        </h6>
                                        <p class="card-text"><?= $this->Text->autoParagraph(
                                                                    h($this->Text->truncate($recommendation->reason, 100)),
                                                                ) ?></p>
                                        <b>Last Modified: </b><?= $recommendation->modified->format('m/d/Y') ?> by
                                        <?= $recommendation->ModifiedByMembers['sca_name'] ?>
                                    </div>
                                </div>
                        <?php endforeach;
                        endif; ?>
                    </td>
                <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
echo $this->KMP->startBlock("modals"); ?>

<?= $this->element('recommendationEditModal') ?>

<?php echo $this->KMP->endBlock(); ?>