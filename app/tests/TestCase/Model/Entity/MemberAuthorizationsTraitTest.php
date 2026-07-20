<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Entity;

use Activities\Model\Entity\MemberAuthorizationsTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Tests for MemberAuthorizationsTrait.
 *
 * Uses a concrete stub class that consumes the trait so we can test
 * its methods in isolation without needing a full Member entity.
 */
class MemberAuthorizationsTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
    }

    public function testGetPendingApprovalsCountReturnsInteger(): void
    {
        // Mock the WorkflowApprovals table and query
        $query = $this->createMock(SelectQuery::class);
        $query->method('where')->willReturnSelf();
        $query->method('count')->willReturn(3);

        $table = $this->createMock(Table::class);
        $table->method('find')->willReturn($query);

        // Register the mock table
        TableRegistry::getTableLocator()->set('WorkflowApprovals', $table);

        $stub = new MemberAuthorizationsTraitStub(42);
        $count = $stub->getPendingApprovalsCount();

        $this->assertIsInt($count);
        $this->assertSame(3, $count);
    }

    public function testGetPendingApprovalsCountQueriesWorkflowApprovals(): void
    {
        $query = $this->createMock(SelectQuery::class);
        $query->expects($this->once())
            ->method('where')
            ->with([
                'approver_id' => 99,
                'status' => 'Pending',
            ])
            ->willReturnSelf();
        $query->method('count')->willReturn(0);

        $table = $this->createMock(Table::class);
        $table->expects($this->once())
            ->method('find')
            ->willReturn($query);

        TableRegistry::getTableLocator()->set('WorkflowApprovals', $table);

        $stub = new MemberAuthorizationsTraitStub(99);
        $count = $stub->getPendingApprovalsCount();

        $this->assertSame(0, $count);
    }

    public function testGetPendingApprovalsCountReturnsZeroWhenNone(): void
    {
        $query = $this->createMock(SelectQuery::class);
        $query->method('where')->willReturnSelf();
        $query->method('count')->willReturn(0);

        $table = $this->createMock(Table::class);
        $table->method('find')->willReturn($query);

        TableRegistry::getTableLocator()->set('WorkflowApprovals', $table);

        $stub = new MemberAuthorizationsTraitStub(1);
        $this->assertSame(0, $stub->getPendingApprovalsCount());
    }
}

/**
 * Stub class that uses MemberAuthorizationsTrait for testing.
 */
class MemberAuthorizationsTraitStub
{
    use MemberAuthorizationsTrait;

    public int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
