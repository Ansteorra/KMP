<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use App\View\Cell\BasePluginCell;
use Cake\Log\Log;

/**
 * Displays award recommendations submitted by a specific member.
 * 
 * Provides a dashboard view showing all recommendations a member has submitted,
 * with Turbo Frame integration for lazy loading. Supports both self-access (ID=-1)
 * and administrative viewing with proper permission checks.
 * 
 * @see \Awards\Services\AwardsViewCellProvider View cell registration
 * @see /docs/5.2.17-awards-services.md Full documentation
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
     * Display member submitted recommendations with permission checking.
     * 
     * Resolves member ID (-1 for current user), validates permissions,
     * and sets template variables for conditional display.
     *
     * @param int $id Member ID to display recommendations for (-1 for current user)
     * @return void Sets template variables or returns early for unauthorized access
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
