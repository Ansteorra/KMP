<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\GridSubscriptionService;
use App\Services\Security\RequestRateLimiter;
use Cake\Http\Response;
use InvalidArgumentException;

/** Opt-in and cancellation are limited to the authenticated member's own subscriptions. */
class GridSubscriptionsController extends AppController
{
    /** @inheritDoc */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'sample');
    }

    /** List only the authenticated member’s subscriptions. */
    public function index(): ?Response
    {
        $this->request->allowMethod(['get']);
        if ($this->request->getHeaderLine('Turbo-Frame') !== 'email-subscriptions') {
            return $this->redirect([
                'controller' => 'Members', 'action' => 'view',
                $this->request->getAttribute('identity')->getIdentifier(),
                '?' => ['emailSubscriptions' => '1'],
            ]);
        }
        $query = $this->Authorization->applyScope($this->fetchTable('GridSubscriptions')->find(), 'index');
        $this->set('subscriptions', $query->orderBy(['created' => 'DESC'])->all());

        return null;
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

    /** Send a bounded one-time sample only to the authenticated member's account email. */
    public function sample(GridSubscriptionService $subscriptions, RequestRateLimiter $limiter): Response
    {
        $this->request->allowMethod(['post']);
        $memberId = (int)$this->request->getAttribute('identity')->getIdentifier();
        $limit = $limiter->attempt(RequestRateLimiter::BUCKET_GRID_EMAIL_SAMPLE, (string)$memberId);
        if (!$limit->allowed) {
            return $this->response->withStatus(429)->withType('application/json')
                ->withHeader('Retry-After', (string)$limit->retryAfterSeconds)
                ->withStringBody(json_encode([
                    'success' => false, 'error' => 'Sample limit reached. Please wait a few minutes and try again.',
                ], JSON_THROW_ON_ERROR));
        }
        $uri = $this->request->getUri();
        try {
            $subscriptions->sendSample(
                $memberId,
                $this->request->getData(),
                $uri->getScheme() . '://' . $uri->getAuthority(),
            );

            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['success' => true], JSON_THROW_ON_ERROR));
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

        return $this->redirect(['action' => 'index'], 303);
    }
}
