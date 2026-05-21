@mode:serial
Feature: Award Recommendations
    As a user of the KMP system
    I want to submit and view award recommendations
    So that deserving members can be recognized

    Scenario: View the public recommendations page
        Given I am logged in as "admin@amp.ansteorra.org"
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
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/recommendations"
        Then the page should contain "Add Recommendation"
        And the page should contain "Group Recommendations"

    Scenario: Recommendation detail edit exposes scheduling and given-state fields
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "detail edit"
        When I open the "detail" recommendation detail view
        And I open the detail edit modal
        Then the open recommendation edit modal should not show the "Plan to Give At" field
        When I change the open recommendation state to "Scheduled"
        Then the open recommendation edit modal should show the "Plan to Give At" field
        And the open recommendation edit modal should not show the "Given On" field
        When I select the first available gathering in the open recommendation edit modal
        And I submit the open recommendation edit modal
        Then the recommendation detail page should show "Scheduled" in the state row
        And the recommendation detail page should show "to be given at" in the state row
        When I open the detail edit modal
        And I change the open recommendation state to "Given"
        Then the open recommendation edit modal should show the "Plan to Give At" field
        And the open recommendation edit modal should show the "Given On" field
        When I set the open recommendation given date to today
        And I fill in the open recommendation note with "Detail edit workflow coverage note"
        And I submit the open recommendation edit modal
        Then the recommendation detail page should show "Given" in the state row
        And the recommendation detail page should show "Closed" in the status row

    Scenario: Recommendation quick edit closes a recommendation with a reason
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "quick edit"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I open the "quick" recommendation quick edit modal from the grid
        And I change the open recommendation state to "No Action"
        Then the open recommendation edit modal should show the "Reason for No Action" field
        When I fill in the open recommendation close reason with "Insufficient supporting detail"
        And I fill in the open recommendation note with "Quick edit workflow coverage note"
        And I submit the open recommendation edit modal
        Then the "quick" recommendation row should contain "No Action"
        And the "quick" recommendation row should contain "Closed"

    Scenario: Recommendation bulk edit transitions multiple recommendations together
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "bulk edit"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I select all current fixture recommendations in the grid
        And I open the bulk edit modal
        And I change the bulk edit state to "No Action"
        Then the bulk edit modal should show the "Reason for No Action" field
        When I fill in the bulk edit close reason with "Bulk workflow regression coverage"
        And I fill in the bulk edit note with "Bulk edit workflow coverage note"
        And I submit the bulk edit modal
        Then each current fixture recommendation row should contain "No Action"
        And each current fixture recommendation row should contain "Closed"

    Scenario: Recommendation grouping supports remove-from-group and ungroup-all flows
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "grouping"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I select all current fixture recommendations in the grid
        And I open the group recommendations modal
        Then the group recommendations modal should describe grouping the selected recommendations
        When I submit the group recommendations modal
        And I open the group head recommendation detail view
        Then the recommendation detail page should show the "Grouped" tab
        And the recommendation group head should list 3 grouped recommendations
        When I remove the grouped recommendation "group-three" from the detail view
        Then the recommendation group head should list 2 grouped recommendations
        When I ungroup all recommendations from the detail view
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
        And I am logged in as "admin@amp.ansteorra.org"
        And I navigate to "/awards/recommendations"
        And I search the grid for "BDD External Recipient Link Guard"
        Then the recommendation row for "BDD External Recipient Link Guard" should not link to a member profile
