@mode:serial
Feature: Award Recommendations
    As a user of the KMP system
    I want to submit and view award recommendations
    So that deserving members can be recognized

    Scenario: View the public recommendations page
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "workflow single local"
        When I navigate to "/awards/recommendations"
        Then I should be on a page containing "Recommendation"
        And the grid should show 1 or more results

    Scenario: Access authenticated add recommendation page
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/recommendations/add"
        Then I should be on a page containing "Submit Award Recommendation"
        And the page should contain "Recommendation For"
        And the page should contain "Reason for Recommendation"

    Scenario: Access submit recommendation page
        When I navigate to "/awards/recommendations/submit-recommendation"
        Then I should be on a page containing "Submit Award Recommendation"
        And the page should contain "Your SCA Name"
        And the page should contain "Reason for Recommendation"

    Scenario: Recommendations index exposes grouping controls
        Given I am logged in as "forest@ampdemo.com"
        When I navigate to "/awards/recommendations"
        Then the page should contain "Add Recommendation"
        And the page should contain "Group Recommendations"

    Scenario: Single-step approval flow creates a bestowal and consumes the run
        Given I am logged in as "forest@ampdemo.com"
        And I create recommendation fixtures for "workflow single crown"
        When the workflow engine processes pending work
        Then the "wf-crown" recommendation should have a workflow run with status "in_progress"
        And the "wf-crown" recommendation should have 1 pending approvals
        When I approve the pending workflow step 1 for "wf-crown"
        And the workflow engine processes pending work
        Then the "wf-crown" recommendation should have a workflow run with status "consumed"
        And the "wf-crown" recommendation workflow run should have terminal reason "consumed_by_bestowal"
        And the "wf-crown" recommendation should be linked to a bestowal

    Scenario: Dual-step approval flow advances local approval then crown approval
        Given I am logged in as "forest@ampdemo.com"
        And I create recommendation fixtures for "workflow local then crown"
        When the workflow engine processes pending work
        Then the "wf-dual" recommendation should have a workflow run with status "in_progress"
        And the "wf-dual" recommendation should have 1 pending approvals
        When I approve the pending workflow step 1 for "wf-dual"
        And the workflow engine processes pending work
        Then the "wf-dual" recommendation should have a workflow run with status "in_progress"
        And the "wf-dual" recommendation should have 1 pending approvals
        When I approve the pending workflow step 2 for "wf-dual"
        And the workflow engine processes pending work
        Then the "wf-dual" recommendation should have a workflow run with status "consumed"
        And the "wf-dual" recommendation workflow run should have terminal reason "consumed_by_bestowal"
        And the "wf-dual" recommendation should be linked to a bestowal

    Scenario: Rejection closes the workflow run and does not create a bestowal
        Given I am logged in as "forest@ampdemo.com"
        And I create recommendation fixtures for "workflow single local"
        When the workflow engine processes pending work
        Then the "wf-local" recommendation should have a workflow run with status "in_progress"
        And the "wf-local" recommendation should have 1 pending approvals
        When I reject the pending workflow step 1 for "wf-local"
        And the workflow engine processes pending work
        Then the "wf-local" recommendation should have a workflow run with status "closed"
        And the "wf-local" recommendation workflow run should have terminal reason "rejected"
        And the "wf-local" recommendation should not be linked to a bestowal
        And the "wf-local" recommendation record should have state "No Action"

    Scenario: Recommendations without an approval process follow fallback path
        Given I delete all test emails
        And I am logged in as "forest@ampdemo.com"
        And I create a recommendation fixture without an approval process
        When the workflow engine processes pending work
        Then the "no-process" recommendation should have no workflow run
        And the "no-process" recommendation should not be linked to a bestowal
        And there should be a fallback submission email to crown for "no-process"

    Scenario: Recommendation grouping supports remove-from-group and ungroup-all flows
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "workflow grouping"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I select all current fixture recommendations in the grid
        And I open the group recommendations modal
        Then the group recommendations modal should describe grouping the selected recommendations
        When I submit the group recommendations modal
        And I open the group head recommendation detail view
        Then the recommendation detail page should show the "Grouped" tab
        And the recommendation group head should list 2 grouped recommendations
        When the workflow engine processes pending work
        And I ungroup all recommendations from the detail view
        Then I should see the flash message "under active approval review."
        When I reject the pending workflow step 1 for "wf-group-head"
        And the workflow engine processes pending work
        And I open the group head recommendation detail view
        And I ungroup all recommendations from the detail view
        Then the recommendation detail page should not show the "Grouped" tab

    Scenario: Submit recommendation marks unmatched recipients as not registered
        When I navigate to "/awards/recommendations/submit-recommendation"
        And I enter "Definitely Not In KMP" as an unmatched recommendation recipient
        Then the submit recommendation form should mark the recipient as not registered
        And the submit recommendation form should enable the local group field

    Scenario: Public submit recommendation succeeds for an unmatched recipient
        When I navigate to "/awards/recommendations/submit-recommendation"
        And I submit a public recommendation for the unmatched recipient "Definitely Not In KMP"
        Then I should see the flash message "The recommendation has been submitted."

    Scenario: Recommendations grid does not link unmatched recipients to member profiles
        When I navigate to "/awards/recommendations/submit-recommendation"
        And I submit a public recommendation for the unmatched recipient "BDD External Recipient Link Guard"
        Then I should see the flash message "The recommendation has been submitted."
        Given I am logged in as "forest@ampdemo.com"
        When I navigate to "/awards/recommendations"
        And I search the grid for "BDD External Recipient Link Guard"
        Then the recommendation row for "BDD External Recipient Link Guard" should not link to a member profile

    Scenario: Recommendation feedback response creates notes and keeps Mailpit assertions scoped
        Given I delete all test emails
        When I navigate to "/awards/recommendations/submit-recommendation"
        And I submit a public feedback-lane recommendation for a unique unmatched recipient
        Then I should see the flash message "The recommendation has been submitted."
        When the workflow engine processes pending work
        Then the public feedback-lane recommendation should have a workflow run
        And there should be no award recommendation submitted email to "bryce@ampdemo.com" for the public feedback-lane recommendation
        Given I am logged in as "forest@ampdemo.com"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current public feedback-lane recipient
        And I open the current public feedback-lane recommendation detail view from the grid
        When I request recommendation feedback from "Bryce Local Seneschal Demoer" with message "Please provide court and award context for this recommendation."
        Then I should see the flash message "Feedback request sent."
        When the workflow engine processes pending work
        Then the recommendation detail page should show the "Feedback" tab
        And the recommendation feedback tab should show "Bryce Local Seneschal Demoer" as "Pending"
        And there should be no recommendation feedback request email to "bryce@ampdemo.com"
        Given I am logged in as "bryce@ampdemo.com"
        When I navigate to "/approvals"
        And I search the approvals grid for the current public feedback-lane recipient
        Then I should see one recommendation feedback request for the current public feedback-lane recommendation from "Forest Crown Demoer"
        When I send the current recommendation feedback response
        Then I should see the flash message "Approval response recorded."
        Given I am logged in as "forest@ampdemo.com"
        When I open the current public feedback-lane recommendation detail view
        Then the recommendation feedback tab should show the current feedback response
        And the recommendation notes tab should show the current feedback response
        And there should be no recommendation feedback request email to "bryce@ampdemo.com"
