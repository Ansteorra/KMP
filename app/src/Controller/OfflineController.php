<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\Security\OfflineIdentity;
use Cake\Event\EventInterface;
use Cake\Http\Response;

/** Public, nonpersonalized shell; private data is fetched only after authentication. */
class OfflineController extends AppController
{
    /** @inheritDoc */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['index', 'assets']);
        $this->Authorization->skipAuthorization();
    }

    /** Render a public shell containing no identity, CSRF token or private page markup. */
    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('offline');
        $this->response = $this->response->withHeader('Cache-Control', 'public, max-age=0, must-revalidate')
            ->withHeader('X-KMP-Public-Offline', '1');
    }

    /** Build-controlled assets only; callers cannot submit URLs to cache. */
    public function assets(): Response
    {
        $this->request->allowMethod(['get']);
        $manifest = json_decode((string)file_get_contents(WWW_ROOT . '.vite/manifest.json'), true);
        $paths = [];
        foreach (is_array($manifest) ? $manifest : [] as $entry) {
            foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                $pattern = '#^(?:js|css|fonts|assets)/[a-zA-Z0-9_.-]+\.(?:js|css|woff2?|png|svg)$#D';
                if (is_string($file) && preg_match($pattern, $file)) {
                    $paths[] = '/' . $file;
                }
            }
        }

        return $this->response->withType('application/json')
            ->withHeader('Cache-Control', 'public, max-age=0, must-revalidate')
            ->withHeader('X-KMP-Public-Offline', '1')
            ->withStringBody(json_encode(['assets' => array_values(array_unique($paths))], JSON_THROW_ON_ERROR));
    }

    /** Fresh same-origin session binding and CSRF for a foreground offline sync. */
    public function context(): Response
    {
        $this->request->allowMethod(['get']);
        $context = OfflineIdentity::context($this->request);
        $allowed = $context !== null && !$context['impersonating'];
        if ($allowed) {
            $context['csrfToken'] = $this->request->getAttribute('csrfToken');
        }

        return $this->response->withStatus($allowed ? 200 : 403)->withType('application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody(json_encode(
                ['success' => $allowed, 'data' => $allowed ? $context : null],
                JSON_THROW_ON_ERROR,
            ));
    }
}
