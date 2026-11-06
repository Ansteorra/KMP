@hotwire @mode:serial
Feature: Dialog setup follows document navigation
    Dialogs must initialize after link navigation and cached restoration.

    Scenario: To-Do and approval dialogs work after repeated document visits
        Given I am logged in as awards admin for hotwire tests
        Then dialog initialization should survive repeated document visits
