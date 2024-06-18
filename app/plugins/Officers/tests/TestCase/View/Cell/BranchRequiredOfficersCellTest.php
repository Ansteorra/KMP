<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\View\Cell;

use Cake\TestSuite\TestCase;
use Officers\View\Cell\BranchRequiredOfficersCell;

/**
 * Officers\View\Cell\BranchRequiredOfficersCell Test Case
 */
class BranchRequiredOfficersCellTest extends TestCase
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
     * @var \Officers\View\Cell\BranchRequiredOfficersCell
     */
    protected $BranchRequiredOfficers;

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
        $this->BranchRequiredOfficers = new BranchRequiredOfficersCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->BranchRequiredOfficers);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Officers\View\Cell\BranchRequiredOfficersCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
