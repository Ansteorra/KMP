@mode:serial
Feature: Quick Login Setup and PIN Authentication
    As a user of the KMP system
    I want to set up quick login with a PIN
    So that I can log in quickly on trusted devices

    Scenario: Enable quick login and complete PIN setup
        Given I am on the login page
        When I check the quick login checkbox
        And I enter valid credentials for quick login setup
            | email    | admin@amp.ansteorra.org |
            | password | TestPassword            |
        And I submit the login form
        Then I should be redirected to the PIN setup page
        And the page should contain "Set quick login PIN"
        When I enter PIN "1234" and confirmation "1234"
        And I click the save PIN button
        Then I should see the flash message "Quick login on this device is now enabled"
        And I should be redirected away from PIN setup

    Scenario: PIN setup rejects mismatched PINs
        Given I am on the login page
        When I check the quick login checkbox
        And I enter valid credentials for quick login setup
            | email    | admin@amp.ansteorra.org |
            | password | TestPassword            |
        And I submit the login form
        Then I should be redirected to the PIN setup page
        When I enter PIN "1234" and confirmation "5678"
        And I click the save PIN button
        Then I should still be on the PIN setup page

    Scenario: Quick login with PIN succeeds after setup and logout works
        Given I am on the login page
        When I check the quick login checkbox
        And I enter valid credentials for quick login setup
            | email    | admin@amp.ansteorra.org |
            | password | TestPassword            |
        And I submit the login form
        Then I should be redirected to the PIN setup page
        When I enter PIN "1234" and confirmation "1234"
        And I click the save PIN button
        Then I should be redirected away from PIN setup
        When I logout from the session
        And I navigate to the login page
        Then I should see the quick login tab
        And the quick login tab should be active
        When I enter PIN "1234" in the quick login form
        And I submit the quick login form
        Then I should be successfully logged in with quick login
        When I logout from the session
        Then I should be on the login page
