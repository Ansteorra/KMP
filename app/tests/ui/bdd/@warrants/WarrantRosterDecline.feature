@officers @mode:serial
Feature: Warrant roster decline workflow
    As a permissioned officer user
    I want declining a warrant roster to run through the workflow approval path
    So that the roster is declined, warrants are not issued, and no warrant-issued emails are sent

    Scenario: Declining a pending warrant roster issues no warrants and no emails
        Given I delete all test emails
        And I prepare the officer lifecycle fixture
        And I am logged in as "forest@ampdemo.com"
        When I assign the officer lifecycle member
        Then I should see the flash message "The officer has been saved."
        And the officer lifecycle should have a pending warrant approval
        When I decline the officer lifecycle warrant roster
        When I process queued emails for the officer lifecycle
        Then the officer lifecycle warrant roster should be declined
        And no warrant-issued email should be queued for the officer lifecycle member
