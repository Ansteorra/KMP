<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Event\EventManager;

class NavigationHistoryTest extends HttpIntegrationTestCase
{
    private array $history;
    private mixed $captureHistory;
    private mixed $recordedHistory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        $this->history = ['/gatherings/calendar', '/members/view/' . self::ADMIN_MEMBER_ID];
        $this->session(['pageStack' => $this->history, 'pageStackVersion' => 2]);
        $this->captureHistory = function ($event): void {
            $this->recordedHistory = $event->getSubject()->getRequest()->getSession()->read('pageStack');
        };
        EventManager::instance()->on('Controller.shutdown', $this->captureHistory);
    }

    protected function tearDown(): void
    {
        EventManager::instance()->off('Controller.shutdown', $this->captureHistory);
        parent::tearDown();
    }

    public function testRedirectDoesNotBecomeABackDestination(): void
    {
        $this->get('/grid-subscriptions');
        $this->assertRedirect();
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testFragmentIndexDoesNotResetOrAppendHistory(): void
    {
        $this->configRequest(['headers' => ['Turbo-Frame' => 'email-subscriptions']]);
        $this->get('/grid-subscriptions');
        $this->assertResponseOk();
        // Fragment sessions close early; inspect the captured in-request history.
        $this->assertSame($this->history, $this->viewVariable('pageStack'));
    }

    public function testAttendancePartialWithoutAjaxHeadersDoesNotEnterHistory(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $this->get('/gatherings/attendance-modal/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testBackgroundDocumentFetchDoesNotEnterHistory(): void
    {
        $this->configRequest(['headers' => ['Sec-Fetch-Dest' => 'empty', 'Accept' => '*/*']]);
        $this->get('/gatherings/calendar');
        $this->assertResponseOk();
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testFullPageTurboVisitStillRecordsTheDocument(): void
    {
        $this->configRequest(['headers' => [
            'Sec-Fetch-Dest' => 'empty', 'Accept' => 'text/html, application/xhtml+xml',
        ]]);
        $this->get('/gatherings/calendar');
        $this->assertResponseOk();
        $this->assertSame(['/gatherings/calendar'], $this->recordedHistory);
    }

    public function testErrorDoesNotEnterHistory(): void
    {
        $this->get('/gatherings/view/not-a-gathering');
        $this->assertResponseCode(404);
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testBackToAnEarlierDocumentTruncatesHistory(): void
    {
        $this->session(['pageStack' => [...$this->history, '/gatherings/all-gatherings']]);
        $this->get('/gatherings/calendar');
        $this->assertResponseOk();
        $this->assertSame(['/gatherings/calendar'], $this->recordedHistory);
    }

    public function testReloadDoesNotDuplicateTheCurrentDocument(): void
    {
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testCalendarDownloadDoesNotEnterHistory(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $this->get('/gatherings/download-calendar/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Disposition', 'attachment');
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testNoStackIndexDoesNotClearHistory(): void
    {
        $this->get('/members?nostack=1');
        $this->assertResponseOk();
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testHistoryIsBoundedAndPreservesQueryParameters(): void
    {
        $this->session(['pageStack' => array_map(fn($i) => '/members/view/' . $i, range(100, 149))]);
        $url = '/gatherings/calendar?year=2026&month=9';
        $this->get($url);
        $this->assertResponseOk();
        $this->assertCount(50, $this->recordedHistory);
        $this->assertSame('/members/view/101', $this->recordedHistory[0]);
        $this->assertSame($url, $this->recordedHistory[49]);
    }

    public function testPostDoesNotEnterHistory(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/grid-subscriptions/add', [
            'gridKey' => 'Core.actionItems.myTasks', 'name' => 'Navigation test',
            'intervalDays' => 1, 'query' => '',
        ]);
        $this->assertResponseOk();
        $this->assertSame($this->history, $this->recordedHistory);
    }

    public function testLegacyPollutedHistoryIsDiscardedOnNextDocument(): void
    {
        $this->session(['pageStackVersion' => null, 'pageStack' => ['/gatherings/attendance-modal/1']]);
        $this->get('/members/view/' . self::ADMIN_MEMBER_ID);
        $this->assertResponseOk();
        $this->assertSame(['/members/view/' . self::ADMIN_MEMBER_ID], $this->recordedHistory);
    }
}
