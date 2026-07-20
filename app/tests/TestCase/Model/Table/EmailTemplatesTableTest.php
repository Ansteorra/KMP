<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\EmailTemplate;
use App\Model\Table\EmailTemplatesTable;
use App\Test\TestCase\BaseTestCase;

/**
 * EmailTemplatesTable Test Case
 */
class EmailTemplatesTableTest extends BaseTestCase
{
    protected EmailTemplatesTable $EmailTemplates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $config = $this->getTableLocator()->exists('EmailTemplates')
            ? []
            : ['className' => EmailTemplatesTable::class];
        $this->EmailTemplates = $this->getTableLocator()->get('EmailTemplates', $config);
    }

    protected function tearDown(): void
    {
        unset($this->EmailTemplates);
        $this->getTableLocator()->clear();
        parent::tearDown();
    }

    public function testDisplayNamePrefersName(): void
    {
        $template = new EmailTemplate(['name' => 'My Template', 'slug' => 'my-template']);
        $this->assertSame('My Template', $template->display_name);
    }

    public function testDisplayNameFallsBackToSlug(): void
    {
        $template = new EmailTemplate(['slug' => 'warrant-issued']);
        $this->assertSame('warrant-issued', $template->display_name);
    }

    public function testDisplayNameDefaultsToUnnamed(): void
    {
        $template = new EmailTemplate([]);
        $this->assertSame('(unnamed template)', $template->display_name);
    }

    public function testIsWorkflowNativeReturnsTrueWhenSlugSet(): void
    {
        $template = new EmailTemplate(['slug' => 'warrant-issued']);
        $this->assertTrue($template->is_workflow_native);
    }

    public function testIsWorkflowNativeReturnsFalseWithoutSlug(): void
    {
        $template = new EmailTemplate([]);
        $this->assertFalse($template->is_workflow_native);
    }

    public function testVariablesSchemaGetterReturnsArray(): void
    {
        $template = new EmailTemplate();
        $template->variables_schema = [['name' => 'recipientName', 'type' => 'string', 'required' => true]];

        $this->assertSame('recipientName', $template->variables_schema[0]['name']);
    }

    public function testVariablesSchemaGetterReturnsEmptyArrayForNull(): void
    {
        $template = new EmailTemplate();
        $this->assertSame([], $template->variables_schema);
    }

    public function testVariablesSchemaSetterDecodesJsonString(): void
    {
        $template = new EmailTemplate();
        $template->variables_schema = '[{"name":"foo","type":"string"}]';

        $this->assertSame('foo', $template->variables_schema[0]['name']);
    }

    public function testVariablesSchemaNormalizesAssociativeFormat(): void
    {
        $template = new EmailTemplate();
        $template->variables_schema = [
            'memberScaName' => ['type' => 'string', 'required' => true],
            'awardName' => ['type' => 'string'],
        ];

        $this->assertSame('memberScaName', $template->variables_schema[0]['name']);
        $this->assertTrue($template->variables_schema[0]['required']);
        $this->assertSame('awardName', $template->variables_schema[1]['name']);
    }

    public function testAvailableVarsNormalizesLegacyStringList(): void
    {
        $template = new EmailTemplate();
        $template->available_vars = ['memberScaName', 'memberViewUrl'];

        $this->assertSame('memberScaName', $template->available_vars[0]['name']);
        $this->assertSame('memberViewUrl', $template->available_vars[1]['name']);
    }

    public function testAvailableVarsNormalizesAssociativeFormat(): void
    {
        $template = new EmailTemplate();
        $template->available_vars = [
            'memberScaName' => 'Member SCA Name',
            'memberViewUrl' => ['description' => 'Member profile URL'],
        ];

        $this->assertSame('memberScaName', $template->available_vars[0]['name']);
        $this->assertSame('Member SCA Name', $template->available_vars[0]['description']);
        $this->assertSame('memberViewUrl', $template->available_vars[1]['name']);
        $this->assertSame('Member profile URL', $template->available_vars[1]['description']);
    }

    public function testValidationRequiresSlug(): void
    {
        $template = $this->EmailTemplates->newEntity([
            'subject_template' => 'Test subject',
            'html_template' => 'Body content',
            'is_active' => true,
        ]);

        $this->assertArrayHasKey('slug', $template->getErrors());
    }

    public function testValidationSlugMustBeLowercaseHyphenated(): void
    {
        $template = $this->EmailTemplates->newEntity([
            'slug' => 'Invalid Slug!',
            'subject_template' => 'Test',
            'html_template' => 'content',
            'is_active' => true,
        ]);

        $this->assertArrayHasKey('slug', $template->getErrors());
    }

    public function testValidationSlugAcceptsValidFormat(): void
    {
        $template = $this->EmailTemplates->newEntity([
            'slug' => 'warrant-issued',
            'name' => 'Warrant Issued',
            'subject_template' => 'Your warrant has been issued',
            'html_template' => 'Content here',
            'is_active' => true,
        ]);

        $this->assertArrayNotHasKey('slug', $template->getErrors());
    }

    public function testUniqueSlugRuleRejectsDuplicateGlobalSlug(): void
    {
        $first = $this->EmailTemplates->newEntity([
            'slug' => 'duplicate-slug',
            'subject_template' => 'First',
            'html_template' => 'Body',
            'is_active' => true,
        ]);
        $this->assertNotFalse($this->EmailTemplates->save($first));

        $second = $this->EmailTemplates->newEntity([
            'slug' => 'duplicate-slug',
            'subject_template' => 'Second',
            'html_template' => 'Body',
            'is_active' => true,
        ]);

        $this->assertFalse($this->EmailTemplates->save($second));
        $this->assertArrayHasKey('slug', $second->getErrors());
    }

    public function testSchemaRegistersVariablesSchemaAsJson(): void
    {
        $schema = $this->EmailTemplates->getSchema();
        $this->assertSame('json', $schema->getColumnType('variables_schema'));
    }

    public function testSchemaRegistersAvailableVarsAsJson(): void
    {
        $schema = $this->EmailTemplates->getSchema();
        $this->assertSame('json', $schema->getColumnType('available_vars'));
    }
}
