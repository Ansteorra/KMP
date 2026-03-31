<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Model\Entity\EmailTemplate;
use App\Services\EmailTemplateRendererService;
use App\Test\TestCase\BaseTestCase;

class EmailTemplateRendererServiceTest extends BaseTestCase
{
    protected EmailTemplateRendererService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->service = new EmailTemplateRendererService();
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
        $template = '{{#if status == "Approved"}}Done{{/if}} {{name}}';
        $vars = $this->service->extractVariables($template);
        $this->assertContains('status', $vars);
        $this->assertContains('name', $vars);
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
