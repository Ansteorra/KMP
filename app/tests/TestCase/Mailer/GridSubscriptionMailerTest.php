<?php
declare(strict_types=1);

namespace App\Test\TestCase\Mailer;

use AddGridEmailSummaryTemplate;
use App\KMP\StaticHelpers;
use App\Mailer\GridSubscriptionMailer;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;
use DOMDocument;
use DOMXPath;
use Migrations\Migration\Environment;
use RuntimeException;

class GridSubscriptionMailerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once ROOT . '/config/Migrations/20260921190000_AddGridEmailSummaryTemplate.php';
        $this->migration()->up();
        StaticHelpers::setAppSetting('Email.GridSubscriptionTemplate', 'grid-email-summary', 'string', true);
    }

    public function testMigrationCreatesMissingDefaults(): void
    {
        $tables = TableRegistry::getTableLocator();
        $tables->get('EmailTemplates')->deleteAll(['slug' => 'grid-email-summary']);
        $tables->get('AppSettings')->deleteAll(['name' => 'Email.GridSubscriptionTemplate']);
        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame(1, $tables->get('EmailTemplates')->find()->where(['slug' => 'grid-email-summary'])->count());
        $setting = $tables->get('AppSettings')->find()
            ->where(['name' => 'Email.GridSubscriptionTemplate'])->firstOrFail();
        $this->assertSame('grid-email-summary', $setting->value);
        $this->assertTrue($setting->required);
    }

    public function testDefaultRendersSamplesScheduledRowsAndEmptySamples(): void
    {
        foreach ([true, false] as $sample) {
            $mailer = new GridSubscriptionMailer();
            $mailer->summary('grid-sample@example.test', 'My pending work', $this->report($sample));
            $body = $mailer->render();
            $this->assertStringContainsString('My pending work', $mailer->getMessage()->getSubject());
            $this->assertSame($sample, str_contains($mailer->getMessage()->getSubject(), 'Sample:'));
            foreach (['html', 'text'] as $format) {
                $this->assertStringContainsString('Example task', $body[$format]);
                $this->assertStringContainsString('https://example.test/tasks', $body[$format]);
                $this->assertSame($sample, str_contains($body[$format], 'one-time sample'));
                $this->assertSame(!$sample, str_contains($body[$format], 'You subscribed'));
                $this->assertStringNotContainsString('{{', $body[$format]);
            }
            $this->assertStringContainsString('<table>', $body['html']);
        }
        $report = $this->report(true);
        $report['rows'] = [];
        $mailer = new GridSubscriptionMailer();
        $mailer->summary('grid-sample@example.test', 'Empty view', $report);
        $body = $mailer->render();
        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('No rows match this view right now.', $body[$format]);
        }
        $this->assertStringNotContainsString('<table>', $body['html']);
    }

    public function testSelectedKingdomTemplateControlsBothSampleAndScheduledMail(): void
    {
        $templates = TableRegistry::getTableLocator()->get('EmailTemplates');
        $template = $templates->newEntity([
            'slug' => 'kingdom-grid-summary-test', 'name' => 'Kingdom custom summary',
            'subject_template' => 'Kingdom {{summaryName}}',
            'html_template' => "Welcome from our kingdom\n\n{{resultsTable}}",
            'text_template' => "Kingdom digest\n\n{{resultsText}}",
            'variables_schema' => [], 'is_active' => true,
        ]);
        $templates->saveOrFail($template);
        StaticHelpers::setAppSetting('Email.GridSubscriptionTemplate', $template->slug);
        foreach ([true, false] as $sample) {
            $mailer = new GridSubscriptionMailer();
            $mailer->summary('grid-sample@example.test', 'Pending work', $this->report($sample));
            $body = $mailer->render();
            $this->assertSame('Kingdom Pending work', $mailer->getMessage()->getSubject());
            $this->assertStringContainsString('Welcome from our kingdom', $body['html']);
            $this->assertStringContainsString('Kingdom digest', $body['text']);
            $this->assertStringContainsString('Example task', $body['html']);
        }
    }

    public function testGridValuesCannotInjectHtmlMarkdownLinksImagesOrColumns(): void
    {
        $report = $this->report(true);
        $value = '<script>alert(1)</script> ![tracking](https://evil.test/img) [link](https://evil.test) | extra';
        $report['rows'] = [[$value]];
        $mailer = new GridSubscriptionMailer();
        $mailer->summary('grid-sample@example.test', 'Safety test', $report);
        $body = $mailer->render();
        $document = new DOMDocument();
        $document->loadHTML($body['html']);
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//script|//img|//a[text()="link"]')->length);
        $this->assertSame(1, $xpath->query('//tbody/tr/td')->length);
        $this->assertSame($value, $xpath->query('//tbody/tr/td')->item(0)->textContent);
    }

    public function testMigrationPreservesCustomizedTemplateAndSetting(): void
    {
        $templates = TableRegistry::getTableLocator()->get('EmailTemplates');
        $template = $templates->find()->where(['slug' => 'grid-email-summary'])->firstOrFail();
        $template->subject_template = 'Our customized subject';
        $template->is_active = false;
        $templates->saveOrFail($template);
        StaticHelpers::setAppSetting('Email.GridSubscriptionTemplate', 'kingdom-custom');
        $this->migration()->up();
        $this->migration()->down();
        $preserved = $templates->get($template->id);
        $this->assertSame('Our customized subject', $preserved->subject_template);
        $this->assertFalse($preserved->is_active);
        $this->assertSame('kingdom-custom', StaticHelpers::getAppSetting('Email.GridSubscriptionTemplate'));
        $this->assertSame(1, $templates->find()->where(['slug' => 'grid-email-summary'])->count());
    }

    public function testMissingSelectedTemplateFailsWithoutFallback(): void
    {
        StaticHelpers::setAppSetting('Email.GridSubscriptionTemplate', 'missing-grid-template');
        $this->expectException(RuntimeException::class);
        (new GridSubscriptionMailer())->summary('grid-sample@example.test', 'Pending work', $this->report(true));
    }

    public function testInactiveSelectedTemplateFailsWithoutFallback(): void
    {
        $templates = TableRegistry::getTableLocator()->get('EmailTemplates');
        $template = $templates->find()->where(['slug' => 'grid-email-summary'])->firstOrFail();
        $template->is_active = false;
        $templates->saveOrFail($template);
        $this->expectException(RuntimeException::class);
        (new GridSubscriptionMailer())->summary('grid-sample@example.test', 'Pending work', $this->report(false));
    }

    private function report(bool $sample): array
    {
        return [
            'sample' => $sample, 'label' => 'My To-Dos', 'headers' => ['Task'], 'rows' => [['Example task']],
            'url' => 'https://example.test/tasks', 'manageUrl' => 'https://example.test/subscriptions',
        ];
    }

    private function migration(): AddGridEmailSummaryTemplate
    {
        $environment = new Environment('grid-email-template-test', ['connection' => 'test']);

        return (new AddGridEmailSummaryTemplate(20260921190000))->setAdapter($environment->getAdapter());
    }
}
