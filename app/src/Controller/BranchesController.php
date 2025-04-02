<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Exception\DatabaseException;
use Cake\Log\Log;
use App\KMP\StaticHelpers;

/**
 * Branches Controller
 *
 * @property \App\Model\Table\BranchesTable $Branches
 */
class BranchesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add");
        $setting = StaticHelpers::getAppSetting("KMP.BranchInitRun", "");
        if (!$setting == "recovered") {
            $branches = $this->Branches;
            $branches->recover();
            StaticHelpers::setAppSetting(
                "KMP.BranchInitRun",
                "recovered",
            );
        }
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {

        $search = $this->request->getQuery("search");
        $search = $search ? trim($search) : null;
        $query = $this->Branches
            ->find("threaded")
            ->join([
                'parent' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'parent.id = Branches.parent_id'
                ]
            ])
            ->join([
                'parent2' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'parent2.id = parent.parent_id'
                ]
            ])
            ->join([
                'parent3' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'parent3.id = parent2.parent_id'
                ]
            ])
            ->orderBy(["Branches.name" => "ASC"]);


        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match("/th/", $search)) {
                $nsearch = str_replace("th", "Þ", $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match("/Þ/", $search)) {
                $usearch = str_replace("Þ", "th", $search);
            }
            $query = $query->where([
                "OR" => [
                    ["Branches.name LIKE" => "%" . $search . "%"],
                    ["Branches.name LIKE" => "%" . $nsearch . "%"],
                    ["Branches.name LIKE" => "%" . $usearch . "%"],
                    ["Branches.location LIKE" => "%" . $search . "%"],
                    ["Branches.location LIKE" => "%" . $nsearch . "%"],
                    ["Branches.location LIKE" => "%" . $usearch . "%"],
                    ["parent.name LIKE" => "%" . $search . "%"],
                    ["parent.name LIKE" => "%" . $nsearch . "%"],
                    ["parent.name LIKE" => "%" . $usearch . "%"],
                    ["parent2.name LIKE" => "%" . $search . "%"],
                    ["parent2.name LIKE" => "%" . $nsearch . "%"],
                    ["parent2.name LIKE" => "%" . $usearch . "%"],
                    ["parent3.name LIKE" => "%" . $search . "%"],
                    ["parent3.name LIKE" => "%" . $nsearch . "%"],
                    ["parent3.name LIKE" => "%" . $usearch . "%"],
                ],
            ]);
        }
        $this->Authorization->authorizeAction();
        $this->Authorization->applyScope($query);
        $branches = $query->toArray();



        $this->set(compact("branches", "search"));
    }

    /**
     * View method
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $branch = $this->Branches->get(
            $id,
            contain: [
                "Parent",
                "Members" => function ($q) {
                    return $q
                        ->select(["id", "sca_name", "branch_id", "membership_number", "membership_expires_on", "status", "birth_month", "birth_year"])
                        ->orderBy(["sca_name" => "ASC"]);
                },
            ],
        );
        if (!$branch) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($branch);
        // get the children for the branch
        $branch->children = $this->Branches
            ->find("children", for: $branch->id, direct: true)
            ->toArray();
        $treeList = $this->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);

        $btArray = StaticHelpers::getAppSetting("Branches.Types");
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }


        // get a list of required offices and officers for the branch

        $this->set(compact("branch", "treeList", "branch_types"));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $branch = $this->Branches->newEmptyEntity();
        if ($this->request->is("post")) {
            $branch = $this->Branches->patchEntity(
                $branch,
                $this->request->getData(),
            );
            $links = json_decode($this->request->getData('branch_links'), true);
            $branch->links = $links;
            if ($this->Branches->save($branch)) {
                $this->Flash->success(__("The branch has been saved."));

                return $this->redirect(["action" => "view", $branch->id]);
            }
            $this->Flash->error(
                __("The branch could not be saved. Please, try again."),
            );
        }
        $btArray = StaticHelpers::getAppSetting("Branches.Types");
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }
        $treeList = $this->Branches
            ->find("list")
            ->orderBy(["name" => "ASC"]);
        $this->set(compact("branch", "treeList", 'branch_types'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $branch = $this->Branches->get($id);
        if (!$branch) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($branch);
        if ($this->request->is(["patch", "post", "put"])) {
            $branch = $this->Branches->patchEntity(
                $branch,
                $this->request->getData(),
            );
            $links = json_decode($this->request->getData('branch_links'), true);
            $branch->links = $links;
            try {
                if ($this->Branches->save($branch)) {
                    $branches = $this->getTableLocator()->get("Branches");
                    $branches->recover();
                    $this->Flash->success(__("The branch has been saved."));

                    return $this->redirect(["action" => "view", $branch->id]);
                }
                $this->Flash->error(
                    __("The branch could not be saved. Please, try again."),
                );

                return $this->redirect(["action" => "view", $branch->id]);
            } catch (DatabaseException $e) {
                // if the error message starts with 'Cannot use node' then it is a tree error
                if (strpos($e->getMessage(), "Cannot use node") === 0) {
                    $this->Flash->error(
                        __(
                            "The branch could not be saved, save would have created a circular reference.",
                        ),
                    );
                } else {
                    $this->Flash->error(
                        __(
                            "The branch could not be saved. Please, try again. Error` {0}",
                            $e->getMessage(),
                        ),
                    );
                }

                return $this->redirect(["action" => "view", $branch->id]);
            }
        }
        $treeList = $this->Branches
            ->find("list")
            ->orderBy(["name" => "ASC"]);
        $this->set(compact("branch", "treeList"));
    }

    /**
     * Delete method
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $branch = $this->Branches->get($id);
        if (!$branch) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($branch);
        $branch->name = "Deleted: " . $branch->name;
        if ($this->Branches->delete($branch)) {
            $this->Flash->success(__("The branch has been deleted."));
        } else {
            $this->Flash->error(
                __("The branch could not be deleted. Please, try again."),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}