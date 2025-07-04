<?php

declare(strict_types=1);

namespace Activities\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Activities\Model\Entity\Authorization;

/**
 * MemberAuthorizations cell
 */
class MemberAuthorizationsCell extends Cell
{
    /**
     * 
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */

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
        //if the id is -1 then we are viewing the current user
        if ($id == -1) {
            $id = $this->request->getAttribute('identity')->getIdentifier();
        }
        $authTable = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $currentAuths = $authTable->find('current')->where(['member_id' => $id])->count();
        $pendingAuths = $authTable->find('pending')->where(['member_id' => $id])->count();
        $previousAuths = $authTable->find('previous')->where(['member_id' => $id])->count();

        $authTypeTable = TableRegistry::getTableLocator()->get(
            "Activities.Activities",
        );
        //get the member
        $memberTbl = TableRegistry::getTableLocator()->get('Members');
        $member = $memberTbl->find('all')
            ->where(['id' => $id])
            ->select(['id', 'birth_month', 'birth_year', 'additional_info'])->first();
        // Get the list of authorization types the member can request based on their age
        $activities = $authTypeTable->find("list")->where([
            "minimum_age <=" => $member->age,
            "maximum_age >=" => $member->age,
        ]);
        $isEmpty = ($currentAuths + $pendingAuths + $previousAuths) == 0;
        $pendingAuthCount = $pendingAuths;
        $this->set(compact('pendingAuthCount', 'isEmpty', 'id', 'activities', 'member'));
    }

    protected function addConditions(SelectQuery $q)
    {

        $rejectFragment = $q->func()->concat([
            "Authorizations.status" => 'identifier',
            ' - ',
            "RevokedBy.sca_name" => 'identifier',
            " on ",
            "expires_on" => 'identifier',
            " note: ",
            "revoked_reason" => 'identifier'
        ]);

        $revokeReasonCase = $q->newExpr()
            ->case()
            ->when(['Authorizations.status' => Authorization::DENIED_STATUS])
            ->then($rejectFragment)
            ->when(['Authorizations.status' => Authorization::REVOKED_STATUS])
            ->then($rejectFragment)
            ->when(['Authorizations.status' => Authorization::EXPIRED_STATUS])
            ->then("Authorization Expired")
            ->else("");
        return $q
            ->select([
                "id",
                "member_id",
                "activity_id",
                "Authorizations.status",
                "start_on",
                "expires_on",
                "revoked_reason" => $revokeReasonCase,
                "revoker_id",
            ])
            ->contain([
                "CurrentPendingApprovals" => function (SelectQuery $q) {
                    return $q->select(["Approvers.sca_name", "requested_on"])
                        ->contain("Approvers");
                },
                "Activities" => function (SelectQuery $q) {
                    return $q->select(["Activities.name", "Activities.id"]);
                },
                "RevokedBy" => function (SelectQuery $q) {
                    return $q->select(["RevokedBy.sca_name"]);
                }
            ]);
    }
}
