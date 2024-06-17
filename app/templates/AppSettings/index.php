<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting[]|\Cake\Collection\CollectionInterface $appSettings
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            App Settings :
        </h3>
    </div>
    <div class="col text-end">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">Add</button>
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
<h4>Info</h4>
<div>
    <p>
        <strong>App Settings</strong> are used to store application-wide settings that are not
        user-specific and are not stored in the configuration file. These settings are stored in the database and can be
        changed by admins at runtime.
    </p>
    <p>
        Settings will be injected by the application into the database with initial settings so please check here if you
        see unusual behavior.
    </p>
    <p>
        all settings are strings and are stored in the database as strings. The application will convert the string to
        the appropriate data type as needed.
        For true/false settings, use 'yes' or 'no' as the value.
    </p>
    <h4>Special Settings</h4>
    <dl>
        <dt>KMP.BranchInitRun</dt>
        <dd class="ms-3">
            This setting is used to determine if the system has rebuild the branch hierarchy after a database rebuild.
            setting this to 'recovered' will prevent the system from attempting to rebuild the branch hierarchy again.
        </dd>
        <dt>KMP.*</dt>
        <dd class="ms-3">
            These settings are used to drive the Kingdom Management Portal site wide behaviors like the name of the
            site, kingdom, and some site wide feature flags.
        </dd>
        <dt>Activity.*</dt>
        <dd class="ms-3">
            Display and behavior settings that impacts aspects of authorizations and activities.
        </dd>
        <dt>Member.*</dt>
        <dd class="ms-3">
            Display and behavior settings that impacts aspects of member management.
            <dl>
                <dt>Member.AdditionalInfo</dt>
                <dd class="ms-3">
                    <p>
                        A list of fields that can be added to the member page by adding settings with the prefix
                        Member.AdditionalInfo. followed by the name.
                        The value should be the name of the field. The field will be added to the additional_info field
                        of
                        the member.
                    </p>
                    <p>
                        <i>Example:</i> Member.AdditionalInfo.Wiki_Page = text will add a field to the additional_info
                        field
                        of the member called Wiki_Page.
                    </p>
                    <strong>Data Types</strong>
                    <dl>
                        <dt>text</dt>
                        <dd class="ms-3">Text field</dd>
                        <dt>date</dt>
                        <dd class="ms-3">Date Field</dd>
                        <dt>number</dt>
                        <dd class="ms-3">Numeric Field</dd>
                        <dt>bool</dt>
                        <dd class="ms-3">Switch</dd>
                    </dl>
                </dd>
                <dt>Member.ExtenralLink.*</dt>
                <dd class="ms-3">
                    <p>
                        A list of External Links can be added to the member page by adding settings with the prefix
                        Member.ExternalLink. followed by the name.
                        The value should be the URL to the external link but you can use {{path->to->data}} to replace
                        parts
                        of the URL with member data.
                    </p>
                    <p>
                        <i>Example:</i> Member.ExternalLink.Wiki =
                        https://wiki.ansteorra.org/{{additional_info->wiki_page}} will replace
                        {{additional_info->Wiki_Page}} with the
                        value of the Wiki_Page field in the additional_info of the member.
                    </p>
                </dd>
            </dl>
        </dd>
    </dl>
</div>

<?php
echo $this->KMP->startBlock("modals");
echo $this->Modal->create("Add App Setting", [
    "id" => "addModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create($emptyAppSetting, [
        "url" => ["action" => "add"],
        "id" => "add_entity",
    ]);
    echo $this->Form->control("name");
    echo $this->Form->control("value");
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_entity__submit",
        "onclick" => '$("#add_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>