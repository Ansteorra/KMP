<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Model\Entity\EmailTemplate;
use App\Services\EmailTemplateRendererService;
use Cake\Log\Log;
use Cake\Mailer\Message;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Trait for using database-stored email templates
 * 
 * This trait intercepts the email rendering pipeline to use database templates
 * when available, falling back to file-based templates if not found.
 */
trait TemplateAwareMailerTrait
{
    use LocatorAwareTrait;

    /**
     * Override the render method to use database templates
     *
     * @param string $content Content
     * @return array
     */
    public function render(string $content = ''): array
    {
        Log::debug('TemplateAwareMailerTrait::render() called', [
            'mailer_class' => get_class($this),
            'current_action' => $this->getCurrentAction(),
            'content_length' => strlen($content),
        ]);

        $dbTemplate = $this->getDbTemplate();

        Log::debug('Database template lookup result', [
            'template_found' => $dbTemplate !== null,
            'is_active' => $dbTemplate !== null ? $dbTemplate->is_active : null,
            'template_id' => $dbTemplate !== null ? $dbTemplate->id : null,
        ]);

        // Only use database template if it exists AND is active
        // Otherwise fall back to file-based templates
        if ($dbTemplate !== null && $dbTemplate->is_active) {
            Log::debug('Using database template for rendering (template is active)');
            $result = $this->renderFromDb($dbTemplate, $content);
            Log::debug('render() returning - HTML: ' . strlen($result[Message::MESSAGE_HTML] ?? '') . ' chars, TEXT: ' . strlen($result[Message::MESSAGE_TEXT] ?? '') . ' chars');

            // IMPORTANT: Also set the message body directly on the message object
            // This ensures the content is available when the email is sent
            $message = $this->getMessage();
            if (!empty($result[Message::MESSAGE_HTML])) {
                $message->setBodyHtml($result[Message::MESSAGE_HTML]);
            }
            if (!empty($result[Message::MESSAGE_TEXT])) {
                $message->setBodyText($result[Message::MESSAGE_TEXT]);
            }

            return $result;
        }

        // Fall back to default file-based rendering
        // This happens when:
        // 1. No database template exists for this mailer/action
        // 2. Database template exists but is marked as inactive
        if ($dbTemplate !== null && !$dbTemplate->is_active) {
            Log::debug('Database template found but inactive - falling back to file-based template');
        } else {
            Log::debug('No database template found - falling back to file-based template');
        }
        // Ensure template name is set correctly (convert camelCase to snake_case)
        $action = $this->getCurrentAction();
        if ($action !== null && empty($this->viewBuilder()->getTemplate())) {
            $templateName = \Cake\Utility\Inflector::underscore($action);
            $this->viewBuilder()->setTemplate($templateName);
        }

        return parent::render($content);
    }

