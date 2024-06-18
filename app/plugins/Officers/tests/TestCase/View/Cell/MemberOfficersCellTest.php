<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use Officers\View\Cell\MemberOfficersCell;

/**
 * Officers\View\Cell\MemberOfficersCell Test Case
 */
class MemberOfficersCellTest extends TestCase
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
     * @var \Officers\View\Cell\MemberOfficersCell
     */
    protected $MemberOfficers;

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
        $this->MemberOfficers = new MemberOfficersCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->MemberOfficers);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Officers\View\Cell\MemberOfficersCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
