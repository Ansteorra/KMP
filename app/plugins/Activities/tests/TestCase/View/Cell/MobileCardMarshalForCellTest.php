<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\View\Cell;

use Activities\View\Cell\MobileCardMarshalForCell;
use Cake\TestSuite\TestCase;

/**
 * Activities\View\Cell\MobileCardMarshalForCell Test Case
 */
class MobileCardMarshalForCellTest extends TestCase
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
     * @var \Activities\View\Cell\MobileCardMarshalForCell
     */
    protected $MobileCardMarshalFor;

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
        $this->MobileCardMarshalFor = new MobileCardMarshalForCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->MobileCardMarshalFor);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Activities\View\Cell\MobileCardMarshalForCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
