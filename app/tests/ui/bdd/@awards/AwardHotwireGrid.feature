@awards @mode:serial
Feature: Hotwire turbo-stream grid saves (Awards)
    Grid edits and return navigation must preserve filters, pagination, and rows per page.

    Scenario: Edit save from filtered grid uses turbo stream and preserves search
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "grid edit"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I open the "quick" recommendation edit modal from the grid
        And I fill in the open recommendation note with "Hotwire stream partial save"
        When I submit the open recommendation edit with a turbo stream response
        Then the recommendations URL should include the current fixture token
        And the recommendations grid shell should remain connected
        And the recommendations grid state script should be present


    Scenario: Modal edits and leaving the recommendations grid preserve page three
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "grid pagination"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I set the grid rows per page to 10
        And I open grid page 3
        And I open the first visible recommendation edit modal
        And I fill in the open recommendation note with "Preserved page three update"
        And I submit the open recommendation edit with a turbo stream response
        Then the "recommendations" grid should retain page 3 with 10 rows per page and fixture search
        And the recommendations grid should show the updated pagination note
        When I leave and return to the recommendations grid
        Then the "recommendations" grid should retain page 3 with 10 rows per page and fixture search

    Scenario: Bulk workflow decisions preserve page three and fall back to the last remaining page
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "grid pagination"
        When I log in as the pagination fixture approval recipient
        And I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I set the grid rows per page to 10
        And I open grid page 3
        And I reject all visible pagination recommendations through the bulk modal
        Then the "recommendations" grid should retain page 3 with 10 rows per page and fixture search
        And the recommendations grid should show 1 pagination fixture row
        When I reject all visible pagination recommendations through the bulk modal
        Then the "recommendations" grid should retain page 2 with 10 rows per page and fixture search
        And the recommendations grid should show 10 pagination fixture rows
        And the recommendations grid should have no third page

    Scenario: A saved view remembers rows per page together with its search
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create recommendation fixtures for "grid pagination"
        When I navigate to "/awards/recommendations"
        And I search the recommendations grid for the current fixture token
        And I set the grid rows per page to 25
        And I save the pagination grid view
        And I set the grid rows per page to 10
        And I switch away from and back to the pagination grid view
        Then the "recommendations" grid should retain page 1 with 25 rows per page and fixture search
        And the recommendations grid should show 25 pagination fixture rows

    Scenario: Native bestowal bulk check posts preserve the current page and search
        Given I am logged in as "admin@amp.ansteorra.org"
        And I create paginated bestowal check fixtures
        When I navigate to "/awards/bestowals"
        And I search the bestowals grid for the pagination fixture token
        And I set the grid rows per page to 10
        And I open grid page 3
        And I complete the pagination bestowal check on two visible rows
        Then the "bestowals" grid should retain page 3 with 10 rows per page and fixture search
        And the selected pagination bestowal checks should be completed
