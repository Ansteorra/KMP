@hotwire @exploratory @mode:serial
Feature: Hotwire phase 2 surfaces
    Tab lazy frames, calendar quick view, and cell streams.
    Scenarios are smoke-level until dedicated fixtures exist.

    @skip
    Scenario: Detail tab switch updates URL with tab query
        Given I am logged in as awards admin for hotwire tests
        Given I navigate to "/members"
        Then I should be on "/members"

    @skip
    Scenario: Gatherings calendar quick view opens modal frame
        Given I am logged in as awards admin for hotwire tests
        Given I navigate to "/gatherings/calendar"
        Then I should be on "/gatherings/calendar"
