@mode:serial
Feature: Officer hire overlap workflow
    As an admin user
    I want unique-office overlap handling to run through the workflow-backed hire path
    So that conflicting officer assignments, linked records, and notifications stay correct

    Scenario Outline: Unique-office hire resolves overlapping assignments
        Given I delete all test emails
        And I prepare the officer overlap fixture for "<case>"
        And I am logged in as "admin@amp.ansteorra.org"
        When I assign the officer overlap replacement member
        Then I should see the flash message "The officer has been saved."
        And the officer overlap replacement should have a pending warrant approval
        When I process queued emails for the officer overlap fixture
        Then the officer overlap state for "<case>" should be correct
        And I should see the officer overlap emails for "<case>"

        Examples:
            | case                  |
            | current-trim          |
            | current-full-release  |
            | upcoming-push         |
            | upcoming-full-release |
            | upcoming-middle-trim  |
