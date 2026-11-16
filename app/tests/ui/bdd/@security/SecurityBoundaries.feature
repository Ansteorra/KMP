@security
@mode:serial
Feature: Security boundaries
    Scenario: Approval reads and logout preserve security across tenant hosts
        When I exercise the approval and logout security browser journeys
        Then the security browser journeys should pass

    Scenario: Tenant email changes require fresh authorized confirmation
        When I exercise the tenant mail security browser journey
        Then the tenant mail security browser journey should pass
