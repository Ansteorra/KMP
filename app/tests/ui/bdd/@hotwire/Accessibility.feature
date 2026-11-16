@hotwire @accessibility @mode:serial
Feature: Accessible navigation and partial updates
    Shared navigation, grids and dialogs remain accessible across supported layouts.

    Scenario Outline: Core and plugin screens meet automated accessibility checks
        Given I am logged in as awards admin for hotwire tests
        Then core and plugin screens pass accessibility checks at <width> by <height>

        Examples:
            | width | height |
            | 1280  | 740    |
            | 320   | 640    |
            | 1280  | 500    |
