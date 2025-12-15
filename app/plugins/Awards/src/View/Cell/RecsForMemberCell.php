<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use App\View\Cell\BasePluginCell;
use Cake\Log\Log;
use Cake\ORM\Table;

/**
 * Displays award recommendations received by a specific member.
 * 
 * Provides a view of recommendations submitted about a member with privacy
 * protection. Members cannot view their own received recommendations unless
 * they have the ViewSubmittedForMember permission.
 * 
 * @see \Awards\Services\AwardsViewCellProvider View cell registration
 * @see /docs/5.2.17-awards-services.md Full documentation
 */
class RecsForMemberCell extends Cell
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
     * Display recommendations received by a member with privacy protection.
     * 
     * Resolves member ID (-1 for current user), validates permissions and
     * relationship, and sets template variables for conditional display.
     * Members cannot view their own received recommendations without permission.
     *
     * @param int $id Member ID to display received recommendations for (-1 for current user)
     * @return void Sets template variables or returns with empty state for unauthorized access
     */
    public function display($id)
    {
        if ($id == -1) {
            $id = $this->request->getAttribute('identity')->getIdentifier();
        }
        $currentUser = $this->request->getAttribute('identity');
        if ($currentUser->id == $id && !$currentUser->checkCan('ViewSubmittedForMember', 'Awards.Recommendations')) {
            $isEmpty = true;
            $this->set(compact('isEmpty', 'id'));
            return;
        }
        $recommendationsTbl = TableRegistry::getTableLocator()->get("Awards.Recommendations");
        $isEmpty = $recommendationsTbl->find('all')->where(['member_id' => $id])->count() === 0;
        $this->set(compact('isEmpty', 'id'));
    }
}
