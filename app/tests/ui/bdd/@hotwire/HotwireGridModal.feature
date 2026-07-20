@hotwire @mode:serial
Feature: Hotwire grid and modal partial updates
    As awards staff
    I want grid filters and modal saves to preserve my place on the list
    So that I can continue working without losing context

    Scenario: Browser back restores grid URL after filter search
        Given I am logged in as awards admin for hotwire tests
        When I navigate to "/awards/recommendations"
        When I apply a recommendations grid search for "hotwire-e2e-token"
        And I apply a recommendations grid search for "hotwire-e2e-narrow"
        Then the recommendations URL should include search "hotwire-e2e-narrow"
        When I go back in the browser on the recommendations grid
        Then the recommendations URL should not include search "hotwire-e2e-narrow"

    Scenario: App settings grid preserves search in the URL
        Given I am logged in as awards admin for hotwire tests
        When I open the app settings grid with search "hotwire-settings-smoke"
        Then the app settings URL should include search "hotwire-settings-smoke"
