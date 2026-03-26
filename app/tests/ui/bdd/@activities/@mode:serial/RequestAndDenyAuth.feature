Feature: User Requests an Authorization and it is Denied
    As a user of the KMP system
    I want to request authorization for an activity
    So that someone can approve or deny my request

    Scenario: Request authorization for an activity
        Given The test inbox is empty
        And I am logged in as "iris@ampdemo.com"
        And I navigate to my profile page
        And I click on the "Request Authorization" button
        And I select the activity "Armored"
        And I select the approver "Kingdom Land: Admin von Admin"
        And I submit the authorization request
        Then I should see the flash message "The Authorization has been requested."
        Then I should have 1 pending authorization request


    Scenario: Authorization request is denied by the approver
        Given I am logged in as "admin@amp.ansteorra.org"
        And I click on my name "Admin von Admin"
        And I click on the "My Auth Queue" link
        And I search the grid for "Iris Basic User Demoer"
        And I see one authorization request for "Armored" from "Iris Basic User Demoer"
        And I click on the "Deny" button for the authorization request
        And I enter the value "Not ready yet" in the input field with label "Reason for Denial"
        And I click on the "Submit" button
        Then I should see the flash message "The authorization approval has been rejected."

    Scenario: Denial authorization is sent an email to the user
        Given I am at the test email inbox
        When I check for an email with subject "Update on Authorization Request"
        And I open the email with subject "Update on Authorization Request"
        Then the email should start with the body:
            """
            Good day Iris Basic User Demoer
            Admin von Admin has responded to your request and the authorization is now Denied for
            Armored.
            """

    Scenario: User can see the denied authorization in their profile
        Given I am logged in as "iris@ampdemo.com"
        And I navigate to my profile page
        Then I should see the denied authorization for "Armored" with a reason "Not ready yet"