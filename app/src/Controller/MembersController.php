<?php

declare(strict_types=1);

namespace App\Controller;

use Activities\Model\Entity\Authorization;
use App\Form\ResetPasswordForm;
use App\KMP\PermissionsLoader;
use App\Services\MartialAuthorizations\AuthorizationManagerInterface;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use Composer\Util\Url;
use Cake\Routing\Router;
use Cake\Http\Exception\NotFoundException;

/**
 * Members Controller
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MembersController extends AppController
{
    use MailerAwareTrait;

    /**
     * controller filters
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            "login",
            "approversList",
            "forgotPassword",
            "resetPassword",
            "register",
            "viewMobileCard",
            "viewMobileCardJson",
            "searchMembers",
            "publicProfile",
            "emailTaken",
            "autoComplete",
        ]);
    }

    #region general use calls

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $search = $this->request->getQuery("search");
        $search = $search ? trim($search) : null;
        // get sort and direction from query string
        $sort = $this->request->getQuery("sort");
        $direction = $this->request->getQuery("direction");

        $query = $this->Members
            ->find()
            ->contain(["Branches"])
            ->select([
                "Members.id",
                "Members.sca_name",
                "Members.first_name",
                "Members.last_name",
                "Branches.name",
                "Members.status",
                "Members.email_address",
                "Members.last_login"
            ]);
        // if there is a search term, filter the query
        if ($search) {
            $query = $query->where([
                "OR" => [
                    "Members.sca_name LIKE" => "%" . $search . "%",
                    "Members.first_name LIKE" => "%" . $search . "%",
                    "Members.last_name LIKE" => "%" . $search . "%",
                    "Members.email_address LIKE" => "%" . $search . "%",
                    "Branches.name LIKE" => "%" . $search . "%",
                ],
            ]);
        }
        #is
        $this->Authorization->authorize($query);
        $query = $this->Authorization->applyScope($query);

        $this->paginate = [
            'sortableFields' => [
                'Branches.name',
                'sca_name',
                'first_name',
                'last_name',
                'email_address',
                'status',
                'last_login',
            ],
        ];

        $Members = $this->paginate($query, [
            'order' => [
                'sca_name' => 'asc',
            ]
        ]);

        $this->set(compact("Members", "sort", "direction", "search"));
    }


    public function verifyQueue()
    {
        $activeTab = $this->request->getQuery("activeTab");
        $activeTab = $activeTab ? trim($activeTab) : null;
        // get sort and direction from query string
        $sort = $this->request->getQuery("sort");
        $direction = $this->request->getQuery("direction");

        $query = $this->Members
            ->find()
            ->contain(["Branches"])
            ->select([
                "Members.id",
                "Members.sca_name",
                "Members.first_name",
                "Members.last_name",
                "Branches.name",
                "Members.status",
                "Members.email_address",
                "Members.membership_card_path",
                "Members.birth_year",
                "Members.birth_month",
            ]);
        $query = $query->where([
            'Members.status IN' => [
                Member::STATUS_ACTIVE,
                Member::STATUS_UNVERIFIED_MINOR,
                Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
                Member::STATUS_MINOR_PARENT_VERIFIED
            ]
        ]);
        #is
        $this->Authorization->authorize($query);
        $query = $this->Authorization->applyScope($query);
        $Members = $query->all();

        $this->set(compact("Members"));
    }

    /**
     * View method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        // Get Member Details
        $memberQuery = $this->Members
            ->find()
            ->contain([
                "Roles",
                "Branches",
                "Parents" => function (SelectQuery $q) {
                    return $q->select(["Parents.sca_name", "Parents.id"]);
                },
                "UpcomingMemberRoles" => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                "CurrentMemberRoles" => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                "PreviousMemberRoles" => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
            ])
            ->where(["Members.id" => $id]);
        //$memberQuery = $this->Members->addJsonWhere($memberQuery, "Members.additional_info", "$.sports", "football");
        $member = $memberQuery->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        // Create the new Note form
        $session = $this->request->getSession();
        // Get the member form data for the edit modal
        $memberForm = $this->Members->get($id);
        // If there is form data in the session, patch the entity so we can show the errors
        $memberFormData = $session->consume("memberFormData");
        if ($memberFormData != null) {
            $this->Members->patchEntity($memberForm, $memberFormData);
        }
        // Get the password reset form data for the change password modal so we can show errors
        $passwordResetData = $session->consume("passwordResetData");
        $passwordReset = new ResetPasswordForm();
        if (!$passwordResetData == null) {
            $passwordReset->setData($passwordResetData);
            $passwordReset->validate($passwordResetData);
        }
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));
            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
        $referer = $this->request->referer(true);
        $backUrl = [];
        $user =  $this->Authentication->getIdentity();
        $statusList = [
            Member::STATUS_ACTIVE => Member::STATUS_ACTIVE,
            Member::STATUS_DEACTIVATED => Member::STATUS_DEACTIVATED,
            Member::STATUS_VERIFIED_MEMBERSHIP => Member::STATUS_VERIFIED_MEMBERSHIP,
            Member::STATUS_UNVERIFIED_MINOR => Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED => Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
            Member::STATUS_MINOR_PARENT_VERIFIED => Member::STATUS_MINOR_PARENT_VERIFIED,
            Member::STATUS_VERIFIED_MINOR => Member::STATUS_VERIFIED_MINOR,
        ];
        $this->set(
            compact(
                "member",
                "treeList",
                "passwordReset",
                "memberForm",
                "months",
                "years",
                "backUrl",
                "statusList"
            ),
        );
    }
    public function viewCard($id = null)
    {
        $member = $this->Members
            ->find()
            ->select('id')
            ->where(["Members.id" => $id])
            ->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);
        // sort by name
        $message_variables = [
            "secretary_email" => StaticHelpers::getAppSetting(
                "Activity.SecretaryEmail",
                "please_set",
            ),
            "kingdom" => StaticHelpers::getAppSetting(
                "KMP.KingdomName",
                "please_set",
            ),
            "secratary" => StaticHelpers::getAppSetting(
                "Activity.SecretaryName",
                "please_set",
            ),
            "marshal_auth_graphic" => StaticHelpers::getAppSetting(
                "Member.ViewCard.Graphic",
                "auth_card_back.gif",
            ),
            "marshal_auth_header_color" => StaticHelpers::getAppSetting(
                "Member.ViewCard.HeaderColor",
                "gold",
            ),
        ];
        $this->set(compact("member", "message_variables"));
        $customTemplate = StaticHelpers::getAppSetting(
            "Member.ViewCard.Template",
            "view_card",
        );
        $this->viewBuilder()->setTemplate($customTemplate);
    }

    public function viewMobileCard($id = null)
    {
        $inactiveStatuses = [
            Member::STATUS_DEACTIVATED,
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
        ];
        $member = $this->Members
            ->find()
            ->select('mobile_card_token')
            ->where(["Members.mobile_card_token" => $id, "Members.status NOT IN" => $inactiveStatuses])
            ->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->skipAuthorization();
        // sort filter out expired member roles
        $message_variables = [
            "secretary_email" => StaticHelpers::getAppSetting(
                "Activity.SecretaryEmail",
                "please_set",
            ),
            "kingdom" => StaticHelpers::getAppSetting(
                "KMP.KingdomName",
                "please_set",
            ),
            "secratary" => StaticHelpers::getAppSetting(
                "Activity.SecretaryName",
                "please_set",
            ),
            "marshal_auth_graphic" => StaticHelpers::getAppSetting(
                "Member.ViewCard.Graphic",
                "auth_card_back.gif",
            ),
            "marshal_auth_header_color" => StaticHelpers::getAppSetting(
                "Member.MobileCard.BgColor",
                "gold",
            ),
        ];
        $this->set(compact("member", "message_variables"));
        $customTemplate = StaticHelpers::getAppSetting(
            "Member.ViewMobileCard.Template",
            "view_mobile_card",
        );
        $this->viewBuilder()->setTemplate($customTemplate);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->authorize($member);
        if ($this->request->is("post")) {
            $member = $this->Members->patchEntity(
                $member,
                $this->request->getData(),
            );
            $member->password = StaticHelpers::generateToken(32);
            if ($member->getErrors()) {
                $this->Flash->error(
                    __("The Member could not be saved. Please, try again."),
                );
                $months = array_reduce(range(1, 12), function ($rslt, $m) {
                    $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));
                    return $rslt;
                });
                $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
                $treeList = $this->Members->Branches
                    ->find("list", keyPath: function ($entity) {
                        return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
                    })
                    ->where(["can_have_members" => true])
                    ->orderBy(["name" => "ASC"])->toArray();
                $this->set(compact(
                    "member",
                    "treeList",
                    "months",
                    "years",
                ));

                return;
            }
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            $member->password = StaticHelpers::generateToken(16);
            if ($member->age < 18) {
                $member->status = Member::STATUS_UNVERIFIED_MINOR;
            } else {
                $member->status = Member::STATUS_ACTIVE;
            }
            if ($this->Members->save($member)) {
                if ($member->age < 18) {
                    $this->Flash->success(__("The Member has been saved and the minor registration email has been sent for verification."));
                    $this->getMailer("KMP")->send("notifySecretaryOfNewMinorMember", [$member]);
                } else {
                    $this->Flash->success(__("The Member has been saved. Please ask the member to use 'forgot password' to set their password."));
                }
                return $this->redirect(["action" => "view", $member->id]);
            }
            $this->Flash->error(
                __("The Member could not be saved. Please, try again."),
            );
        }
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));
            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
        $this->set(compact(
            "member",
            "treeList",
            "months",
            "years",
        ));
    }

    /**
     * Edit method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is(["patch", "post", "put"])) {
            $member = $this->Members->patchEntity(
                $member,
                $this->request->getData(),
            );
            if ($member->getErrors()) {
                $session = $this->request->getSession();
                $session->write("memberFormData", $this->request->getData());

                return $this->redirect(["action" => "view", $member->id]);
            }
            if ($member->membership_number == null || $member->membership_number == "") {
                $member->membership_expires_on = null;
                switch ($member->status) {
                    case Member::STATUS_VERIFIED_MEMBERSHIP:
                        $member->status = Member::STATUS_ACTIVE;
                        break;
                    case Member::STATUS_MINOR_MEMBERSHIP_VERIFIED:
                        $member->status = Member::STATUS_UNVERIFIED_MINOR;
                        break;
                    case Member::STATUS_VERIFIED_MINOR:
                        $member->status = Member::STATUS_MINOR_PARENT_VERIFIED;
                        break;
                }
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__("The Member has been saved."));

                return $this->redirect(["action" => "view", $member->id]);
            }
            $this->Flash->error(
                __("The Member could not be saved. Please, try again."),
            );
        }
        // $this->redirect(['action' => 'view', $member->id]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $member = $this->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);

        $member->email_address = "Deleted: " . $member->email_address;
        if ($this->Members->delete($member)) {
            $this->Flash->success(__("The Member has been deleted."));
        } else {
            $this->Flash->error(
                __("The Member could not be deleted. Please, try again."),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
    #endregion

    #region Member Specific calls

    public function sendMobileCardEmail($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($member->mobile_card_token == null || $member->mobile_card_token == "") {
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            $this->Members->save($member);
        }
        $this->getMailer("KMP")->send("mobileCard", [$member]);
        $this->Flash->success(__("The email has been sent."));

        return $this->redirect(["action" => "view", $member->id]);
    }

    public function partialEdit($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is(["patch", "post", "put"])) {
            $member->sca_name = $this->request->getData("sca_name");
            $member->branch_id = $this->request->getData("branch_id");
            $member->first_name = $this->request->getData("first_name");
            $member->middle_name = $this->request->getData("middle_name");
            $member->last_name = $this->request->getData("last_name");
            $member->street_address = $this->request->getData("street_address");
            $member->city = $this->request->getData("city");
            $member->state = $this->request->getData("state");
            $member->zip = $this->request->getData("zip");
            $member->phone_number = $this->request->getData("phone_number");
            $member->email_address = $this->request->getData("email_address");
            //$member->parent_name = $this->request->getData("parent_name");
            if ($member->getErrors()) {
                $session = $this->request->getSession();
                $session->write("memberFormData", $this->request->getData());

                return $this->redirect(["action" => "view", $member->id]);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__("The Member has been saved."));

                return $this->redirect(["action" => "view", $member->id]);
            }
            $this->Flash->error(
                __("The Member could not be saved. Please, try again."),
            );
        }
        $this->redirect(["action" => "view", $member->id]);
    }

    public function editAdditionalInfo($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);
        $user = $this->Authentication->getIdentity();
        $userEditableOnly = !$user->can("edit", $member);
        if ($this->request->is(["patch", "post", "put"])) {
            $member->additional_info = [];
            $aiFormConfig = StaticHelpers::getAppSettingsStartWith("Member.AdditionalInfo.");
            $aiForm = [];
            if (empty($aiFormConfig)) {
                $this->Flash->error(
                    __("The Additional Information could not be saved. Please, try again."),
                );
                return $this->redirect(["action" => "view", $member->id]);
            }
            foreach ($aiFormConfig as $key => $value) {
                $shortKey = str_replace("Member.AdditionalInfo.", "", $key);
                $aiForm[$shortKey] = $value;
            }
            foreach ($aiForm as $fieldKey => $fieldType) {
                $userEditable = false;
                //check fieldType for |
                $pipePos = strpos($fieldType, "|");
                if ($pipePos !== false) {
                    $fieldSecDetails = explode("|", $fieldType);
                    $fieldType = $fieldSecDetails[0];
                    $userEditable = $fieldSecDetails[1] == "user";
                }
                if ($userEditableOnly && !$userEditable) {
                    continue;
                }
                $colonPos = strpos($fieldType, ":");
                $aiOptions = [];
                if ($colonPos !== false) {
                    $fieldDetails = explode(":", $fieldType);
                    $fieldType =  $fieldDetails[0];
                    $aiOptions = explode(",", $fieldDetails[1]);
                }
                //if aiOptions are not emoty then check the value is one of the options
                $fieldValue = $this->request->getData($fieldKey);
                if (!empty($aiOptions)) {
                    if ($fieldValue != "" && !in_array($fieldValue, $aiOptions)) {
                        $this->Flash->error(
                            __("The Additional Information could not be saved. Please, try again."),
                        );
                        return $this->redirect(["action" => "view", $member->id]);
                    }
                }
                if ($fieldValue != "") {
                    $newData[$fieldKey] = $this->request->getData($fieldKey);
                }
            }
            $member->additional_info = $newData;
            if ($this->Members->save($member)) {
                $this->Flash->success(__("The Additional Information saved."));

                return $this->redirect(["action" => "view", $member->id]);
            }
            $this->Flash->error(
                __("The Additional Information could not be saved. Please, try again."),
            );
        }
        return $this->redirect(["action" => "view", $member->id]);
    }
    #endregion

    #region ASYNC calls
    public function searchMembers()
    {
        $q = $this->request->getQuery("q");
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $query = $this->Members
            ->find("all")
            ->where(["sca_name LIKE" => "%$q%"])
            ->select(["id", "sca_name"])
            ->limit(10);
        //$query = $this->Authorization->applyScope($query);
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($query));
        return $this->response;
    }

    public function viewCardJson($id = null)
    {
        $member = $this->Members
            ->find()
            ->select([
                "Members.id",
                "Members.sca_name",
                "Members.title",
                "Members.first_name",
                "Members.last_name",
                "Members.membership_number",
                "Members.membership_expires_on",
                "Members.background_check_expires_on",
                "Members.additional_info",
            ])
            ->contain([
                "Branches" => function (SelectQuery $q) {
                    return $q->select(["Branches.name"]);
                }
            ])
            ->where(["Members.id" => $id])
            ->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($member->title) {
            $member->sca_name = $member->title . " " . $member->sca_name;
        }
        $this->Authorization->authorize($member);
        $this->viewBuilder()
            ->setClassName("Ajax")
            ->setOption('serialize', 'responseData');
        $this->set(compact("member"));
    }
    public function viewMobileCardJson($id = null)
    {
        $inactiveStatuses = [
            Member::STATUS_DEACTIVATED,
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
        ];
        $member = $this->Members
            ->find()
            ->select([
                "Members.id",
                "Members.title",
                "Members.sca_name",
                "Members.first_name",
                "Members.last_name",
                "Members.membership_number",
                "Members.membership_expires_on",
                "Members.background_check_expires_on",
                "Members.additional_info",
            ])
            ->contain([
                "Branches" => function (SelectQuery $q) {
                    return $q->select(["Branches.name"]);
                }
            ])
            ->where(["Members.mobile_card_token" => $id, "Members.status NOT IN" => $inactiveStatuses])
            ->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($member->title) {
            $member->sca_name = $member->title . " " . $member->sca_name;
        }
        $this->Authorization->skipAuthorization();
        $this->viewBuilder()
            ->setClassName("Ajax")
            ->setOption('serialize', 'responseData');
        $this->set(compact("member"));
        $this->viewBuilder()->setTemplate('view_card_json');
    }
    public function publicProfile($id = null)
    {
        $member = $this->Members
            ->find()
            ->where(["Members.id" => $id])
            ->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->skipAuthorization();
        $publicProfile = [
            "sca_name" => $member->sca_name,
            "branch" => $member->branch->name,
            "external_links" => $member->publicLinks(),
            "additional_info" => $member->publicAdditionalInfo(),
        ];
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($publicProfile));
        return $this->response;
    }
    public function autoComplete()
    {
        $q = $this->request->getQuery("q");
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match("/th/", $q)) {
            $nq = str_replace("th", "Þ", $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match("/Þ/", $q)) {
            $uq = str_replace("Þ", "th", $q);
        }

        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $query = $this->Members
            ->find("all")
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [["sca_name LIKE" => "%$q%"], ["sca_name LIKE" => "%$nq%"], ["sca_name LIKE" => "%$uq%"]]
            ])
            ->select(["id", "sca_name"])
            ->limit(50);
        $this->set(compact("query", "q", "nq", "uq"));
    }

    public function emailTaken()
    {
        $email = $this->request->getQuery("email");
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $emailUsed = $this->Members
            ->find("all")
            ->where(["email_address" => $email])
            ->count();
        $result = "";
        if ($emailUsed > 0) {
            $result = true;
        } else {
            $result = false;
        }
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($result));
        return $this->response;
    }

    #endregion

    #region Password specific calls
    public function changePassword($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member);
        $passwordReset = new ResetPasswordForm();
        if ($this->request->is(["patch", "post", "put"])) {
            $passwordReset->validate($this->request->getData());
            if ($passwordReset->getErrors()) {
                $session = $this->request->getSession();
                $session->write("passwordResetData", $this->request->getData());

                return $this->redirect(["action" => "view", $member->id]);
            }
            $member->password = $this->request->getData()["new_password"];
            $member->password_token = null;
            $member->password_token_expires_on = null;
            if ($this->Members->save($member)) {
                $this->Flash->success(__("The password has been changed."));

                return $this->redirect(["action" => "view", $member->id]);
            }
            $this->Flash->error(
                __("The password could not be changed. Please, try again."),
            );
        }
    }

    public function forgotPassword()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is("post")) {
            $member = $this->Members
                ->find()
                ->where([
                    "email_address" => $this->request->getData("email_address"),
                ])
                ->first();
            if ($member) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(
                    1,
                );
                $this->Members->save($member);
                $this->getMailer("KMP")->send("resetPassword", [$member]);
                $this->Flash->success(
                    __(
                        "Password reset request sent to " .
                            $member->email_address,
                    ),
                );

                return $this->redirect(["action" => "login"]);
            } else {
                $this->Flash->error(
                    __(
                        "Your email was not found, please contact the Marshalate Secretary at " .
                            StaticHelpers::getAppSetting(
                                "Activity.SecretaryEmail",
                                ""
                            ),
                    ),
                );
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            "KMP.Login.Graphic",
            "populace_badge.png",
        );
        $this->set(compact("headerImage"));
    }

    public function resetPassword($token = null)
    {
        $this->Authorization->skipAuthorization();
        $member = $this->Members
            ->find()
            ->where(["password_token" => $token])
            ->first();
        if ($member) {
            if ($member->password_token_expires_on < DateTime::now()) {
                $this->Flash->error("Invalid Token, please request a new one.");

                return $this->redirect(["action" => "forgotPassword"]);
            }
            $passwordReset = new ResetPasswordForm();
            if (
                $this->request->is("post") &&
                $passwordReset->validate($this->request->getData())
            ) {
                $member->password = $this->request->getData("new_password");
                $member->password_token = null;
                $member->password_token_expires_on = null;
                $this->Members->save($member);
                $this->Flash->success(__("Password successfully reset"));

                return $this->redirect(["action" => "login"]);
            }
            $headerImage = StaticHelpers::getAppSetting(
                "KMP.Login.Graphic",
                "populace_badge.png",
            );
            $this->set(compact("headerImage", "passwordReset"));
        } else {
            $this->Flash->error("Invalid Token, please request a new one.");

            return $this->redirect(["action" => "forgotPassword"]);
        }
    }
    #endregion

    #region Authorization specific calls

    /**
     * login logic
     */
    public function login()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is("post")) {
            $authentication = $this->request->getAttribute("authentication");
            $result = $authentication->getResult();
            // regardless of POST or GET, redirect if user is logged in
            if ($result->isValid()) {
                $user = $this->Members->get(
                    $authentication->getIdentity()->getIdentifier(),
                );
                $this->Flash->success("Welcome " . $user->sca_name . "!");
                $page = $this->request->getQuery("redirect");
                if (
                    $page == "/" ||
                    $page == "/Members/login" ||
                    $page == "/Members/logout" ||
                    $page == null
                ) {
                    return $this->redirect(["action" => "view", $user->id]);
                } else {
                    return $this->redirect($page);
                }
            }
            $errors = $result->getErrors();
            if (
                isset($errors["KMPBruteForcePassword"]) &&
                count($errors["KMPBruteForcePassword"]) > 0
            ) {
                $message = $errors["KMPBruteForcePassword"][0];
                switch ($message) {
                    case "Account Locked":
                        $this->Flash->error(
                            "Your account has been locked. Please try again later.",
                        );
                        break;
                    case "Account Not Verified":
                        $contactAddress = StaticHelpers::getAppSetting(
                            "Members.AccountVerificationContactEmail",
                            "please_set",
                        );
                        $this->Flash->error(
                            "Your account is being verified. This process may take several days after you have verified your email address. Please contact " . $contactAddress . " if you have not been verified within a week."
                        );
                        break;
                    case "Account Disabled":
                        $contactAddress = StaticHelpers::getAppSetting(
                            "Members.AccountDisabledContactEmail",
                            "please_set",
                        );
                        $this->Flash->error(
                            "Your account deactivated. Please contact " . $contactAddress . " if you feel this is in error.",
                        );
                        break;
                    default:
                        $this->Flash->error(
                            "Your email or password is incorrect.",
                        );
                        break;
                }
            } else {
                $this->Flash->error("Your email or password is incorrect.");
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            "KMP.Login.Graphic",
            "populace_badge.png",
        );
        $allowRegistration = StaticHelpers::getAppSetting(
            "KMP.EnablePublicRegistration",
            "yes",
        );
        $this->set(compact("headerImage", "allowRegistration"));
    }

    public function logout()
    {
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();

        return $this->redirect([
            "controller" => "Members",
            "action" => "login",
        ]);
    }

    public function register()
    {
        $allowRegistration = StaticHelpers::getAppSetting(
            "KMP.EnablePublicRegistration",
            "yes",
        );
        if (strtolower($allowRegistration) != "yes") {
            $this->Flash->error(
                "Public registration is not allowed at this time.",
            );
            return $this->redirect(["action" => "login"]);
        }
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();
        if ($this->request->is("post")) {

            $file = $this->request->getData("member_card");
            if ($file->getSize() > 0) {
                $storageLoc = WWW_ROOT . '../images/uploaded/';
                $fileName = StaticHelpers::generateToken(10);
                StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
                $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
                $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
                if (!$fileResult) {
                    $this->Flash->error("Error saving image, please try again.");
                }
                //trim the path off of the filename
                $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);
                $member->membership_card_path = $fileName;
            }
            $member->sca_name = $this->request->getData("sca_name");
            $member->branch_id = $this->request->getData("branch_id");
            $member->first_name = $this->request->getData("first_name");
            $member->middle_name = $this->request->getData("middle_name");
            $member->last_name = $this->request->getData("last_name");
            $member->street_address = $this->request->getData("street_address");
            $member->city = $this->request->getData("city");
            $member->state = $this->request->getData("state");
            $member->zip = $this->request->getData("zip");
            $member->phone_number = $this->request->getData("phone_number");
            $member->email_address = $this->request->getData("email_address");
            $member->birth_month = (int) $this->request->getData("birth_month");
            $member->birth_year = (int) $this->request->getData("birth_year");
            if ($member->age > 17) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(1);
            }
            $member->password = StaticHelpers::generateToken(12);
            if ($member->getErrors()) {

                return $this->redirect(["action" => "view", $member->id]);
            }
            if ($member->age > 17) {
                $member->status = Member::STATUS_ACTIVE;
            } else {
                $member->status = Member::STATUS_UNVERIFIED_MINOR;
            }
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            if ($this->Members->save($member)) {
                if ($member->age > 17) {
                    $this->Flash->success(__("Your registration has been submitted. Please check your email for a link to set up your password."));
                    $this->getMailer("KMP")->send("newRegistration", [$member]);
                    $this->getMailer("KMP")->send("notifySecretaryOfNewMember", [$member]);
                } else {
                    $this->Flash->success(__("Your registration has been submitted. The Kingdom Secretary will need to verify your account with your parent or guardian"));
                    $this->getMailer("KMP")->send("notifySecretaryOfNewMinorMember", [$member]);
                }

                return $this->redirect(["action" => "login"]);
            }
            $this->Flash->error(
                __("The Member could not be saved. Please, try again."),
            );
        }
        $headerImage = StaticHelpers::getAppSetting(
            "KMP.Login.Graphic",
            "populace_badge.png",
        );
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));
            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();

        $this->set(compact("member", "treeList", "months", "years", "headerImage"));
    }

    #endregion

    #region Import/Export calls

    /**
     * Import Member Expiration dates from CSV based on Membership number
     */
    public function importExpirationDates()
    {
        $this->Authorization->authorize($this->Members->newEmptyEntity());
        if ($this->request->is("post")) {
            $file = $this->request->getData("importData");
            $file = $file->getStream()->getMetadata("uri");
            $csv = array_map("str_getcsv", file($file));
            $this->Members->getConnection()->begin();
            foreach ($csv as $row) {
                if (
                    "Member Number" == $row[0] ||
                    "Expiration Date" == $row[1]
                ) {
                    continue;
                }

                $member = $this->Members
                    ->find()
                    ->where(["membership_number" => $row[0]])
                    ->first();
                if ($member) {
                    $member->membership_expires_on = new DateTime($row[1]);
                    $member->setDirty("membership_expires_on", true);
                    if (!$this->Members->save($member)) {
                        $this->Members->getConnection()->rollback();
                        $this->Flash->error(
                            __(
                                "Error saving member expiration date at " .
                                    $row[0] .
                                    " with date " .
                                    $row[1] .
                                    ". All modified have been rolled back.",
                            ),
                        );

                        return;
                    }
                }
            }
            $this->Members->getConnection()->commit();
            $this->Flash->success(__("Expiration dates imported successfully"));
        }
    }

    #endregion

    #region Verification calls
    public function verifyMembership($id = null)
    {
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        if ($this->request->is(["patch", "post", "put"])) {
            $verifyMembership = $this->request->getData("verify_membership");
            $verifyParent = $this->request->getData("verify_parent");
            if ($verifyMembership == "1") {
                $membership_number = $this->request->getData("membership_number");
                if (strlen($membership_number) == 0) {
                    $this->Flash->error("Membership number is required.");
                    return $this->redirect(["action" => "view", $member->id]);
                }
                $member->membership_expires_on = $this->request->getData("membership_expires_on");
                if ($member->membership_expires_on == null) {
                    $this->Flash->error("Membership expiration date is required.");
                    return $this->redirect(["action" => "view", $member->id]);
                }
                $member->membership_number = $membership_number;
                $member->membership_expires_on = $this->request->getData("membership_expires_on");
            }
            if ($member->age < 18 && $verifyParent == "1") {
                $parentId = $this->request->getData("parent_id");
                if ($parentId) {
                    if ($parentId && strlen($parentId) > 0)

                        $parent = $this->Members->get($parentId);
                    if ($parentId == $member->id) {
                        $this->Flash->error("Parent cannot be the same as the member.");
                        return $this->redirect(["action" => "view", $member->id]);
                    }
                    if ($parent->age < 18) {
                        $this->Flash->error("Parent must be an adult.");
                        return $this->redirect(["action" => "view", $member->id]);
                    }
                    $member->parent_id = $parent->id;
                } else {
                    $this->Flash->error("Parent is required for minors.");
                    return $this->redirect(["action" => "view", $member->id]);
                }
            }
            //if the member is an adult and the membership was validated then set the status to active
            if ($member->age > 17 && $verifyMembership == "1") {
                $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
            }
            //if the member is a minor and the parent was validated then set the status to verified minor
            if ($member->age < 18 && $verifyParent == "1" && $verifyMembership == "1") {
                $member->status = Member::STATUS_VERIFIED_MINOR;
            }
            //if the member is a minor and the parent was validated then set the status to parent validataed
            if ($member->age < 18 && $verifyParent == "1" && $verifyMembership != "1") {
                //if the member is already membership verified then set to minor verified
                if ($member->status == Member::STATUS_MINOR_MEMBERSHIP_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_PARENT_VERIFIED;
                }
            }
            //if the the member is a minor and the parent was not validated by the membership was then set the status to minor membership verified
            if ($member->age < 18 && $verifyParent != "1" && $verifyMembership == "1") {

                if ($member->status == Member::STATUS_MINOR_PARENT_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_MEMBERSHIP_VERIFIED;
                }
            }
            $image = $member->membership_card_path;
            $deleteImage =  $member->status == Member::STATUS_VERIFIED_MEMBERSHIP ||
                $member->status == Member::STATUS_VERIFIED_MINOR ||
                $member->status == Member::STATUS_MINOR_MEMBERSHIP_VERIFIED;

            $member->verified_by = $this->Authentication->getIdentity()->getIdentifier();
            $member->verified_date = DateTime::now();
            if ($deleteImage) {
                $member->membership_card_path = null;
            }
            if (!$this->Members->save($member)) {
                $this->Flash->error(
                    __("The Member could not be verified. Please, try again."),
                );
                $this->redirect(["action" => "view", $member->id]);
            }
            if ($image != null && $deleteImage) {
                $image = WWW_ROOT . '../images/uploaded/' . $image;
                $member->membership_card_path = null;
                if (!StaticHelpers::deleteFile($image)) {
                    $this->Flash->error("Error deleting image, please try again.");
                    return $this->redirect(["action" => "view", $member->id]);
                }
            }
        }
        $this->Flash->success(__("The Membership has been verified."));
        return $this->redirect(["action" => "view", $member->id]);
    }
    #endregion

    #region protected
    protected function _addRolesSelectAndContain(SelectQuery $q)
    {
        return $q
            ->select([
                "id",
                "member_id",
                "role_id",
                "start_on",
                "expires_on",
                "role_id",
                "approver_id",
                "granting_model"
            ])
            ->contain([
                "Roles" => function (SelectQuery $q) {
                    return $q->select(["Roles.name"]);
                },
                "ApprovedBy" => function (SelectQuery $q) {
                    return $q->select(["ApprovedBy.sca_name"]);
                }
            ]);
    }
    #endregion
}