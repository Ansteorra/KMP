<?php

/**
 * Warrant Periods Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\WarrantPeriod $emptyWarrantPeriod
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrant Periods';
$this->KMP->endBlock();

$this->assign('title', __('Warrant Periods'));
?>

<div class="warrant-periods index content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('Warrant Periods') ?></h3>
        <div>
            <?php if ($user->checkCan("add", "WarrantPeriods")) : ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#addModal">
                    <i class="bi bi-plus-circle me-1"></i><?= __('Add Warrant Period') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'WarrantPeriods.index.main',
        'frameId' => 'warrant-periods-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>

<?php
// Add modal for creating new warrant periods
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
$this->KMP->endBlock();
?>