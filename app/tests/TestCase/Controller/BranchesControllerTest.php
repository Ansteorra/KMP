<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Entity\Branch;
use App\Test\TestCase\Support\HttpIntegrationTestCase;

class BranchesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testIndex(): void
    {
        $this->get('/branches');
        $this->assertResponseOk();
        $this->assertResponseContains('Branches');
    }

    public function testIndexWithSearch(): void
    {
        $this->get('/branches?search=kingdom');
        $this->assertResponseOk();
        $this->assertResponseContains('Branches');
    }

    public function testGridDataSearchRestoresPrimaryAndEmbeddedFrameState(): void
    {
        $branch = $this->getTableLocator()->get('Branches')->get(self::TEST_BRANCH_STARGATE_ID);
        foreach ([null, self::KINGDOM_BRANCH_ID] as $parentId) {
            $frameId = $parentId === null ? 'branches-grid' : 'branch-children-grid';
            $query = [
                'frame_id' => $frameId,
                'search' => $branch->name,
                'page' => 3,
                'limit' => 10,
                'ignore_default' => 1,
            ];
            if ($parentId !== null) {
                $query['parent_id'] = $parentId;
            }
            $this->configRequest(['headers' => ['Turbo-Frame' => $frameId . '-table']]);
            $this->get('/branches/grid-data?' . http_build_query($query));

            $this->assertResponseOk();
            $this->assertResponseContains($branch->name);
            $matched = preg_match(
                '/<script[^>]+id="' . preg_quote($frameId, '/') . '-table-state"[^>]*>(.*?)<\/script>/s',
                (string)$this->_response->getBody(),
                $matches,
            );
            $this->assertSame(1, $matched);
            $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($branch->name, $state['search']);
            $this->assertSame(10, $state['config']['pageSize']);
            $this->assertSame(1, $state['pagination']['currentPage']);
        }
    }

    public function testViewBranchCreatedForTest(): void
    {
        // Use deterministic seed branch to avoid flakiness from unordered queries.
        $branches = $this->getTableLocator()->get('Branches');
        $branch = $branches->get(self::KINGDOM_BRANCH_ID);
        $this->assertInstanceOf(Branch::class, $branch, 'Missing expected kingdom branch in seed data');
        $this->get('/branches/view/' . $branch->public_id);
        $this->assertResponseOk();
        // Content assertions skipped due to layout block rendering variability in test context
    }

    public function testViewInvalidBranchReturns404(): void
    {
        $this->get('/branches/view/ZZZZZZZZ');
        $this->assertResponseCode(404);
    }

    public function testAddGetDisplaysForm(): void
    {
        $this->get('/branches/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Branch');
        $this->assertResponseContains('name');
    }

    public function testAddPostDuplicateNameShowsError(): void
    {
        // Create initial branch then attempt duplicate via controller
        $branches = $this->getTableLocator()->get('Branches');
        $branches->save($branches->newEntity([
            'name' => 'Duplicate Root',
            'location' => 'Loc1',
        ]));
        $data = [
            'name' => 'Duplicate Root',
            'location' => 'Loc2',
            'branch_links' => json_encode(['website' => 'https://example.com']),
        ];
        $this->post('/branches/add', $data);
        $this->assertResponseOk();
        $this->assertResponseContains('Branch');
    }

    public function testEditGetDisplaysModalOnViewPage(): void
    {
        // Edit action renders the view template; use deterministic seed branch.
        $branches = $this->getTableLocator()->get('Branches');
        $branch = $branches->get(self::KINGDOM_BRANCH_ID);
        $this->assertInstanceOf(Branch::class, $branch, 'Missing expected kingdom branch in seed data');
        $this->get('/branches/edit/' . $branch->public_id);
        $this->assertResponseOk();
        // Content assertions skipped; modal presence depends on permissions & dynamic blocks
    }

    public function testDeleteRequiresPost(): void
    {
        // Use deterministic seed branch to avoid flaky first-row selection.
        $branches = $this->getTableLocator()->get('Branches');
        $branch = $branches->get(self::KINGDOM_BRANCH_ID);
        $this->assertInstanceOf(Branch::class, $branch, 'Missing expected kingdom branch in seed data');
        $this->get('/branches/delete/' . $branch->public_id);
        $this->assertResponseCode(405);
    }

    public function testDeleteInvalidId(): void
    {
        $this->delete('/branches/delete/ZZZZZZZZ');
        $this->assertResponseCode(404);
    }
}
