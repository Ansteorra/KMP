<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 * @var array $memberAttendanceGatherings
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
            if ($user->checkCan('view', $recommendation->member)) {
                echo $this->Html->link($recommendation->member->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->member_id]);
            } else {
                echo h($recommendation->member->sca_name);
            }
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
            // Format as date only (no timezone conversion) since it's stored as midnight UTC
            $given = $recommendation->given->format('F j, Y');
            $gatheringName = h($recommendation->assigned_gathering->name);
            $isCancelled = $recommendation->assigned_gathering && $recommendation->assigned_gathering->cancelled_at !== null;
            if ($isCancelled) {
                echo ' at <span class="text-danger fw-bold">[CANCELLED]</span> ' . $gatheringName . ' on ' . $given;
            } else {
                echo " at " . $gatheringName . " on " . $given;
            }
        endif;
        if ($recommendation->assigned_gathering && $recommendation->given == null):
            $gatheringName = h($recommendation->assigned_gathering->name);
            $isCancelled = $recommendation->assigned_gathering->cancelled_at !== null;
            if ($isCancelled) {
                echo '<div class="alert alert-danger mt-2 mb-0 py-1 px-2"><i class="bi bi-exclamation-triangle-fill"></i> <strong>' . __('Warning:') . '</strong> ' . __('Scheduled for cancelled gathering:') . ' <span class="fw-bold">[CANCELLED]</span> ' . $gatheringName . '. ' . __('Please reschedule.') . '</div>';
            } else {
                echo "to be given at " . $gatheringName;
            }
        endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Requester Sca Name') ?></th>
    <td><?php
        if ($recommendation->requester_id == null) {
            echo h($recommendation->requester_sca_name);
        } else {
            if ($user->checkCan('view', $recommendation->requester)) {
                echo $this->Html->link($recommendation->requester->sca_name, ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $recommendation->requester_id]);
            } else {
                echo h($recommendation->requester->sca_name);
            }
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
    <th scope="row"><?= __('Suggested Gatherings') ?></th>
    <td>
        <ul>
            <?php foreach ($recommendation->gatherings as $gathering) : 
                $isCancelled = $gathering->cancelled_at !== null;
            ?>
                <li>
                    <?php if ($isCancelled): ?><span class="text-danger fw-bold">[CANCELLED]</span> <?php endif; ?>
                    <?= $this->Html->link($gathering->name, ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, 'plugin' => null], $isCancelled ? ['class' => 'text-danger'] : []) ?>
                    <?php if ($recommendation->gathering_id == $gathering->id) {
                        echo " (Plan to Give)";
                    } ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </td>
</tr>
<?php if (!empty($memberAttendanceGatherings)) : ?>
    <tr>
        <th scope="row"><?= __('Member\'s Planned Attendance') ?></th>
        <td>
            <ul>
                <?php foreach ($memberAttendanceGatherings as $gathering) : ?>
                    <li>
                        <?= $this->Html->link($gathering->name, ['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, 'plugin' => null]) ?>
                        <?php if ($gathering->start_date) : ?>
                            <small class="text-muted">(<?= $gathering->start_date->format('M j, Y') ?>)</small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <small class="text-muted">
                <i class="bi bi-info-circle"></i> Events the member has indicated they plan to attend
            </small>
        </td>
    </tr>
<?php endif; ?>
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