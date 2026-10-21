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
        And runtime tenant migrations should not write schema lock files
        When I queue a 7 day backup for tenant "kmp2" through the Platform Admin portal
        Then tenant "kmp2" should have a queued backup job without secret leakage
        When the platform job runner drains queued jobs
        Then tenant "kmp2" should have a verified encrypted JSON logical backup
        And the completed tenant backup should have an operator timeline
        When I download the managed tenant backup and recovery key for "kmp2"
        Then the downloaded tenant backup and recovery key for "kmp2" are a matched pair
        When I suspend tenant "kmp2" through the Platform Admin portal
        And I queue the latest backup to restore tenant "kmp2"
        Then tenant "kmp2" should have a queued restore job without secret leakage
        And another tenant remains available while tenant "kmp2" restore is queued
        When the platform job runner drains queued jobs
        Then tenant "kmp2" should have a completed restore job
        And runtime tenant migrations should not write schema lock files
        When I reactivate tenant "kmp2" through the Platform Admin portal
        Then tenant "kmp2" should be active for host "kmp2.localhost"
        And the tenant "kmp2" host should show the sign in page
        And the tenant super user "superuser@kmp2.localhost" can request a password reset on tenant "kmp2"
        When I inspect managed backups as tenant "kmp2"
        Then tenant restores should remain restricted to Platform Admin
        When I delete the latest managed backup for tenant "kmp2"
        Then tenant "kmp2" should retain audited metadata for the deleted backup
