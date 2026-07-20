Feature: Member registration workflow
    As a prospective member
    I want registration to handle age and membership-card edge cases
    So that my account state and notifications are correct

    Scenario Outline: Public registration creates the correct member state and emails
        Given I prepare the member registration fixture for a "<registrantType>" registrant with a "<cardMode>" membership card
        When I submit the prepared public registration form
        Then I should be on "/members/login"
        And I should see the expected registration success message
        And the registration should create the member in the expected state
        When I process the registration email queue
        Then the registration emails should match the expected workflow notifications

        Examples:
            | registrantType | cardMode |
            | adult          | no       |
            | adult          | uploaded |
            | youth          | no       |
            | youth          | uploaded |

    Scenario: Public registration rejects an invalid membership card upload
        Given I prepare the member registration fixture for a "adult" registrant with a "invalid" membership card
        When I submit the prepared public registration form
        Then the invalid upload should block registration before any member is created
