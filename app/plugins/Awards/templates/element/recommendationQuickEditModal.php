<?php
$formUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'edit']);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboQuickEditForm']);

echo $this->Form->create(null, [

    "id" => "recommendation_form",
    "url" => [
        "controller" => "Recommendations",
        "action" => "edit",
    ],
    'data-awards-rec-quick-edit-public-profile-url-value' => $this->URL->build([
        'controller' => 'Members',
        'action' => 'PublicProfile',
        'plugin' => null
    ]),
    'data-action' => 'submit->awards-rec-quick-edit#submit',
    'data-controller' => 'awards-rec-quick-edit',
    'data-awards-rec-quick-edit-grid-btn-outlet' => '.edit-rec',
    'data-awards-rec-quick-edit-award-list-url-value' => $this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => "Awards"]),
    'data-awards-rec-quick-edit-form-url-value' => $formUrl,
    'data-awards-rec-quick-edit-turbo-frame-url-value' => $turboFrameUrl,


]);
echo $this->Form->control(
    "current_page",
    [
        "type" => "hidden",
        "id" => "recommendation__current_page",
        "value" => $this->request->getRequestTarget()
    ]

);
echo $this->Modal->create("Edit Recommendation", [
    "id" => "editModal",
    "close" => true,
]);
?>
<turbo-frame id="editRecommendation" data-awards-rec-quick-edit-target="turboFrame">
    loading
</turbo-frame>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "recommendation_submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);

echo $this->Form->end();
?>