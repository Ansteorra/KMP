@mode:serial
Feature: Officer release workflow
    As a permissioned officer user
    I want the officer lifecycle to use the workflow-backed release path
    So that officer, warrant, role, and email side effects stay correct

    Scenario: Hire approve and release an officer
        Given I delete all test emails
        And I prepare the officer lifecycle fixture
        And I am logged in as "admin@amp.ansteorra.org"
        When I assign the officer lifecycle member
        Then I should see the flash message "The officer has been saved."
        And the officer lifecycle should have a pending warrant approval
        When I approve the officer lifecycle warrant
        Then I should see the flash message "Approval response recorded."
        When I process queued emails for the officer lifecycle
        Then the officer lifecycle should have an active warrant
        When I release the officer lifecycle member
        Then I should see the flash message "The officer release workflow has been initiated."
        When I process queued emails for the officer lifecycle
        Then the officer lifecycle database records should show the full lifecycle
        And I should see the officer lifecycle emails
