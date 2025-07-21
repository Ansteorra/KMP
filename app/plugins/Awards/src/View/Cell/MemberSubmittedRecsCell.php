<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use App\View\Cell\BasePluginCell;
use Cake\Log\Log;

/**
 * MemberSubmittedRecs cell
 */
class MemberSubmittedRecsCell extends Cell
{

    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        if ($id == -1) {
            $id = $this->request->getAttribute('identity')->getIdentifier();
        }
        $currentUser = $this->request->getAttribute('identity');
        if ($currentUser->id != $id && !$currentUser->checkCan('ViewSubmittedByMember', 'Awards.Recommendations')) {
            return;
        }
        $recommendationsTbl = TableRegistry::getTableLocator()->get("Awards.Recommendations");
        $isEmpty = $recommendationsTbl->find('all')->where(['requester_id' => $id])->count() === 0;
        $this->set(compact('isEmpty', 'id'));
    }
}