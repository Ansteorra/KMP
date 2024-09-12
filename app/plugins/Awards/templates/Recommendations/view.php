<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Recommendation - ' . $recommendation->member_sca_name . ' for ' . $recommendation->award->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($recommendation->member_sca_name . ' for ' . $recommendation->award->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->can('edit', $recommendation)) : ?>
<button type="button" class="btn btn-primary btn-sm edit-rec" data-bs-toggle="modal" data-bs-target="#editModal"
    data-controller="grid-btn" data-action="click->grid-btn#fireNotice"
    data-grid-btn-row-data-value='{ "id":<?= $recommendation->id ?>}'>Edit</button>
<?php
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $recommendation->id],
        [
            "confirm" => __(
                "Are you sure you want to delete recommendation for {0}?",
                h($recommendation->member_sca_name . ' about ' . $recommendation->award->name),
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    ); ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __('Award') ?></th>
    <td><?= $recommendation->hasValue('award') ? $this->Html->link($recommendation->award->name, ['controller' => 'Awards', 'action' => 'view', $recommendation->award->id]) : '' ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Member Sca Name') ?></th>
    <td><?php
        if ($recommendation->member_id == null) {
            echo h($recommendation->member_sca_name);
        } else {
            echo $this->Html->link($recommendation->member->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->member_id]);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td><?= h($recommendation->status) ?>
        <?php if ($recommendation->status == "given") {
            echo " at " . h($recommendation->scheduled_event->name) . "  on " . h($recommendation->given->toFormattedDateString());
        } ?>
        <?php if ($recommendation->status == "scheduled") {
            echo "to be given at " . h($recommendation->scheduled_event->name);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Requester Sca Name') ?></th>
    <td><?php
        if ($recommendation->requester_id == null) {
            echo h($recommendation->requester_sca_name);
        } else {
            echo $this->Html->link($recommendation->requester->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->requester_id]);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Contact Email') ?></th>
    <td><?= h($recommendation->contact_email) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Contact Number') ?></th>
    <td><?= h($recommendation->contact_number) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Suggested Events') ?></th>
    <td>
        <ul>
            <?php foreach ($recommendation->events as $events) : ?>
            <li><?= $this->Html->link($events->name, ['controller' => 'Events', 'action' => 'view', $events->id]) ?>
                <?php if ($recommendation->event_id == $events->id) {
                        echo " (Plan to Give)";
                    } ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Call Into Court') ?></th>
    <td><?= h($recommendation->call_into_court) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Court Availability') ?></th>
    <td><?= h($recommendation->court_availability) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Person to Notify') ?></th>
    <td><?= h($recommendation->person_to_notify) ?></td>
</tr>
<?php if ($recommendation->member) : ?>
<tr>
    <th colspan='2' scope="row">
        <h4><?= __('Member Details') ?></h4>
    </th>
</tr>
<?php
    $member = $recommendation->member;
    echo $this->element('members/memberDetails', [
        'member' => $member,
    ]);
else :
    echo '<tr><th colspan="2" scope="row">' . __('Member Details') . '</th></tr>';
    echo '<tr><td colspan="2">' . __('Member not found in database') . '</td></tr>';
endif;
$this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link active" id="nav-reason-tab" data-bs-toggle="tab" data-bs-target="#nav-reason" type="button"
    role="tab" aria-controls="nav-reason" aria-selected="true" data-detail-tabs-target='tabBtn'><?= __("Reason") ?>
</button>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Notes") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active show m-3" id="nav-reason" role="tabpanel" aria-labelledby="nav-reason-tab"
    data-detail-tabs-target="tabContent">
    <?= $this->Text->autoParagraph(h($recommendation->reason)) ?>
</div>
<div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab"
    data-detail-tabs-target="tabContent">
    <?= $this->cell('Notes', [
        'topic_id' => $recommendation->id,
        'topic_model' => 'Awards.Recommendations',
        'viewPrivate' => $user->can("viewPrivateNotes", "Awards.Recommendations"),
        'canCreate' => $user->can('edit', $recommendation),
    ]) ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals"); ?>

<?= $this->element('recommendationEditModal') ?>

<?php $this->KMP->endBlock(); ?>