    /**
     * Render email from database template
     *
     * @param \App\Model\Entity\EmailTemplate $template Database template
     * @param string $content Fallback content
     * @return array
     */
    protected function renderFromDb(EmailTemplate $template, string $content = ''): array
    {
        $renderer = new EmailTemplateRendererService();
        $vars = $this->viewBuilder()->getVars();

        // Log what variables we have
        Log::debug('Rendering email from database template - vars: ' . json_encode(array_keys($vars)));
        Log::debug('Rendering email from database template - var count: ' . count($vars));

        $result = [Message::MESSAGE_HTML => '', Message::MESSAGE_TEXT => ''];

        // Render subject
        $subject = $renderer->renderSubject($template, $vars);
        if (!empty($subject)) {
            $this->setSubject($subject);
        }

        // Render HTML version if available
        $html = $renderer->renderHtml($template, $vars);
        if ($html !== null) {
            $result[Message::MESSAGE_HTML] = $html;
        }

        // Render text version if available
        $text = $renderer->renderText($template, $vars);
        if ($text !== null) {
            $result[Message::MESSAGE_TEXT] = $text;
        }

        // If only one format is provided, generate the other
        if (empty($result[Message::MESSAGE_TEXT]) && !empty($result[Message::MESSAGE_HTML])) {
            // Generate text from HTML body (not the wrapped version with CSS)
            $htmlBody = $renderer->renderHtmlBody($template, $vars);
            $result[Message::MESSAGE_TEXT] = $renderer->htmlToText($htmlBody);
        } elseif (empty($result[Message::MESSAGE_HTML]) && !empty($result[Message::MESSAGE_TEXT])) {
            $result[Message::MESSAGE_HTML] = $renderer->textToHtml($result[Message::MESSAGE_TEXT]);
        }

        // IMPORTANT: Set email format to 'both' for multipart (HTML + text) or 'html' for HTML only
        // This tells CakePHP to send as text/html instead of text/plain
        if (!empty($result[Message::MESSAGE_HTML]) && !empty($result[Message::MESSAGE_TEXT])) {
            $this->setEmailFormat('both');
            Log::debug('Email format set to: both (multipart)');
        } elseif (!empty($result[Message::MESSAGE_HTML])) {
            $this->setEmailFormat('html');
            Log::debug('Email format set to: html');
        } else {
            $this->setEmailFormat('text');
            Log::debug('Email format set to: text');
        }

        // Log template usage for debugging
        Log::debug('Email rendered - HTML length: ' . strlen($result[Message::MESSAGE_HTML] ?? ''));
        Log::debug('Email rendered - TEXT length: ' . strlen($result[Message::MESSAGE_TEXT] ?? ''));
        Log::debug('Email rendered - HTML preview: ' . substr($result[Message::MESSAGE_HTML] ?? '', 0, 200));
        Log::debug('Email rendered - Array keys: ' . json_encode(array_keys($result)));

        return $result;
    }

    /**
     * Get database template for current mailer and action
     *
     * @return \App\Model\Entity\EmailTemplate|null
     */
    protected function getDbTemplate(): ?EmailTemplate
    {
        $action = $this->getCurrentAction();

        Log::debug('getDbTemplate() called', [
            'mailer_class' => get_class($this),
            'action' => $action,
        ]);

        if (empty($action)) {
            Log::debug('No action found (empty), cannot load template');
            return null;
        }

        try {
            /** @var \App\Model\Table\EmailTemplatesTable $templatesTable */
            $templatesTable = $this->fetchTable('EmailTemplates');
            $className = get_class($this);

            Log::debug('Searching for template', [
                'mailer_class' => $className,
                'action' => $action,
            ]);

            $template = $templatesTable->findForMailer($className, $action);

            Log::debug('Template search result', [
                'found' => $template !== null,
                'template_id' => $template !== null ? $template->id : null,
            ]);

            return $template;
        } catch (\Exception $e) {
            Log::error('Failed to fetch email template from database', [
                'mailer_class' => get_class($this),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the current action being called
     * This is determined by looking at the backtrace
     *
     * @return string|null
     */
    protected function getCurrentAction(): ?string
    {
        // Try to get from viewBuilder template
        $template = $this->viewBuilder()->getTemplate();
        if (!empty($template)) {
            return $template;
        }

        // Analyze backtrace to find the calling method
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($backtrace as $trace) {
            if (!isset($trace['class']) || !isset($trace['function'])) {
                continue;
            }

            // Look for a method in this class that's not magic or internal
            if ($trace['class'] === get_class($this)) {
                $function = $trace['function'];

                // Skip constructor, magic methods, and trait methods
                if (
                    $function === '__construct' ||
                    str_starts_with($function, '__') ||
                    str_starts_with($function, '_') ||
                    $function === 'render' ||
                    $function === 'renderFromDb' ||
                    $function === 'getDbTemplate' ||
                    $function === 'getCurrentAction'
                ) {
                    continue;
                }

                return $function;
            }
        }

        return null;
    }
}
