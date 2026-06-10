@platform-admin
@destructive
@mode:serial
Feature: Platform Admin tenant provisioning
    As a platform operator
    I want to rebuild a tenant from the Platform Admin portal
    So that visible tenant onboarding controls are backed by real provisioning work

    Scenario: Platform admin rebuilds kmp2 from the portal
        Given the "kmp2" tenant has been removed from the platform registry
        And I am logged into the Platform Admin portal
        When I create tenant "kmp2" for host "kmp2.localhost" through the Platform Admin portal
        Then tenant "kmp2" should have a queued provisioning job without secret leakage
        When the platform job runner drains queued jobs
        Then tenant "kmp2" should be active for host "kmp2.localhost"
        And tenant "kmp2" should have the initial super user "superuser@kmp2.localhost"
        And the tenant "kmp2" host should show the sign in page
        And the tenant super user "superuser@kmp2.localhost" can request a password reset on tenant "kmp2"
