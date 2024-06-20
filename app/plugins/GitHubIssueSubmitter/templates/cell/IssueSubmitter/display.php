<?php
if (!$activeFeature) {
    return;
}
$feedbackTypes = [
    "Feature Request" => "Feature Request",
    "Bug" => "Bug",
    "Other" => "Other",
];
echo $this->KMP->startBlock("modals");
echo $this->Modal->create("Submit Issue", [
    "id" => "githubIssueModal",
    "close" => true,
]); ?>
<fieldset class="text-start">
    <?php
    echo $this->Form->create(null, [
        "id" => "githubIssueForm",
        "url" => ["controller" => "Issues", "action" => "Submit", "plugin" => "GitHubIssueSubmitter"],
    ]);
    echo $this->Form->control("title", ["label" => "Title", "placeholder" => "Enter a title for the issue."]);
    echo $this->Form->control("feedbackType", ["label" => "Feedback", "type" => "select", "options" => $feedbackTypes]);
    echo $this->Form->control("body", ["label" => "Details", "type" => "textarea", "placeholder" => "Please provide a detailed description of the issue."]);
    echo $this->Form->end();
    ?>
</fieldset>
<div id="githubIssue_success" class="text-center">
    <h3>Issue Submitted</h3>
    <p>Thank you for your feedback.</p>
    <a href="#" id="githubIssueLink" target="_blank">View on Github</a>
</div>
<?php

echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "githubIssueForm__submit",
        "onclick" => '$("#githubIssueForm").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);
$this->KMP->endBlock();
echo $this->KMP->startBlock("script"); ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    $('#githubIssue_success').hide();
    $('#githubIssueModal').on('hidden.bs.modal', function() {
        $('#githubIssueForm').show();
        $('#githubIssue_success').hide();
        $('#githubIssueForm__submit').show();
    });
    $('#githubIssueForm').submit(function(e) {
        e.preventDefault(); // Prevent the default form submission
        $url = $('#githubIssueForm').attr('action');
        $.ajax({
            url: $url, // Your server-side script
            type: 'POST',
            data: $(this).serialize(), // Serializes the form's elements
            success: function(response) {
                // Handle success
                if (response.message) {
                    alert("Error:" + response.message);
                    return;
                }
                $('#githubIssueForm').trigger("reset");
                $('#githubIssueForm').hide();
                $('#githubIssueForm__submit').hide();
                $('#githubIssueLink').attr('href', response.url);
                $('#githubIssue_success').show();
            },
            error: function(xhr, status, error) {
                // Handle errors
                console.error(error);
                alert('An error occurred while creating the issue.');
            }
        });
    });
});
</script>
<?php $this->KMP->endBlock(); ?>
<button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#githubIssueModal"
    id='githubIssueModalBtn'>Submit Feedback</button>