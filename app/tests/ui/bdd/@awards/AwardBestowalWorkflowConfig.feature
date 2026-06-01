@mode:serial
Feature: Award Bestowal Workflow Config
    As an awards administrator
    I want to manage bestowal statuses and states
    So that court workflow behavior can be configured without code changes

    Scenario: Bestowal statuses index loads for admin
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/bestowal-statuses"
        Then I should be on a page containing "Bestowal Statuses"
        And the bestowal statuses grid should load successfully

    Scenario: Bestowal states index loads for admin
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/bestowal-states"
        Then I should be on a page containing "Bestowal States"
        And the bestowal states grid should load successfully

    Scenario: Admin can open a seeded bestowal state detail
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/bestowal-states"
        And the bestowal states grid should load successfully
        And I open the bestowal state named "Created" from the grid
        Then I should be on a page containing "Created"
        And the bestowal state detail should show the "Field Rules" tab

    Scenario: Admin can add a field rule on a bestowal state
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to the bestowal state view for "Created"
        And I add a visible field rule for "herald_notes" on the current bestowal state

    Scenario: Admin can save the bestowal state transition matrix
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to the bestowal state view for "Created"
        And I submit the bestowal state transitions form on the current state
        Then I should see the flash message "Transitions updated."
