<?php

declare(strict_types=1);

namespace App\Test\TestCase\Services\WorkflowEngine;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowVersion;
use Cake\TestSuite\TestCase;

/**
 * Unit tests for entity status-check helper methods.
 */
class EntityStateTest extends TestCase
{
    // =====================================================
    // WorkflowInstance state helpers
    // =====================================================

    public function testIsRunningTrue(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_RUNNING]);
        $this->assertTrue($instance->isRunning());
    }

    public function testIsRunningFalseWhenCompleted(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_COMPLETED]);
        $this->assertFalse($instance->isRunning());
    }

    public function testIsWaitingTrue(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_WAITING]);
        $this->assertTrue($instance->isWaiting());
    }

    public function testIsCompletedTrue(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_COMPLETED]);
        $this->assertTrue($instance->isCompleted());
    }

    public function testIsTerminalForCompleted(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_COMPLETED]);
        $this->assertTrue($instance->isTerminal());
    }

    public function testIsTerminalForFailed(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_FAILED]);
        $this->assertTrue($instance->isTerminal());
    }

    public function testIsTerminalForCancelled(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_CANCELLED]);
        $this->assertTrue($instance->isTerminal());
    }

    public function testIsTerminalFalseForRunning(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_RUNNING]);
        $this->assertFalse($instance->isTerminal());
    }

    public function testIsTerminalFalseForWaiting(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_WAITING]);
        $this->assertFalse($instance->isTerminal());
    }

    public function testIsTerminalFalseForPending(): void
    {
        $instance = new WorkflowInstance(['status' => WorkflowInstance::STATUS_PENDING]);
        $this->assertFalse($instance->isTerminal());
    }

    // =====================================================
    // WorkflowVersion state helpers
    // =====================================================

    public function testIsDraftTrue(): void
    {
        $version = new WorkflowVersion(['status' => WorkflowVersion::STATUS_DRAFT]);
        $this->assertTrue($version->isDraft());
    }

    public function testIsDraftFalseWhenPublished(): void
    {
        $version = new WorkflowVersion(['status' => WorkflowVersion::STATUS_PUBLISHED]);
        $this->assertFalse($version->isDraft());
    }

    public function testIsPublishedTrue(): void
    {
        $version = new WorkflowVersion(['status' => WorkflowVersion::STATUS_PUBLISHED]);
        $this->assertTrue($version->isPublished());
    }

    public function testIsPublishedFalseWhenArchived(): void
    {
        $version = new WorkflowVersion(['status' => WorkflowVersion::STATUS_ARCHIVED]);
        $this->assertFalse($version->isPublished());
    }

    // =====================================================
    // WorkflowApproval state helpers
    // =====================================================

    public function testIsPendingTrue(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_PENDING]);
        $this->assertTrue($approval->isPending());
    }

    public function testIsPendingFalseWhenApproved(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_APPROVED]);
        $this->assertFalse($approval->isPending());
    }

    public function testIsResolvedForApproved(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_APPROVED]);
        $this->assertTrue($approval->isResolved());
    }

    public function testIsResolvedForRejected(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_REJECTED]);
        $this->assertTrue($approval->isResolved());
    }

    public function testIsResolvedForExpired(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_EXPIRED]);
        $this->assertTrue($approval->isResolved());
    }

    public function testIsResolvedForCancelled(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_CANCELLED]);
        $this->assertTrue($approval->isResolved());
    }

    public function testIsResolvedFalseForPending(): void
    {
        $approval = new WorkflowApproval(['status' => WorkflowApproval::STATUS_PENDING]);
        $this->assertFalse($approval->isResolved());
    }

    public function testHasReachedThresholdTrue(): void
    {
        $approval = new WorkflowApproval([
            'approved_count' => 3,
            'required_count' => 2,
        ]);
        $this->assertTrue($approval->hasReachedThreshold());
    }

    public function testHasReachedThresholdExactly(): void
    {
        $approval = new WorkflowApproval([
            'approved_count' => 2,
            'required_count' => 2,
        ]);
        $this->assertTrue($approval->hasReachedThreshold());
    }

    public function testHasReachedThresholdFalse(): void
    {
        $approval = new WorkflowApproval([
            'approved_count' => 1,
            'required_count' => 3,
        ]);
        $this->assertFalse($approval->hasReachedThreshold());
    }
}
