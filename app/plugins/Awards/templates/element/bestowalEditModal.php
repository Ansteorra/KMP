<?php
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'edit']);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'TurboEditForm']);
$initialBestowalId = $initialBestowalId ?? null;
$initialTurboFrameSrc = $initialBestowalId
    ? $turboFrameUrl . '/' . (int)$initialBestowalId
    : null;
$formAction = $initialBestowalId
    ? $formUrl . '/' . (int)$initialBestowalId
    : [
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'edit',
    ];
?>
<div id="bestowal_edit_root"
    data-controller="awards-bestowal-edit"
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
echo $this->Form->create(null, [
    'id' => 'bestowal_form',
    'url' => $formAction,
    'data-action' => 'submit->awards-bestowal-edit#submit input->awards-bestowal-edit#updateSubmitState change->awards-bestowal-edit#updateSubmitState autocomplete.change->awards-bestowal-edit#updateSubmitState',
]);
echo $this->Form->control('current_page', [
    'type' => 'hidden',
    'id' => 'bestowal__current_page',
    'value' => $this->request->getRequestTarget(),
]);
echo $this->Modal->create(__('Edit Bestowal'), [
    'id' => $modalId,
    'close' => true,
]);
?>
<turbo-frame id="editBestowal"
    data-awards-bestowal-edit-target="turboFrame"
    data-action="turbo:frame-load->awards-bestowal-edit#onTurboFrameLoad"<?= $initialTurboFrameSrc ? ' src="' . h($initialTurboFrameSrc) . '"' : '' ?>>
    loading
</turbo-frame>
<?php
echo $this->Modal->end([
    $this->Form->button(__('Submit'), [
        'class' => 'btn btn-primary',
        'id' => 'bestowal_submit',
        'data-awards-bestowal-edit-target' => 'submitButton',
        'disabled' => true,
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'id' => 'bestowal_edit_close',
    ]),
]);
echo $this->Form->end();
?>
</div>
