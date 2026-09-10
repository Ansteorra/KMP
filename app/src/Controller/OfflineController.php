<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Services\Security\OfflineIdentity;
use App\Services\Security\RequestRateLimiter;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
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
        $page = $this->request->getQuery('page', 'card');
        $pages = [
            'card' => ['Members/view_mobile_card', 'Auth Card', 'auth-card', 'bi-person-vcard'],
            'rsvps' => ['GatheringAttendances/my_rsvps', 'My RSVPs', 'rsvps', 'bi-calendar-check'],
            'calendar' => ['Gatherings/mobile_calendar', 'Events', 'events', 'bi-calendar-event'],
        ];
        if (!is_string($page) || !isset($pages[$page])) {
            throw new NotFoundException();
        }
        [$template, $title, $section, $icon] = $pages[$page];
        $this->set([
            'publicOfflineShell' => true,
            'mobileTitle' => $title,
            'mobileSection' => $section,
            'mobileIcon' => $icon,
            'authCardUrl' => '/members/view-mobile-card',
            'defaultYear' => (int)date('Y'),
            'defaultMonth' => (int)date('n'),
            'message_variables' => ['kingdom' => StaticHelpers::getAppSetting('KMP.KingdomName')],
            'watermarkImage' => $this->appSettingImageDataUri('Member.ViewCard.Graphic'),
        ]);
        if ($page === 'card') {
            $this->set('cardUrl', '/members/view-mobile-card-json');
        }
        $this->viewBuilder()->setTemplate('/' . $template)->setLayout('mobile_app');
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

    /** Verify the current member's password before encrypting it on their device. */
    public function verifyLogin(): Response
    {
        $this->request->allowMethod(['post']);
        $context = OfflineIdentity::context($this->request);
        $identity = $this->request->getAttribute('identity');
        $password = $this->request->getData('password');
        $ok = false;
        $email = null;
        $status = 403;
        if ($identity && $context && !$context['impersonating']) {
            $limiter = new RequestRateLimiter();
            $limit = $limiter->attempt($limiter::BUCKET_PIN, 'device-setup:' . $identity->getIdentifier());
            if (!$limit->allowed) {
                $status = 429;
            } elseif (is_string($password) && strlen($password) <= 125) {
                $member = $this->fetchTable('Members')->get($identity->getIdentifier());
                $ok = (new DefaultPasswordHasher())->check($password, $member->password);
                $email = $ok ? $member->email_address : null;
            }
        }

        return OfflineIdentity::bind($this->response, $this->request)
            ->withType('application/json')->withStatus($ok ? 200 : $status)
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody(json_encode(['success' => $ok, 'email' => $email], JSON_THROW_ON_ERROR));
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
            ->withHeader('X-KMP-Offline-Clear', !empty($context['impersonating']) ? '1' : '0')
            ->withStringBody(json_encode(
                ['success' => $allowed, 'data' => $allowed ? $context : null],
                JSON_THROW_ON_ERROR,
            ));
    }
}
