<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting[]|\Cake\Collection\CollectionInterface $appSettings
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': App Settings';
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
        $infoHelpUrl = $this->KMP->getAppSetting("KMP.AppSettings.HelpUrl", "https://github.com/Ansteorra/KMP/wiki/App-Settings");
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
        <tr>

            <td class='align-middle'><?= h($appSetting->name) ?></td>
            <td><?= $this->Form->create($appSetting, [
                        "url" => ["action" => "edit", $appSetting->id],
                        "id" => "edit_entity__" . $appSetting->id,
                    ]) ?>
                <?= $this->Form->control("value", [
                        "label" => false,
                        "spacing" => "inline",
                        "id" => "edit_form_" . $appSetting->id . "_value",
                        "onKeypress" =>
                        '$("#edit_entity_' .
                            $appSetting->id .
                            '_submit").prop("disabled",false);',
                    ]) ?>
                <?= $this->Form->end() ?></td>
            <td class="actions">
                <?= $this->Form->button("Save", [
                        "class" => "btn btn-secondary",
                        "id" => "edit_entity_" . $appSetting->id . "_submit",
                        "onclick" =>
                        '$("#edit_entity__' . $appSetting->id . '").submit();',
                        "disabled" => true,
                    ]) ?>
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
    ]),
]);
echo $this->Form->end();

?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>