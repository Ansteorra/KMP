<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\LiveSubscriptionService;
use App\Services\TurboConfig;
use App\Services\UiUpdateRegistry;
use Cake\Http\Exception\ServiceUnavailableException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

/** UI reads always repeat the normal resource policy; targets are server-owned. */
class UiUpdatesController extends AppController
{
    /** Issue only the resources authorized for this screen. */
    public function subscriptions(): Response
    {
        $context = $this->authorizedContext();
        $subscription = (new LiveSubscriptionService())->issue($this->request, $context['topics']);

        return $this->response->withType('application/json')->withHeader('Cache-Control', 'no-store, private')
            ->withStringBody(json_encode($subscription, JSON_THROW_ON_ERROR));
    }

    /** Reconcile through the registered screen's normal authorization. */
    public function refresh(): Response
    {
        $context = $this->authorizedContext();
        if ($this->request->getData('changes') === ['notes'] && ($context['notesPrivate'] ?? null) !== null) {
            $this->renderNotes($context);

            return $this->render()->withType('text/vnd.turbo-stream.html')
                ->withHeader('Cache-Control', 'no-store, private');
        }
        $this->set('contextUrl', $context['url']);
        $this->viewBuilder()->disableAutoLayout()->setTemplatePath('element')->setTemplate('ui_invalidate');

        return $this->render()->withType('text/vnd.turbo-stream.html')
            ->withHeader('Cache-Control', 'no-store, private');
    }

    /** Render only a registered Notes list using the owning screen's privacy contract. */
    private function renderNotes(array $context): void
    {
        $alias = $context['alias'];
        $entity = $context['resource'];
        $identity = $this->request->getAttribute('identity');
        $viewPrivate = $context['notesPrivate'] !== false
            && $identity->can('viewPrivateNotes', $context['notesPrivate'] === 'table' ? $alias : $entity);
        $query = TableRegistry::getTableLocator()->get('Notes')->find()->contain(['Authors'])
            ->where(['entity_type' => $alias, 'entity_id' => $entity->get('id')]);
        if (!$viewPrivate) {
            $query->where(['private' => false]);
        }
        $this->set('notes', $query->orderByAsc('Notes.created')->all());
        $this->set('notesListId', 'notes-list-' . md5($alias . ':' . $entity->get('id')));
        $this->viewBuilder()->disableAutoLayout()->setTemplatePath('element')->setTemplate('ui_notes_refresh');
    }

    /** @return array<string, mixed> */
    private function authorizedContext(): array
    {
        $this->request->allowMethod(['post']);
        if (!TurboConfig::liveEnabled()) {
            throw new ServiceUnavailableException('Live updates are disabled.');
        }
        $context = UiUpdateRegistry::resolve($this->request, (string)$this->request->getData('context_url', ''));
        $this->Authorization->authorize($context['resource'], $context['action']);

        return $context;
    }
}
