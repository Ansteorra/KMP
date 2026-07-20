<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformDataConsoleService;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use InvalidArgumentException;
use Throwable;

class DataConsoleController extends PlatformAdminAppController
{
    /**
     * Render an allowlisted, read-only platform data inspection surface.
     *
     * @return void
     */
    public function index(): void
    {
        if (!Configure::read('Platform.adminPortal.dataConsole.enabled')) {
            throw new NotFoundException('Platform data console is not enabled.');
        }

        $service = new PlatformDataConsoleService($this->platform());
        $queryName = strtolower(trim((string)$this->request->getQuery('query', 'tenants')));
        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = (int)$this->request->getQuery('limit', 25);

        try {
            $result = $service->run($queryName, $page, $limit);
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestException($exception->getMessage());
        }

        $this->auditDataConsoleAccess($result['query'], $result['page'], $result['limit'], count($result['rows']));
        $this->set([
            'queries' => $service->queryList(),
            'result' => $result,
        ]);
    }

    /**
     * Best-effort audit trail for each successful data console query.
     */
    private function auditDataConsoleAccess(string $queryName, int $page, int $limit, int $rowCount): void
    {
        try {
            (new PlatformAuditService($this->platform()))->record(
                'data_console.query',
                isset($this->platformAdmin['id']) ? (string)$this->platformAdmin['id'] : null,
                'platform_data_console',
                $queryName,
                null,
                [
                    'query' => $queryName,
                    'page' => $page,
                    'limit' => $limit,
                    'row_count' => $rowCount,
                    'admin_email' => $this->platformAdmin['email'] ?? null,
                ],
                true,
                [
                    'ipAddress' => $this->request->clientIp(),
                    'userAgent' => $this->request->getHeaderLine('User-Agent'),
                ],
            );
        } catch (Throwable) {
            // Data inspection must not expose audit backend failures to admins.
        }
    }
}
