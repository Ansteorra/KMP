<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\KMP\TimezoneHelper;
use App\Model\Entity\GatheringScheduledActivity;
use App\Model\Entity\Permission;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Services\BestowalCreationService;
use Awards\Services\RecommendationGroupingService;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use DateTimeZone;
use DOMDocument;
use DOMXPath;

/**
 * CourtAgendasControllerTest
 */
class CourtAgendasControllerTest extends HttpIntegrationTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * @return void
     */
    public function testGatheringAgendaRendersPerCourtBuilder(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'public_id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Projected Agenda Runtime');
        $this->assertResponseContains('Court Activities');
        $this->assertResponseContains('Build one court at a time');
        $this->assertResponseContains('data-controller="court-agenda-board"');
        $this->assertResponseContains('Back to Gathering');
        $this->assertResponseContains(
            '/gatherings/view/' . $gathering->public_id . '?tab=gathering-bestowals',
        );
        $this->assertResponseContains('Printer Ready — Full');
        $this->assertResponseContains('Printer Ready — Heralds');
        $this->assertResponseContains('?version=heralds');
        $this->assertResponseContains('Remove from Agenda');
        $this->assertResponseNotContains('No linked scheduled activity');

        $document = new DOMDocument();
        $document->loadHTML((string)$this->_response->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@method="get"]//select[@name="segment_id"]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="segment_id"]/option[@selected]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="court-agenda-tools" and @aria-labelledby]')->length);
        $this->assertGreaterThan(0, $xpath->query('//article[@role="listitem"]//details/summary')->length);
        $this->assertSame(0, $xpath->query('//article[@role="listitem"]//details[@open]')->length);
        $this->assertGreaterThan(0, $xpath->query('//button[@data-direction="up" and @aria-label]')->length);
        $this->assertSame(0, $xpath->query('//article[@role="listitem"]/form')->length);
    }

    /**
     * @return void
     */
    public function testGatheringAgendaShowsCourtLanesFromScheduleWithoutImport(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
        );

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Court Session');
        $this->assertResponseNotContains('No court activities are available yet.');
    }

    public function testAgendaExplainsAttachedAwardActivityNeedsSchedulingAndClearsWarningAfterScheduling(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Unscheduled Presentation <Oak>']));
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $links->saveOrFail($links->newEntity([
            'award_id' => $award->id, 'gathering_activity_id' => $activity->id,
        ]));
        $attached = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $attached->saveOrFail($attached->newEntity([
            'gathering_id' => $gathering->id, 'gathering_activity_id' => $activity->id,
        ]));

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertSame([(int)$activity->id => $activity->name], $this->viewVariable('unscheduledAwardActivities'));
        $this->assertResponseContains('Award-giving activities need scheduling');
        $this->assertResponseContains(h($activity->name));
        $this->assertResponseContains('Adding an activity under Activities does not schedule it.');
        $this->assertResponseContains('/gatherings/view/' . $gathering->public_id . '?tab=schedule');
        $this->assertResponseNotContains($activity->name);

        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
            (int)$activity->id,
        );
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertSame([], $this->viewVariable('unscheduledAwardActivities'));
        $this->assertResponseNotContains('Award-giving activities need scheduling');
        $this->assertResponseContains('Court Session');
    }

    public function testAgendaWithoutEligibleActivityExplainsAwardCatalogAndLinksToActivities(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('No court activities are available yet.');
        $this->assertResponseContains('check its award eligibility in the awards catalog');
        $this->assertResponseContains('/gatherings/view/' . $gathering->public_id . '?tab=activities');
    }

    /**
     * @return void
     */
    public function testScopedCourtManagerCanManageAgendaHostedByAnotherBranch(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->where(['branch_id IS NOT' => null, 'branch_id !=' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $this->grantCourtAgendaManagement(
            self::TEST_MEMBER_AGATHA_ID,
            self::KINGDOM_BRANCH_ID,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);

        $this->assertResponseOk();
        $this->assertResponseContains('draggable="true"');
        $this->assertResponseContains('Refresh Scheduled Bestowals');
        $this->assertResponseNotContains('Noble court secret.');
    }

    /** @return void */
    public function testScopedCourtManagerCanPrepareScheduledAgendaBeforeBestowalsExist(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $source = $gatherings->find()->where(['branch_id !=' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Empty award-giving agenda',
            'created_by' => self::ADMIN_MEMBER_ID,
            'branch_id' => $source->branch_id,
            'gathering_type_id' => $source->gathering_type_id,
            'start_date' => $source->start_date,
            'end_date' => $source->end_date,
            'location' => 'Test Hall',
        ]));
        $award = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->saveOrFail($activities->newEntity(['name' => 'Agenda Access Test Presentation']));
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $links->saveOrFail($links->newEntity([
            'award_id' => $award->id,
            'gathering_activity_id' => $activity->id,
        ]));
        $this->getTableLocator()->get('MemberRoles')->deleteAll(['member_id' => self::TEST_MEMBER_AGATHA_ID]);
        $this->grantCourtAgendaManagement(self::TEST_MEMBER_AGATHA_ID, self::KINGDOM_BRANCH_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $testUser = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $this->assertFalse($testUser->isSuperUser());
        $testPolicy = $testUser->getPolicies()['Awards\\Policy\\CourtAgendaPolicy']['canGathering'];
        $this->assertSame(Permission::SCOPE_BRANCH_ONLY, $testPolicy->scoping_rule);
        $this->assertSame([self::KINGDOM_BRANCH_ID], $testPolicy->branch_ids);
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertAuthenticatedAs(self::TEST_MEMBER_AGATHA_ID);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendas')->exists([
            'gathering_id' => $gathering->id,
        ]));

        $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
            (int)$activity->id,
        );
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Refresh Scheduled Bestowals');
        $this->assertResponseContains('Court Session');
        $agenda = $this->getTableLocator()->get('Awards.CourtAgendas')->find()
            ->where(['gathering_id' => $gathering->id])->firstOrFail();
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertResponseOk();

        // Keeping a scheduled session must not grant access once it supports only out-of-scope awards.
        $links->deleteAll(['gathering_activity_id' => $activity->id]);
        $otherAward = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['branch_id !=' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $links->saveOrFail($links->newEntity([
            'award_id' => $otherAward->id,
            'gathering_activity_id' => $activity->id,
        ]));
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertRedirectContains('/pages/unauthorized');
    }

    /**
     * @return void
     */
    public function testPrintAgendaRendersPrinterReadyFormat(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('<table>');
        $this->assertResponseContains('Projected court runtime');
        $this->assertResponseContains('Print Agenda');
        $this->assertResponseContains('Call into court:');
        $this->assertResponseContains('With Notice');
        $this->assertResponseContains('Court availability:');
        $this->assertResponseContains('Evening');
        $this->assertResponseContains('Notify:');
        $this->assertResponseContains('Agenda Contact');
        $this->assertResponseContains('class="np-checkbox"');
        $this->assertResponseContains('aria-label="Not Present:');
        $this->assertResponseContains('title="Not Present">NP</abbr>');
        $this->assertResponseNotContains('<th scope="col">Minutes</th>');
        $this->assertResponseNotContains('hint:');
        $this->assertResponseNotContains('State:');
        $this->assertResponseNotContains('Hand notes:');
    }

    /** @return void */
    public function testHeraldsPrintShowsCleanNotesAndOneCallInSeparateColumn(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $this->createBestowalForGathering((int)$gathering->id);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->find()->where(['gathering_id' => $gathering->id])->firstOrFail();
        $bestowal->specialty = 'Heralds <Class>';
        $bestowal->herald_notes = $bestowal->getRecipientName()
            . "\nOld Centurion (Grant) — Call: Yes\nOld Amicitia (Non-Armigerous) — Call: Yes"
            . "\nSpeak clearly. <Oak>";
        $bestowals->saveOrFail($bestowal);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id . '?version=heralds');

        $this->assertResponseOk();
        $this->assertTrue($this->viewVariable('isHeraldsVersion'));
        $this->assertResponseContains('Court Agenda (Heralds)');
        $this->assertResponseContains($bestowal->getRecipientName());
        $this->assertResponseContains('Class:');
        $this->assertResponseContains('Heralds &lt;Class&gt;');
        $this->assertResponseContains(h($this->viewVariable('eventDates')));
        $this->assertResponseContains(h($this->viewVariable('segments')[0]['printHeading']));
        $this->assertResponseContains('class="np-checkbox"');
        $this->assertResponseNotContains('Court Notes');
        $this->assertResponseContains('Notes:');
        $this->assertResponseNotContains('Herald:');
        $this->assertResponseNotContains('Old Centurion');
        $this->assertResponseNotContains('Old Amicitia');
        $this->assertResponseNotContains('Call: Yes');
        $this->assertResponseNotContains('Projected court runtime');
        $this->assertResponseNotContains(' min)');
        $this->assertResponseContains('Speak clearly. &lt;Oak&gt;');
        $this->assertSame(1, substr_count((string)$this->_response->getBody(), 'Call into court:'));
        $this->assertResponseNotContains('<th scope="col">Herald Notes</th>');
        $this->assertResponseContains('Speak clearly.');
        $this->assertResponseNotContains('Noble court secret.');
        $this->assertResponseNotContains('Agenda Contact');
        $dom = new DOMDocument();
        $dom->loadHTML((string)$this->_response->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $headings = [];
        foreach ($xpath->query('(//table)[1]/thead/tr/th') as $heading) {
            $headings[] = trim($heading->textContent);
        }
        $this->assertSame(['Order', 'Recipient / Block', 'Award / Action', 'Notes', 'NP'], $headings);
        $awardCellNotes = $xpath->query('//tbody/tr/td[4]/div[contains(@class, "court-note")]');
        $this->assertGreaterThan(0, $awardCellNotes->length);
        $this->assertStringContainsString('Speak clearly.', $awardCellNotes->item(0)->textContent);
        foreach ($xpath->query('//tbody/tr[td/input[@type="checkbox"]]') as $row) {
            $this->assertSame(5, $xpath->query('td', $row)->length);
        }

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id . '?version=full');
        $this->assertResponseOk();
        $this->assertFalse($this->viewVariable('isHeraldsVersion'));
        $this->assertResponseContains('Court Notes');
        $this->assertResponseContains('Speak clearly.');
    }

    /** @return void */
    public function testHeraldsPrintEmptyCourtUsesFiveColumnSpan(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $this->createScheduledActivityForAward((int)$gathering->id, (int)$award->id, $gathering->start_date);
        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $agenda = $this->viewVariable('agenda');

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id . '?version=heralds');

        $this->assertResponseOk();
        $this->assertResponseContains('<td colspan="5">');
        $this->assertResponseNotContains('colspan="4"');
        $this->assertResponseContains('No agenda items in this segment.');
    }

    /** @return void */
    public function testFullPrintShowsOriginalAwardsAndGroupCountOnlyWithCrownAccess(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $awards = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])->limit(2)->all()->toList();
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $ids = [];
        foreach ([$awards[0], $awards[0], $awards[1]] as $award) {
            $recommendation = $recommendations->saveOrFail($recommendations->newEntity([
                'member_id' => self::ADMIN_MEMBER_ID,
                'member_sca_name' => 'Agenda Group Count Recipient',
                'requester_id' => self::ADMIN_MEMBER_ID,
                'requester_sca_name' => 'Agenda Group Submitter',
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'award_id' => $award->id,
                'state' => 'King Approved',
                'contact_email' => 'agenda@example.test',
                'reason' => 'Agenda recommendation context test.',
                'call_into_court' => 'Not Set',
                'court_availability' => 'Not Set',
                'person_to_notify' => '',
            ]));
            $ids[] = (int)$recommendation->id;
        }
        (new RecommendationGroupingService($recommendations))->groupRecommendations($ids, self::ADMIN_MEMBER_ID);
        $result = (new BestowalCreationService())->createFromRecommendation(
            $ids[0],
            self::ADMIN_MEMBER_ID,
            (int)$gathering->id,
        );
        $this->assertTrue($result['success'], $result['error'] ?? 'Unable to create grouped bestowal.');
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->get($result['data']['bestowalId']);
        $bestowal->award_id = $awards[1]->id;
        $bestowal->herald_notes = 'Agenda herald instructions.';
        $bestowal->noble_notes = 'Agenda noble instructions.';
        $session = $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$awards[1]->id,
            $gathering->start_date,
        );
        $bestowal->gathering_scheduled_activity_id = $session->id;
        $bestowals->saveOrFail($bestowal);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $url = '/awards/court-agendas/print-agenda/' . $agenda->id;

        $this->get($url);
        $this->assertResponseOk();
        $this->assertResponseContains('Recommendation count:</strong> 3');
        $this->assertResponseContains('Original recommendations were for:</strong>');
        $this->assertResponseContains(h($awards[0]->name . ', ' . $awards[1]->name));
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, 'Recommendation count:'), strpos($body, 'Agenda herald instructions.'));
        $this->assertLessThan(strpos($body, 'Recommendation count:'), strpos($body, 'Agenda noble instructions.'));
        $this->assertResponseContains('Reasons:');
        $this->assertLessThan(strpos($body, 'Recommendation count:'), strpos($body, 'Reasons:'));

        $this->get($url . '?version=heralds');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Recommendation count:');
        $this->assertResponseNotContains('Original recommendations were for:');

        $this->getTableLocator()->get('MemberRoles')->deleteAll(['member_id' => self::TEST_MEMBER_AGATHA_ID]);
        $this->grantCourtAgendaManagement(self::TEST_MEMBER_AGATHA_ID, self::KINGDOM_BRANCH_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get($url);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Recommendation count:');
        $this->assertResponseNotContains('Original recommendations were for:');
    }

    /** @return void */
    public function testBothPrintVersionsOrderCourtsByScheduleInsteadOfSavedSectionOrder(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $sessions = $this->getTableLocator()->get('GatheringScheduledActivities');
        $late = $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date->addDays(1),
        );
        $late->display_title = 'Later Court';
        $sessions->saveOrFail($late);
        $early = $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
        );
        $early->display_title = 'Earlier Court';
        $sessions->saveOrFail($early);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $segments = $this->getTableLocator()->get('Awards.CourtAgendaSegments');
        $segments->updateAll(['sort_order' => 0], ['gathering_scheduled_activity_id' => $late->id]);
        $segments->updateAll(['sort_order' => 100], ['gathering_scheduled_activity_id' => $early->id]);

        foreach (['full', 'heralds'] as $version) {
            $this->get('/awards/court-agendas/print-agenda/' . $agenda->id . '?version=' . $version);
            $this->assertResponseOk();
            $body = (string)$this->_response->getBody();
            $this->assertResponseContains('Earlier Court - ');
            $this->assertResponseContains('Later Court - ');
            $this->assertLessThan(strpos($body, 'Later Court - '), strpos($body, 'Earlier Court - '));
        }
        $this->assertSame(0, $segments->find()->where([
            'gathering_scheduled_activity_id' => $late->id,
        ])->firstOrFail()->sort_order);
        $this->assertSame(100, $segments->find()->where([
            'gathering_scheduled_activity_id' => $early->id,
        ])->firstOrFail()->sort_order);
    }

    /** @return void */
    public function testPrintIncludesNewlyScheduledAwardGivingSessionsAndCurrentScheduleDetails(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $session = $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$award->id,
            $gathering->start_date,
        );
        $session->display_title = 'New Prize Presentation';
        $this->getTableLocator()->get('GatheringScheduledActivities')->saveOrFail($session);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('New Prize Presentation');
        $localStart = $session->start_datetime->setTimezone(new DateTimeZone(TimezoneHelper::getGatheringTimezone($gathering)));
        $this->assertResponseContains('New Prize Presentation - ' . $localStart->format('m/d/Y g:i A T'));
        $this->assertResponseContains('New Prize Presentation - ' . $localStart->format('m/d/Y'));
        $localEnd = $session->end_datetime->setTimezone(new DateTimeZone(TimezoneHelper::getGatheringTimezone($gathering)));
        $endFormat = $localEnd->format('Y-m-d') === $localStart->format('Y-m-d') ? 'g:i A T' : 'm/d/Y g:i A T';
        $slot = $localStart->format('m/d/Y g:i A T') . ' - ' . $localEnd->format($endFormat);
        $this->assertResponseContains('New Prize Presentation - ' . $slot);
        $dom = new DOMDocument();
        $dom->loadHTML((string)$this->_response->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//h2[contains(., "New Prize Presentation - ' . $slot . '")]')->length);
        $this->assertSame(0, $xpath->query('//div[@class="segment-heading"]/p')->length);
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id . '?version=heralds');
        $this->assertResponseOk();
        $this->assertResponseContains('New Prize Presentation - ' . $slot);
        $this->assertResponseNotContains('Projected court runtime');

        $session->display_title = 'Updated Prize Presentation';
        $session->start_datetime = $session->start_datetime->addMinutes(15);
        $this->getTableLocator()->get('GatheringScheduledActivities')->saveOrFail($session);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $localStart = $session->start_datetime->setTimezone(new DateTimeZone(TimezoneHelper::getGatheringTimezone($gathering)));
        $this->assertResponseContains('New Prize Presentation - ' . $localStart->format('m/d/Y g:i A T'));
        $this->assertResponseNotContains('Updated Prize Presentation - ');
        $this->assertResponseContains('New Prize Presentation - ' . $localStart->format('m/d/Y'));
    }

    /** @return void */
    public function testPrintShowsCurrentClassInAwardColumnAndOmitsUnsetNotes(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $this->createBestowalForGathering((int)$gathering->id);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->find()->where(['gathering_id' => $gathering->id])->firstOrFail();
        $bestowal->specialty = 'Current <Class>';
        $bestowal->herald_notes = 'Award — Specialty: Obsolete class — Call: With Notice';
        $bestowal->noble_notes = '   ';
        $bestowal->call_into_court = 'Not Set';
        $bestowal->court_availability = 'Not Set';
        $bestowal->person_to_notify = '';
        $bestowals->saveOrFail($bestowal);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Class:');
        $this->assertResponseContains('Current &lt;Class&gt;');
        $this->assertResponseNotContains('Obsolete class');
        $this->assertResponseNotContains('Specialties:');
        $this->assertResponseNotContains('Call into court:');
        $this->assertResponseNotContains('Court availability:');
        $this->assertResponseNotContains('Notify:');
        $this->assertResponseNotContains('Noble:');
        $this->assertResponseNotContains('Reasons:');

        $items = $this->getTableLocator()->get('Awards.CourtAgendaItems');
        $items->updateAll(['include_specialties' => false], ['bestowal_id' => $bestowal->id]);
        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Class:');
        $this->assertResponseNotContains('Current &lt;Class&gt;');
        $this->assertResponseNotContains('Obsolete class');
    }

    public function testCourtManagerPrintAgendaShowsHeraldNotesButHidesCrownFields(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $this->grantCourtAgendaManagement(
            self::TEST_MEMBER_AGATHA_ID,
            self::KINGDOM_BRANCH_ID,
        );
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        foreach (['full', 'heralds'] as $version) {
            $this->get('/awards/court-agendas/print-agenda/' . $agenda->id . '?version=' . $version);

            $this->assertResponseOk();
            $this->assertResponseContains('Speak clearly.');
            $this->assertResponseNotContains('Noble court secret.');
        }
    }

    public function testAgendaAndPrintBlockOutOfScopeBestowalsAndHideUnassignedCandidates(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $visibleAward = $awards->find()->where(['branch_id' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $blockedAward = $awards->find()->where(['branch_id !=' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $session = $this->createScheduledActivityForAward(
            (int)$gathering->id,
            (int)$visibleAward->id,
            $gathering->start_date,
        );
        $links = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $linkData = [
            'award_id' => $blockedAward->id,
            'gathering_activity_id' => $session->gathering_activity_id,
        ];
        if (!$links->exists($linkData)) {
            $links->saveOrFail($links->newEntity($linkData));
        }
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowalData = [
            ['Visible scheduled recipient', $visibleAward, $session->id],
            ['Restricted scheduled recipient', $blockedAward, $session->id],
            ['Visible unassigned recipient', $visibleAward, null],
            ['Restricted unassigned recipient', $blockedAward, null],
        ];
        foreach ($bestowalData as [$recipient, $award, $sessionId]) {
            $bestowals->saveOrFail($bestowals->newEntity([
                'member_sca_name' => $recipient,
                'award_id' => $award->id,
                'gathering_id' => $gathering->id,
                'gathering_scheduled_activity_id' => $sessionId,
                'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
                'source' => Bestowal::SOURCE_AD_HOC,
                'herald_notes' => $recipient . ' herald notes',
                'noble_notes' => $recipient . ' noble notes',
                'specialty' => $recipient . ' specialty',
                'person_to_notify' => $recipient . ' contact',
                'call_into_court' => 'With Notice',
                'court_availability' => 'Evening',
            ]));
        }
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $items = $this->getTableLocator()->get('Awards.CourtAgendaItems');
        $blockedItem = $items->find()->innerJoinWith('Bestowals')->where([
            'Bestowals.gathering_id' => $gathering->id,
            'Bestowals.award_id' => $blockedAward->id,
        ])->firstOrFail();
        $blockedItem->presentation_notes = 'Restricted agenda notes';
        $blockedItem->print_notes = 'Restricted print notes';
        $blockedItem->planned_action = 'Restricted planned action';
        $items->saveOrFail($blockedItem);
        $this->getTableLocator()->get('MemberRoles')->deleteAll(['member_id' => self::TEST_MEMBER_AGATHA_ID]);
        $this->grantCourtAgendaManagement(self::TEST_MEMBER_AGATHA_ID, self::KINGDOM_BRANCH_ID);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $urls = [
            '/awards/court-agendas/gathering/' . $gathering->id . '?segment_id=' . $blockedItem->court_agenda_segment_id,
            '/awards/court-agendas/print-agenda/' . $agenda->id,
            '/awards/court-agendas/print-agenda/' . $agenda->id . '?version=heralds',
        ];
        foreach ($urls as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('Visible scheduled recipient');
            $this->assertResponseContains('Court Item  (details blocked by security)');
            $this->assertResponseNotContains('Restricted');
            foreach ($this->viewVariable('segments') as $segment) {
                foreach ($segment['items'] as $item) {
                    if ((int)$item['entity']->id === (int)$blockedItem->id) {
                        $this->assertNull($item['entity']->bestowal);
                        $this->assertSame([], $item['moveSegmentOptions']);
                    }
                }
                foreach ($segment['eligibleBestowals'] as $candidate) {
                    $this->assertSame((int)$visibleAward->id, (int)$candidate->award_id);
                }
            }
        }

        $this->get('/awards/court-agendas/gathering/' . $gathering->id
            . '?segment_id=' . $blockedItem->court_agenda_segment_id);
        $this->assertResponseContains('Visible unassigned recipient');
        $this->assertResponseNotContains('id="agenda-item-' . $blockedItem->id . '-edit-modal"');
        $this->assertResponseContains(h(__('Move {0} up', 'Court Item  (details blocked by security)')));
        $candidates = $this->viewVariable('unscheduledBestowals');
        $this->assertCount(1, $candidates);
        $this->assertSame('Visible unassigned recipient', $candidates[0]['label']);

        $blockedUnassigned = $bestowals->find()->where([
            'gathering_id' => $gathering->id,
            'member_sca_name' => 'Restricted unassigned recipient',
        ])->firstOrFail();
        foreach (['add-bestowal', 'move-to-roaming'] as $action) {
            $this->post('/awards/court-agendas/' . $action, [
                'court_agenda_id' => $agenda->id,
                'court_agenda_segment_id' => $blockedItem->court_agenda_segment_id,
                'bestowal_id' => $blockedUnassigned->id,
            ]);
            $this->assertRedirectContains('/pages/unauthorized');
            $this->assertFalse($items->exists(['bestowal_id' => $blockedUnassigned->id]));
            $unchanged = $bestowals->get($blockedUnassigned->id);
            $this->assertNull($unchanged->gathering_scheduled_activity_id);
            $this->assertFalse((bool)$unchanged->roaming_court);
        }
    }

    public function testAgendaPermissionWithoutBestowalReadOnlyShowsPlaceholdersIncludingRoaming(): void
    {
        $gathering = $this->createGatheringWithoutActivities();
        $award = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['branch_id' => self::KINGDOM_BRANCH_ID])->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id, (int)$award->id);
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        foreach (['Roaming hidden recipient', 'Unassigned hidden recipient'] as $recipient) {
            $bestowals->saveOrFail($bestowals->newEntity([
                'member_sca_name' => $recipient,
                'award_id' => $award->id,
                'gathering_id' => $gathering->id,
                'roaming_court' => str_starts_with($recipient, 'Roaming'),
                'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
                'source' => Bestowal::SOURCE_AD_HOC,
            ]));
        }
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $this->getTableLocator()->get('MemberRoles')->deleteAll(['member_id' => self::TEST_MEMBER_AGATHA_ID]);
        $this->grantCourtAgendaManagement(self::TEST_MEMBER_AGATHA_ID, self::KINGDOM_BRANCH_ID, false);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertSame([], $this->viewVariable('unscheduledBestowals'));
        foreach ($this->viewVariable('segments') as $segment) {
            $this->assertSame([], $segment['eligibleBestowals']);
            foreach ($segment['items'] as $item) {
                $this->assertTrue($item['detailsBlocked']);
                $this->assertSame('Court Item  (details blocked by security)', $item['label']);
            }
        }
        $this->assertResponseNotContains('Speak clearly.');
        $this->assertResponseNotContains('Agenda Contact');
        $this->assertResponseNotContains('hidden recipient');

        $this->get('/awards/court-agendas/print-agenda/' . $agenda->id);
        $this->assertResponseOk();
        $this->assertSame(2, substr_count((string)$this->_response->getBody(), 'Court Item  (details blocked by security)'));
        $this->assertResponseNotContains('Speak clearly.');
        $this->assertResponseNotContains('Agenda Contact');
        $this->assertResponseNotContains('hidden recipient');
    }

    /**
     * @return void
     */
    public function testRemoveItemEndpointClearsBestowalCourtAssignment(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $this->createBestowalForGathering((int)$gathering->id);
        $agenda = $this->importBestowalsForGathering((int)$gathering->id);
        $item = $this->getTableLocator()->get('Awards.CourtAgendaItems')
            ->find()
            ->contain(['CourtAgendaSegments'])
            ->where(['CourtAgendaSegments.court_agenda_id' => $agenda->id])
            ->where(['bestowal_id IS NOT' => null])
            ->firstOrFail();

        $this->post('/awards/court-agendas/remove-item', [
            'item_id' => (int)$item->id,
            'return_segment_id' => (int)$item->court_agenda_segment_id,
        ]);

        $this->assertRedirectContains('/awards/court-agendas/gathering/' . $gathering->id);
        $this->assertFalse($this->getTableLocator()->get('Awards.CourtAgendaItems')->exists([
            'id' => (int)$item->id,
        ]));
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->get((int)$item->bestowal_id);
        $this->assertNull($bestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$bestowal->roaming_court);
    }

    private function createGatheringWithoutActivities(): object
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $source = $gatherings->find()->firstOrFail();
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'name' => 'Agenda setup guidance fixture', 'created_by' => self::ADMIN_MEMBER_ID,
            'branch_id' => $source->branch_id, 'gathering_type_id' => $source->gathering_type_id,
            'start_date' => $source->start_date, 'end_date' => $source->end_date, 'location' => 'Test Hall',
        ]));
        $this->getTableLocator()->get('GatheringsGatheringActivities')->deleteAll(['gathering_id' => $gathering->id]);

        return $gathering;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return \Awards\Model\Entity\CourtAgenda
     */
    private function importBestowalsForGathering(int $gatheringId)
    {
        $this->get('/awards/court-agendas/gathering/' . $gatheringId);
        $this->assertResponseOk();
        $agenda = $this->getTableLocator()->get('Awards.CourtAgendas')
            ->find()
            ->where(['gathering_id' => $gatheringId])
            ->firstOrFail();

        $this->post('/awards/court-agendas/import/' . $agenda->id);
        $this->assertRedirect(['controller' => 'CourtAgendas', 'action' => 'gathering', $gatheringId]);

        return $agenda;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return void
     */
    private function createBestowalForGathering(int $gatheringId, ?int $awardId = null): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'start_date'])
            ->where(['id' => $gatheringId])
            ->firstOrFail();
        $awardQuery = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id']);
        if ($awardId !== null) {
            $awardQuery->where(['id' => $awardId]);
        }
        $award = $awardQuery->firstOrFail();
        $scheduledActivity = $this->createScheduledActivityForAward(
            $gatheringId,
            (int)$award->id,
            $gathering->start_date,
        );

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gatheringId,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'state' => 'Court Scheduled',
            'status' => 'Scheduling',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
            'herald_notes' => 'Speak clearly.',
            'noble_notes' => 'Noble court secret.',
            'call_into_court' => 'With Notice',
            'court_availability' => 'Evening',
            'person_to_notify' => 'Agenda Contact',
        ]));
    }

    /**
     * @param int $memberId Member ID.
     * @param int $branchId Permission assignment branch ID.
     * @param bool $canViewBestowals Whether to grant normal bestowal read permission.
     * @return void
     */
    private function grantCourtAgendaManagement(
        int $memberId,
        int $branchId,
        bool $canViewBestowals = true,
    ): void {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Test Court Agenda Management ' . uniqid(),
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
        ]));

        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        foreach (['canGathering', 'canEdit', 'canPrintAgenda'] as $policyMethod) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'Awards\\Policy\\CourtAgendaPolicy',
                'policy_method' => $policyMethod,
            ]));
        }
        $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
            'permission_id' => (int)$permission->id,
            'policy_class' => 'Awards\\Policy\\BestowalPolicy',
            'policy_method' => 'canAccessHeraldNotes',
        ]));
        if ($canViewBestowals) {
            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'Awards\\Policy\\BestowalPolicy',
                'policy_method' => 'canView',
            ]));
        }

        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Test Court Agenda Manager ' . uniqid(),
        ]));
        $connection = $roles->getConnection();
        $connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
             VALUES (?, ?, NOW(), ?)',
            [(int)$role->id, (int)$permission->id, self::ADMIN_MEMBER_ID],
        );
        $connection->execute(
            'INSERT INTO member_roles
             (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type,
              created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)',
            [
                $memberId,
                (int)$role->id,
                $branchId,
                '2020-01-01 00:00:00',
                '2100-01-01',
                self::ADMIN_MEMBER_ID,
                'Direct Grant',
                self::ADMIN_MEMBER_ID,
                self::ADMIN_MEMBER_ID,
            ],
        );
        Cache::clearGroup('security');
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @param int $awardId Award ID.
     * @param \Cake\I18n\DateTime $startDate Gathering start date.
     * @param int|null $activityId Optional activity type.
     * @return \App\Model\Entity\GatheringScheduledActivity
     */
    private function createScheduledActivityForAward(
        int $gatheringId,
        int $awardId,
        DateTime $startDate,
        ?int $activityId = null,
    ): GatheringScheduledActivity {
        $activity = $this->getTableLocator()->get('GatheringActivities')
            ->find()
            ->where($activityId === null ? [] : ['id' => $activityId])
            ->firstOrFail();
        $awardActivities = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        if (!$awardActivities->exists(['award_id' => $awardId, 'gathering_activity_id' => $activity->id])) {
            $awardActivities->saveOrFail($awardActivities->newEntity([
                'award_id' => $awardId,
                'gathering_activity_id' => $activity->id,
            ]));
        }

        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivities->newEntity([
            'gathering_id' => $gatheringId,
            'gathering_activity_id' => $activity->id,
            'start_datetime' => (clone $startDate)->modify('+1 hour'),
            'end_datetime' => (clone $startDate)->modify('+2 hours'),
            'has_end_time' => true,
            'display_title' => 'Court Session',
            'description' => 'Court Session description.',
            'pre_register' => false,
            'is_other' => false,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $scheduledActivities->saveOrFail($scheduledActivity);
    }
}
