Feature: Award Recommendations
    As a user of the KMP system
    I want to submit and view award recommendations
    So that deserving members can be recognized

    Scenario: View the public recommendations page
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/recommendations"
        Then I should be on a page containing "Recommendation"

    Scenario: Access submit recommendation page
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/recommendations/submit-recommendation"
        Then I should be on a page containing "Recommendation"

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
