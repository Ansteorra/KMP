<?php
declare(strict_types=1);

namespace App\Services;

use App\Application;
use App\Services\Security\MemberSessionState;
use Authentication\AuthenticationService;
use Cake\Controller\ControllerFactory;
use Cake\Core\ContainerFactory;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use DOMDocument;
use DOMXPath;
use RuntimeException;

/** Reuses the live grid's authorization, query, enrichment and visible-cell rendering. */
class GridSubscriptionReportService
{
    /** Configure report dependencies. */
    public function __construct(private readonly Application $application)
    {
    }

    /** Render a bounded summary using a fresh identity, with no saved login session. */
    public function generate(int $memberId, string $gridKey, array $query, string $origin): array
    {
        $grid = GridSubscriptionRegistry::get($gridKey);
        $member = TableRegistry::getTableLocator()->get('Members')->find()
            ->where(['Members.id' => $memberId])->first();
        if (!$member || !MemberSessionState::eligible($member)) {
            throw new ForbiddenException('The account can no longer receive grid summaries.');
        }
        $viewId = $query['view_id'] ?? null;
        if (is_numeric($viewId)) {
            $view = TableRegistry::getTableLocator()->get('GridViews')->find()->where([
                'id' => (int)$viewId, 'grid_key' => $gridKey,
                'OR' => [['member_id' => $memberId], ['member_id IS' => null, 'is_system_default' => true]],
            ])->first();
            if (!$view) {
                throw new ForbiddenException('The saved view is no longer available.');
            }
        }
        $query = array_replace($query, ['page' => 1, 'limit' => 50, 'nostack' => 1]);
        unset($query['export']);
        $params = $grid['route'] + ['prefix' => null, 'pass' => [], '_ext' => null];
        $url = Router::url($grid['route']);
        $request = new ServerRequest([
            'url' => $origin . $url, 'params' => $params, 'query' => $query,
            'session' => new GridReportSession(),
        ]);
        $authorization = $this->application->getAuthorizationService($request);
        $request = $request->withAttribute('identity', $member->setAuthorization($authorization))
            ->withAttribute('authorization', $authorization)
            ->withAttribute('authentication', new AuthenticationService())
            ->withHeader('Turbo-Frame', 'grid-subscription-report');

        // A separate container prevents request/controller state leaking between recipients.
        $container = ContainerFactory::create();
        $this->application->services($container);
        foreach ($this->application->getPlugins()->with('services') as $plugin) {
            $plugin->services($container);
        }
        $container->addShared(ServerRequest::class, $request);
        $factory = new ControllerFactory($container);
        $previousRequest = Router::getRequest();
        Router::setRequest($request);
        try {
            $controller = $factory->create($request);
            $controller->disableAutoRender();
            $response = $factory->invoke($controller);
            if (in_array($response->getStatusCode(), [301, 302, 401, 403, 404], true)) {
                throw new ForbiddenException('Access to this grid is no longer available.');
            }
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('Grid summary temporarily unavailable.');
            }
            $vars = $controller->viewBuilder()->getVars();
            if (
                ($vars['gridKey'] ?? null) !== $gridKey
                || !isset($vars['data'], $vars['columns'], $vars['visibleColumns'])
            ) {
                throw new RuntimeException('Grid did not return the expected summary data.');
            }
            if (
                $viewId !== null && $viewId !== '' && $viewId !== 'all'
                && (string)($vars['gridState']['view']['currentId'] ?? '') !== (string)$viewId
            ) {
                throw new ForbiddenException('The selected view is no longer available.');
            }
            $html = $controller->createView()->element('dataverse_table', [
                'data' => $vars['data'], 'columns' => $vars['columns'],
                'visibleColumns' => $vars['visibleColumns'], 'rowActions' => [],
                'enableColumnPicker' => false, 'enableBulkSelection' => false,
                'user' => $member,
            ]);
            $summary = $this->extractText($html);
            $summary['label'] = $grid['label'];
            $summary['viewName'] = $vars['gridState']['view']['currentName'] ?? $grid['label'];
            unset($query['page'], $query['limit'], $query['nostack']);
            $summary['url'] = $origin . Router::url($grid['page'] + ['?' => $query]);
            $summary['manageUrl'] = $origin . Router::url([
                'plugin' => null, 'controller' => 'GridSubscriptions', 'action' => 'index',
            ]);

            return $summary;
        } finally {
            Router::setRequest($previousRequest ?? new ServerRequest(['session' => new GridReportSession()]));
        }
    }

    /** Extract display text only: no interactive controls, URLs, markup or hidden data attributes. */
    public function extractText(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $hidden = '//script|//style|//input|//select|//textarea|//*[@hidden]|//*[@aria-hidden="true"]';
        foreach ($xpath->query($hidden) as $node) {
            $node->parentNode?->removeChild($node);
        }
        $text = static fn($node): string => mb_substr(
            trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? ''),
            0,
            2000,
        );
        $headers = [];
        foreach ($xpath->query('//table/thead/tr/th') as $cell) {
            $headers[] = $text($cell);
        }
        $rows = [];
        foreach ($xpath->query('//table/tbody/tr[@data-id]') as $row) {
            $values = [];
            foreach ($xpath->query('./td', $row) as $cell) {
                $values[] = $text($cell);
            }
            $rows[] = $values;
            if (count($rows) === 50) {
                break;
            }
        }

        return compact('headers', 'rows');
    }
}
