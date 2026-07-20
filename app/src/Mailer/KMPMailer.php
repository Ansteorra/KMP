<?php
declare(strict_types=1);

namespace App\Mailer;

use App\KMP\StaticHelpers;
use Cake\Mailer\Mailer;

class KMPMailer extends Mailer
{
    use TemplateAwareMailerTrait;

    /**
     * Send a DB-backed email template by slug or numeric template ID.
     *
     * @param string $to
     * @param string $_templateId
     * @param string|null $_replyTo
     * @param mixed ...$templateVars
     * @return void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException
     * @throws \RuntimeException
     */
    public function sendFromTemplate(string $to, string $_templateId, ?string $_replyTo = null, mixed ...$templateVars): void
    {
        /** @var \App\Model\Table\EmailTemplatesTable $templatesTable */
        $templatesTable = $this->getTableLocator()->get('EmailTemplates');

        if (is_numeric($_templateId)) {
            $template = $templatesTable->get((int)$_templateId);
        } else {
            $template = $templatesTable->findForSlug($_templateId);
            if ($template === null) {
                throw new \RuntimeException(
                    "Email template with slug '{$_templateId}' not found or is inactive",
                );
            }
        }

        $this->setTo($to)
            ->setFrom(StaticHelpers::getAppSetting('Email.SystemEmailFromAddress', 'site@test.com', null, true));

        if ($_replyTo) {
            $this->setReplyTo($_replyTo);
        }

        $this->_preloadedTemplate = $template;

        // Inject site-wide email variables as defaults so every DB-backed template
        // (workflow-sent via Core.SendEmail or manually sent) can reference them
        // without each caller or seed workflow mapping them explicitly. Per-send
        // values still win because they are merged on top of these defaults.
        $globalVars = [
            'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
        ];
        $this->setViewVars(array_merge($globalVars, $templateVars));
    }
}
