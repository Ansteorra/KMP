<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\GridSubscriptionService;
use Cake\Http\Response;
use InvalidArgumentException;

/** Opt-in and cancellation are limited to the authenticated member's own subscriptions. */
class GridSubscriptionsController extends AppController
{
    /** @inheritDoc */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add');
    }

    /** List only the authenticated member’s subscriptions. */
    public function index(): void
    {
        $query = $this->Authorization->applyScope($this->fetchTable('GridSubscriptions')->find(), 'index');
        $this->set('subscriptions', $query->orderBy(['created' => 'DESC'])->all());
    }

    /** Validate and create an opt-in subscription for the authenticated member. */
    public function add(GridSubscriptionService $subscriptions): Response
    {
        $this->request->allowMethod(['post']);
        $uri = $this->request->getUri();
        try {
            $subscription = $subscriptions->subscribe(
                (int)$this->request->getAttribute('identity')->getIdentifier(),
                $this->request->getData(),
                $uri->getScheme() . '://' . $uri->getAuthority(),
            );

            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true, 'id' => $subscription->id,
            ], JSON_THROW_ON_ERROR));
        } catch (InvalidArgumentException $exception) {
            return $this->response->withStatus(422)->withType('application/json')->withStringBody(json_encode([
                'success' => false, 'error' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));
        }
    }

    /** Authorize ownership and cancel a subscription, including queued deliveries. */
    public function delete(int $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $table = $this->fetchTable('GridSubscriptions');
        $subscription = $table->get($id);
        $this->Authorization->authorize($subscription, 'delete');
        $table->deleteOrFail($subscription);
        $this->Flash->success(__('Email subscription cancelled.'));

        return $this->redirect(['action' => 'index']);
    }
}
