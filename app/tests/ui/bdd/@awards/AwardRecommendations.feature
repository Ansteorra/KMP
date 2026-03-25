Feature: Award Recommendations
    As a user of the KMP system
    I want to submit and view award recommendations
    So that deserving members can be recognized

    Scenario: View the public recommendations page
        Given I am logged in as "admin@test.com"
        When I navigate to "/awards/recommendations"
        Then I should be on a page containing "Recommendation"

    Scenario: Access submit recommendation page
        Given I am logged in as "admin@test.com"
        When I navigate to "/awards/recommendations/submit-recommendation"
        Then I should be on a page containing "Recommendation"
