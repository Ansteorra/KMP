<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup[]|\Cake\Collection\CollectionInterface $activityGroup
 */
$ticks = microtime(true);
if (!$isTurboFrame) {
    $this->extend("/layout/TwitterBootstrap/dashboard");

    echo $this->KMP->startBlock("title");
    echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Award Recommendations';
    $this->KMP->endBlock();
}

?>
<?php if (!$isTurboFrame) : ?>
<h3>
    Award Recommendations
</h3>
<?php endif; ?>
<turbo-frame id="recommendationList" data-turbo='true'>
    <?= $this->Form->create(null, ["url" => ["action" => "index"], "type" => "get", "data-turbo-frame" => "recommendationList"]) ?>
    <?= $this->Form->hidden("sort", ["value" => $this->request->getQuery("sort")]) ?>
    <?= $this->Form->hidden("direction", ["value" => $this->request->getQuery("direction")]) ?>
    <table class="table table-striped">
        <thead>
            <tr class="align-top">
                <th scope="col"><?= $this->Paginator->sort("created", "Submitted") ?></th>
                <th scope="col"><?= $this->Paginator->sort("member_sca_name", "For") ?>
                    <?= $this->Form->control("for", [
                        "type" => "text",
                        "label" => false,
                        "placeholder" => "For",
                        "value" => $this->request->getQuery("for"),
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col">OP</th>
                <th scope="col"><?= $this->Paginator->sort("Branches.name", "Branch") ?>
                    <?= $this->Form->control("branch_id", [
                        "type" => "select",
                        "label" => false,
                        "value" => $this->request->getQuery("branch_id"),
                        "options" => $branches,
                        "empty" => true,
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("call_into_court") ?>
                    <?= $this->Form->control("call_into_court", [
                        "type" => "select",
                        "label" => false,
                        "value" => $this->request->getQuery("call_into_court"),
                        "options" => $callIntoCourt,
                        "empty" => true,
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("Court Avail.") ?>
                    <?= $this->Form->control("court_avail", [
                        "type" => "select",
                        "label" => false,
                        "value" => $this->request->getQuery("court_avail"),
                        "options" => $courtAvailability,
                        "empty" => true,
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("requester_sca_name", "Submitted By") ?>
                    <?= $this->Form->control("requester_sca_name", [
                        "type" => "text",
                        "label" => false,
                        "placeholder" => "Submitted By",
                        "value" => $this->request->getQuery("requester_sca_name"),
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("Contact Email") ?></th>
                <th scope="col"><?= $this->Paginator->sort("Contact Phone") ?></th>
                <th scope="col"><?= $this->Paginator->sort("Domains.name", "Domain") ?>
                    <?= $this->Form->control("domain_id", [
                        "type" => "select",
                        "label" => false,
                        "value" => $this->request->getQuery("domain_id"),
                        "options" => $domains,
                        "empty" => true,
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("Awards.name", "Award") ?>
                    <?= $this->Form->control("award_id", [
                        "type" => "select",
                        "label" => false,
                        "placeholder" => "Award",
                        "value" => $this->request->getQuery("award_id"),
                        "options" => $awards,
                        "empty" => true,
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col">Reason</th>
                <th scope="col">Events</th>
                <th scope="col">Notes</th>
                <th scope="col"><?= $this->Paginator->sort("Status") ?>
                    <?= $this->Form->control("status", [
                        "type" => "select",
                        "label" => false,
                        "placeholder" => "Status",
                        "value" => $this->request->getQuery("status"),
                        "options" => $statuses,
                        "empty" => true,
                        "onchange" => "document.getElementById('filter_btn').click();",
                    ]) ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("Status Date") ?></th>
                <th scope="col" class="actions"><?= __("Actions") ?>
                    <?= $this->Form->button('Filter', ["id" => "filter_btn", "class" => "d-show"]); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recommendations as $recommendation) : ?>
            <tr>
                <td><?= h($recommendation->created) ?></td>
                <td><?php
                        if ($recommendation->member_id) {
                            echo $this->Html->link(
                                h($recommendation->member_sca_name),
                                ["controller" => "Members", "plugin" => null, "action" => "view", $recommendation->member_id],
                                ["title" => __("View"), "data-turbo-frame" => "_top"],
                            );
                        } else {
                            echo h($recommendation->member_sca_name);
                        }
                        ?></td>
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
                <td><?= h($recommendation->branch->name) ?></td>
                <td><?= h($recommendation->call_into_court) ?></td>
                <td><?= h($recommendation->court_availability) ?></td>
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
                <td><?= h($recommendation->contact_email) ?></td>
                <td><?= h($recommendation->contact_phone) ?></td>
                <td><?= h($recommendation->award->domain->name) ?></td>
                <td><?= h($recommendation->award->name) ?></td>
                <td><?= $this->Text->autoParagraph($recommendation->reason) ?></td>
                <td>
                    <ul>
                        <?php foreach ($recommendation->events as $event) : ?>
                        <li><?= h($event->name) ?> : <br> <?= h($event->start_date->toDateString()) ?> -
                            <?= h($event->end_date->toDateString()) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </td>
                <td>
                    <ul>
                        <?php foreach ($recommendation->notes as $note) : ?>
                        <li><?= h($note->created->toDateTimeString()) ?> :
                            <?= $this->Text->autoParagraph($note->body) ?></li>
                        <?php endforeach; ?>
                </td>
                <td><?= h($recommendation->status) ?></td>
                <td><?= $recommendation->status_date ? h($recommendation->status_date->toDateString()) : h($recommendation->created->toDateString()) ?>
                </td>
                <td class="actions">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#editModal"
                        onclick="loadRec(<?= $recommendation->id ?>,'<?= $currentUrl ?>');">Edit</button>
                    <?= $this->Html->link(
                            __("View"),
                            ["action" => "view", $recommendation->id],
                            ["title" => __("View"), "class" => "btn btn-secondary btn-sm", "data-turbo-frame" => "_top"],
                        ) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?= $this->Form->end() ?>
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
</turbo-frame>

<?php
echo $this->KMP->startBlock("modals"); ?>

<?php
echo $this->Form->create($recommendation, [
    "id" => "recommendation_form",
    "url" => [
        "controller" => "Recommendations",
        "action" => "edit",
    ],
]);
echo $this->Form->control(
    "current_page",
    [
        "type" => "hidden",
        "id" => "recommendation__current_page",
        "value" => $currentUrl,
    ]

);
echo $this->Modal->create("Edit Recommendation", [
    "id" => "editModal",
    "close" => true,
]);
?>
<turbo-frame id="editRecommendation">
    loading
</turbo-frame>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "recommendation_submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);

echo $this->Form->end();
?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>
<?= $this->element('recommendationEditScript') ?>
<?php echo $this->KMP->startBlock("script"); ?>
<script>
loadRec = function(id, returnUrl) {
    formSrc =
        "<?= $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'edit']) ?>" +
        "/" + id;
    src =
        "<?= $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboEditForm']) ?>" +
        "/" + id;
    $("#recommendation_form").attr("action", formSrc);
    $("#recommendation__current_page").val(returnUrl);
    $("#editRecommendation").attr("src", src);
}
window.addEventListener('DOMContentLoaded', function() {
    $("#editRecommendation").on("turbo:frame-load", function() {
        var recAdd = new recommendationsAdd();
        recAdd.run();
    });
});
</script>
<?php echo $this->KMP->endBlock(); ?>