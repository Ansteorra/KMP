<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Model\Entity\ServicePrincipal;
use Cake\Event\EventInterface;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;

/**
 * Base API Controller - Foundation for all API endpoints
 *
 * Provides JSON-only responses, API-specific error handling, and 
 * service principal authentication support.
 */
abstract class ApiController extends AppController
{
    /**
     * Initialization hook.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // API controllers return JSON only
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Before filter - runs before each action.
     *
     * @param \Cake\Event\EventInterface $event Event
     * @return \Cake\Http\Response|null
     */
    public function beforeFilter(EventInterface $event): ?Response
    {
        $parentResponse = parent::beforeFilter($event);
        if ($parentResponse !== null) {
            return $parentResponse;
        }

        // Force JSON content type on all API responses
        $this->response = $this->response->withType('application/json');

        // Skip identity check for actions marked as unauthenticated
        $unauthActions = $this->Authentication->getUnauthenticatedActions();
        $currentAction = $this->request->getParam('action');
        if (in_array($currentAction, $unauthActions, true)) {
            return null;
        }

        // Ensure we have a valid identity
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            throw new UnauthorizedException('Authentication required');
        }

        return null;
    }

    /**
     * Get the authenticated service principal.
     *
     * @return \App\Model\Entity\ServicePrincipal
     * @throws \Cake\Http\Exception\UnauthorizedException
     */
    protected function getServicePrincipal(): ServicePrincipal
    {
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            throw new UnauthorizedException('Authentication required');
        }

        $originalData = $identity->getOriginalData();
        if (!($originalData instanceof ServicePrincipal)) {
            throw new UnauthorizedException('API requires service principal authentication');
        }

        return $originalData;
    }

    /**
     * Check if current identity is a service principal.
     *
     * @return bool
     */
    protected function isServicePrincipal(): bool
    {
        $identity = $this->Authentication->getIdentity();
        if (!$identity) {
            return false;
        }

        $originalData = $identity->getOriginalData();
        return $originalData instanceof ServicePrincipal;
    }

    /**
     * Standard success response envelope.
     *
     * @param mixed $data Response data
     * @param array $meta Optional metadata
     * @return void
     */
    protected function apiSuccess(mixed $data, array $meta = []): void
    {
        $response = ['data' => $data];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $this->set($response);
        $this->viewBuilder()->setOption('serialize', array_keys($response));
    }

    /**
     * Standard error response envelope.
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param array $details Additional error details
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function apiError(
        string $code,
        string $message,
        array $details = [],
        int $statusCode = 400
    ): void {
        $this->response = $this->response->withStatus($statusCode);

        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (!empty($details)) {
            $error['details'] = $details;
        }

        $this->set('error', $error);
        $this->viewBuilder()->setOption('serialize', ['error']);
    }

    /**
     * Build pagination metadata.
     *
     * @return array
     */
    protected function getPaginationMeta(): array
    {
        $paging = $this->request->getAttribute('paging');
        if (empty($paging)) {
            return [];
        }

        // Get the first (and usually only) paged result set
        $pagingData = reset($paging);
        if (!$pagingData) {
            return [];
        }

        return [
            'pagination' => [
                'total' => $pagingData['count'] ?? 0,
                'page' => $pagingData['page'] ?? 1,
                'per_page' => $pagingData['perPage'] ?? 20,
                'total_pages' => $pagingData['pageCount'] ?? 1,
            ],
        ];
    }
}
