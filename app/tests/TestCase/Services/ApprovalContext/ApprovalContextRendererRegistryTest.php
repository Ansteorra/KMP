<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\ApprovalContext;

use App\Model\Entity\WorkflowInstance;
use App\Services\ApprovalContext\ApprovalContext;
use App\Services\ApprovalContext\ApprovalContextRendererInterface;
use App\Services\ApprovalContext\ApprovalContextRendererRegistry;
use App\Test\TestCase\BaseTestCase;

class ApprovalContextRendererRegistryTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ApprovalContextRendererRegistry::clear();
    }

    protected function tearDown(): void
    {
        ApprovalContextRendererRegistry::clear();
        parent::tearDown();
    }

    /**
     * Create a WorkflowInstance entity with the given entity_type and entity_id.
     */
    private function makeInstance(?string $entityType = 'Members', ?int $entityId = 1): WorkflowInstance
    {
        return new WorkflowInstance([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => WorkflowInstance::STATUS_PENDING,
        ]);
    }

    /**
     * Create a mock renderer that responds to canRender with the given value
     * and returns the given ApprovalContext on render().
     */
    private function makeRenderer(bool $canRender, ?ApprovalContext $context = null): ApprovalContextRendererInterface
    {
        $renderer = $this->createMock(ApprovalContextRendererInterface::class);
        $renderer->method('canRender')->willReturn($canRender);

        if ($context !== null) {
            $renderer->method('render')->willReturn($context);
        }

        return $renderer;
    }

    public function testRegisterAndRender(): void
    {
        $expected = new ApprovalContext(
            title: 'Auth Request',
            description: 'Please approve this authorization',
            fields: [['label' => 'Type', 'value' => 'Heavy Fighting']],
            entityUrl: '/authorizations/view/42',
            icon: 'bi-shield-check',
            requester: 'Sir Test',
        );

        $renderer = $this->makeRenderer(true, $expected);

        ApprovalContextRendererRegistry::register('Authorizations', $renderer);

        $result = ApprovalContextRendererRegistry::render($this->makeInstance());

        $this->assertSame($expected, $result);
    }

    public function testDefaultContextWhenNoRendererMatches(): void
    {
        $renderer = $this->makeRenderer(false);
        ApprovalContextRendererRegistry::register('Nope', $renderer);

        $instance = $this->makeInstance('Awards', 99);
        $result = ApprovalContextRendererRegistry::render($instance);

        $this->assertStringContainsString('Awards', $result->getTitle());
        $this->assertStringContainsString('Awards', $result->getDescription());
        $this->assertSame('bi-question-circle', $result->getIcon());
        $this->assertNull($result->getEntityUrl());
        $this->assertNull($result->getRequester());
    }

    public function testFirstMatchingRendererWins(): void
    {
        $firstContext = new ApprovalContext(
            title: 'First',
            description: 'First renderer matched',
        );
        $secondContext = new ApprovalContext(
            title: 'Second',
            description: 'Second renderer matched',
        );

        $first = $this->makeRenderer(true, $firstContext);
        $second = $this->makeRenderer(true, $secondContext);

        ApprovalContextRendererRegistry::register('RendererA', $first);
        ApprovalContextRendererRegistry::register('RendererB', $second);

        $result = ApprovalContextRendererRegistry::render($this->makeInstance());

        $this->assertSame('First', $result->getTitle());
        $this->assertSame('First renderer matched', $result->getDescription());
    }

    public function testApprovalContextToArray(): void
    {
        $context = new ApprovalContext(
            title: 'Award Nomination',
            description: 'Nominate for Purple Fret',
            fields: [
                ['label' => 'Award', 'value' => 'Purple Fret'],
                ['label' => 'Nominee', 'value' => 'Jane Doe'],
            ],
            entityUrl: '/awards/view/7',
            icon: 'bi-trophy',
            requester: 'John Smith',
        );

        $array = $context->toArray();

        $this->assertSame('Award Nomination', $array['title']);
        $this->assertSame('Nominate for Purple Fret', $array['description']);
        $this->assertCount(2, $array['fields']);
        $this->assertSame('Purple Fret', $array['fields'][0]['value']);
        $this->assertSame('/awards/view/7', $array['entityUrl']);
        $this->assertSame('bi-trophy', $array['icon']);
        $this->assertSame('John Smith', $array['requester']);
    }

    public function testApprovalContextImmutability(): void
    {
        $context = new ApprovalContext(
            title: 'Test Title',
            description: 'Test Desc',
            fields: [['label' => 'Key', 'value' => 'Val']],
            entityUrl: '/test/1',
            icon: 'bi-star',
            requester: 'Tester',
        );

        $this->assertSame('Test Title', $context->getTitle());
        $this->assertSame('Test Desc', $context->getDescription());
        $this->assertCount(1, $context->getFields());
        $this->assertSame('/test/1', $context->getEntityUrl());
        $this->assertSame('bi-star', $context->getIcon());
        $this->assertSame('Tester', $context->getRequester());

        // Calling getters again returns the same values (readonly properties)
        $this->assertSame($context->getTitle(), $context->getTitle());
        $this->assertSame($context->getFields(), $context->getFields());

        // toArray is also consistent
        $this->assertSame($context->toArray(), $context->toArray());
    }

    public function testApprovalContextDefaults(): void
    {
        $context = new ApprovalContext(
            title: 'Minimal',
            description: 'Only required fields',
        );

        $this->assertSame([], $context->getFields());
        $this->assertNull($context->getEntityUrl());
        $this->assertSame('bi-question-circle', $context->getIcon());
        $this->assertNull($context->getRequester());
    }

    public function testUnregisterRemovesRenderer(): void
    {
        $context = new ApprovalContext(title: 'X', description: 'Y');
        $renderer = $this->makeRenderer(true, $context);

        ApprovalContextRendererRegistry::register('TestSource', $renderer);
        $this->assertTrue(ApprovalContextRendererRegistry::isRegistered('TestSource'));

        ApprovalContextRendererRegistry::unregister('TestSource');
        $this->assertFalse(ApprovalContextRendererRegistry::isRegistered('TestSource'));
    }

    public function testGetRegisteredSources(): void
    {
        $a = $this->makeRenderer(false);
        $b = $this->makeRenderer(false);

        ApprovalContextRendererRegistry::register('Alpha', $a);
        ApprovalContextRendererRegistry::register('Beta', $b);

        $sources = ApprovalContextRendererRegistry::getRegisteredSources();

        $this->assertContains('Alpha', $sources);
        $this->assertContains('Beta', $sources);
        $this->assertCount(2, $sources);
    }

    public function testClearRemovesAll(): void
    {
        ApprovalContextRendererRegistry::register('One', $this->makeRenderer(false));
        ApprovalContextRendererRegistry::register('Two', $this->makeRenderer(false));

        ApprovalContextRendererRegistry::clear();

        $this->assertEmpty(ApprovalContextRendererRegistry::getAllRenderers());
        $this->assertEmpty(ApprovalContextRendererRegistry::getRegisteredSources());
    }
}
