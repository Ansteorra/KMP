<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * App\Controller\GatheringAttendancesController Test Case
 */
class GatheringAttendancesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testCalendarRsvpReturnsTurboStreamAfterSave(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'public_id' => 'rsvpmod1',
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'gathering_type_id' => 1,
            'name' => 'Calendar RSVP Modal Test',
            'start_date' => '2099-08-10 10:00:00',
            'end_date' => '2099-08-10 16:00:00',
            'timezone' => 'America/Chicago',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/gathering-attendances/add', [
            'gathering_id' => $gathering->id,
            'member_id' => self::ADMIN_MEMBER_ID,
            'page_context_url' => '/gatherings/calendar',
        ]);

        $this->assertResponseOk();
        $this->assertContentType('text/vnd.turbo-stream.html');
        $this->assertResponseContains('Your attendance has been registered.');
        $this->assertSame(
            1,
            $this->getTableLocator()->get('GatheringAttendances')
                ->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'member_id' => self::ADMIN_MEMBER_ID,
                ])
                ->count(),
        );
    }
}
