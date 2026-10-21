@mode:serial
Feature: Trusted device PIN setup and authentication
    As a user of the KMP system
    I want to protect my personal device with a PIN
    So that I can sign in and use my saved information safely

    Background:
        Given I start PIN setup on a personal device
            | email    | admin@amp.ansteorra.org |
            | password | TestPassword            |

    Scenario: Complete personal device PIN setup
        When I save device PIN "582694" with confirmation "582694"
        Then my personal device should be ready offline

    Scenario: Device setup rejects mismatched PINs
        When I save device PIN "582694" with confirmation "582695"
        Then the device PIN mismatch should be announced

    Scenario: Device PIN signs in after logout
        When I save device PIN "582694" with confirmation "582694"
        Then my personal device should be ready offline
        When I finish device setup and log out
        And I unlock the personal device with PIN "582694"
        Then I should be signed in through device unlock
        When I sign out after device unlock
        Then the personal device should be locked on the login page
