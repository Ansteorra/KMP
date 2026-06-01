<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\KMP\TimezoneHelper;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalCourtSlotService;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;

/**
 * BestowalCourtSlotServiceTest
 */
class BestowalCourtSlotServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testGatheringSupportsCourtSlotsWhenGatheringSelected(): void
    {
        $service = new BestowalCourtSlotService();
        $this->assertFalse($service->gatheringSupportsCourtSlots(null));
        $this->assertFalse($service->gatheringSupportsCourtSlots(0));
        $this->assertTrue($service->gatheringSupportsCourtSlots(1));
        $this->assertSame(0, $service->countScheduledActivities(null));
    }

    /**
     * @return void
     */
    public function testBuildOptionsIncludesRoamingCourtFirst(): void
    {
        $service = new BestowalCourtSlotService();
        $options = $service->buildOptions(999999);
        $this->assertArrayHasKey(BestowalCourtSlotService::ROAMING_COURT_VALUE, $options);
        $keys = array_keys($options);
        $this->assertSame(BestowalCourtSlotService::ROAMING_COURT_VALUE, $keys[0]);
    }

    /**
     * @return void
     */
    public function testApplyCourtSessionSelectionRoaming(): void
    {
        $service = new BestowalCourtSlotService();
        $bestowal = new Bestowal();
        $bestowal->gathering_scheduled_activity_id = 99;

        $service->applyCourtSessionSelection($bestowal, BestowalCourtSlotService::ROAMING_COURT_VALUE);

        $this->assertTrue($bestowal->roaming_court);
        $this->assertNull($bestowal->gathering_scheduled_activity_id);
    }

    /**
     * @return void
     */
    public function testFormatCourtSlotDisplayRoaming(): void
    {
        $bestowal = new Bestowal(['roaming_court' => true]);
        $label = (new BestowalCourtSlotService())->formatCourtSlotDisplay($bestowal);
        $this->assertSame(BestowalCourtSlotService::roamingCourtLabel(), $label);
    }

    /**
     * @return void
     */
    public function testIsRoamingCourtAcceptsPostgresStyleBooleanStrings(): void
    {
        $service = new BestowalCourtSlotService();
        $bestowal = new Bestowal(['roaming_court' => 't']);
        $this->assertTrue($service->isRoamingCourt($bestowal));
        $bestowal->roaming_court = 'f';
        $this->assertFalse($service->isRoamingCourt($bestowal));
    }

    /**
     * @return void
     */
    public function testHelpMessagesAreNonEmpty(): void
    {
        $this->assertNotSame('', BestowalCourtSlotService::fieldHelpText());
        $this->assertNotSame('', BestowalCourtSlotService::noScheduleMessage());
        $this->assertStringContainsString('Event Schedule', BestowalCourtSlotService::fieldHelpText());
    }

    /**
     * @return void
     */
    public function testResolveBestowedDateWithoutGathering(): void
    {
        $service = new BestowalCourtSlotService();
        $this->assertNull($service->resolveBestowedDate(null, null));
        $this->assertNull($service->getGatheringStartDateYmd(null));
        $this->assertSame([], $service->buildOptionDates(null));
    }

    /**
     * @return void
     */
    public function testBuildInitialFormDataWithoutGatheringReturnsEmptyContext(): void
    {
        $service = new BestowalCourtSlotService();

        $data = $service->buildInitialFormData(null);

        $this->assertFalse($data['available']);
        $this->assertFalse($data['hasScheduledSessions']);
        $this->assertSame([], $data['options']);
        $this->assertSame([], $data['optionDates']);
        $this->assertNull($data['gatheringStartDate']);
        $this->assertNull($data['suggestedBestowedDate']);
    }

    /**
     * @return void
     */
    public function testBuildInitialFormDataIncludesRoamingForSelectedGathering(): void
    {
        $service = new BestowalCourtSlotService();

        $data = $service->buildInitialFormData(999999);

        $this->assertTrue($data['available']);
        $this->assertFalse($data['hasScheduledSessions']);
        $this->assertArrayHasKey(BestowalCourtSlotService::ROAMING_COURT_VALUE, $data['options']);
        $this->assertSame('', $data['optionDates'][BestowalCourtSlotService::ROAMING_COURT_VALUE]);
    }

    /**
     * Court session labels use the viewer timezone (same helper as dropdown formatting).
     *
     * @return void
     */
    public function testCourtSessionDisplayUsesViewerTimezone(): void
    {
        $utc = new DateTime('2026-06-15 18:00:00', 'UTC');
        $member = ['timezone' => 'America/New_York'];

        $formatted = TimezoneHelper::formatForDisplay($utc, $member, 'M j, Y g:i A');
        $this->assertStringContainsString('Jun 15, 2026', $formatted);
        $this->assertStringContainsString('2:00 PM', $formatted);

        $local = TimezoneHelper::toUserTimezone($utc, $member);
        $this->assertSame('2026-06-15', $local->format('Y-m-d'));
    }
}
