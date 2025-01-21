<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission $permission
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Office - ' . h($office->name);
$this->KMP->endBlock();
echo $this->KMP->startBlock("pageTitle") ?>
<?= h($office->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?= $this->Form->postLink(
    __("Delete"),
    ["action" => "delete", $office->id],
    [
        "confirm" => __(
            "Are you sure you want to delete {0}?",
            $office->name,
        ),
        "title" => __("Delete"),
        "class" => "btn btn-danger btn-sm",
    ],
) ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr scope="row">
    <th class='col'><?= __("Department") ?></th>
    <td class="col-10"><?= $office->hasValue(
                            "department",
                        )
                            ? $this->Html->link($office->department->name, [
                                "controller" => "Departments",
                                "action" => "view",
                                $office->department->id,
                            ])
                            : "" ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Branch Types") ?></th>
    <td class="col-10">
        <?php
        echo implode(", ", $office->branch_types);
        ?>
    </td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Term (Years)") ?></th>
    <td><?= h($office->term_length) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Required") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $office->required_office,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Skip Report") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $office->can_skip_report,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("Warrant") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $office->requires_warrant,
                            $this->Html,
                        ) ?></td>
</tr>
<tr scope="row">
    <th class='col'><?= __("One Per Branch") ?></th>
    <td class="col-10"><?= $this->Kmp->bool(
                            $office->only_one_per_branch,
                            $this->Html,
                        ) ?></td>
</tr>
<th class='col'><?= __("Reports To") ?></th>
<td class="col-10"><?= $office->hasValue(
                        "reports_to",
                    )
                        ? $this->Html->link($office->reports_to->name, [
                            "controller" => "Offices",
                            "action" => "view",
                            $office->reports_to->id,
                        ])
                        : "Society" ?></td>
</tr>
<th class='col'><?= __("Deputy To") ?></th>
<td class="col-10"><?= $office->hasValue(
                        "deputy_to",
                    )
                        ? $this->Html->link($office->deputy_to->name, [
                            "controller" => "Offices",
                            "action" => "view",
                            $office->deputy_to->id,
                        ])
                        : "" ?></td>
</tr>
</tr>
<th class='col'><?= __("Grants Role") ?></th>
<td class="col-10"><?= $office->hasValue(
                        "grants_role",
                    )
                        ? $this->Html->link($office->grants_role->name, [
                            "plugin" => null,
                            "controller" => "Roles",
                            "action" => "view",
                            $office->grants_role->id,
                        ])
                        : "" ?></td>
</tr>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<?php $this->KMP->endBlock() ?>
<?php //Start writing to modal block in layout

echo $this->KMP->startBlock("modals"); ?>

<?php echo $this->Modal->create("Edit Office", [
    "id" => "editModal",
    "close" => true,
]); ?>
<fieldset>
    <?php
    echo $this->Form->create($office, [
        "id" => "edit_entity",
        "url" => [
            "controller" => "Offices",
            "action" => "edit",
            $office->id,
        ],
        'data-controller' => 'office-form'
    ]);
    echo $this->Form->control("name");
    echo $this->Form->control("department_id", [
        "options" => $departments,
        "empty" => true,
    ]);
    echo $this->Form->control("branch_types", [
        "type" => "select",
        "multiple" => "checkbox",
        "options" => $branch_types,
        "switch" => true,
        "label" => [
            "class" => "required",
        ],

    ]);
    echo $this->Form->control("term_length");
    echo $this->Form->control("required_office", ["switch" => true, 'label' => 'Required']);
    echo $this->Form->control("can_skip_report", ["switch" => true, 'label' => 'Skip Report']);
    echo $this->Form->control("requires_warrant", ["switch" => true, 'label' => 'Warrant']);
    echo $this->Form->control("only_one_per_branch", ["switch" => true, 'label' => 'One Per Branch']);
    echo $this->Form->control("is_deputy", [
        "type" => "checkbox",
        "switch" => true,
        'label' => 'Is Deputy Office',
        'data-action' => 'office-form#toggleIsDeputy',
        'data-office-form-target' => 'isDeputy'
    ]);
    echo $this->Form->control("reports_to_id", [
        "options" => $report_to_offices,
        "empty" => true,
        'data-office-form-target' => 'reportsTo',
        'container' => ['data-office-form-target' => 'reportsToBlock']
    ]);
    echo $this->Form->control("deputy_to_id", [
        "required" => true,
        "options" => $deputy_to_offices,
        "empty" => true,
        'data-office-form-target' => 'deputyTo',
        'container' => ['data-office-form-target' => 'deputyToBlock']
    ]);
    echo $this->Form->control("grants_role_id", [
        "options" => $roles,
        "empty" => true,
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);

echo $this->Form->end(); ?>


<?php //finish writing to modal block in layout

$this->KMP->endBlock(); ?>