<?php

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

class BranchesTableTest extends BaseTestCase
{
    /** @var \App\Model\Table\BranchesTable */
    protected $Branches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Branches = $this->getTableLocator()->get('Branches');
    }

    public function testValidationRequiresNameAndLocation(): void
    {
        $branch = $this->Branches->newEntity([
            'name' => '',
            'location' => '',
        ]);
        $this->assertNotEmpty($branch->getErrors()['name']);
        $this->assertNotEmpty($branch->getErrors()['location']);
    }

    public function testValidationUniqueName(): void
    {
        $root = $this->Branches->find()->where(['parent_id IS' => null])->first();
        if (!$root) {
            $this->markTestSkipped('No root branch found in seed data');
        }
        $duplicate = $this->Branches->newEntity([
            'name' => $root->name,
            'location' => 'Duplicate Location',
        ]);
        $this->Branches->save($duplicate);
        $this->assertNotEmpty($duplicate->getErrors()['name']);
    }

    public function testGetThreadedTreeReturnsNonEmptyArray(): void
    {
        $tree = $this->Branches->getThreadedTree();
        $this->assertIsArray($tree);
        $this->assertNotEmpty($tree, 'Tree should contain created branches');
        $root = $tree[0];
        // The threaded finder may omit an explicit children property when there are no children.
        // Treat absence as acceptable but assert that if children exist they are an array.
        $children = $root->get('children');
        if ($children !== null) {
            $this->assertIsArray($children, 'Children should be an array when present');
        }
        // Additionally ensure at least one node in the tree has children if multiple branches exist
        $anyChildren = false;
        foreach ($tree as $node) {
            $nodeChildren = $node->get('children');
            if (!empty($nodeChildren)) {
                $anyChildren = true;
                break;
            }
        }
        if (count($tree) > 1) {
            $this->assertTrue($anyChildren, 'Expected at least one branch to have children in a multi-node tree');
        }
    }

    public function testGetAllDescendentIdsCachingBehavior(): void
    {
        $root = $this->Branches->find()->where(['parent_id IS' => null])->first();
        if (!$root) {
            $this->markTestSkipped('No root branch found in seed data');
        }
        $first = $this->Branches->getAllDecendentIds($root->id);
        $second = $this->Branches->getAllDecendentIds($root->id);
        $this->assertEquals($first, $second, 'Cached descendant list should match initial computation');
    }

    public function testGetAllParentsRootHasNoParents(): void
    {
        $root = $this->Branches->find()->where(['parent_id IS' => null])->first();
        if (!$root) {
            $this->markTestSkipped('No root branch found in seed data');
        }
        $parents = $this->Branches->getAllParents($root->id);
        $this->assertIsArray($parents);
        $this->assertEmpty($parents, 'Root branch should have no parents');
    }

    public function testGetAllParentsOfDescendantContainsRoot(): void
    {
        $child = $this->Branches->find()->where(['parent_id IS NOT' => null])->first();
        if (!$child) {
            $this->markTestSkipped('No descendant branch found in seed data');
        }
        $parents = $this->Branches->getAllParents($child->id);
        $this->assertNotEmpty($parents);
    }

    public function testSchemaDefinesJsonLinksColumn(): void
    {
        $schema = $this->Branches->getSchema();
        $this->assertEquals('json', $schema->getColumnType('links'));
    }

    public function testBuildRulesPreventsDuplicateNameOnSave(): void
    {
        $root = $this->Branches->find()->where(['parent_id IS' => null])->first();
        if (!$root) {
            $this->markTestSkipped('No root branch found in seed data');
        }
        $duplicate = $this->Branches->newEntity([
            'name' => $root->name,
            'location' => 'Elsewhere'
        ]);
        $result = $this->Branches->save($duplicate);
        $this->assertFalse($result, 'Duplicate name should not save');
        $this->assertNotEmpty($duplicate->getErrors()['name']);
    }

    public function testTreeBehaviorFieldsExist(): void
    {
        $root = $this->Branches->find()->where(['parent_id IS' => null])->first();
        if (!$root) {
            $this->markTestSkipped('No root branch found in seed data');
        }
        $this->assertNotNull($root->get('lft'));
        $this->assertNotNull($root->get('rght'));
    }
}
