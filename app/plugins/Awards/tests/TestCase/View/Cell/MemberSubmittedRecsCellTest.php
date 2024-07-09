<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\View\Cell;

use Awards\View\Cell\MemberSubmittedRecsCell;
use Cake\TestSuite\TestCase;

/**
 * Awards\View\Cell\MemberSubmittedRecsCell Test Case
 */
class MemberSubmittedRecsCellTest extends TestCase
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
     * @var \Awards\View\Cell\MemberSubmittedRecsCell
     */
    protected $MemberSubmittedRecs;

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
        $this->MemberSubmittedRecs = new MemberSubmittedRecsCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->MemberSubmittedRecs);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \Awards\View\Cell\MemberSubmittedRecsCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
