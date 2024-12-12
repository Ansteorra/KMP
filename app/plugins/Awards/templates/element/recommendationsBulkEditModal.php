<?php
$formUrl = $this->URL->build(["controller" => "Recommendations", "action" => "updateStates", "plugin" => "Awards",]);
$turboFrameUrl = $this->URL->build(['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'TurboBulkEditForm']);
echo $this->Form->create(null, [

    "id" => "recommendation_bulk_form",
    "url" => [
        "controller" => "Recommendations",
        "action" => "updateStates",
    ],
    'data-awards-rec-bulk-edit-public-profile-url-value' => $this->URL->build([
        'controller' => 'Members',
        'action' => 'PublicProfile',
        'plugin' => null
    ]),
    'data-action' => 'submit->awards-rec-bulk-edit#submit',
    'data-controller' => 'awards-rec-bulk-edit awards-rec-table',
    'data-awards-rec-bulk-edit-grid-btn-outlet' => '.edit-rec',
    'data-awards-rec-bulk-edit-award-list-url-value' => $this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => "Awards"]),
    'data-awards-rec-bulk-edit-form-url-value' => $formUrl,
    'data-awards-rec-bulk-edit-turbo-frame-url-value' => $turboFrameUrl,


]);
echo $this->Form->control(
    "current_page",
    [
        "type" => "hidden",
        "id" => "recommendation__current_page",
        "value" => $this->request->getRequestTarget()
    ]

);
echo $this->Form->control(
    "bulkIds",
    [

        "type" => "hidden",
        "id" => "recommendation__ids",
        "value" => [],
        'data-controller' => 'awards-rec-bulk-edit',
        'data-awards-rec-bulk-edit-target' => 'bulkIds',
    ]

);
echo $this->Modal->create("Bulk Edit Recommendations", [
    "id" => $modalId,
    "close" => true,
]);
?>
<turbo-frame id="bulkEditRecommendation" data-awards-rec-bulk-edit-target="turboFrame">
    loading
</turbo-frame>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "recommendation_bulk_submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
        "id" => "recommendation_bulk_edit_close",

    ]),
]);

echo $this->Form->end();
?>