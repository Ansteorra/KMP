<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\KMP\TimezoneHelper;
use App\Model\Entity\EmailTemplate;
use App\Services\EmailTemplateRendererService;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use ReflectionClass;
use RuntimeException;

class EmailTemplateRendererServiceTest extends BaseTestCase
{
    protected EmailTemplateRendererService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->forceAppTimezone('America/Chicago');
        $this->service = new EmailTemplateRendererService();
    }

    protected function tearDown(): void
    {
        $this->forceAppTimezone(null);

        parent::tearDown();
    }

    private function forceAppTimezone(?string $timezone): void
    {
        $reflection = new ReflectionClass(TimezoneHelper::class);
        $property = $reflection->getProperty('appTimezoneCache');
        $property->setAccessible(true);
        $property->setValue(null, $timezone);
    }

    // =========================================
    // renderTemplate tests
    // =========================================

    public function testRenderTemplateBasicSubstitution(): void
    {
        $template = 'Hello {{name}}, welcome to {{place}}!';
        $vars = ['name' => 'Alice', 'place' => 'KMP'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Hello Alice, welcome to KMP!', $result);
    }

    public function testRenderTemplateDollarSyntax(): void
    {
        $template = 'Hello ${name}, welcome to ${place}!';
        $vars = ['name' => 'Bob', 'place' => 'Ansteorra'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Hello Bob, welcome to Ansteorra!', $result);
    }

    public function testRenderTemplateMixedSyntax(): void
    {
        $template = '{{greeting}} ${name}!';
        $vars = ['greeting' => 'Hello', 'name' => 'Charlie'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Hello Charlie!', $result);
    }

    public function testRenderTemplateUnusedVarsIgnored(): void
    {
        $template = 'Hello {{name}}!';
        $vars = ['name' => 'Alice', 'unused' => 'value'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Hello Alice!', $result);
    }

    public function testRenderTemplateMissingVarsNotReplaced(): void
    {
        $template = 'Hello {{name}}, your role is {{role}}!';
        $vars = ['name' => 'Alice'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('{{role}}', $result);
    }

    public function testRenderTemplateEmptyVars(): void
    {
        $template = 'No variables here.';
        $result = $this->service->renderTemplate($template, []);
        $this->assertEquals('No variables here.', $result);
    }

    public function testRenderTemplateNullValue(): void
    {
        $template = 'Value is: {{val}}';
        $vars = ['val' => null];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Value is: ', $result);
    }

    public function testRenderTemplateFormatsDateTimeInKingdomTimezone(): void
    {
        $template = 'Expires: {{expires_on}}';
        $vars = ['expires_on' => new DateTime('2026-06-02T20:00:00Z')];

        $result = $this->service->renderTemplate($template, $vars);

        $this->assertSame('Expires: June 2, 2026 3:00 PM CDT', $result);
    }

    public function testRenderSubjectFormatsSchemaDateTimeStringInKingdomTimezone(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Expires: {{expires_on}}';
        $template->variables_schema = [
            ['name' => 'expires_on', 'type' => 'date_time'],
        ];

        $result = $this->service->renderSubject($template, [
            'expires_on' => '2026-06-02T20:00:00+00:00',
        ]);

        $this->assertSame('Expires: June 2, 2026 3:00 PM CDT', $result);
    }

    public function testRenderSubjectFormatsSchemaDateStringInKingdomTimezone(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Event date: {{event_date}}';
        $template->variables_schema = [
            ['name' => 'event_date', 'type' => 'date'],
        ];

        $result = $this->service->renderSubject($template, [
            'event_date' => '2026-06-02T20:00:00+00:00',
        ]);

        $this->assertSame('Event date: June 2, 2026', $result);
    }

    public function testRenderTemplateBoolValues(): void
    {
        $template = '{{active}} and {{inactive}}';
        $vars = ['active' => true, 'inactive' => false];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Yes and No', $result);
    }

    public function testRenderTemplateArrayValue(): void
    {
        $template = 'Items: {{items}}';
        $vars = ['items' => ['apple', 'banana', 'cherry']];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Items: apple, banana, cherry', $result);
    }

    // =========================================
    // Conditional rendering tests
    // =========================================

    public function testConditionalEqualityTrue(): void
    {
        $template = '{{#if status == "Approved"}}Approved content{{/if}}';
        $vars = ['status' => 'Approved'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Approved content', $result);
    }

    public function testConditionalEqualityFalse(): void
    {
        $template = '{{#if status == "Approved"}}Approved content{{/if}}';
        $vars = ['status' => 'Pending'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('', $result);
    }

    public function testConditionalNotEqual(): void
    {
        $template = '{{#if status != "Declined"}}Not declined{{/if}}';
        $vars = ['status' => 'Approved'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Not declined', $result);
    }

    public function testConditionalOrOperator(): void
    {
        $template = '{{#if status == "Approved" || status == "Pending"}}Active{{/if}}';
        $vars = ['status' => 'Pending'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Active', $result);
    }

    public function testConditionalAndOperator(): void
    {
        $template = '{{#if role == "Admin" && active == "Yes"}}Full access{{/if}}';
        $vars = ['role' => 'Admin', 'active' => true];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Full access', $result);
    }

    public function testConditionalAndOperatorFails(): void
    {
        $template = '{{#if role == "Admin" && active == "Yes"}}Full access{{/if}}';
        $vars = ['role' => 'Admin', 'active' => false];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('', $result);
    }

    public function testConditionalWithVariableSubstitution(): void
    {
        $template = '{{#if status == "Approved"}}Welcome {{name}}!{{/if}}';
        $vars = ['status' => 'Approved', 'name' => 'Alice'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Welcome Alice!', $result);
    }

    public function testConditionalBareVariableRendersWhenUseful(): void
    {
        $template = '{{#if awardReason}}Reason: {{awardReason}}{{/if}}';
        $vars = ['awardReason' => 'Long service'];
        $result = $this->service->renderTemplate($template, $vars);
        $this->assertEquals('Reason: Long service', $result);
    }

    public function testConditionalBareVariableSkipsWhenMissingOrEmpty(): void
    {
        $template = '{{#if awardReason}}Reason: {{awardReason}}{{/if}}';

        $this->assertSame('', $this->service->renderTemplate($template, []));
        $this->assertSame('', $this->service->renderTemplate($template, ['awardReason' => '']));
        $this->assertSame('', $this->service->renderTemplate($template, ['awardReason' => null]));
        $this->assertSame('', $this->service->renderTemplate($template, ['awardReason' => false]));
        $this->assertSame('', $this->service->renderTemplate($template, ['awardReason' => []]));
    }

    // =========================================
    // renderSubject tests
    // =========================================

    public function testRenderSubject(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Warrant Issued: {{warrantName}}';
        $vars = ['warrantName' => 'Kingdom Seneschal'];
        $result = $this->service->renderSubject($template, $vars);
        $this->assertEquals('Warrant Issued: Kingdom Seneschal', $result);
    }

    // =========================================
    // renderHtml tests
    // =========================================

    public function testRenderHtmlReturnsNullForEmptyTemplate(): void
    {
        $template = new EmailTemplate();
        $template->html_template = '';
        $result = $this->service->renderHtml($template, []);
        $this->assertNull($result);
    }

    public function testRenderHtmlReturnsWrappedHtml(): void
    {
        $template = new EmailTemplate();
        $template->id = 1;
        $template->html_template = '**Bold text** and {{name}}';
        $result = $this->service->renderHtml($template, ['name' => 'Alice']);
        $this->assertNotNull($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('<strong>Bold text</strong>', $result);
        $this->assertStringContainsString('Alice', $result);
    }

    // =========================================
    // renderHtmlBody tests
    // =========================================

    public function testRenderHtmlBodyReturnsNullForEmptyTemplate(): void
    {
        $template = new EmailTemplate();
        $template->html_template = '';
        $result = $this->service->renderHtmlBody($template, []);
        $this->assertNull($result);
    }

    public function testRenderHtmlBodyReturnsBodyOnly(): void
    {
        $template = new EmailTemplate();
        $template->html_template = '**Hello** {{name}}';
        $result = $this->service->renderHtmlBody($template, ['name' => 'Bob']);
        $this->assertNotNull($result);
        $this->assertStringContainsString('<strong>Hello</strong>', $result);
        $this->assertStringContainsString('Bob', $result);
        // Should NOT contain full HTML wrapper
        $this->assertStringNotContainsString('<!DOCTYPE html>', $result);
    }

    // =========================================
    // renderText tests
    // =========================================

    public function testRenderTextReturnsNullForEmptyTemplate(): void
    {
        $template = new EmailTemplate();
        $template->text_template = '';
        $result = $this->service->renderText($template, []);
        $this->assertNull($result);
    }

    public function testRenderTextSubstitutesVariables(): void
    {
        $template = new EmailTemplate();
        $template->text_template = 'Dear {{name}}, your warrant {{warrant}} has been issued.';
        $vars = ['name' => 'Alice', 'warrant' => 'Kingdom Seneschal'];
        $result = $this->service->renderText($template, $vars);
        $this->assertEquals('Dear Alice, your warrant Kingdom Seneschal has been issued.', $result);
    }

    // =========================================
    // extractVariables tests
    // =========================================

    public function testExtractVariablesDoubleBrace(): void
    {
        $template = 'Hello {{name}}, your role is {{role}}.';
        $vars = $this->service->extractVariables($template);
        $this->assertContains('name', $vars);
        $this->assertContains('role', $vars);
    }

    public function testExtractVariablesDollarSyntax(): void
    {
        $template = 'Hello ${name}, from ${place}.';
        $vars = $this->service->extractVariables($template);
        $this->assertContains('name', $vars);
        $this->assertContains('place', $vars);
    }

    public function testExtractVariablesFromConditionals(): void
    {
        $template = '{{#if status == "Approved" && awardReason}}Done{{/if}} {{name}}';
        $vars = $this->service->extractVariables($template);
        $this->assertContains('status', $vars);
        $this->assertContains('awardReason', $vars);
        $this->assertContains('name', $vars);
        $this->assertNotContains('Approved', $vars);
    }

    public function testExtractVariablesDeduplicates(): void
    {
        $template = '{{name}} and {{name}} again';
        $vars = $this->service->extractVariables($template);
        $uniqueCount = count(array_filter($vars, fn($v) => $v === 'name'));
        $this->assertEquals(1, $uniqueCount, 'Duplicate variable names should be deduplicated');
    }

    public function testExtractVariablesEmptyTemplate(): void
    {
        $vars = $this->service->extractVariables('No variables here.');
        $this->assertEmpty($vars);
    }

    // =========================================
    // getMissingVariables tests
    // =========================================

    public function testGetMissingVariablesFindsAll(): void
    {
        $template = '{{name}} {{email}} {{role}}';
        $vars = ['name' => 'Alice'];
        $missing = $this->service->getMissingVariables($template, $vars);
        $this->assertContains('email', $missing);
        $this->assertContains('role', $missing);
        $this->assertNotContains('name', $missing);
    }

    public function testGetMissingVariablesReturnsEmptyWhenComplete(): void
    {
        $template = '{{name}} {{email}}';
        $vars = ['name' => 'Alice', 'email' => 'alice@test.com'];
        $missing = $this->service->getMissingVariables($template, $vars);
        $this->assertEmpty($missing);
    }

    // =========================================
    // textToHtml / htmlToText tests
    // =========================================

    public function testTextToHtmlEscapesAndWraps(): void
    {
        $text = "Hello <script>alert('xss')</script>\nNew line";
        $html = $this->service->textToHtml($text);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<br', $html);
    }

    public function testHtmlToTextStripsTagsAndDecodes(): void
    {
        $html = '<p>Hello <strong>world</strong> &amp; friends</p>';
        $text = $this->service->htmlToText($html);
        $this->assertStringNotContainsString('<p>', $text);
        $this->assertStringNotContainsString('<strong>', $text);
        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('world', $text);
        $this->assertStringContainsString('& friends', $text);
    }

    public function testHtmlToTextTrimsWhitespace(): void
    {
        $html = '   <p>  spaced  out  </p>   ';
        $text = $this->service->htmlToText($html);
        $this->assertEquals('spaced out', $text);
    }

    // =========================================
    // extractAllPlaceholders tests
    // =========================================

    public function testExtractAllPlaceholdersCombinesAllFields(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Dear {{name}}, your warrant {{warrantName}} is ready.';
        $template->text_template = 'Role: {{role}}';

        $placeholders = $this->service->extractAllPlaceholders($template);
        $this->assertContains('name', $placeholders);
        $this->assertContains('warrantName', $placeholders);
        $this->assertContains('role', $placeholders);
        $this->assertCount(3, $placeholders, 'Duplicate "name" should be deduplicated');
    }

    public function testExtractAllPlaceholdersHandlesNullFields(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        // html_template and text_template are null/empty

        $placeholders = $this->service->extractAllPlaceholders($template);
        $this->assertEquals(['name'], $placeholders);
    }

    // =========================================
    // validateForSend tests
    // =========================================

    public function testValidateForSendReturnsEmptyOnSuccess(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Dear {{name}}, your role is {{role}}.';

        $result = $this->service->validateForSend($template, ['name' => 'Alice', 'role' => 'Admin']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    public function testValidateForSendErrorsOnMissingPlaceholderValue(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Role: {{role}}';

        $result = $this->service->validateForSend($template, ['name' => 'Alice']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('role', $result['errors'][0]);
    }

    public function testValidateForSendErrorsOnMissingRequiredSchemaVar(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Body here.';
        $template->variables_schema = [
            ['name' => 'name', 'type' => 'string', 'required' => true],
            ['name' => 'requiredExtra', 'type' => 'string', 'required' => true],
        ];

        // 'requiredExtra' is required by schema but not provided and not in template
        $result = $this->service->validateForSend($template, ['name' => 'Alice']);
        $errorMessages = implode(' ', $result['errors']);
        $this->assertStringContainsString('requiredExtra', $errorMessages);
    }

    public function testValidateForSendSupportsAssociativeSchemaFormat(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Body here.';
        $template->variables_schema = [
            'name' => ['type' => 'string', 'required' => true],
            'requiredExtra' => ['type' => 'string', 'required' => true],
        ];

        $result = $this->service->validateForSend($template, ['name' => 'Alice']);
        $this->assertStringContainsString('requiredExtra', implode(' ', $result['errors']));
    }

    public function testValidateForSendDoesNotErrorOnConditionOnlyVariable(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = '{{#if status == "Approved"}}Done{{/if}}';

        $result = $this->service->validateForSend($template, ['name' => 'Alice']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateForSendNoErrorForOptionalSchemaVar(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Body.';
        $template->variables_schema = [
            ['name' => 'name', 'type' => 'string', 'required' => true],
            ['name' => 'optional', 'type' => 'string', 'required' => false],
        ];

        $result = $this->service->validateForSend($template, ['name' => 'Alice']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateForSendWarnsOnUndeclaredPlaceholder(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = '{{undeclared}} is here.';
        $template->variables_schema = [
            ['name' => 'name', 'type' => 'string', 'required' => false],
        ];

        $result = $this->service->validateForSend($template, ['name' => 'Alice', 'undeclared' => 'X']);
        $this->assertEmpty($result['errors']);
        $warningMessages = implode(' ', $result['warnings']);
        $this->assertStringContainsString('undeclared', $warningMessages);
    }

    public function testValidateForSendNoWarningWhenNoSchema(): void
    {
        // When variables_schema is empty there is no schema to drift against
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Body.';
        // variables_schema defaults to []

        $result = $this->service->validateForSend($template, ['name' => 'Alice']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    // =========================================
    // assertValidForSend tests
    // =========================================

    public function testAssertValidForSendPassesOnValidTemplate(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hi {{name}}';
        $template->html_template = 'Body.';

        // Should not throw
        $this->service->assertValidForSend($template, ['name' => 'Bob']);
        $this->assertTrue(true); // reached
    }

    public function testAssertValidForSendThrowsOnMissingVar(): void
    {
        $template = new EmailTemplate();
        $template->slug = 'test-slug';
        $template->subject_template = 'Hi {{name}}';
        $template->html_template = 'Role: {{role}}';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/role/');
        $this->service->assertValidForSend($template, ['name' => 'Bob']);
    }

    // =========================================
    // validateSchemaConsistency tests
    // =========================================

    public function testValidateSchemaConsistencyWarnsOnUnusedSchemaVar(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Body.';
        $template->variables_schema = [
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'neverUsed', 'type' => 'string'],
        ];

        $result = $this->service->validateSchemaConsistency($template);
        $this->assertEmpty($result['errors']);
        $warningMessages = implode(' ', $result['warnings']);
        $this->assertStringContainsString('neverUsed', $warningMessages);
    }

    public function testValidateSchemaConsistencyNoWarningWhenSchemaEmpty(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Body.';

        $result = $this->service->validateSchemaConsistency($template);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    public function testValidateSchemaConsistencyNoWarningWhenAllVarsUsed(): void
    {
        $template = new EmailTemplate();
        $template->subject_template = 'Hello {{name}}';
        $template->html_template = 'Role: {{role}}';
        $template->variables_schema = [
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'role', 'type' => 'string'],
        ];

        $result = $this->service->validateSchemaConsistency($template);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    // =========================================
    // preview tests
    // =========================================

    public function testPreviewReturnsAllParts(): void
    {
        $template = new EmailTemplate();
        $template->id = 1;
        $template->subject_template = 'Subject: {{name}}';
        $template->html_template = '**Hello** {{name}}';
        $template->text_template = 'Hello {{name}}';
        $template->available_vars = [
            ['name' => 'name', 'description' => 'Name'],
        ];

        $result = $this->service->preview($template, ['name' => 'TestUser']);
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('html', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertStringContainsString('TestUser', $result['subject']);
        $this->assertStringContainsString('TestUser', $result['text']);
    }

    public function testPreviewWithPlaceholders(): void
    {
        $template = new EmailTemplate();
        $template->id = 1;
        $template->subject_template = 'Subject: {{name}}';
        $template->html_template = '**Hello** {{name}}';
        $template->text_template = 'Hello {{name}}';
        $template->available_vars = [
            ['name' => 'name', 'description' => 'Name'],
        ];

        $result = $this->service->preview($template);
        $this->assertStringContainsString('[name]', $result['subject']);
    }
}
