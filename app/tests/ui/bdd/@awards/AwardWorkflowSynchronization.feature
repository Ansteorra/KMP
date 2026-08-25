@mode:serial
Feature: Award workflow synchronization
    As the Crown taking responsibility for open award work
    I want to synchronize recommendations and bestowal to-dos from their management screens
    So that recommendation approvals restart under current policy while bestowal work keeps its audit history

    Scenario: Synchronize an in-flight approval and its bestowal to-dos to current definitions
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create an in-flight award workflow synchronization fixture
        When I open the award approval process synchronization page
        And I open the recommendation synchronization confirmation with the keyboard
        Then the recommendation synchronization confirmation should be accessible and initially focused
        When I dismiss the synchronization confirmation with Escape
        Then focus should return to the recommendation synchronization control
        When I confirm recommendation synchronization with the keyboard
        Then the in-flight recommendation should restart without carrying its approval forward
        And recommendation synchronization should not create a bestowal
        Then recommendation synchronization should be disabled because the replacement is current
        Given I create an open bestowal for the To-Do synchronization fixture
        Given I change the fixture's current bestowal to-do template
        When I open the bestowal to-do synchronization page
        And I open the bestowal synchronization confirmation with the keyboard
        Then the bestowal synchronization confirmation should be accessible and initially focused
        When I dismiss the synchronization confirmation with Escape
        Then focus should return to the bestowal synchronization control
        When I confirm bestowal synchronization with the keyboard
        Then the current to-do definition should preserve completion audit and retire removed work
        And the bestowal should remain open
        And bestowal synchronization should be disabled because the to-do list is current
        Given I restore the removed fixture to-do definition
        When I synchronize the open bestowals again
        Then the restored to-do should reopen without a duplicate
        And the bestowal should remain open
