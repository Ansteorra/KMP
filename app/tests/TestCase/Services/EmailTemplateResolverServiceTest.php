<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Exception\EmailTemplateNotFoundException;
use App\Model\Table\EmailTemplatesTable;
use App\Services\EmailTemplateResolverService;
use App\Test\TestCase\BaseTestCase;

/**
 * Tests for EmailTemplateResolverService.
 */
class EmailTemplateResolverServiceTest extends BaseTestCase
{
    protected EmailTemplatesTable $EmailTemplates;
    protected EmailTemplateResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $config = $this->getTableLocator()->exists('EmailTemplates')
            ? []
            : ['className' => EmailTemplatesTable::class];
        $this->EmailTemplates = $this->getTableLocator()->get('EmailTemplates', $config);
        $this->resolver = new EmailTemplateResolverService($this->EmailTemplates);
    }

    protected function tearDown(): void
    {
        unset($this->EmailTemplates, $this->resolver);
        $this->getTableLocator()->clear();
        parent::tearDown();
    }

    private function saveTemplate(array $data): object
    {
        $defaults = [
            'subject_template' => 'Test subject',
            'html_template' => 'Test body',
            'is_active' => true,
        ];
        $entity = $this->EmailTemplates->newEntity(array_merge($defaults, $data));
        $result = $this->EmailTemplates->save($entity);
        $this->assertNotFalse($result, 'Fixture template failed to save: ' . json_encode($entity->getErrors()));

        return $result;
    }

    public function testResolveBySlugReturnsGlobalTemplate(): void
    {
        $this->saveTemplate(['slug' => 'test-global-template']);

        $template = $this->resolver->resolveBySlug('test-global-template');
        $this->assertSame('test-global-template', $template->slug);
    }

    public function testResolveBySlugThrowsWhenNotFound(): void
    {
        $this->expectException(EmailTemplateNotFoundException::class);
        $this->expectExceptionMessageMatches("/no active email template found for slug 'nonexistent-slug'/i");

        $this->resolver->resolveBySlug('nonexistent-slug');
    }

    public function testResolveBySlugThrowsWhenInactive(): void
    {
        $this->saveTemplate(['slug' => 'test-inactive-template', 'is_active' => false]);

        $this->expectException(EmailTemplateNotFoundException::class);
        $this->resolver->resolveBySlug('test-inactive-template');
    }

    public function testResolveBySlugExceptionMentionsSlug(): void
    {
        $this->expectException(EmailTemplateNotFoundException::class);
        $this->expectExceptionMessageMatches('/missing-slug/');

        $this->resolver->resolveBySlug('missing-slug');
    }
}
