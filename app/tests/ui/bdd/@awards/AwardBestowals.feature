@mode:serial
Feature: Award Bestowals
    As awards operations staff
    I want bestowals separated from crown recommendations
    So that scroll prep and court scheduling can be managed independently

    Scenario: Bestowals index and grid load for admin
        Given I am logged in as "admin@amp.ansteorra.org"
        When I navigate to "/awards/bestowals"
        Then I should be on a page containing "Award Bestowals"
        And the bestowals grid should load successfully

    Scenario: Need to Schedule creates a linked bestowal
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create a bestowal handoff recommendation fixture
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current bestowal fixture token
        And I select the bestowal handoff recommendation in the grid
        And I open the bulk edit modal
        And I change the bulk edit state to "Need to Schedule"
        And I submit the bulk edit modal
        Then the bestowal handoff recommendation row should contain "Need to Schedule"
        And the handoff recommendation should have a linked bestowal
        When I navigate to "/awards/bestowals"
        Then the bestowals grid should load successfully
        When I open the bestowal detail for the handoff fixture
        Then the bestowal detail page should show "Created" in the state row
        And the bestowal detail page should show "recommendation" in the source row

    Scenario: Cancelling a bestowal unwinds the linked recommendation
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create a bestowal handoff recommendation fixture with an active bestowal
        When I open the bestowal detail for the handoff fixture
        And I cancel the open bestowal from the detail page
        Then I should see the flash message "The bestowal has been cancelled."
        And the bestowal detail page should show "Cancelled" in the state row
        When I open the bestowal handoff recommendation detail view
        Then the recommendation detail page should show "King Approved" in the state row

    Scenario: Bestowal edit modal keeps award fields paired and submit disabled when invalid
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create a bestowal handoff recommendation fixture with an active bestowal
        When I open the bestowal detail for the handoff fixture
        And I open the bestowal edit modal
        Then the bestowal edit modal submit button should be enabled
        When I clear the bestowal edit award type field
        Then the bestowal edit award to bestow field should be disabled
        And the bestowal edit award to bestow field should be empty
        And the bestowal edit modal submit button should be disabled
        When I select the original bestowal award type in the edit modal
        And I select the original bestowal award in the edit modal
        Then the bestowal edit modal submit button should be enabled
        When I clear the bestowal edit award to bestow field
        Then the bestowal edit award type field should be empty
        And the bestowal edit modal submit button should be disabled

    Scenario: Bestowal edit award changes do not sync back to the linked recommendation
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create a bestowal handoff recommendation fixture with an active bestowal
        When I open the bestowal detail for the handoff fixture
        And I open the bestowal edit modal
        And I change the bestowal edit award to the alternate award
        And I submit the bestowal edit modal
        Then the linked recommendation should keep its original award
        And the bestowal should have the alternate award

    Scenario: Bestowal bulk edit keeps submit disabled until a state is selected
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create a bestowal handoff recommendation fixture with an active bestowal
        When I navigate to "/awards/bestowals"
        And the bestowals grid should load successfully
        And I select the handoff bestowal in the grid
        And I open the bestowal bulk edit modal
        Then the bestowal bulk edit submit button should be disabled
        When I change the bestowal bulk edit state to "Created"
        Then the bestowal bulk edit submit button should be enabled
