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
class MemberAuthorizationsCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_TAB,
        'label' => 'Authorizations',
        'id' => 'member-authorizations',
        'order' => 1,
        'tabBtnBadge' => null,
        'cell' => 'Activities.MemberAuthorizations'
    ];
    public static function getViewConfigForRoute($route)
    {
        return parent::getRouteEventResponse($route, self::$pluginData, self::$validRoutes);
    }

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
    public function initialize(): void
    {
    }

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        $authTable = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $currentAuths = $this->addConditions($authTable->find('current')->where(['member_id' => $id]))->toArray();
        $pendingAuths = $this->addConditions($authTable->find('pending')->where(['member_id' => $id]))->toArray();
        $previousAuths = $this->addConditions($authTable->find('previous')->where(['member_id' => $id]))->toArray();

        $authTypeTable = TableRegistry::getTableLocator()->get(
            "Activities.Activities",
        );
        //get the member
        $memberTbl = TableRegistry::getTableLocator()->get('Members');
        $member = $memberTbl->find('all')
            ->where(['id' => $id])
            ->select(['id', 'birth_month', 'birth_year'])->first();
        // Get the list of authorization types the member can request based on their age
        $activities = $authTypeTable->find("list")->where([
            "minimum_age <" => $member->age,
            "maximum_age >" => $member->age,
        ]);
        $this->set(compact('currentAuths', 'pendingAuths', 'previousAuths', 'id', 'activities'));
    }

    protected function addConditions(SelectQuery $q)
    {

        $rejectFragment = $q->func()->concat([
            "Authorizations.status" => 'identifier',
            ' - ', "RevokedBy.sca_name" => 'identifier',
            " on ", "expires_on" => 'identifier',
            " note: ", "revoked_reason" => 'identifier'
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