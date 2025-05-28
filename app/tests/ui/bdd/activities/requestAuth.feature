Feature: User Requests an Authorization
    As a user of the KMP system
    I want to request authorization for an activity
    So that someone can approve or deny my request

    Background:
        Given I am logged in as "admin@test.com"

    Scenario: Request authorization for an activity
        Given I navigate to my profile page
        And I click on the "Request Authorization" button
        And I select the activity "Armored Combat"
        And I select the approver "Barony 2: Earl Realm"
        Then I should have 1 pending authorization request
        And I should see the activity "Armored Combat" in my pending requests


