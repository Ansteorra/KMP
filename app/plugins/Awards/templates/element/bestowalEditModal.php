<?php
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'TurboEditForm']);
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'edit']);
$initialBestowalId = $initialBestowalId ?? null;
$initialTurboFrameSrc = $initialBestowalId
    ? $turboFrameUrl . '/' . (int)$initialBestowalId
    : null;
?>
<div id="bestowal_edit_root"
    data-controller="awards-bestowal-edit page-context"
    data-awards-bestowal-edit-modal-id-value="<?= h($modalId) ?>"
    data-awards-bestowal-edit-outlet-btn-outlet=".edit-bestowal"
    data-awards-bestowal-edit-form-url-value="<?= h($formUrl) ?>"
    data-awards-bestowal-edit-turbo-frame-url-value="<?= h($turboFrameUrl) ?>"
    data-awards-bestowal-edit-court-slots-url-value="<?= h($this->URL->build([
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'courtSlotsForGathering',
    ])) ?>"
    data-awards-bestowal-edit-gatherings-lookup-url-value="<?= h($this->URL->build([
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'gatheringsForBestowalAutoComplete',
    ])) ?>"
    data-awards-bestowal-edit-award-list-url-value="<?= h($this->URL->build([
        'plugin' => 'Awards',
        'controller' => 'Awards',
        'action' => 'awardsByDomain',
    ])) ?>">
<?php
echo $this->Modal->create(__('Edit Bestowal'), [
    'id' => $modalId,
    'close' => true,
]);
?>
<turbo-frame id="editBestowalQuick"
    loading="eager"
    data-awards-bestowal-edit-target="turboFrame"
    data-action="turbo:frame-load->awards-bestowal-edit#onTurboFrameLoad"<?= $initialTurboFrameSrc ? ' src="' . h($initialTurboFrameSrc) . '"' : '' ?>>
    <div class="text-center p-4 text-muted"><?= __('Loading...') ?></div>
</turbo-frame>
<?php
echo $this->Modal->end([
    $this->Form->button(__('Submit'), [
        'class' => 'btn btn-primary',
        'id' => 'bestowal_submit',
        'type' => 'submit',
        'form' => 'bestowal_form',
        'disabled' => true,
        'data-awards-bestowal-edit-target' => 'submitButton',
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'id' => 'bestowal_edit_close',
    ]),
]);
?>
</div>
