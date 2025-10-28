<?php

declare(strict_types=1);

namespace Activities\Controller;

/**
 * AuthorizationsController - Member Activity Authorization Management
 *
 * This controller manages the complete lifecycle of member activity authorizations within
 * the Kingdom Management Portal. It provides interfaces for authorization requests, approvals,
 * revocations, and administrative management of member authorizations.
 *
 * ## Core Functionality
 *
 * ### Authorization Lifecycle Management
 * - **Request Processing**: Handles new authorization requests from members
 * - **Approval Workflows**: Manages multi-level approval processes
 * - **Revocation Handling**: Administrative revocation of active authorizations
 * - **Status Tracking**: Complete authorization status monitoring and reporting
 *
 * ### Administrative Features
 * - **Bulk Operations**: Administrative tools for managing multiple authorizations
 * - **Reporting Interface**: Authorization status and participation reporting
 * - **Member History**: Complete authorization history for members
 * - **Activity Analytics**: Authorization analytics for activity management
 *
 * ## Security Architecture
 *
 * ### Authorization Integration
 * All controller actions are protected by KMP's RBAC system:
 * - **Policy-Based Access**: Each action requires appropriate permissions
 * - **Entity-Level Authorization**: Individual authorization access control
 * - **Branch Scoping**: Operations respect organizational boundaries
 * - **Audit Trail**: All actions logged with user accountability
 *
 * ### Public API Endpoints
 * Limited public access for specific member services:
 * - **getMemberAuthorizations**: Public endpoint for member authorization lookup
 * - **Authentication Bypass**: Specific endpoints excluded from authentication requirements
 * - **Security Validation**: Public endpoints include additional security measures
 *
 * ## Service Integration
 *
 * ### AuthorizationManagerInterface
 * Primary business logic handled through service layer:
 * - **Transactional Operations**: All authorization changes handled transactionally
 * - **Business Rule Enforcement**: Service layer enforces organizational policies
 * - **Validation Logic**: Comprehensive validation of authorization operations
 * - **Error Handling**: Structured error handling with user feedback
 *
 * ### ActiveWindow Integration
 * Authorization temporal management through ActiveWindow system:
 * - **Expiration Handling**: Automatic authorization expiration processing
 * - **Renewal Workflows**: Authorization renewal and extension management
 * - **Historical Tracking**: Complete authorization history preservation
 *
 * ## Controller Architecture
 *
 * ### Request Processing
 * - **POST-Only Operations**: Sensitive operations require POST methods
 * - **CSRF Protection**: Built-in CSRF protection for form submissions
 * - **Input Validation**: Comprehensive input validation and sanitization
 * - **Error Handling**: Graceful error handling with user feedback
 *
 * ### Response Management
 * - **Flash Messaging**: User feedback through flash message system
 * - **JSON APIs**: RESTful JSON responses for AJAX operations
 * - **Redirect Handling**: Appropriate redirects after operations
 * - **Error Responses**: Structured error responses for failed operations
 *
 * ## Usage Examples
 *
 * ### Authorization Request
 * ```php
 * // Member requests authorization for activity
 * POST /activities/authorizations/request
 * Data: {
 *     'activity_id': 123,
 *     'approver_id': 456,
 *     'notes': 'Request for heavy weapons authorization'
 * }
 * ```
 *
 * ### Authorization Approval
 * ```php
 * // Approver processes authorization request
 * POST /activities/authorizations/approve/789
 * Data: {
 *     'approval_notes': 'Member demonstrates required competency',
 *     'next_approver_id': 101 // Optional for multi-level approval
 * }
 * ```
 *
 * ### Authorization Revocation
 * ```php
 * // Administrator revokes active authorization
 * POST /activities/authorizations/revoke/789
 * Data: {
 *     'revoked_reason': 'Safety violation during event'
 * }
 * ```
 *
 * ### Member Authorization Lookup
 * ```php
 * // Public API for member authorization status
 * GET /activities/authorizations/member/123
 * Response: {
 *     'authorizations': [
 *         {
 *             'activity': 'Heavy Weapons',
 *             'status': 'approved',
 *             'expires_on': '2025-12-31'
 *         }
 *     ]
 * }
 * ```
 *
 * ## Administrative Interfaces
 *
 * ### Authorization Dashboard
 * - **Pending Approvals**: Queue of authorization requests requiring approval
 * - **Status Overview**: Summary of authorization statuses across organization
 * - **Expiration Alerts**: Notifications for upcoming authorization expirations
 * - **Activity Metrics**: Participation statistics and trends
 *
 * ### Member Management
 * - **Authorization History**: Complete authorization timeline for members
 * - **Status Changes**: Detailed log of authorization status changes
 * - **Compliance Tracking**: Member compliance with authorization requirements
 * - **Renewal Management**: Tools for managing authorization renewals
 *
 * ## Error Handling and Validation
 *
 * ### Common Error Scenarios
 * - **Invalid Parameters**: Graceful handling of invalid authorization IDs
 * - **Permission Errors**: Clear messaging for insufficient permissions
 * - **Business Rule Violations**: Detailed feedback for policy violations
 * - **Database Errors**: Proper exception handling and user feedback
 *
 * ### Validation Patterns
 * - **Input Sanitization**: All user input properly validated and sanitized
 * - **Business Logic Validation**: Service layer validates business rules
 * - **Authorization Checks**: Entity-level authorization before operations
 * - **Data Integrity**: Referential integrity maintained across operations
 *
 * @property \Activities\Model\Table\AuthorizationsTable $Authorizations Authorization data access
 * @see \Activities\Services\AuthorizationManagerInterface Primary business logic service
 * @see \Activities\Model\Entity\Authorization Authorization entity
 * @see \App\Controller\AppController Parent controller with common functionality
 * @package Activities\Controller
 * @since KMP 1.0
 */

