@awards @mode:serial
Feature: Hotwire turbo-stream grid saves (Awards)
    Grid modal saves must return turbo-stream responses and preserve filter state.

    Scenario: Quick edit save from filtered grid uses turbo stream and preserves search
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "quick edit"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I open the "quick" recommendation quick edit modal from the grid
        And I change the open recommendation state to "In Consideration"
        And I fill in the open recommendation note with "Hotwire stream partial save"
        When I submit the open recommendation quick edit with a turbo stream response
        Then the recommendations URL should include the current fixture token
        And the recommendations grid shell should remain connected
        And the recommendations grid state script should be present
