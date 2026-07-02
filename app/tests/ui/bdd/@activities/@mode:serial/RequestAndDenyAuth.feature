Feature: User Requests an Authorization and it is Denied
    As a user of the KMP system
    I want to request authorization for an activity
    So that someone can approve or deny my request

    Scenario: Request authorization for an activity
        Given I delete all test emails
        And the Activities plugin is enabled for UI tests
        And I am logged in as "iris@ampdemo.com"
        And I navigate to my profile page
        And I click on the "Request Authorization" button
        And I select the activity "Armored"
        And I select the approver "Out of Kingdom: Scale Member 0001"
        And I submit the authorization request
        Then I should see the flash message "The Authorization has been requested."
        Then I should have 1 pending authorization request


    Scenario: Authorization request is denied by the approver
        Given I am logged in as "scale.member+0001@example.test"
        And I navigate to "/approvals"
        And I sort the grid by "Created" descending
        Then I see one approval request for "Armored" from "Iris Basic User Demoer"
        When I click the respond button for the approval request
        And I select the "Reject" decision in the approval modal
        And I enter the approval comment "Not ready yet"
        And I submit the approval response
        Then I should see the flash message "Approval response recorded."

    Scenario: Denial authorization is sent an email to the user
        Given I am at the test email inbox
        When I check for an email with subject "Update on Authorization Request"
        And I open the email with subject "Update on Authorization Request"
        Then the email should start with the body:
            """
            Good day Iris Basic User Demoer
            Scale Member 0001 has responded to your request and the authorization is now Denied for
            Armored.
            """
        And the email should be addressed to "iris@ampdemo.com"
        And the email should be from "donotreply@amp.ansteorra.org"
        And there should be an email to "scale.member+0001@example.test" with subject "Authorization Approval Request"
        And there should be an email to "iris@ampdemo.com" with subject "Update on Authorization Request"

    Scenario: User can see the denied authorization in their profile
        Given I am logged in as "iris@ampdemo.com"
        And I navigate to my profile page
        Then I should see the denied authorization for "Armored" with a reason "Not ready yet"