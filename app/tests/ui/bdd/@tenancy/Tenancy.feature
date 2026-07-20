@tenancy
@mode:serial
Feature: Tenant data isolation
    As a platform operator
    I want tenant-bound browser contexts to read only their own tenant database
    So that one tenant cannot see or open another tenant's records

    Scenario: Tenant-bound contexts show isolated seeded records
        Given I am logged into tenant "kmp" as "admin@amp.ansteorra.org"
        When I open member id "2878" in the active tenant
        Then the active tenant page should contain "Iris Basic User Demoer"
        And the active tenant page should not contain "Iris Second Basic User"
        When I open branch public id "mwnuttW8" in the active tenant
        Then the active tenant page should contain "Ansteorra"
        And the active tenant page should not contain "Second Kingdom"
        When I switch to tenant "kmp2" as "admin@amp2demo.com"
        And I open member id "2878" in the active tenant
        Then the active tenant page should contain "Iris Second Basic User"
        And the active tenant page should not contain "Iris Basic User Demoer"
        When I open branch public id "mwnuttW8" in the active tenant
        Then the active tenant page should contain "Second Kingdom"

    Scenario: Tenant two rejects a tenant-one-only member record
        Given I am logged into tenant "kmp2" as "admin@amp2demo.com"
        When I open member id "2871" in the active tenant
        Then the active tenant response status should be 404
