<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $event
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Rec Event - ' . $event->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($event->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php
echo $this->Form->postLink(
    __("Delete"),
    ["action" => "delete", $event->id],
    [
        "confirm" => __(
            "Are you sure you want to delete {0}?",
            $event->name,
        ),
        "title" => __("Delete"),
        "class" => "btn btn-danger btn-sm",
    ],
); ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __("Description") ?></th>
    <td><?= h($event->description) ?></td>
</tr>
<tr>
    <th scope="row"><?= __("Start Date") ?></th>
    <td><?= h(($event->start_date ? $event->start_date->toDateString() : "")) ?></td>
</tr>
<tr>
    <th scope="row"><?= __("End Date") ?></th>
    <td><?= h(($event->end_date ? $event->end_date->toDateString() : "")) ?></td>
</tr>

<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php
$currentUser = $this->request->getAttribute('identity');
if ($currentUser->can("view", "Awards.Recommendations")): ?>
<button class="nav-link" id="nav-scheduledAwards-tab" data-bs-toggle="tab" data-bs-target="#nav-scheduledAwards"
    type="button" role="tab" aria-controls="nav-scheduledAwards" aria-selected="false"
    data-detail-tabs-target='tabBtn'><?= __("Scheduled Awards") ?></button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<?php
if ($currentUser->can("view", "Awards.Recommendations")): ?>
<div class="related tab-pane fade m-3" id="nav-scheduledAwards" role="tabpanel"
    aria-labelledby="nav-scheduledAwards-tab" data-detail-tabs-target="tabContent">
    <?php if (!empty($event->recommendations_to_give)) :
            $csv = [];
            $csv[] = ["Title", "Name", "Pronunciation", "Pronouns", "Award", "Court Availability", "Call Into Court", "Person To Notify", "Status", "Reason"];
            foreach ($event->recommendations_to_give as $rec) {
                $csv[] = [
                    $rec->title,
                    $rec->member_sca_name,
                    $rec->pronunciation,
                    $rec->pronouns,
                    $rec->award->abbreviation . ($rec->specialty ? " (" . $rec->specialty . ")" : ""),
                    $rec->court_availability,
                    $rec->call_into_court,
                    $rec->person_to_notify,
                    $rec->status,
                    $rec->reason,
                ];
            }
            $exportString = $this->KMP->makeCsv($csv);
            //url encode the csv string
            $exportString = urlencode($exportString);
            //replace encoded spaces with spaces
            $exportString = str_replace("+", " ", $exportString);
        ?>
    <div class="table-responsive">
        <a href="data:text/csv;charset=utf-8,<?= $exportString ?>" download="recommendations.csv"
            class="btn btn-primary btn-sm">Export CSV</a>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col"><?= h("Title") ?></th>
                    <th scope="col"><?= h("Name") ?></th>
                    <th scope="col"><?= h("Pronunciation") ?></th>
                    <th scope="col"><?= h("Pronouns") ?></th>
                    <th scope="col"><?= h(
                                                "Award",
                                            ) ?></th>
                    <th scope="col"><?= h(
                                                "Court Availability",
                                            ) ?></th>
                    <th scope="col"><?= h(
                                                "Call Into Court",
                                            ) ?></th>
                    <th scope="col"><?= h(
                                                "Person To Notify",
                                            ) ?></th>
                    <th scope="col"><?= h(
                                                "Status",
                                            ) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (
                            $event->recommendations_to_give
                            as $rec
                        ) : ?>
                <tr>
                    <td><?= h($rec->title) ?></td>
                    <td><?= h($rec->member_sca_name) ?></td>
                    <td><?= h($rec->pronunciation) ?></td>
                    <td><?= h($rec->pronouns) ?></td>
                    <td><?= h($rec->award->abbreviation) ?>
                        <?php if ($rec->specialty) : ?>
                        (<?= h($rec->specialty) ?>)
                        <?php endif; ?>
                    </td>
                    <td><?= h($rec->call_into_court) ?></td>
                    <td><?= h($rec->court_availability) ?></td>
                    <td><?= h($rec->person_to_notify) ?></td>
                    <td><?= h($rec->status) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p>No Awards Scheduled</p>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($event, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Events",
        "action" => "edit",
        $event->id,
    ],
]);
echo $this->Modal->create("Edit Award Rec Event", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("description");
    echo $this->Form->control('branch_id', ['options' => $branches]);
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
        "id" => "edit_entity__submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end(); ?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>