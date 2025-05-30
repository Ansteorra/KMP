Feature: User Authentication
    As a user of the KMP system
    I want to be able to log into my account
    So that I can access protected features

    Background:
        Given I am on the login page

    Scenario: Display login form when not authenticated
        When I navigate to a protected route "/roles"
        Then I should see the login form
        And I should see the email address field
        And I should see the password field

    Scenario: Show validation errors for empty form
        When I submit the login form without entering credentials
        Then I should see validation error messages

    Scenario: Show error for invalid credentials
        When I enter invalid credentials
            | email    | invalid@example.com |
            | password | wrongpassword       |
        And I submit the login form
        Then I should see an authentication error message

    Scenario: Successfully log in with valid credentials
        When I enter valid admin credentials
            | email    | admin@test.com |
            | password | Password123    |
        And I submit the login form
        Then I should be successfully logged in
        And I should see the flash message "Welcome Admin von Admin!"

    Scenario: When I am logged in I can log out
        Given I am logged in as "admin@test.com"
        When I logout
        Then I should see the login form
