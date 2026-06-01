<?php
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboQuickEditForm']);
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'edit']);
?>
<div id="recommendation_quick_edit_root"
    data-controller="awards-rec-quick-edit page-context"
    data-awards-rec-quick-edit-public-profile-url-value="<?= h($this->URL->build([
        'controller' => 'Members',
        'action' => 'PublicProfile',
        'plugin' => null,
    ])) ?>"
    data-awards-rec-quick-edit-outlet-btn-outlet=".edit-rec"
    data-awards-rec-quick-edit-award-list-url-value="<?= h($this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => 'Awards'])) ?>"
    data-awards-rec-quick-edit-form-url-value="<?= h($formUrl) ?>"
    data-awards-rec-quick-edit-turbo-frame-url-value="<?= h($turboFrameUrl) ?>"
    data-awards-rec-quick-edit-gatherings-url-value="<?= h($this->URL->build(['controller' => 'Recommendations', 'action' => 'gatheringsForAward', 'plugin' => 'Awards'])) ?>"
    data-awards-rec-quick-edit-gatherings-lookup-url-value="<?= h($this->URL->build(['controller' => 'Recommendations', 'action' => 'gatheringsAutoComplete', 'plugin' => 'Awards'])) ?>">
<?php
echo $this->Modal->create('Edit Recommendation', [
    'id' => $modalId,
    'close' => true,
]);
?>
<turbo-frame id="editRecommendationQuick"
    data-awards-rec-quick-edit-target="turboFrame"
    data-action="turbo:frame-load->awards-rec-quick-edit#onTurboFrameLoad">
    <div class="text-center p-4 text-muted"><?= __('Loading...') ?></div>
</turbo-frame>
<?php
echo $this->Modal->end([
    $this->Form->button(__('Submit'), [
        'class' => 'btn btn-primary',
        'id' => 'recommendation_submit',
        'type' => 'submit',
        'form' => 'recommendation_form',
    ]),
    $this->Form->button(__('Close'), [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
        'id' => 'recommendation_edit_close',
    ]),
]);
?>
</div>
