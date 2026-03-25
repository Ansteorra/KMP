Feature: Member Profile Management
    As a logged-in user of the KMP system
    I want to view and manage my profile
    So that my information is up to date

    Scenario: View my own profile page
        Given I am logged in as "admin@test.com"
        And I navigate to my profile page
        Then I should be on a page containing "Admin von Admin"

    Scenario: Navigate to members list as admin
        Given I am logged in as "admin@test.com"
        When I navigate to "/members"
        Then the grid should show 1 or more results

    Scenario: Search for a member in the grid
        Given I am logged in as "admin@test.com"
        When I navigate to "/members"
        And I search for "Admin" in the grid search box
        Then the grid should show results containing "Admin"
