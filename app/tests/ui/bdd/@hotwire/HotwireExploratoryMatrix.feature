@hotwire @exploratory @mode:serial
Feature: Hotwire exploratory matrix
    Manual and automated coverage checklist for partial-page Hotwire flows.
    Run with: npm run test:ui -- --grep @hotwire

    Scenario: Matrix app settings filtered grid URL
        Given I am logged in as awards admin for hotwire tests
        When I open the app settings grid with search "matrix-smoke"
        Then the app settings URL should include search "matrix-smoke"

    Scenario: Matrix recommendations grid search URL
        Given I am logged in as awards admin for hotwire tests
        When I open the recommendations grid with search "matrix-smoke"
        Then the recommendations URL should include search "matrix-smoke"

    @fixme
    Scenario: Matrix grid back navigation after filter
        Given I am logged in as awards admin for hotwire tests
        When I navigate to "/awards/recommendations"
        When I apply a recommendations grid search for "matrix-back-a"
        And I apply a recommendations grid search for "matrix-back-b"
        Then the recommendations URL should include search "matrix-back-b"
        When I go back in the browser on the recommendations grid
        Then the recommendations URL should not include search "matrix-back-b"
