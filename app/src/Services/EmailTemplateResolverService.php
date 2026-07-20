<?php
declare(strict_types=1);

namespace App\Services;

use App\Exception\EmailTemplateNotFoundException;
use App\Model\Entity\EmailTemplate;
use App\Model\Table\EmailTemplatesTable;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Resolves active email templates by stable slug identity.
 *
 * Throws EmailTemplateNotFoundException (never returns null) so callers can rely
 * on a valid, active template or fail explicitly.
 *
 * @property \App\Model\Table\EmailTemplatesTable $EmailTemplates
 */
class EmailTemplateResolverService
{
    use LocatorAwareTrait;

    protected EmailTemplatesTable $emailTemplates;

    /**
     * @param \App\Model\Table\EmailTemplatesTable|null $emailTemplates Injected for testing; auto-fetched if null.
     */
    public function __construct(?EmailTemplatesTable $emailTemplates = null)
    {
        $this->emailTemplates = $emailTemplates ?? $this->fetchTable('EmailTemplates');
    }

    /**
     * Resolve an active template by slug.
     *
     * @param string $slug Stable template slug
     * @return \App\Model\Entity\EmailTemplate
     * @throws \App\Exception\EmailTemplateNotFoundException
     */
    public function resolveBySlug(string $slug): EmailTemplate
    {
        $template = $this->emailTemplates->findForSlug($slug);
        if ($template === null) {
            throw EmailTemplateNotFoundException::forSlug($slug);
        }

        return $template;
    }
}
