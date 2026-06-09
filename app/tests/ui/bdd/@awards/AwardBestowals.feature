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

    Scenario: Workflow approval creates a linked bestowal
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "workflow single crown"
        When the workflow engine processes pending work
        And I approve the pending workflow step 1 for "wf-crown"
        And the workflow engine processes pending work
        Then the "wf-crown" recommendation should be linked to a bestowal
        When I open the bestowal detail linked to recommendation "wf-crown"
        Then the bestowal detail page should show "Created" in the state row
        And the bestowal detail page should show "recommendation" in the source row

    Scenario: Cancelling a linked bestowal updates lifecycle state
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "workflow single crown"
        When the workflow engine processes pending work
        And I approve the pending workflow step 1 for "wf-crown"
        And the workflow engine processes pending work
        And I open the bestowal detail linked to recommendation "wf-crown"
        And I cancel the open bestowal from the detail page
        Then I should see the flash message "The bestowal has been cancelled."
        And the bestowal detail page should show "Cancelled" in the state row
        And the "wf-crown" recommendation should not be linked to a bestowal
        And the "wf-crown" recommendation workflow run should have terminal reason "bestowal_cancelled"

    Scenario: Different awards for the same member generate separate bestowals
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "workflow multi-award local"
        When the workflow engine processes pending work
        And I approve the pending workflow step 1 for "wf-award-a"
        And I approve the pending workflow step 1 for "wf-award-b"
        And the workflow engine processes pending work
        Then the "wf-award-a" recommendation should be linked to a bestowal
        And the "wf-award-b" recommendation should be linked to a bestowal
        And recommendations "wf-award-a" and "wf-award-b" should link to different bestowals
