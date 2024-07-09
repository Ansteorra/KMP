<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\View\Cell;

use Awards\View\Cell\RecsForMemberCell;
use Cake\TestSuite\TestCase;

/**
 * Awards\View\Cell\RecsForMemberCell Test Case
 */
class RecsForMemberCellTest extends TestCase
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
     * @var \Awards\View\Cell\RecsForMemberCell
     */
    protected $RecsForMember;

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
        $this->RecsForMember = new RecsForMemberCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->RecsForMember);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Awards\View\Cell\RecsForMemberCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
