<?php

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Cache\Cache;
use Cake\Utility\Hash;

/**
 * Branches Model
 *
 * @method \App\Model\Entity\Branch get($primaryKey, $options = [])
 * @method \App\Model\Entity\Branch newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Branch[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Branch|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Branch patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Branch[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Branch findOrCreate($search, callable $callback = null, $options = [])
 */
class BranchesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("branches");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");

        $this->BelongsTo("Parent", [
            "className" => "Branches",
            "foreignKey" => "parent_id",
        ]);

        $this->HasMany("Members", [
            "className" => "Members",
            "foreignKey" => "branch_id",
        ]);
        $this->addBehavior("Tree");
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        $schema->setColumnType('links', 'json');

        return $schema;
    }


    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence("name", "create")
            ->notEmptyString("name")
            ->add("name", "unique", [
                "rule" => "validateUnique",
                "provider" => "table",
            ]);

        $validator
            ->requirePresence("location", "create")
            ->notEmptyString("location");

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(["name"]));

        return $rules;
    }

    public function getAllDecendentIds($id): array
    {
        $descendants = Cache::read("descendants_" . $id);
        if (!$descendants) {
            $descendants = $this->getDescendantsLookup();
            foreach ($descendants as $key => $value) {
                Cache::write("descendants_" . $key, $value);
            }
            $descendants = $descendants[$id] ?? [];
        }
        return $descendants ?? [];
    }

    public function getAllParents($id): array
    {
        $parents = Cache::read("parents_" . $id);
        if (!$parents) {
            $parents = $this->getParentsLookup();
            foreach ($parents as $key => $value) {
                Cache::write("parents_" . $key, $value);
            }
            $parents = $parents[$id] ?? [];
        }
        return $parents ?? [];
    }

    public function getThreadedTree()
    {
        // rebuild the array into a tree structure
        $branches = $this->find("threaded", [
            "parentField" => "parent_id",
            "keyForeign" => "id",
            "nestingKey" => "children",
        ])->select(['id', 'name', 'parent_id'])->toArray();
        //create a quick index of all of the decendents for each branch

        return $branches;
    }

    protected function getParentsLookup(): array
    {
        $tree = $this->getThreadedTree();
        $lookup = [];

        // we need to iterate through the tree creating the list of parents for each node
        $populateParents = function (object $node, array $parentIds = []) use (&$lookup, &$populateParents) {
            $lookup[$node['id']] = $parentIds;
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $populateParents($child, array_merge($parentIds, [$node['id']]));
                }
            }
        };

        foreach ($tree as $node) {
            $populateParents($node);
        }
        return $lookup;
    }

    protected function getDescendantsLookup(): array
    {
        $tree = $this->getThreadedTree();
        $lookup = [];

        // Recursive function to populate lookup for each node.
        $populateLookup = function (object $node) use (&$lookup, &$populateLookup) {
            $childIDs = [];
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child) {
                    $childIDs[] = $child['id'];
                    $populateLookup($child);
                    // Merge in any descendants already computed for the child.
                    if (isset($lookup[$child['id']])) {
                        $childIDs = array_merge($childIDs, $lookup[$child['id']]);
                    }
                }
            }
            $lookup[$node['id']] = $childIDs;
        };

        // Process each top-level node.
        foreach ($tree as $node) {
            $populateLookup($node);
        }

        return $lookup;
    }
}