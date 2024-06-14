<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Cell;

use App\View\Cell\NavigationCell;
use Cake\TestSuite\TestCase;

/**
 * App\View\Cell\NavigationCell Test Case
 */
class NavigationCellTest extends TestCase
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
     * @var \App\View\Cell\NavigationCell
     */
    protected $Navigation;

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
        $this->Navigation = new NavigationCell($this->request, $this->response);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Navigation);

        parent::tearDown();
    }

    /**
     * Test display method
     *
     * @return void
     * @uses \App\View\Cell\NavigationCell::display()
     */
    public function testDisplay(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
