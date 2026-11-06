@hotwire @mode:serial
Feature: Dialog setup follows document navigation
    Dialogs must initialize after link navigation and cached restoration.

    Scenario: To-Do and approval dialogs work after repeated document visits
        Given I am logged in as awards admin for hotwire tests
        Then dialog initialization should survive repeated document visits

    Scenario: Back restores the preceding list and its query
        Then the real browser check "turbo-navigation" should pass

    Scenario: Nested tabs and loaded frames retain their place
        Then the real browser check "detail-tabs" should pass

    Scenario: Notes, RSVPs and schedule saves reconcile dependent details
        Then the real browser check "partial-updates" should pass
