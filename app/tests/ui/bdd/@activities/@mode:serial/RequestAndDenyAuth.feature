Feature: User Requests an Authorization and it is Denied
    As a user of the KMP system
    I want to request authorization for an activity
    So that someone can approve or deny my request
    So that someone can approve or deny my request

    Scenario: Request authorization for an activity
        Given The test inbox is empty
        And I am logged in as "admin@test.com"
        And I navigate to my profile page
        And I click on the "Request Authorization" button
        And I select the activity "Armored Combat"
        And I select the approver "Barony 2: Earl Realm"
        And I submit the authorization request
        Then I should see the flash message "The Authorization has been requested."
        Then I should have 1 pending authorization request


    Scenario: Authorization request is denied by the approver
        Given I am logged in as "Earl@test.com"
        And I click on my name "Earl Realm"
        And My Queue shows 1 pending authorization request
        And I click on the "My Auth Queue" link
        And I see one authorization request for "Armored Combat" from "Admin von Admin"
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
            Good day Admin von Admin
            Earl Realm has responded to your request and the authorization is now Denied for
            Armored Combat.
            """

    Scenario: User can see the denied authorization in their profile
        Given I am logged in as "admin@test.com"
        And I navigate to my profile page
        Then I should see the denied authorization for "Armored Combat" with a reason "Not ready yet"