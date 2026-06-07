<?php
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'edit']);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboEditForm']);
?>
<div id="recommendation_edit_root"
    data-controller="awards-rec-edit"
    data-awards-rec-edit-public-profile-url-value="<?= h($this->URL->build([
        'controller' => 'Members',
        'action' => 'PublicProfile',
        'plugin' => null,
    ])) ?>"
    data-awards-rec-edit-outlet-btn-outlet=".edit-rec"
    data-awards-rec-edit-award-list-url-value="<?= h($this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => 'Awards'])) ?>"
    data-awards-rec-edit-form-url-value="<?= h($formUrl) ?>"
    data-awards-rec-edit-turbo-frame-url-value="<?= h($turboFrameUrl) ?>">
<?php
echo $this->Form->create(null, [
    'id' => 'recommendation_form',
    'url' => [
        'controller' => 'Recommendations',
        'action' => 'edit',
    ],
    'data-action' => 'submit->awards-rec-edit#submit',
]);
echo $this->Form->control('current_page', [
    'type' => 'hidden',
    'id' => 'recommendation__current_page',
    'value' => $this->request->getRequestTarget(),
]);
echo $this->Modal->create('Edit Recommendation', [
    'id' => 'editModal',
    'close' => true,
]);
?>
<turbo-frame id="editRecommendation"
    data-awards-rec-edit-target="turboFrame"
    data-action="turbo:frame-load->awards-rec-edit#onTurboFrameLoad">
    loading
</turbo-frame>
<?php
echo $this->Modal->end([
    $this->Form->button('Submit', [
        'class' => 'btn btn-primary',
        'id' => 'recommendation_submit',
    ]),
    $this->Form->button('Close', [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
    ]),
]);
echo $this->Form->end();
?>
</div>
