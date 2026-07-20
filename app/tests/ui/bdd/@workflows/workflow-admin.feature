@workflows
Feature: Workflow Engine Administration
  As an admin user
  I want to manage workflow definitions
  So that I can configure business processes visually

  Background:
    Given I am logged in as "admin@amp.ansteorra.org"

  Scenario: View workflow definitions list
    When I navigate to the workflows page
    Then I should see the workflow definitions list
    And I should see "Officer Hire" in the list
    And I should see "Warrant Roster Approval" in the list
    And I should see "Authorization Request" in the list

  Scenario: Open workflow designer
    When I navigate to the workflows page
    And I click the design button for "Officer Hire"
    Then I should see the workflow designer
    And I should see the node palette
    And I should see the workflow canvas

  Scenario: View workflow instances
    When I navigate to the workflow instances page
    Then I should see the instances list

  Scenario: View workflow approvals
    When I navigate to the workflow approvals page
    Then I should see the approvals list

  Scenario: View workflow versions
    When I navigate to the workflows page
    And I click the versions button for "Officer Hire"
    Then I should see the versions list
    And I should see version 1 with status "published"
