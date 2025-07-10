<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Award Recommendation - ' . $recommendation->member_sca_name . ' for ' . $recommendation->award->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($recommendation->member_sca_name . ' for ' . $recommendation->award->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan('edit', $recommendation)) : ?>
    <button type="button" class="btn btn-primary btn-sm edit-rec" data-bs-toggle="modal" data-bs-target="#editModal"
        data-controller="outlet-btn" data-action="click->outlet-btn#fireNotice"
        data-outlet-btn-btn-data-value='{ "id":<?= $recommendation->id ?>}'>Edit</button>
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
        <?= h(($recommendation->specialty ? " (" . $recommendation->specialty . ")" : "")) ?>
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
    <th scope="row"><?= __('Reason') ?></th>
    <td><?= $this->Text->autoParagraph(h($recommendation->reason)) ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td><?= h($recommendation->status)  ?>
        <?php
        if ($recommendation->close_reason) {
            echo " - " . h($recommendation->close_reason);
        } ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('State') ?></th>
    <td><?= h($recommendation->state) ?>
        <?php
        if ($recommendation->given != null) :
            $given =  h($recommendation->given->toFormattedDateString());
            echo " at " . h($recommendation->scheduled_event->name) . "  on " . $given;
        endif;
        if ($recommendation->scheduled_event && $recommendation->given == null):
            echo "to be given at " . h($recommendation->scheduled_event->name);
        endif; ?>
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
<?php
$this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Notes") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab"
    data-detail-tabs-target="tabContent">
    <?= $this->cell('Notes', [
        'entity_id' => $recommendation->id,
        'entity_type' => 'Awards.Recommendations',
        'viewPrivate' => $user->checkCan("viewPrivateNotes", $recommendation),
        'canCreate' => $user->checkCan('edit', $recommendation),
    ]) ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals"); ?>

<?= $this->element('recommendationEditModal') ?>

<?php $this->KMP->endBlock(); ?>