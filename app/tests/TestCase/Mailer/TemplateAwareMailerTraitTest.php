<?php
declare(strict_types=1);

namespace App\Test\TestCase\Mailer;

use App\Mailer\TemplateAwareMailerTrait;
use App\Model\Entity\EmailTemplate;
use Cake\Mailer\Mailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemplateAwareMailerTraitTest extends TestCase
{
    public function testRenderFromDbValidatesRequiredSchemaVarsBeforeRendering(): void
    {
        $mailer = new class () extends Mailer {
            use TemplateAwareMailerTrait;

            public function exposeRenderFromDb(EmailTemplate $template, array $vars = []): array
            {
                $this->setViewVars($vars);

                return $this->renderFromDb($template);
            }
        };

        $template = new EmailTemplate([
            'slug' => 'member-registration-welcome',
            'subject_template' => 'Welcome {{memberScaName}}',
            'text_template' => 'Use {{passwordResetUrl}} to finish setup.',
            'variables_schema' => [
                'memberScaName' => ['type' => 'string', 'required' => true],
                'passwordResetUrl' => ['type' => 'string', 'required' => true],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('passwordResetUrl');

        $mailer->exposeRenderFromDb($template, ['memberScaName' => 'Test User']);
    }
}
