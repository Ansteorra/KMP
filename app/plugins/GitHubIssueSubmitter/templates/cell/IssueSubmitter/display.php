<?php

use App\KMP\StaticHelpers;

if (!$activeFeature) {
    return;
}
$feedbackTypes = [
    "Feature Request" => "Feature Request",
    "Bug" => "Bug",
    "Other" => "Other",
];
echo $this->Form->create(null, [
    "data-controller" => "github-submitter",
    "data-github-submitter-target" => "form",
    "data-github-submitter-url-value" => $this->URL->build(["controller" => "Issues", "action" => "Submit", "plugin" => "GitHubIssueSubmitter"]),
]);
echo $this->Modal->create("Submit Issue", [
    "id" => "githubIssueModal",
    "close" => true,
    "data-github-submitter-target" => "modal",
]); ?>
<div data-github-submitter-target="formBlock">

    <fieldset class="text-start">
        <div class="mb-3 text-wrap"><?= StaticHelpers::getAppSetting("Plugin.GitHubIssueSubmitter.PopupMessage") ?>
        </div>
        <?php
        echo $this->Form->control("title", ["label" => "Title", "placeholder" => "Enter a title for the issue."]);
        echo $this->Form->control("feedbackType", ["label" => "Feedback", "type" => "select", "options" => $feedbackTypes]);
        echo $this->Form->control("body", ["label" => "Details", "type" => "textarea", "placeholder" => "Please provide a detailed description of the issue."]);
        ?>
    </fieldset>
</div>
<div data-github-submitter-target="success" class="text-center">
    <h3>Issue Submitted</h3>
    <p>Thank you for your feedback.</p>
    <a href="#" data-github-submitter-target="issueLink" target="_blank">View on Github</a>
</div>

<?php

echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-github-submitter-target" => "submitBtn",
        "data-action" => "click->github-submitter#submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end(); ?>
<button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#githubIssueModal"
    id='githubIssueModalBtn'>Submit Feedback</button>