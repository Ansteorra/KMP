<?php

declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use Cake\TestSuite\TestCase;

/**
 * @coversNothing
 */
class BestowalWorkflowFieldRulesTest extends TestCase
{
    private static bool $rulesLoaded = false;

    private static function loadRules(): void
    {
        if (!self::$rulesLoaded) {
            require_once dirname(__DIR__, 3) . '/config/bestowal_workflow_field_rules.php';
            self::$rulesLoaded = true;
        }
    }

    /**
     * @return void
     */
    public function testOptionalRulesAccumulateUntilRequiredSupersedes(): void
    {
        self::loadRules();

        $byState = bestowalCumulativeFieldRulesForStates(
            BESTOWAL_LINEAR_PROGRESSION,
            bestowalOptionalFieldMilestones(),
            bestowalRequiredFieldMilestones(),
        );

        $this->assertSame([], $byState['Court Pending']);

        $courtScheduledTypes = array_column($byState['Court Scheduled'], 'rule_type', 'field_target');
        $this->assertSame('Optional', $courtScheduledTypes['gathering_id']);
        $this->assertSame('Optional', $courtScheduledTypes['gathering_scheduled_activity_id']);

        $readyTypes = array_column($byState['Ready for Court'], 'rule_type', 'field_target');
        $this->assertSame('Optional', $readyTypes['gathering_id']);

        $givenTypes = array_column($byState['Given'], 'rule_type', 'field_target');
        $this->assertSame('Required', $givenTypes['gathering_id']);
        $this->assertSame('Required', $givenTypes['gathering_scheduled_activity_id']);
        $this->assertSame('Required', $givenTypes['bestowed_at']);
        $this->assertNotContains('Optional', $givenTypes);
    }

    /**
     * @return void
     */
    public function testAnnouncedNotGivenInheritsOptionalCourtSchedulingOnly(): void
    {
        self::loadRules();

        $courtOnly = bestowalCumulativeFieldRulesForStates(
            ['Court Scheduled'],
            bestowalOptionalFieldMilestones(),
            [],
        );
        $rules = $courtOnly['Court Scheduled'];

        $types = array_column($rules, 'rule_type', 'field_target');
        $this->assertSame('Optional', $types['gathering_id']);
        $this->assertSame('Optional', $types['gathering_scheduled_activity_id']);
        $this->assertArrayNotHasKey('bestowed_at', $types);
    }

    /**
     * @return void
     */
    public function testCancelledRequiresCloseReasonOnly(): void
    {
        self::loadRules();

        $rules = bestowalCancelledRequiredRules();
        $this->assertCount(1, $rules);
        $this->assertSame('close_reason', $rules[0]['field_target']);
    }
}
