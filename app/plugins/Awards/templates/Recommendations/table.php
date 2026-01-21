<?php
$columns = $pageConfig['table']['columns'];
//count columns that are set to true
$columnCount = count(array_filter($columns, function ($value) {
    return $value;
}));
//get the current url
$currentUrl = $this->request->getRequestTarget();
?>
<turbo-frame id="tableView-frame" data-turbo='true'>


    <div class="row">
        <div class="col-12 text-end mt-2">
            <?php if ($user->checkCan("edit", "Awards.Recommendations")): ?>
            <button type="button" class="btn btn-primary btn-sm bulk-edit-btn" data-bs-toggle="modal"
                data-bs-target="#tableBulkEditModal" data-controller="outlet-btn"
                data-outlet-btn-require-data-value="true" data-action="click->outlet-btn#fireNotice" disabled>Bulk
                Edit</button>
            <?php endif; ?>
            <?php
            if ($enableExport != ""):
                #add .csv to the current url by adding it before the ? if there is one
                if (strpos($currentUrl, "?") !== false) {
                    $currentUrlStart = substr($currentUrl, 0, strpos($currentUrl, "?"));
                    $currentUrlEnd = substr($currentUrl, strpos($currentUrl, "?"));
                    $currentUrl = $currentUrlStart . ".csv" . $currentUrlEnd;
                } else {
                    $currentUrl = $currentUrl . ".csv";
                }
                $exportUrl = $currentUrl;
            ?>
            <a href="<?= $exportUrl ?>" class="btn btn-outline-primary btn-sm" data-controller="csv-download"
                data-csv-download-url-value="<?= $exportUrl ?>" data-csv-download-filename-value="recommendations.csv"
                title="Download CSV">
                <i class="bi bi-download"></i> Download CSV
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $url = $this->URL->build(["controller" => "Recommendations", "action" => "table", "plugin" => "Awards", $view, $status]);
    ?>
    <?= $this->Form->create(null, ["url" => $url, "type" => "get", "data-controller" => "filter-grid"]) ?>
    <?php
    //check if there is a member_id in the query string and add a hidden field for it is there
    if ($this->request->getQuery("member_id")) {
        echo $this->Form->hidden("member_id", ["value" => $this->request->getQuery("member_id")]);
    }
    //check if there is a hidden gathering_id in the query string and add a hidden field for it is there
    if ($this->request->getQuery("gathering_id")) {
        echo $this->Form->hidden("gathering_id", ["value" => $this->request->getQuery("gathering_id")]);
    } ?>
    <?= $this->Form->hidden("sort", ["value" => $this->request->getQuery("sort")]) ?>
    <?= $this->Form->hidden("direction", ["value" => $this->request->getQuery("direction")]) ?>
    <div data-controller='awards-rec-table' data-awards-rec-table-outlet-btn-outlet=".bulk-edit-btn">
        <table class="table table-striped">
            <thead>
                <tr class="align-top">
                    <!-- <th><?= $this->Form->checkbox('selectAll', ['onchange' => 'checkAll(this)']) ?></th> -->
                    <th><input type="checkbox" name="checkAllButton" data-awards-rec-table-target="CheckAllBox"
                            data-action="awards-rec-table#checkAll"></th>
                    <!--                     <?php
                                                if ($user->checkCan("edit", "Awards.Recommendations")): ?>
                        <th scope="col"></th>
                    <?php endif; ?>
 -->
                    <?php if ($columns["Submitted"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("created", "Submitted") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["For"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("member_sca_name", "For") ?>
                        <?= $this->Form->control("for", [
                                "type" => "text",
                                "label" => false,
                                "placeholder" => "For",
                                "value" => $this->request->getQuery("for"),
                                "data-action" => "change->filter-grid#submitForm",
                            ]) ?>
                    </th>
                    <?php endif; ?>
                    <?php if ($columns["For Herald"]): ?>
                    <th scope="col">For</th>
                    <?php endif; ?>
                    <?php if ($columns["Title"]): ?>
                    <th scope="col">Title</th>
                    <?php endif; ?>
                    <?php if ($columns["Pronouns"]): ?>
                    <th scope="col">Pronouns</th>
                    <?php endif; ?>
                    <?php if ($columns["Pronunciation"]): ?>
                    <th scope="col">Pronunciation</th>
                    <?php endif; ?>
                    <?php if ($columns["OP"]): ?>
                    <th scope="col">OP</th>
                    <?php endif; ?>
                    <?php if ($columns["Branch"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Branches.name", "Branch") ?>
                        <?= $this->Form->control("branch_id", [
                                "type" => "select",
                                "label" => false,
                                "value" => $this->request->getQuery("branch_id"),
                                "options" => $branches,
                                "empty" => true,
                                "data-action" => "change->filter-grid#submitForm",
                            ]) ?>
                    </th>
                    <?php endif; ?>
                    <?php if ($columns["Call Into Court"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("call_into_court") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["Court Avail"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Court Avail.") ?></th>

                    <?php endif; ?>
                    <?php if ($columns["Person to Notify"]): ?>
                    <th scope="col">Person to Notify</th>
                    <?php endif; ?>
                    <?php if ($columns["Submitted By"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("requester_sca_name", "Submitted By") ?>
                        <?= $this->Form->control("requester_sca_name", [
                                "type" => "text",
                                "label" => false,
                                "placeholder" => "Submitted By",
                                "value" => $this->request->getQuery("requester_sca_name"),
                                "data-action" => "change->filter-grid#submitForm",
                            ]) ?>
                    </th>
                    <?php endif; ?>
                    <?php if ($columns["Contact Email"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Contact Email") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["Contact Phone"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Contact Phone") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["Domain"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Domains.name", "Domain") ?>
                        <?= $this->Form->control("domain_id", [
                                "type" => "select",
                                "label" => false,
                                "value" => $this->request->getQuery("domain_id"),
                                "options" => $domains,
                                "empty" => true,
                                "data-action" => "change->filter-grid#submitForm",
                            ]) ?>
                    </th>
                    <?php endif; ?>
                    <?php if ($columns["Award"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Awards.name", "Award") ?>
                        <?= $this->Form->control("award_id", [
                                "type" => "select",
                                "label" => false,
                                "placeholder" => "Award",
                                "value" => $this->request->getQuery("award_id"),
                                "options" => $awards,
                                "empty" => true,
                                "data-action" => "change->filter-grid#submitForm",
                            ]) ?>
                    </th>
                    <?php endif; ?>
                    <?php if ($columns["Reason"]): ?>
                    <th scope="col">Reason</th>
                    <?php endif; ?>
                    <?php if ($columns["Events"]): ?>
                    <th scope="col">Events</th>
                    <?php endif; ?>
                    <?php if ($columns["Notes"]): ?>
                    <th scope="col">Notes</th>
                    <?php endif; ?>
                    <?php if ($columns["Status"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Status") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["State"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("State") ?>
                        <?= $this->Form->control("state", [
                                "type" => "select",
                                "label" => false,
                                "placeholder" => "State",
                                "value" => $this->request->getQuery("state"),
                                "options" => $statusList,
                                "empty" => true,
                                "data-action" => "change->filter-grid#submitForm",
                            ]) ?>
                    </th>
                    <?php endif; ?>
                    <?php if ($columns["Close Reason"]): ?>
                    <th scope="col">Close Reason</th>
                    <?php endif; ?>
                    <?php if ($columns["Event"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("AssignedGathering.name", "Event") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["State Date"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("State Date") ?></th>
                    <?php endif; ?>
                    <?php if ($columns["Given Date"]): ?>
                    <th scope="col"><?= $this->Paginator->sort("Given Date") ?></th>
                    <?php endif; ?>
                    <th scope="col" class="actions">
                        <?= $this->Form->button('Filter', ["id" => "filter_btn", "class" => "d-none"]); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recommendations as $recommendation) : ?>
                <tr>
                    <?php if ($user->checkCan("edit", "Awards.Recommendations")): ?>
                    <td><input type="checkbox" name="check_list[]" value=<?= h($recommendation->id) ?> form="bulkForm"
                            data-awards-rec-table-target="rowCheckbox" data-action="awards-rec-table#checked"></td>
                    <?php endif; ?>

                    <?php if ($columns["Submitted"]): ?>
                    <td><?= $recommendation->created ? $this->Timezone->format($recommendation->created, 'Y-m-d', false) : '-' ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["For"]): ?>
                    <td><?php
                                if ($recommendation->member_id && $user->checkCan('view', "Members")) {
                                    echo $this->Html->link(
                                        h($recommendation->member_sca_name),
                                        ["controller" => "Members", "plugin" => null, "action" => "view", $recommendation->member_id],
                                        ["title" => __("View"), "data-turbo-frame" => "_top"],
                                    );
                                } else {
                                    echo h($recommendation->member_sca_name);
                                }
                                ?></td>
                    <?php endif; ?>
                    <?php if ($columns["For Herald"]): ?>
                    <td><?php
                                $name = $recommendation->member ? $recommendation->member->name_for_herald : $recommendation->member_sca_name;
                                if ($recommendation->member_id) {
                                    echo $this->Html->link(
                                        h($name),
                                        ["controller" => "Members", "plugin" => null, "action" => "view", $recommendation->member_id],
                                        ["title" => __("View"), "data-turbo-frame" => "_top"],
                                    );
                                } else {
                                    echo h($recommendation->member_sca_name);
                                }
                                ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Title"]): ?>
                    <td><?= h(($recommendation->member ? $recommendation->member->title : "")) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Pronouns"]): ?>
                    <td><?= h(($recommendation->member ? $recommendation->member->pronouns : "")) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Pronunciation"]): ?>
                    <td><?= h(($recommendation->member ? $recommendation->member->pronunciation : "")) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["OP"]): ?>
                    <td>
                        <?php
                                if ($recommendation->member) :
                                    $member = $recommendation->member;
                                    $externalLinks =  $member->publicLinks();
                                    if ($externalLinks) :
                                        foreach ($externalLinks as $name => $link) : ?>
                        <ul>
                            <li><?= $this->Html->link(
                                                        $name,
                                                        $link,
                                                        ["title" => $name, "target" => "_blank"],
                                                    ) ?></li>
                        </ul>
                        <?php endforeach;
                                    endif;
                                endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["Branch"]): ?>
                    <td><?= h($recommendation->branch->name) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Call Into Court"]): ?>
                    <td><?= h($recommendation->call_into_court) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Court Avail"]): ?>
                    <td><?= h($recommendation->court_availability) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Person to Notify"]): ?>
                    <td><?= h($recommendation->person_to_notify) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Submitted By"]): ?>
                    <td><?php
                                if ($recommendation->requester_id) {
                                    echo $this->Html->link(
                                        h($recommendation->requester_sca_name),
                                        ["controller" => "Members", "plugin" => null, "action" => "view", $recommendation->requester_id],
                                        ["title" => __("View"), "data-turbo-frame" => "_top"],
                                    );
                                } else {
                                    echo h($recommendation->requester_sca_name);
                                }
                                ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Contact Email"]): ?>
                    <td><?= h($recommendation->contact_email) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Contact Phone"]): ?>
                    <td><?= h($recommendation->contact_phone) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Domain"]): ?>
                    <td><?= h($recommendation->award->domain->name) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Award"]): ?>
                    <td><?= h($recommendation->award->abbreviation) . ($recommendation->specialty ? " (" . $recommendation->specialty . ")" : "") ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["Reason"]):
                            $reason = $recommendation->reason;
                            $showMore = false;
                            if (strlen($reason) > 100):
                                $reason = substr($reason, 0, 100) . "...";
                                $showMore = true;
                            endif;
                        ?>
                    <td><?= $this->Text->autoParagraph($reason) ?>
                        <?php if ($showMore): ?>
                        <a data-bs-toggle="collapse" href="#reason_<?= $recommendation->id ?>" role="button"
                            aria-expanded="false" aria-controls="reason_<?= $recommendation->id ?>">Show More</a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["Events"]): ?>
                    <td>
                        <?php

                                //sort the events array by start date asending
                                usort($recommendation->gatherings, function ($a, $b) {
                                    return $a->start_date->toUnixString() <=> $b->start_date->toUnixString();
                                });
                                $remainingEvents = $recommendation->gatherings;
                                //filter out events that have already happened
                                $remainingEvents = array_filter($remainingEvents, function ($event) {
                                    return $event->end_date->toUnixString() >= \Cake\I18n\Date::now()->toUnixString();
                                });
                                $eventCount = count($remainingEvents ?? []);
                                $eventsRendered = 0;
                                if ($eventCount > 3) {
                                    $eventsToRendered = 3;
                                } else {
                                    $eventsToRendered = $eventCount;
                                }
                                ?>
                        <?php if ($eventsToRendered > 0) : ?>
                        Next <?= $eventsToRendered ?> Events:
                        <?php else : ?>
                        No Upcoming Events
                        <?php endif; ?>
                        <ul>
                            <?php

                                    foreach ($remainingEvents as $event) :
                                        $eventsRendered++ ?>
                            <li style="white-space:nowrap">
                                <?= h($event->name) ?></li>
                            <?php if ($eventsRendered >= 3) :
                                            break;
                                        endif; ?>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($eventCount > 3) : ?>
                        <a data-bs-toggle="collapse" href="#events_<?= $recommendation->id ?>" role="button"
                            aria-expanded="false" aria-controls="events_<?= $recommendation->id ?>">Show All</a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["Notes"]): ?>
                    <td>
                        <a data-bs-toggle="collapse" href="#notes_<?= $recommendation->id ?>" role="button"
                            aria-expanded="false" aria-controls="notes_<?= $recommendation->id ?>">Show Notes</a>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["Status"]): ?>
                    <td><?= h($recommendation->status) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["State"]): ?>
                    <td><?= h($recommendation->state) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Close Reason"]): ?>
                    <td><?= h($recommendation->close_reason) ?></td>
                    <?php endif; ?>
                    <?php if ($columns["Event"]): ?>
                    <td><?= h($recommendation->assigned_gathering ? $recommendation->assigned_gathering->name : "") ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["State Date"]): ?>
                    <td><?= $recommendation->state_date ? $this->Timezone->format($recommendation->state_date, 'Y-m-d', false) : ($recommendation->created ? $this->Timezone->format($recommendation->created, 'Y-m-d', false) : '-') ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($columns["Given Date"]): ?>
                    <td><?= $recommendation->given ? $recommendation->given->format('Y-m-d') : "" ?>
                    </td>
                    <?php endif; ?>
                    <td class="actions text-end text-nowrap">
                        <?php if ($user->checkCan("edit", $recommendation)) : ?>
                        <button type="button" class="btn btn-primary btn-sm edit-rec bi-pencil-fill"
                            data-bs-toggle="modal" data-bs-target="#tableEditModal" data-controller="outlet-btn"
                            data-action="click->outlet-btn#fireNotice"
                            data-outlet-btn-btn-data-value='{ "id":<?= $recommendation->id ?>}' ,></button>
                        <?php endif; ?>
                        <?php if ($user->checkCan("view", $recommendation)) : ?>
                        <?= $this->Html->link(
                                    __(""),
                                    ["action" => "view", $recommendation->id],
                                    ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill", "data-turbo-frame" => "_top"],
                                ) ?>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php $rowCount = 1; ?>
                <?php if ($columns["Notes"]):
                        $rowCount++ ?>
                <tr class="collapse table-active" id="notes_<?= $recommendation->id ?>"
                    colspan="<?= ($columnCount + 1) ?>">
                    <td colspan="<?= $columnCount + 1 ?>">
                        <div class="card">
                            <div class="card-header">Notes</div>
                            <div class="card-body">
                                <ul>
                                    <?php foreach ($recommendation->notes as $note) : ?>
                                    <li><?= h($note->author->sca_name) ?> -
                                        <?= $note->created ? $this->Timezone->format($note->created, 'F j, Y g:i A', true) : '-' ?>
                                        :
                                        <?= $this->Text->autoParagraph($note->body) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($columns["Events"]):
                        $rowCount++ ?>
                <tr class="collapse table-active" id="events_<?= $recommendation->id ?>"
                    colspan="<?= ($columnCount + 1) ?>">
                    <td colspan="<?= $columnCount + 1 ?>">
                        <div class="card">
                            <div class="card-header">Gatherings/Events</div>
                            <div class="card-body">
                                <ul>
                                    <?php if (!empty($recommendation->gatherings)) : ?>
                                    <?php foreach ($recommendation->gatherings as $gathering) : ?>
                                    <li style="white-space:nowrap">
                                        <?= h($gathering->name) ?><br><?= $this->Timezone->format($gathering->start_date, $gathering, 'Y-m-d') ?>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php else : ?>
                                    <li>No gatherings/events assigned</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($columns["Reason"]):
                        $rowCount++ ?>
                <tr class="collapse table-active" id="reason_<?= $recommendation->id ?>"
                    colspan="<?= ($columnCount + 1) ?>">
                    <td colspan="<?= $columnCount + 1 ?>">
                        <div class="card">
                            <div class="card-header">Reason</div>
                            <div class="card-body">
                                <?= $this->Text->autoParagraph($recommendation->reason) ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($rowCount % 2 == 0): ?>
                <tr class="collapse">
                    <td colspan="<?= ($columnCount + 1) ?>"></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>

            </tbody>
        </table>
        <?= $this->Form->end() ?>


    </div>

    <?php
    $bulkUrl = $this->URL->build(["controller" => "Recommendations", "action" => "updateStates", "plugin" => "Awards",]);
    ?>
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
    <?= $this->element('recommendationQuickEditModal', ['modalId' => 'tableEditModal']) ?>
    <?= $this->element('recommendationsBulkEditModal', ['modalId' => 'tableBulkEditModal']) ?>

</turbo-frame>