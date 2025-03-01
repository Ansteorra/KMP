<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting[]|\Cake\Collection\CollectionInterface $appSettings
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': App Settings';
$this->KMP->endBlock(); ?>

<div class="row align-items-start">
    <div class="col">
        <h3>
            App Settings :
        </h3>
    </div>
    <div class="col text-end">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
            data-bs-target="#addModal">Add</button>
        <?php
        $infoHelpUrl = $this->KMP->getAppSetting("KMP.AppSettings.HelpUrl");
        echo $this->Html->link(
            "App Settings Help",
            $infoHelpUrl,
            [
                "class" => "btn btn-secondary btn-sm",
                "target" => "_blank",
            ],
        ); ?>
    </div>
</div>
<table class="table table-striped">
    <thead>
        <tr scope="row">
            <th class="col-3"><?= $this->Paginator->sort("name") ?></th>
            <th class="col-6"><?= $this->Paginator->sort("value") ?></th>
            <th class="col-3" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($appSettings as $appSetting) : ?>
            <tr data-controller='app-setting-form'>

                <td class='align-middle'><?= h($appSetting->name) ?></td>
                <td><?= $this->Form->create($appSetting, [
                        "url" => ["action" => "edit", $appSetting->id],
                        "data-app-setting-form-target" => "form",
                    ]) ?>
                    <?php if ($appSetting->type == "json" || $appSetting->type == "yaml") : ?>
                        <div data-controller="guifier-control" data-guifier-control-value='<?= $appSetting->raw_value ?>'
                            data-guifier-control-type-value='<?= $appSetting->type ?>'>
                            <?= $this->Form->hidden("raw_value", [
                                "value" => $appSetting->raw_value,
                                "id" => "raw_value_" . $appSetting->id,
                                "label" => false,
                                "spacing" => "inline",
                                "data-action" => "change->app-setting-form#enableSubmit",
                                "data-guifier-control-target" => "hidden",
                            ]) ?>
                            <div id="guifier_<?= $appSetting->id ?>" data-guifier-control-target="container">
                            </div>
                        <?php else : ?>
                            <?= $this->Form->textarea("raw_value", [
                                "label" => false,
                                "spacing" => "inline",
                                "data-action" => "change->app-setting-form#enableSubmit",
                            ]) ?>
                        <?php endif; ?>
                        <?= $this->Form->end() ?>
                </td>
                <td class="actions text-end text-nowrap">
                    <?= $this->Form->button("Save", [
                        "class" => "btn btn-secondary",
                        "disabled" => true,
                        "data-action" => "click->app-setting-form#submit",
                        "data-app-setting-form-target" => "submitBtn",
                    ]) ?>
                    <?php if (!$appSetting->required) : ?>
                        <?= $this->Form->postLink(
                            __("Delete"),
                            ["action" => "delete", $appSetting->id],
                            [
                                "confirm" => __(
                                    "Are you sure you want to delete {0}?",
                                    $appSetting->name,
                                ),
                                "title" => __("Delete"),
                                "class" => "btn btn-danger",
                            ],
                        ) ?>
                    <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first("«", ["label" => __("First")]) ?>
        <?= $this->Paginator->prev("‹", [
            "label" => __("Previous"),
        ]) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next("›", ["label" => __("Next")]) ?>
        <?= $this->Paginator->last("»", ["label" => __("Last")]) ?>
    </ul>
    <p><?= $this->Paginator->counter(
            __(
                "Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total",
            ),
        ) ?></p>
</div>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($emptyAppSetting, [
    "url" => ["action" => "add"],
    "id" => "add_entity",
]);
echo $this->Modal->create("Add App Setting", [
    "id" => "addModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("value");
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