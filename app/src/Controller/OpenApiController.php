<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\OpenApiMergeService;
use Cake\Http\Response;

/**
 * Serves the merged OpenAPI specification (base + plugin fragments).
 */
class OpenApiController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->addUnauthenticatedActions(['spec']);
        $this->Authorization->skipAuthorization();
    }

    /**
     * Return the merged OpenAPI spec as JSON.
     *
     * @return \Cake\Http\Response
     */
    public function spec(): Response
    {
        $service = new OpenApiMergeService();
        $spec = $service->getMergedSpec();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
