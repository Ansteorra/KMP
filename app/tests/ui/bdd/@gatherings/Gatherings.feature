@gatherings @mode:serial
Feature: Gatherings lifecycle
    As a permissioned branch user
    I want to manage the gatherings lifecycle from setup through attendance
    So that gatherings, staff, and attendance records appear in the right listings

    Scenario: Create a gathering, staff member, and attendance
        Given I delete all test emails
        And I prepare the gatherings lifecycle fixture
        And I am logged in as "bryce@ampdemo.com"
        When I create the gatherings lifecycle gathering
        Then the gatherings lifecycle gathering should appear in the gatherings grid
        When I add the gatherings lifecycle staff member
        Then the gatherings lifecycle staff member should appear on the gathering staff tab
        When I record attendance for the gatherings lifecycle gathering
        Then the gatherings lifecycle attendance should appear on the gathering attendance tab
        And the gatherings lifecycle attendance should appear in the member gatherings listing
