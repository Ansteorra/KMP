<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\View\Cell;

use Activities\View\Cell\PermissionActivitiesCell;
use Cake\TestSuite\TestCase;

/**
 * Activities\View\Cell\PermissionActivitiesCell Test Case
 */
class PermissionActivitiesCellTest extends TestCase
{
    /**
     * Request mock
     *
     * @var \Cake\Http\ServerRequest|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $request;

    /**
     * Response mock
     *
     * @var \Cake\Http\Response|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $response;

    /**
     * Test subject
     *
     * @var \Activities\View\Cell\PermissionActivitiesCell
     */
    protected $PermissionActivities;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->getMockBuilder('Cake\Http\ServerRequest')->getMock();
        $this->response = $this->getMockBuilder('Cake\Http\Response')->getMock();
        $this->PermissionActivities = new PermissionActivitiesCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->PermissionActivities);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Activities\View\Cell\PermissionActivitiesCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
