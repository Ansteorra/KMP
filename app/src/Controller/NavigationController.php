<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\UiSession;
use App\Services\NavigationContextService;
use Cake\Http\Response;

class NavigationController extends AppController
{
    /** Validate cached content without revealing identity/session fields. */
    public function context(): Response
    {
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $this->Authorization->authorize(new UiSession(['member_id' => $identity?->getIdentifier()]), 'access');

        return $this->response->withType('application/json')
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Vary', 'Cookie')
            ->withStringBody(json_encode([
                'context' => (new NavigationContextService())->signature($this->request),
            ], JSON_THROW_ON_ERROR));
    }
}
