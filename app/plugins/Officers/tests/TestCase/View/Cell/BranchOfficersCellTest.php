<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use Officers\View\Cell\BranchOfficersCell;

/**
 * Officers\View\Cell\BranchOfficersCell Test Case
 */
class BranchOfficersCellTest extends TestCase
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
     * @var \Officers\View\Cell\BranchOfficersCell
     */
    protected $BranchOfficers;

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
        $this->BranchOfficers = new BranchOfficersCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->BranchOfficers);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Officers\View\Cell\BranchOfficersCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
