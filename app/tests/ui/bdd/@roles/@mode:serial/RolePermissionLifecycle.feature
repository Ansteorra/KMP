@mode:serial
Feature: Role permission lifecycle
    As an admin user
    I want role grants to control awards recommendation access end to end
    So that permission changes stay safe across role and plugin boundaries

    Scenario: Grant and revoke awards recommendation access through a role
        Given I prepare the award recommendation role fixture
        When I switch to the fixture member account
        Then awards recommendation access should be denied
        When I switch to the fixture admin account
        And I create the prepared access role
        And I rename the prepared access role
        And the add permission modal should require a selection before submit
        And I add the fixture award recommendation permission to the role
        And the add member modal should require a member before submit
        And I assign the prepared member to the role
        Then the role fixture should show the member has the permission-granting role
        When I switch to the fixture member account
        Then awards recommendation access should be allowed
        When I switch to the fixture admin account
        And I deactivate the prepared member role assignment
        Then the role fixture should show the member assignment is deactivated
        When I switch to the fixture member account
        Then awards recommendation access should be denied
