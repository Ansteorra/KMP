<?php

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\EntityInterface[]|\Cake\Collection\CollectionInterface $warrantPeriods
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrants';
$this->KMP->endBlock(); ?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Warrant Periods
        </h3>
    </div>
    <div class="col text-end">
        <?php
        $warrantPeriodsTable = \Cake\ORM\TableRegistry::getTableLocator()->get("WarrantPeriods");
        $tempWarrantPeriod = $warrantPeriodsTable->newEmptyEntity();
        if ($user->checkCan("add", $tempWarrantPeriod)) :
        ?>
            <button type="button" class="btn btn-primary btn-sm bi bi-plus-circle" data-bs-toggle="modal"
                data-bs-target="#addModal"> Add Warrant Period</button>
        <?php endif; ?>
    </div>
</div>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort('start_date') ?></th>
            <th scope="col"><?= $this->Paginator->sort('end_date') ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($warrantPeriods as $warrantPeriod) : ?>
            <tr>
                <td><?= h($warrantPeriod->start_date) ?></td>
                <td><?= h($warrantPeriod->end_date) ?></td>
                <td class="actions text-end text-nowrap">
                    <?= $this->Form->postLink(
                        __("Delete"),
                        ["action" => "delete", $warrantPeriod->id],
                        [
                            "confirm" => __(
                                "Are you sure you want to delete This Warrant period?",
                            ),
                            "title" => __("Delete"),
                            "class" => "btn btn-danger",
                        ],
                    ) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first('«', ['label' => __('First')]) ?>
        <?= $this->Paginator->prev('‹', ['label' => __('Previous')]) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next('›', ['label' => __('Next')]) ?>
        <?= $this->Paginator->last('»', ['label' => __('Last')]) ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
    </p>
</div>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($emptyWarrantPeriod, [
    "url" => ["action" => "add"],
    "id" => "add_entity",
]);
echo $this->Modal->create("Add Warrant Period", [
    "id" => "addModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("start_date", [
        "type" => "date",
        "label" => __("Start Date"),
    ]);
    echo $this->Form->control("end_date", [
        "type" => "date",
        "label" => __("End Date"),
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_entity__submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();

?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>