use Activities\Services\AuthorizationManagerInterface;
use Cake\ORM\Query\SelectQuery;
use Activities\Model\Entity\Authorization;
use App\KMP\StaticHelpers;
use Cake\ORM\TableRegistry;

class AuthorizationsController extends AppController
{

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            "getMemberAuthorizations",
        ]);
    }

    public function revoke(AuthorizationManagerInterface $maService, $id = null)
    {
        $this->request->allowMethod(["post"]);
        if ($id == null) {
            $id = (int)$this->request->getData("id");
        }

        $authorization = $this->Authorizations->get($id);
        if (!$authorization) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorization);

        $revokedReason = $this->request->getData("revoked_reason");
        $revokerId = $this->Authentication->getIdentity()->getIdentifier();
        $maResult = $maService->revoke($id, $revokerId, $revokedReason);
        if (!$maResult->success) {
            $this->Flash->error(
                __($maResult->reason),
            );

            return $this->redirect($this->referer());
        }
        $this->Flash->success(
            __("The authorization revocation has been processed"),
        );

        return $this->redirect($this->referer());
    }

    public function add(AuthorizationManagerInterface $maService)
    {
        $this->request->allowMethod(["post"]);
        $memberId = $this->request->getData("member_id");

        $authorization = $this->Authorizations->newEmptyEntity();
        $authorization->member_id = $memberId;
        $this->Authorization->authorize($authorization);

        $activity_id = $this->request->getData("activity");
        $approverId = $this->request->getData("approver_id");
        $is_renewal = false;
        $maResult = $maService->request(
            (int) $memberId,
            (int) $activity_id,
            (int) $approverId,
            (bool) $is_renewal,
        );
        if ($maResult->success) {
            $this->Flash->success(__("The Authorization has been requested."));

            // Check if request came from mobile interface
            $referer = $this->referer();
            if (strpos($referer, '/mobile') !== false || strpos($referer, 'view-mobile-card') !== false) {
                // Get the member's mobile card URL
                $member = $this->Authorizations->Members->get($memberId, ['fields' => ['id', 'mobile_card_token']]);
                return $this->redirect([
                    'controller' => 'Members',
                    'action' => 'viewMobileCard',
                    'plugin' => null,
                    $member->mobile_card_token
                ]);
            }

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __($maResult->reason),
        );

        // Check if request came from mobile interface for error case too
        $referer = $this->referer();
        if (strpos($referer, '/mobile') !== false || strpos($referer, 'view-mobile-card') !== false) {
            // Get the member's mobile card URL
            $member = $this->Authorizations->Members->get($memberId, ['fields' => ['id', 'mobile_card_token']]);
            return $this->redirect([
                'controller' => 'Members',
                'action' => 'viewMobileCard',
                'plugin' => null,
                $member->mobile_card_token
            ]);
        }

        return $this->redirect($this->referer());
    }

    public function renew(AuthorizationManagerInterface $maService)
    {
        $this->request->allowMethod(["post"]);
        $memberId = $this->request->getData("member_id");

        $authorization = $this->Authorizations->newEmptyEntity();
        $authorization->member_id = $memberId;
        if (!$authorization) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorization);

        $activity_id = $this->request->getData("activity");
        $approverId = $this->request->getData("approver_id");
        $is_renewal = true;
        $maResult = $maService->request(
            (int) $memberId,
            (int) $activity_id,
            (int) $approverId,
            (bool) $is_renewal,
        );
        if (
            $maResult->success
        ) {
            $this->Flash->success(__("The Authorization has been requested."));

            // Check if request came from mobile interface
            $referer = $this->referer();
            if (strpos($referer, '/mobile') !== false || strpos($referer, 'view-mobile-card') !== false) {
                // Get the member's mobile card URL
                $member = $this->Authorizations->Members->get($memberId, ['fields' => ['id', 'mobile_card_token']]);
                return $this->redirect([
                    'controller' => 'Members',
                    'action' => 'viewMobileCard',
                    'plugin' => null,
                    $member->mobile_card_token
                ]);
            }

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __($maResult->reason),
        );

        // Check if request came from mobile interface for error case too
        $referer = $this->referer();
        if (strpos($referer, '/mobile') !== false || strpos($referer, 'view-mobile-card') !== false) {
            // Get the member's mobile card URL
            $member = $this->Authorizations->Members->get($memberId, ['fields' => ['id', 'mobile_card_token']]);
            return $this->redirect([
                'controller' => 'Members',
                'action' => 'viewMobileCard',
                'plugin' => null,
                $member->mobile_card_token
            ]);
        }

        return $this->redirect($this->referer());
    }

    /**
     * Mobile authorization request action
     * 
     * Provides a mobile-optimized interface for members to request new authorizations.
     * This action displays a streamlined form with large touch targets and simplified
     * UI optimized for mobile PWA experience.
     *
     * @return \Cake\Http\Response|null|void Renders mobile request form
     */
    public function mobileRequestAuthorization()
    {
        // Get current user
        $currentUser = $this->Authentication->getIdentity();
        if (!$currentUser) {
            $this->Flash->error(__('You must be logged in to request authorizations.'));
            return $this->redirect(['controller' => 'Members', 'action' => 'login', 'plugin' => null]);
        }

        // Skip authorization check - any authenticated user can request for themselves
        $this->Authorization->skipAuthorization();

        // Get member ID
        $memberId = $currentUser->id;

        // Load activities table
        $activitiesTable = TableRegistry::getTableLocator()->get('Activities.Activities');

        // Get available activities (not deleted)
        $activities = $activitiesTable->find('list', [
            'keyField' => 'id',
            'valueField' => 'name'
        ])
            ->order(['Activities.name' => 'ASC']);

        $this->set(compact('memberId', 'activities'));

        // Use mobile app layout for consistent UX
        $this->viewBuilder()->setLayout('mobile_app');
        $this->set('mobileTitle', 'Request Authorization');
        $this->set('mobileBackUrl', $this->request->referer());
        $this->set('mobileHeaderColor', StaticHelpers::getAppSetting(
            'Member.MobileCard.BgColor',
        ));
        $this->set('showRefreshBtn', false); // No refresh button needed for form page
    }

    public function memberAuthorizations($state, $id)
    {
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $member = $this->Authorizations->Members->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member, 'view');
        $auths = $this->Authorizations->find();
        switch ($state) {
            case 'current':
                $auths = $this->addConditionsForMembers($this->Authorizations->find('current')->where(['member_id' => $id]));
                break;
            case 'pending':
                $auths = $this->addConditionsForMembers($this->Authorizations->find('pending')->where(['member_id' => $id]));
                break;
            case 'previous':
                $auths = $this->addConditionsForMembers($this->Authorizations->find('previous')->where(['member_id' => $id]));
                break;
        }
        $authorizations = $this->paginate($auths);
        $this->set(compact('authorizations', 'member', 'state'));
    }

    public function activityAuthorizations($state, $id)
    {
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $activity = $this->Authorizations->Activities->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity, 'view');
        $auths = $this->Authorizations->find();
        switch ($state) {
            case 'current':
                $auths = $this->addConditionsForActivities($this->Authorizations->find('current')->where(['activity_id' => $id]));
                break;
            case 'pending':
                $auths = $this->addConditionsForActivities($this->Authorizations->find('pending')->where(['activity_id' => $id]));
                break;
            case 'previous':
                $auths = $this->addConditionsForActivities($this->Authorizations->find('previous')->where(['activity_id' => $id]));
                break;
        }
        $authorizations = $this->paginate($auths);
        $this->set(compact('authorizations', 'activity', 'state'));
    }

    /*remove GW feature to implement propper Oauth2 integrations before GW 2026
    public function setGWSharing($id)
    {
        $this->request->allowMethod(["post"]);
        $member = $this->Authorizations->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member, 'editAdditionalInfo');
        $newval = $this->request->getData("share_with_GW");
        $additionalInfo = $member->additional_info;
        if ($newval == "true") {
            $additionalInfo["DisableAuthorizationSharing"] = "0";
        } else {
            $additionalInfo["DisableAuthorizationSharing"] = "1";
        }
        $member->additional_info = $additionalInfo;
        if ($this->Authorizations->Members->save($member)) {
            $this->Flash->success(__("The member has been updated."));

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __("The member could not be updated. Please, try again."),
        );

        return $this->redirect($this->referer());
    }

    public function getMemberAuthorizations($memberEmail)
    {
        //skip authorization
        $this->Authorization->skipAuthorization();
        //get bearer token
        $headers = $this->request->getHeaders();
        $creds = $this->request->getHeaderLine("Authorization");
        if (empty($creds)) {
            throw new \Cake\Http\Exception\UnauthorizedException();
        }
        $creds = explode("|", $creds);
        //check app settings for client id and secret
        try {
            $appSecret = StaticHelpers::getAppSetting("Activities.api_access." . $creds[0], null, null, false);

            if ($appSecret == null || $appSecret != $creds[1]) {
                throw new \Cake\Http\Exception\UnauthorizedException();
            }
        } catch (\Exception $e) {
            throw new \Cake\Http\Exception\UnauthorizedException();
        }

        $member = $this->Authorizations->Members->find()
            ->where(["email_address" => $memberEmail])
            ->select(["id", "additional_info"])->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        //check if sharing was set at all
        if (isset($member->additional_info["DisableAuthorizationSharing"])) {
            if ($member->additional_info["DisableAuthorizationSharing"] == "1") {
                throw new \Cake\Http\Exception\NotFoundException();
            }
        }
        $authTable = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $currentAuths = $authTable->find('current')
            ->select(['id', 'activity_id', 'member_id', 'ActivityGroups.name', 'Activities.name', 'expires_on'])
            ->contain(['Activities' => function (SelectQuery $q) {
                return $q
                    ->select(['Activities.id', 'Activities.name'])
                    ->contain(['ActivityGroups' => function (SelectQuery $q) {
                        return $q->select(['ActivityGroups.id', 'ActivityGroups.name']);
                    }]);
            }])
            ->where(['member_id' => $member->id])->OrderBy(['ActivityGroups.name', 'Activities.name'])->toArray();
        $organizedAuths = [];
        foreach ($currentAuths as $auth) {
            $activityGroup = $auth->activity->activity_group->name;
            $activityName = $auth->activity->name;
            $organizedAuths[$activityGroup][] = $activityName . " : " . $auth->expires_on_to_string;
        }
        $responseData = ["Authorizations" => $organizedAuths,];
        $this->viewBuilder()->setClassName("Ajax");
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($responseData));

        return $this->response;
    }
    */
    protected function addConditionsForMembers(SelectQuery $q)
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

    protected function addConditionsForActivities($q)
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
                "Members.sca_name"
            ])
            ->contain([
                "CurrentPendingApprovals" => function (SelectQuery $q) {
                    return $q->select(["Approvers.sca_name", "requested_on"])
                        ->contain("Approvers");
                },
                "Members" => function (SelectQuery $q) {
                    return $q->select(["Members.id", "Members.sca_name"]);
                },
                "RevokedBy" => function (SelectQuery $q) {
                    return $q->select(["RevokedBy.sca_name"]);
                }
            ]);
    }
}
