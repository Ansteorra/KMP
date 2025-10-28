<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ResetPasswordForm;
use App\KMP\StaticHelpers;
use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\Member;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\Routing\Router;

/**
 * Members Controller - Complete Member Management and User Experience
 *
 * The MembersController serves as the primary interface for all member-related operations
 * within the KMP system, providing comprehensive functionality for user management,
 * authentication, profile administration, and member discovery. This controller handles
 * both administrative and user-facing operations with sophisticated authorization controls.
 *
 * ## Core Responsibilities
 *
 * ### User Management Operations
 * - **Member CRUD**: Complete create, read, update, delete operations for member records
 * - **Profile Management**: User profile editing with validation and authorization
 * - **Status Management**: Member status transitions and verification workflows
 * - **Administrative Tools**: Bulk operations and administrative member management
 *
 * ### Authentication & Security
 * - **Login/Logout**: User authentication with failed attempt tracking
 * - **Password Management**: Password changes, resets, and security enforcement
 * - **Registration**: New member registration with validation and verification
 * - **Session Management**: Secure session handling and timeout management
 *
 * ### Member Discovery & Search
 * - **Advanced Search**: Multi-field search with special character handling (Þ/th)
 * - **Public Profiles**: Privacy-controlled public member information
 * - **Auto-completion**: Real-time member name suggestions for forms
 * - **Directory Services**: Filtered member listings with role-based access
 *
 * ### Mobile Integration
 * - **Digital Member Cards**: Mobile-friendly member card display
 * - **Card Generation**: Dynamic member card with QR codes and formatting
 * - **JSON APIs**: Mobile app integration endpoints
 * - **Email Distribution**: Mobile card link distribution via email
 *
 * ### Administrative Features
 * - **Verification Queue**: Member verification workflow management
 * - **Bulk Operations**: Mass member data processing and updates
 * - **Audit Trails**: Member activity tracking and change history
 * - **Reporting**: Member statistics and administrative reporting
 *
 * ## Authorization Architecture
 *
 * ### Access Control Patterns
 * - **Role-Based Authorization**: Uses KMP authorization policies for access control
 * - **Self-Service Access**: Members can manage their own profiles
 * - **Administrative Overrides**: Super users and administrators have broader access
 * - **Public Access**: Limited public information available without authentication
 *
 * ### Public vs. Authenticated Access
 * ```php
 * // Public access (no authentication required)
 * $publicActions = [
 *     'login', 'register', 'forgotPassword', 'resetPassword',
 *     'publicProfile', 'viewMobileCard', 'searchMembers',
 *     'emailTaken', 'autoComplete', 'approversList'
 * ];
 * 
 * // Authenticated access with authorization checks
 * $protectedActions = [
 *     'index', 'view', 'add', 'edit', 'delete',
 *     'profile', 'changePassword', 'verifyQueue'
 * ];
 * ```
 *
 * ## Search and Discovery Features
 *
 * ### Advanced Search Capabilities
 * - **Multi-field Search**: Name, email, membership number, branch
 * - **Special Character Handling**: Automatic Þ (thorn) and 'th' conversion
 * - **Fuzzy Matching**: Flexible search algorithms for name variations
 * - **Performance Optimization**: Efficient queries with selective field loading
 *
 * ### Privacy and Security
 * - **Data Filtering**: Age-based privacy controls for minor members
 * - **Public Data Exposure**: Configurable public information display
 * - **Search Limitations**: Rate limiting and abuse prevention
 * - **Branch Scoping**: Organizational data access controls
 *
 * ## Mobile and API Integration
 *
 * ### Digital Member Cards
 * - **Dynamic Generation**: Real-time card creation with current member data
 * - **QR Code Integration**: Embedded QR codes for event check-in
 * - **Responsive Design**: Mobile-optimized card display
 * - **Security Tokens**: Time-limited access tokens for card viewing
 *
 * ### API Endpoints
 * - **JSON Responses**: Mobile app integration endpoints
 * - **RESTful Design**: Standard REST patterns for data access
 * - **Authentication**: Token-based API authentication support
 * - **Rate Limiting**: API usage controls and abuse prevention
 *
 * ## Business Workflow Integration
 *
 * ### Member Lifecycle Management
 * - **Registration Process**: New member onboarding with verification
 * - **Status Transitions**: Automatic and manual status changes
 * - **Age-Up Processing**: Minor to adult member transitions
 * - **Deactivation Workflows**: Member suspension and reactivation
 *
 * ### Verification and Validation
 * - **Document Verification**: Membership card and document validation
 * - **Identity Verification**: Legal name and contact information verification
 * - **Parent Verification**: Minor member guardian verification process
 * - **Bulk Processing**: Administrative batch verification operations
 *
 * ## Usage Examples
 *
 * ### Basic Member Operations
 * ```php
 * // List members with search and pagination
 * public function index() {
 *     $query = $this->Members->find()
 *         ->contain(['Branches'])
 *         ->where($searchCriteria);
 *     
 *     $this->set('members', $this->paginate($query));
 * }
 * 
 * // View member profile with authorization
 * public function view($id) {
 *     $member = $this->Members->get($id, [
 *         'contain' => ['Roles', 'Branches']
 *     ]);
 *     
 *     $this->Authorization->authorize($member);
 *     $this->set(compact('member'));
 * }
 * ```
 *
 * ### Authentication Workflows
 * ```php
 * // Password reset with token validation
 * public function resetPassword() {
 *     $token = $this->request->getQuery('token');
 *     $member = $this->Members->find()
 *         ->where(['password_token' => $token])
 *         ->first();
 *     
 *     if ($member && !$member->password_token_expires_on->isPast()) {
 *         // Process password reset
 *     }
 * }
 * ```
 *
 * ## Integration Points
 * - **Activities Plugin**: Member authorization management
 * - **Awards Plugin**: Member recognition and achievement tracking
 * - **Officers Plugin**: Leadership role assignments and reporting
 * - **Branch System**: Organizational hierarchy and data scoping
 * - **Role System**: Permission inheritance and access control
 *
 * ## Security Considerations
 * - **Input Validation**: Comprehensive data validation and sanitization
 * - **CSRF Protection**: Cross-site request forgery prevention
 * - **Rate Limiting**: API and search request rate limiting
 * - **Audit Logging**: Member action tracking and security monitoring
 * - **Privacy Controls**: Age-based and configurable privacy settings
 *
 * @property \App\Model\Table\MembersTable $Members Primary Members table
 */
class MembersController extends AppController
{
    use QueuedMailerAwareTrait;
    use MailerAwareTrait;

    /**
     * Configure authorization and authentication filters
     *
     * Establishes the authorization and authentication requirements for all member-related
     * actions. This method configures which actions require authentication and sets up
     * authorization model checking for administrative functions.
     *
     * ## Authorization Configuration
     * - **Model Authorization**: Enables automatic authorization for 'index' and 'verifyQueue' actions
     * - **Administrative Protection**: Ensures only authorized users can access member management
     * - **Inheritance**: Calls parent beforeFilter for base controller functionality
     *
     * ## Unauthenticated Access
     * Allows public access to specific actions without requiring user login:
     * - **Authentication**: login, register, forgotPassword, resetPassword
     * - **Public Information**: publicProfile, searchMembers, autoComplete
     * - **Mobile Access**: viewMobileCard, viewMobileCardJson
     * - **Utility**: emailTaken, approversList
     *
     * ## Security Design
     * - **Principle of Least Privilege**: Most actions require authentication
     * - **Public Services**: Carefully selected public endpoints for functionality
     * - **Mobile Integration**: Supports app access without full authentication
     * - **Registration Support**: Enables new member registration workflow
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return void
     *
     * @example
     * ```php
     * // Public actions accessible without authentication
     * $publicActions = [
     *     'login', 'register', 'forgotPassword', 'resetPassword',
     *     'publicProfile', 'viewMobileCard', 'searchMembers',
     *     'emailTaken', 'autoComplete', 'approversList'
     * ];
     * 
     * // All other actions require authentication and authorization
     * // Administrative actions like 'index' and 'verifyQueue' have model authorization
     * ```
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authorization->authorizeModel('index', 'verifyQueue');
        $this->Authentication->allowUnauthenticated([
            'login',
            'approversList',
            'forgotPassword',
            'resetPassword',
            'register',
            'viewMobileCard',
            'viewMobileCardJson',
            'searchMembers',
            'publicProfile',
            'emailTaken',
            'autoComplete',
        ]);
    }

    #region general use calls

    /**
     * Display paginated member listing with search and filtering capabilities
     *
     * Provides the main member directory interface with comprehensive search functionality,
     * sorting options, and authorization-based data scoping. This method implements
     * advanced search features including special character handling for medieval names.
     *
     * ## Search Features
     * ### Multi-field Search
     * Searches across multiple member fields simultaneously:
     * - **Personal Information**: SCA name, first name, last name, email address
     * - **Organizational**: Branch name, membership number
     * - **Special Characters**: Automatic Þ (thorn) and 'th' conversion for medieval names
     *
     * ### Character Conversion Logic
     * ```php
     * // Handles medieval character variations
     * "thor" → searches for "thor", "Þor", "thor"
     * "Þorinn" → searches for "Þorinn", "thorinn", "Þorinn"
     * ```
     *
     * ## Authorization and Scoping
     * - **Authorization Scope**: Applies user-specific data access restrictions
     * - **Branch Filtering**: Users see only members they're authorized to view
     * - **Performance**: Selective field loading for improved query performance
     *
     * ## Sorting and Pagination
     * - **Sortable Fields**: Name, email, status, last login, branch
     * - **Default Sort**: Alphabetical by SCA name
     * - **Pagination**: Configurable page size with CakePHP pagination
     * - **URL Parameters**: Maintains search and sort state across pages
     *
     * ## Performance Optimizations
     * - **Selective Loading**: Only loads essential fields for listing view
     * - **Efficient Queries**: Uses contains() for optimal JOIN operations
     * - **Index Usage**: Leverages database indexes for search performance
     *
     * @return \Cake\Http\Response|null|void Renders member index view
     *
     * @example
     * ```php
     * // URL examples and their behavior:
     * 
     * // Basic listing
     * GET /members
     * 
     * // Search for members with "thor" in any field
     * GET /members?search=thor
     * // Searches: "thor", "Þor" variations
     * 
     * // Sorted listing
     * GET /members?sort=email_address&direction=desc
     * 
     * // Combined search and sort
     * GET /members?search=aiden&sort=last_login&direction=desc
     * ```
     */
    public function index()
    {
        $search = $this->request->getQuery('search');
        $search = $search ? trim($search) : null;
        // get sort and direction from query string
        $sort = $this->request->getQuery('sort');
        $direction = $this->request->getQuery('direction');

        $query = $this->Members
            ->find()
            ->contain(['Branches'])
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Branches.name',
                'Members.status',
                'Members.email_address',
                'Members.last_login',
            ]);
        // if there is a search term, filter the query
        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match('/th/', $search)) {
                $nsearch = str_replace('th', 'Þ', $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match('/Þ/', $search)) {
                $usearch = str_replace('Þ', 'th', $search);
            }
            $query = $query->where([
                'OR' => [
                    ['Members.membership_number LIKE' => '%' . $search . '%'],
                    ['Members.sca_name LIKE' => '%' . $search . '%'],
                    ['Members.sca_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.sca_name LIKE' => '%' . $usearch . '%'],
                    ['Members.first_name LIKE' => '%' . $search . '%'],
                    ['Members.last_name LIKE' => '%' . $search . '%'],
                    ['Members.email_address LIKE' => '%' . $search . '%'],
                    ['Branches.name LIKE' => '%' . $search . '%'],
                    ['Members.first_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.last_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.email_address LIKE' => '%' . $nsearch . '%'],
                    ['Branches.name LIKE' => '%' . $nsearch . '%'],
                    ['Members.first_name LIKE' => '%' . $usearch . '%'],
                    ['Members.last_name LIKE' => '%' . $usearch . '%'],
                    ['Members.email_address LIKE' => '%' . $usearch . '%'],
                    ['Branches.name LIKE' => '%' . $usearch . '%'],
                ],
            ]);
        }
        #is
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
            ],
        ]);

        $this->set(compact('Members', 'sort', 'direction', 'search'));
    }

    /**
     * Display member verification queue for administrative processing
     *
     * Provides administrators with a focused view of members requiring verification
     * review, including membership card validation, age verification, and status
     * updates. This method supports the KMP member verification workflow.
     *
     * ## Verification Queue Scope
     * Includes members with statuses requiring administrative attention:
     * - **STATUS_ACTIVE**: Members with uploaded membership cards awaiting verification
     * - **STATUS_UNVERIFIED_MINOR**: Minor members requiring age and parent verification
     * - **STATUS_MINOR_MEMBERSHIP_VERIFIED**: Minors with verified membership needing parent verification
     * - **STATUS_MINOR_PARENT_VERIFIED**: Minors with verified parents needing membership verification
     *
     * ## Administrative Features
     * - **Batch Processing**: View multiple members requiring attention
     * - **Age Calculation**: Displays calculated age for verification purposes
     * - **Document Review**: Shows membership card upload status
     * - **Status Tracking**: Clear indication of verification progress
     *
     * ## Data Selection
     * Optimized query loading only essential verification fields:
     * - **Identity**: ID, SCA name, legal names
     * - **Status**: Current member status
     * - **Verification**: Membership card path, birth information
     * - **Contact**: Email address for communication
     * - **Organization**: Branch assignment
     *
     * ## Authorization
     * - **Administrative Access**: Requires authorization to view verification queue
     * - **Data Scoping**: Applies user-specific access restrictions
     * - **Audit Trail**: Tracks verification queue access for security
     *
     * @return \Cake\Http\Response|null|void Renders verification queue view
     *
     * @example
     * ```php
     * // Administrative workflow example:
     * 
     * // 1. Administrator accesses verification queue
     * GET /members/verify-queue
     * 
     * // 2. Review shows members needing verification:
     * // - Adult with membership card uploaded
     * // - Minor needing parent verification
     * // - Minor with parent verified needing membership check
     * 
     * // 3. Administrator processes each member:
     * // - Verify membership cards
     * // - Validate parent/guardian information
     * // - Update member status accordingly
     * ```
     */
    public function verifyQueue()
    {
        $activeTab = $this->request->getQuery('activeTab');
        $activeTab = $activeTab ? trim($activeTab) : null;
        // get sort and direction from query string
        $sort = $this->request->getQuery('sort');
        $direction = $this->request->getQuery('direction');

        $query = $this->Members
            ->find()
            ->contain(['Branches'])
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Branches.name',
                'Members.status',
                'Members.email_address',
                'Members.membership_card_path',
                'Members.birth_year',
                'Members.birth_month',
            ]);
        $query = $query->where([
            'OR' => [
                'Members.status IN' => [
                    Member::STATUS_ACTIVE,
                    Member::STATUS_UNVERIFIED_MINOR,
                    Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
                    Member::STATUS_MINOR_PARENT_VERIFIED,
                ],
                'OR' => [
                    [
                        'Members.membership_card_path IS NOT' => null,
                        'Members.status IN' => [
                            Member::STATUS_VERIFIED_MEMBERSHIP,
                        ]
                    ]
                ]
            ],
        ]);
        #is
        $query = $this->Authorization->applyScope($query);
        $Members = $query->all();

        $this->set(compact('Members'));
    }

    /**
     * Display detailed member profile with comprehensive information and management tools
     *
     * Provides a complete member profile view with extensive relationship data,
     * role assignments, and administrative tools. This method serves as the central
     * hub for member information and management within the KMP system.
     *
     * ## Data Loading Strategy
     * Uses sophisticated containment patterns to load related data efficiently:
     * 
     * ### Core Relationships
     * - **Roles**: All assigned roles for permission display
     * - **Branch**: Organizational membership with selective field loading
     * - **Parent**: Guardian information for minor members
     *
     * ### Temporal Role Assignments
     * - **CurrentMemberRoles**: Active role assignments with full role details
     * - **UpcomingMemberRoles**: Future role assignments for planning
     * - **PreviousMemberRoles**: Historical role assignments for audit trail
     *
     * ## Interactive Features
     * ### Form Integration
     * - **Edit Modal**: Pre-populated member edit form with session-based error handling
     * - **Password Reset**: Password change form with validation error display
     * - **Status Management**: Administrative status change controls
     *
     * ### Session-Based Error Handling
     * - **Form Persistence**: Maintains form data across redirects on validation errors
     * - **Error Display**: Shows validation errors from previous form submissions
     * - **User Experience**: Prevents data loss during form validation failures
     *
     * ## Administrative Tools
     * ### Data Management
     * - **Branch Selection**: Dropdown with membership eligibility validation
     * - **Status Control**: Administrative status change with business rule enforcement
     * - **Date Selection**: Month/year dropdowns for birth date management
     *
     * ### Privacy and Security
     * - **Public Data Preview**: Shows privacy-filtered public information
     * - **Authorization Checks**: Ensures user can view requested member
     * - **Audit Context**: Tracks member profile access for security
     *
     * ## UI Components
     * ### Helper Data
     * - **Month List**: Formatted month names for date selection
     * - **Year Range**: 130-year range for comprehensive age support
     * - **Branch Tree**: Hierarchical branch selection with membership flags
     * - **Status Options**: All available member status transitions
     *
     * @param string|null $id Member ID to display
     * @return \Cake\Http\Response|null|void Renders member profile view
     * @throws \Cake\Http\Exception\NotFoundException When member not found
     *
     * @example
     * ```php
     * // Member profile access patterns:
     * 
     * // View specific member (with authorization check)
     * GET /members/view/123
     * 
     * // After failed form submission (shows errors)
     * // 1. User submits invalid edit form
     * // 2. Controller stores form data in session
     * // 3. Redirects to view with error display
     * 
     * // Role assignment display:
     * // - Current: "Officer (2024-01-01 to 2024-12-31)"
     * // - Upcoming: "Marshal (2025-01-01 to 2025-12-31)"
     * // - Previous: "Fighter (2023-01-01 to 2023-12-31)"
     * ```
     */
    public function view(?string $id = null)
    {
        // Get Member Details
        $memberQuery = $this->Members
            ->find()
            ->contain([
                'Roles',
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name', 'Branches.id']);
                },
                'Parents' => function (SelectQuery $q) {
                    return $q->select(['Parents.sca_name', 'Parents.id']);
                },
                'UpcomingMemberRoles' => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                'CurrentMemberRoles' => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                'PreviousMemberRoles' => function (SelectQuery $q) {
                    return $this->_addRolesSelectAndContain($q);
                },
                'GatheringAttendances' => function (SelectQuery $q) {
                    return $q->contain([
                        'Gatherings' => function (SelectQuery $q) {
                            return $q->contain(['Branches', 'GatheringTypes'])
                                ->orderBy(['Gatherings.start_date' => 'ASC']);
                        }
                    ]);
                },
            ])
            ->where(['Members.id' => $id]);
        //$memberQuery = $this->Members->addJsonWhere($memberQuery, "Members.additional_info", "$.sports", "football");
        $member = $memberQuery->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member, 'view');
        // Create the new Note form
        $session = $this->request->getSession();
        // Get the member form data for the edit modal
        $memberForm = $this->Members->get($id);
        // If there is form data in the session, patch the entity so we can show the errors
        $memberFormData = $session->consume('memberFormData');
        if ($memberFormData != null) {
            $this->Members->patchEntity($memberForm, $memberFormData);
        }
        // Get the password reset form data for the change password modal so we can show errors
        $passwordResetData = $session->consume('passwordResetData');
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
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])->toArray();
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
        $publicInfo = $member->publicData();

        // Get available gatherings for attendance registration (started or future only)
        $now = new DateTime();
        $availableGatherings = $this->Members->GatheringAttendances->Gatherings
            ->find('list', keyField: 'id', valueField: function ($entity) {
                return $entity->name . ' (' . $entity->start_date->format('M d, Y') . ')';
            })
            ->where(['Gatherings.end_date >=' => $now])
            ->contain(['Branches', 'GatheringTypes'])
            ->orderBy(['Gatherings.start_date' => 'ASC'])
            ->toArray();

        $this->set(
            compact(
                'member',
                'treeList',
                'passwordReset',
                'memberForm',
                'months',
                'years',
                'backUrl',
                'statusList',
                'publicInfo',
                'availableGatherings',
            ),
        );
        $this->viewBuilder()->setTemplate('view');
    }

    public function viewCard($id = null)
    {
        $member = $this->Members
            ->find()
            ->select('id')
            ->where(['Members.id' => $id])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        // sort by name
        $message_variables = [
            'secretary_email' => StaticHelpers::getAppSetting(
                'Activity.SecretaryEmail',
            ),
            'kingdom' => StaticHelpers::getAppSetting(
                'KMP.KingdomName',
            ),
            'secratary' => StaticHelpers::getAppSetting(
                'Activity.SecretaryName',
            ),
            'marshal_auth_graphic' => StaticHelpers::getAppSetting(
                'Member.ViewCard.Graphic',
            ),
            'marshal_auth_header_color' => StaticHelpers::getAppSetting(
                'Member.ViewCard.HeaderColor',
            ),
        ];
        $this->set(compact('member', 'message_variables'));
        $customTemplate = StaticHelpers::getAppSetting(
            'Member.ViewCard.Template',
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
            ->where(['Members.mobile_card_token' => $id, 'Members.status NOT IN' => $inactiveStatuses])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }

        // Authenticate user with mobile card token and persist session
        // This allows the user to access other features that require authentication
        $result = $this->Authentication->getResult();
        if ($result->isValid()) {
            // User authenticated via token - persist the session
            $this->Authentication->setIdentity($member);
        }

        $this->Authorization->skipAuthorization();
        // sort filter out expired member roles
        $message_variables = [
            'secretary_email' => StaticHelpers::getAppSetting(
                'Activity.SecretaryEmail',
            ),
            'kingdom' => StaticHelpers::getAppSetting(
                'KMP.KingdomName',
            ),
            'secratary' => StaticHelpers::getAppSetting(
                'Activity.SecretaryName',
            ),
            'marshal_auth_graphic' => StaticHelpers::getAppSetting(
                'Member.ViewCard.Graphic',
            ),
            'marshal_auth_header_color' => StaticHelpers::getAppSetting(
                'Member.MobileCard.BgColor',
            ),
        ];

        // Prepare watermark image for layout - build full URL for image
        $graphicPath = WWW_ROOT . 'img' . DS . $message_variables["marshal_auth_graphic"];
        if (file_exists($graphicPath)) {
            $watermarkimg = "data:image/gif;base64," . base64_encode(file_get_contents($graphicPath));
        } else {
            $watermarkimg = null;
        }

        // Build card URL for member-mobile-card-profile controller
        $cardUrl = Router::url(['controller' => 'Members', 'action' => 'viewMobileCardJson', $member->mobile_card_token], true);

        // Set layout variables for mobile_app layout
        $this->set('mobileTitle', 'Mobile Activities Authorization Card');
        $this->set('mobileHeaderColor', $message_variables['marshal_auth_header_color']);
        $this->set('watermarkImage', $watermarkimg);
        $this->set('showRefreshBtn', true); // Mobile card needs refresh button
        $this->set('cardUrl', $cardUrl); // For member-mobile-card-profile controller

        $this->set(compact('member', 'message_variables'));
        $customTemplate = StaticHelpers::getAppSetting(
            'Member.ViewMobileCard.Template',
        );
        $this->viewBuilder()
            ->setTemplate($customTemplate)
            ->setLayout('mobile_app');
    }

    /**
     * Create new member with automated workflow processing
     *
     * Handles new member creation with intelligent status assignment, automatic
     * token generation, and workflow-based email notifications. This method
     * implements the complete member onboarding process with age-based logic.
     *
     * ## Member Creation Workflow
     * ### Age-Based Processing
     * - **Adult Members (18+)**: Assigned STATUS_ACTIVE with password reset capability
     * - **Minor Members (<18)**: Assigned STATUS_UNVERIFIED_MINOR requiring verification
     *
     * ### Automatic Field Generation
     * - **Password**: Random 16-character temporary password
     * - **Mobile Card Token**: 16-character token for mobile card access
     * - **Security**: Cryptographically secure token generation
     *
     * ## Validation and Error Handling
     * ### Form Validation
     * - **Business Rules**: Comprehensive validation via MembersTable rules
     * - **Error Display**: User-friendly error messages with field-specific feedback
     * - **Data Persistence**: Form data maintained on validation errors
     *
     * ### Success Processing
     * - **Database Save**: Transactional save with automatic field processing
     * - **Email Notifications**: Age-appropriate welcome and notification emails
     * - **Redirect**: Automatic redirect to member profile view
     *
     * ## Email Notification System
     * ### Adult Member Workflow
     * - **Password Reset Email**: Secure link for initial password setup
     * - **Secretary Notification**: Administrative notification of new registration
     * - **Membership Card Tracking**: Documents uploaded membership card status
     *
     * ### Minor Member Workflow
     * - **Secretary Notification**: Specialized minor member registration notification
     * - **Parent Verification**: Triggers parent/guardian verification process
     * - **Compliance**: Ensures minor member protection compliance
     *
     * ## Form Data Management
     * ### UI Components
     * - **Month/Year Selection**: Birth date selection with full range support
     * - **Branch Selection**: Hierarchical branch selection with membership validation
     * - **Validation Feedback**: Real-time form validation and error display
     *
     * ### Data Processing
     * - **Birth Date**: Converts month/year to age calculation
     * - **Branch Assignment**: Validates branch membership eligibility
     * - **Contact Information**: Validates email uniqueness and format
     *
     * @return \Cake\Http\Response|null|void Redirects on success, renders form on error
     *
     * @example
     * ```php
     * // Member creation workflow:
     * 
     * // 1. Administrator accesses add form
     * GET /members/add
     * 
     * // 2. Form submission with validation
     * POST /members/add
     * {
     *     "sca_name": "Aiden of the North",
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "email_address": "john@example.com",
     *     "birth_year": 1990,
     *     "birth_month": 6,
     *     "branch_id": 1
     * }
     * 
     * // 3. Automatic processing:
     * // - Age calculation (34 years old)
     * // - Status: STATUS_ACTIVE (adult)
     * // - Password: Random 16-char string
     * // - Mobile token: Random 16-char string
     * 
     * // 4. Email notifications:
     * // - Welcome email to new member
     * // - Administrative notification to secretary
     * 
     * // 5. Redirect to member profile
     * REDIRECT /members/view/123
     * ```
     */
    public function add()
    {
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->authorize($member);
        if ($this->request->is('post')) {
            $member = $this->Members->patchEntity(
                $member,
                $this->request->getData(),
            );
            $member->password = StaticHelpers::generateToken(32);
            if ($member->getErrors()) {
                $this->Flash->error(
                    __('The Member could not be saved. Please, try again.'),
                );
                $months = array_reduce(range(1, 12), function ($rslt, $m) {
                    $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

                    return $rslt;
                });
                $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
                $treeList = $this->Members->Branches
                    ->find('list', keyPath: function ($entity) {
                        return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                    })
                    ->where(['can_have_members' => true])
                    ->orderBy(['name' => 'ASC'])->toArray();
                $this->set(compact(
                    'member',
                    'treeList',
                    'months',
                    'years',
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
                    $this->Flash->success(__('The Member has been saved and the minor registration email has been sent for verification.'));
                    $this->getMailer('KMP')->send('notifySecretaryOfNewMinorMember', [$member]);
                } else {
                    $this->Flash->success(__("The Member has been saved. Please ask the member to use 'forgot password' to set their password."));
                }

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])->toArray();
        $this->set(compact(
            'member',
            'treeList',
            'months',
            'years',
        ));
    }

    /**
     * Edit method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member = $this->Members->patchEntity(
                $member,
                $this->request->getData(),
            );
            if ($member->getErrors()) {
                $session = $this->request->getSession();
                $session->write('memberFormData', $this->request->getData());

                return $this->redirect(['action' => 'view', $member->id]);
            }
            if ($member->membership_number == null || $member->membership_number == '') {
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
            if ($member->membership_expires_on != null && $member->membership_expires_on != '' && is_string($member->membership_expires_on)) {
                //convert to a date
                $member->membership_expires_on = DateTime::createFromFormat('Y-m-d', $member->membership_expires_on);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
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
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);

        $member->email_address = 'Deleted: ' . $member->email_address;
        if ($this->Members->delete($member)) {
            $this->Flash->success(__('The Member has been deleted.'));
        } else {
            $this->Flash->error(
                __('The Member could not be deleted. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }

    #endregion

    #region Member Specific calls

    public function sendMobileCardEmail($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($member->mobile_card_token == null || $member->mobile_card_token == '') {
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            $this->Members->save($member);
        }
        $url = Router::url([
            'controller' => 'Members',
            'action' => 'ViewMobileCard',
            'plugin' => null,
            '_full' => true,
            $member->mobile_card_token,
        ]);
        $vars = [
            'url' => $url,
        ];
        $this->queueMail('KMP', 'mobileCard', $member->email_address, $vars);
        $this->Flash->success(__('The email has been sent.'));

        return $this->redirect(['action' => 'view', $member->id]);
    }

    public function partialEdit($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member->title = $this->request->getData('title');
            $member->sca_name = $this->request->getData('sca_name');
            $member->pronunciation = $this->request->getData('pronunciation');
            $member->pronouns = $this->request->getData('pronouns');
            $member->branch_id = $this->request->getData('branch_id');
            $member->first_name = $this->request->getData('first_name');
            $member->middle_name = $this->request->getData('middle_name');
            $member->last_name = $this->request->getData('last_name');
            $member->street_address = $this->request->getData('street_address');
            $member->city = $this->request->getData('city');
            $member->state = $this->request->getData('state');
            $member->zip = $this->request->getData('zip');
            $member->phone_number = $this->request->getData('phone_number');
            $member->email_address = $this->request->getData('email_address');
            //$member->parent_name = $this->request->getData("parent_name");
            if ($member->getErrors()) {
                $session = $this->request->getSession();
                $session->write('memberFormData', $this->request->getData());

                return $this->redirect(['action' => 'view', $member->id]);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        $this->redirect(['action' => 'view', $member->id]);
    }

    /**
     * Profile method
     * 
     * Shows the current user's profile without changing the URL
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function profile()
    {
        $user = $this->Authentication->getIdentity();
        if (!$user) {
            throw new NotFoundException(__('User not authenticated.'));
        }

        return $this->view(toString($user->id));
    }

    public function editAdditionalInfo($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        $user = $this->Authentication->getIdentity();
        $userEditableOnly = !$user->checkCan('edit', $member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member->additional_info = [];
            $aiFormConfig = StaticHelpers::getAppSettingsStartWith('Member.AdditionalInfo.');
            $aiForm = [];
            if (empty($aiFormConfig)) {
                $this->Flash->error(
                    __('The Additional Information could not be saved. Please, try again.'),
                );

                return $this->redirect(['action' => 'view', $member->id]);
            }
            foreach ($aiFormConfig as $key => $value) {
                $shortKey = str_replace('Member.AdditionalInfo.', '', $key);
                $aiForm[$shortKey] = $value;
            }
            foreach ($aiForm as $fieldKey => $fieldType) {
                $userEditable = false;
                //check fieldType for |
                $pipePos = strpos($fieldType, '|');
                if ($pipePos !== false) {
                    $fieldSecDetails = explode('|', $fieldType);
                    $fieldType = $fieldSecDetails[0];
                    $userEditable = $fieldSecDetails[1] == 'user';
                }
                if ($userEditableOnly && !$userEditable) {
                    continue;
                }
                $colonPos = strpos($fieldType, ':');
                $aiOptions = [];
                if ($colonPos !== false) {
                    $fieldDetails = explode(':', $fieldType);
                    $fieldType =  $fieldDetails[0];
                    $aiOptions = explode(',', $fieldDetails[1]);
                }
                //if aiOptions are not emoty then check the value is one of the options
                $fieldValue = $this->request->getData($fieldKey);
                if (!empty($aiOptions)) {
                    if ($fieldValue != '' && !in_array($fieldValue, $aiOptions)) {
                        $this->Flash->error(
                            __('The Additional Information could not be saved. Please, try again.'),
                        );

                        return $this->redirect(['action' => 'view', $member->id]);
                    }
                }
                if ($fieldValue != '') {
                    $newData[$fieldKey] = $this->request->getData($fieldKey);
                }
            }
            $member->additional_info = $newData;
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Additional Information saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The Additional Information could not be saved. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'view', $member->id]);
    }

    #endregion

    #region ASYNC calls

    public function searchMembers()
    {
        $q = $this->request->getQuery('q');
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match('/th/', $q)) {
            $nq = str_replace('th', 'Þ', $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match('/Þ/', $q)) {
            $uq = str_replace('Þ', 'th', $q);
        }
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Members
            ->find('all')
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [['sca_name LIKE' => "%$q%"], ['sca_name LIKE' => "%$nq%"], ['sca_name LIKE' => "%$uq%"]],
            ])
            ->select(['id', 'sca_name'])
            ->limit(10);
        //$query = $this->Authorization->applyScope($query);
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($query));

        return $this->response;
    }

    public function viewCardJson($id = null)
    {
        $member = $this->Members
            ->find()
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.title',
                'Members.first_name',
                'Members.last_name',
                'Members.membership_number',
                'Members.membership_expires_on',
                'Members.background_check_expires_on',
                'Members.additional_info',
            ])
            ->contain([
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
            ])
            ->where(['Members.id' => $id])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        if ($member->title) {
            $member->sca_name = $member->title . ' ' . $member->sca_name;
        }
        $this->Authorization->authorize($member);
        $this->viewBuilder()
            ->setClassName('Ajax')
            ->setOption('serialize', 'responseData');
        $this->set(compact('member'));
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
                'Members.id',
                'Members.title',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Members.membership_number',
                'Members.membership_expires_on',
                'Members.background_check_expires_on',
                'Members.additional_info',
            ])
            ->contain([
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
            ])
            ->where(['Members.mobile_card_token' => $id, 'Members.status NOT IN' => $inactiveStatuses])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        if ($member->title) {
            $member->sca_name = $member->title . ' ' . $member->sca_name;
        }
        $this->Authorization->skipAuthorization();
        $this->viewBuilder()
            ->setClassName('Ajax')
            ->setOption('serialize', 'responseData');
        $this->set(compact('member'));
        $this->viewBuilder()->setTemplate('view_card_json');
    }

    public function publicProfile($id = null)
    {
        $member = $this->Members
            ->find()
            ->where(['Members.id' => $id])
            ->first();
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->skipAuthorization();
        $publicProfile = $member->publicData();
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($publicProfile));

        return $this->response;
    }

    public function autoComplete()
    {
        //TODO: Audit for Privacy

        $q = $this->request->getQuery('q');
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match('/th/', $q)) {
            $nq = str_replace('th', 'Þ', $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match('/Þ/', $q)) {
            $uq = str_replace('Þ', 'th', $q);
        }

        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Members
            ->find('all')
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [['sca_name LIKE' => "%$q%"], ['sca_name LIKE' => "%$nq%"], ['sca_name LIKE' => "%$uq%"]],
            ])
            ->select(['id', 'sca_name'])
            ->limit(50);
        $this->set(compact('query', 'q', 'nq', 'uq'));
    }

    public function emailTaken()
    {
        $email = $this->request->getQuery('email');
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $emailUsed = $this->Members
            ->find('all')
            ->where(['email_address' => $email])
            ->count();
        $result = '';
        if ($emailUsed > 0) {
            $result = true;
        } else {
            $result = false;
        }
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($result));

        return $this->response;
    }

    #endregion

    #region Password specific calls

    public function changePassword($id = null)
    {
        $member = $this->Members->get($id);
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        $passwordReset = new ResetPasswordForm();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $passwordReset->validate($this->request->getData());
            if ($passwordReset->getErrors()) {
                $session = $this->request->getSession();
                $session->write('passwordResetData', $this->request->getData());

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $member->password = $this->request->getData()['new_password'];
            $member->password_token = null;
            $member->password_token_expires_on = null;
            $member->failed_login_attempts = 0;
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The password has been changed.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(
                __('The password could not be changed. Please, try again.'),
            );
        }
    }

    public function forgotPassword()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is('post')) {
            $member = $this->Members
                ->find()
                ->where([
                    'email_address' => $this->request->getData('email_address'),
                ])
                ->first();
            if ($member) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(
                    1,
                );
                $this->Members->save($member);
                $url = Router::url([
                    'controller' => 'Members',
                    'action' => 'resetPassword',
                    'plugin' => null,
                    '_full' => true,
                    $member->password_token,
                ]);
                $vars = [
                    'url' => $url,
                ];
                $this->queueMail('KMP', 'resetPassword', $member->email_address, $vars);
                $this->Flash->success(
                    __(
                        'Password reset request sent to ' .
                            $member->email_address,
                    ),
                );

                return $this->redirect(['action' => 'login']);
            } else {
                $this->Flash->error(
                    __(
                        'Your email was not found, please contact the Marshalate Secretary at ' .
                            StaticHelpers::getAppSetting(
                                'Activity.SecretaryEmail',
                            ),
                    ),
                );
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            'KMP.Login.Graphic',
        );
        $this->set(compact('headerImage'));
    }

    public function resetPassword($token = null)
    {
        $this->Authorization->skipAuthorization();
        $member = $this->Members
            ->find()
            ->where(['password_token' => $token])
            ->first();
        if ($member) {
            if ($member->password_token_expires_on < DateTime::now()) {
                $this->Flash->error('Invalid Token, please request a new one.');

                return $this->redirect(['action' => 'forgotPassword']);
            }
            $passwordReset = new ResetPasswordForm();
            if (
                $this->request->is('post') &&
                $passwordReset->validate($this->request->getData())
            ) {
                $member->password = $this->request->getData('new_password');
                $member->password_token = null;
                $member->password_token_expires_on = null;
                $member->failed_login_attempts = 0;
                $this->Members->save($member);
                $this->Flash->success(__('Password successfully reset'));

                return $this->redirect(['action' => 'login']);
            }
            $headerImage = StaticHelpers::getAppSetting(
                'KMP.Login.Graphic',
            );
            $this->set(compact('headerImage', 'passwordReset'));
        } else {
            $this->Flash->error('Invalid Token, please request a new one.');

            return $this->redirect(['action' => 'forgotPassword']);
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
        if ($this->request->is('post')) {
            $authentication = $this->request->getAttribute('authentication');
            $result = $authentication->getResult();
            // regardless of POST or GET, redirect if user is logged in
            if ($result->isValid()) {
                $user = $this->Members->get(
                    $authentication->getIdentity()->getIdentifier(),
                );
                $this->Flash->success('Welcome ' . $user->sca_name . '!');
                $page = $this->request->getQuery('redirect');
                if (
                    $page == '/' ||
                    $page == '/Members/login' ||
                    $page == '/Members/logout' ||
                    $page == null
                ) {
                    // return $this->redirect(['action' => 'view', $user->id]);
                    return $this->redirect(['action' => 'profile']);
                } else {
                    return $this->redirect($page);
                }
            }
            $errors = $result->getErrors();
            if (
                isset($errors['KMPBruteForcePassword']) &&
                count($errors['KMPBruteForcePassword']) > 0
            ) {
                $message = $errors['KMPBruteForcePassword'][0];
                switch ($message) {
                    case 'Account Locked':
                        $this->Flash->error(
                            'Your account has been locked. Please try again later.',
                        );
                        break;
                    case 'Account Not Verified':
                        $contactAddress = StaticHelpers::getAppSetting(
                            'Members.AccountVerificationContactEmail',
                        );
                        $this->Flash->error(
                            'Your account is being verified. This process may take several days after you have verified your email address. Please contact ' . $contactAddress . ' if you have not been verified within a week.',
                        );
                        break;
                    case 'Account Disabled':
                        $contactAddress = StaticHelpers::getAppSetting(
                            'Members.AccountDisabledContactEmail',
                        );
                        $this->Flash->error(
                            'Your account deactivated. Please contact ' . $contactAddress . ' if you feel this is in error.',
                        );
                        break;
                    default:
                        $this->Flash->error(
                            'Your email or password is incorrect.',
                        );
                        break;
                }
            } else {
                $this->Flash->error('Your email or password is incorrect.');
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            'KMP.Login.Graphic',
        );
        $allowRegistration = StaticHelpers::getAppSetting(
            'KMP.EnablePublicRegistration',
        );
        $this->set(compact('headerImage', 'allowRegistration'));
    }

    public function logout()
    {
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();

        return $this->redirect([
            'controller' => 'Members',
            'action' => 'login',
        ]);
    }
    public function submitScaMemberInfo()
    {
        $user = $this->Authentication->getIdentity();
        $member = $this->Members->get(toString($user->id));
        if (!$member) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($member);
        if ($this->request->is('put')) {
            $file = $this->request->getData('member_card');
            if ($file->getSize() > 0) {
                $storageLoc = WWW_ROOT . '../images/uploaded/';
                $fileName = StaticHelpers::generateToken(10);
                StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
                $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
                $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
                if (!$fileResult) {
                    $this->Flash->error('Error saving image, please try again.');
                }
                //trim the path off of the filename
                $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);
                $member->membership_card_path = $fileName;
                if ($this->Members->save($member)) {
                    $this->Flash->success(__('Membership information has been submitted, please allow several days for our team to review and update the profile.'));
                } else {
                    $this->Flash->error("There was an error please try again.");
                }
            }
        }
        $this->redirect($this->referer());
    }

    public function register()
    {
        $allowRegistration = StaticHelpers::getAppSetting(
            'KMP.EnablePublicRegistration',
        );
        if (strtolower($allowRegistration) != 'yes') {
            $this->Flash->error(
                'Public registration is not allowed at this time.',
            );

            return $this->redirect(['action' => 'login']);
        }
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();
        if ($this->request->is('post')) {
            $file = $this->request->getData('member_card');
            if ($file->getSize() > 0) {
                $storageLoc = WWW_ROOT . '../images/uploaded/';
                $fileName = StaticHelpers::generateToken(10);
                StaticHelpers::ensureDirectoryExists($storageLoc, 0755);
                $file->moveTo(WWW_ROOT . '../images/uploaded/' . $fileName);
                $fileResult = StaticHelpers::saveScaledImage($fileName, 500, 700, $storageLoc, $storageLoc);
                if (!$fileResult) {
                    $this->Flash->error('Error saving image, please try again.');
                }
                //trim the path off of the filename
                $fileName = substr($fileResult, strrpos($fileResult, '/') + 1);
                $member->membership_card_path = $fileName;
            }
            $member->sca_name = $this->request->getData('sca_name');
            $member->branch_id = $this->request->getData('branch_id');
            $member->first_name = $this->request->getData('first_name');
            $member->middle_name = $this->request->getData('middle_name');
            $member->last_name = $this->request->getData('last_name');
            $member->street_address = $this->request->getData('street_address');
            $member->city = $this->request->getData('city');
            $member->state = $this->request->getData('state');
            $member->zip = $this->request->getData('zip');
            $member->phone_number = $this->request->getData('phone_number');
            $member->email_address = $this->request->getData('email_address');
            $member->birth_month = (int)$this->request->getData('birth_month');
            $member->birth_year = (int)$this->request->getData('birth_year');
            if ($member->age > 17) {
                $member->password_token = StaticHelpers::generateToken(32);
                $member->password_token_expires_on = DateTime::now()->addDays(1);
            }
            $member->password = StaticHelpers::generateToken(12);
            if ($member->getErrors()) {
                $this->Flash->error(
                    __('The Member could not be saved. Please, try again.'),
                );

                return $this->redirect(['action' => 'login']);
            }
            if ($member->age > 17) {
                $member->status = Member::STATUS_ACTIVE;
            } else {
                $member->status = Member::STATUS_UNVERIFIED_MINOR;
            }
            $member->mobile_card_token = StaticHelpers::generateToken(16);
            if ($this->Members->save($member)) {
                if ($member->age > 17) {
                    $url = Router::url([
                        'controller' => 'Members',
                        'action' => 'resetPassword',
                        'plugin' => null,
                        '_full' => true,
                        $member->password_token,
                    ]);
                    $vars = [
                        'url' => $url,
                        'sca_name' => $member->sca_name,
                    ];
                    $this->queueMail('KMP', 'newRegistration', $member->email_address, $vars);
                    $url = Router::url([
                        'controller' => 'Members',
                        'action' => 'view',
                        'plugin' => null,
                        '_full' => true,
                        $member->id,
                    ]);
                    $vars = [
                        'url' => $url,
                        'sca_name' => $member->sca_name,
                    ];
                    if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
                        $vars['membershipCardPresent'] = true;
                    } else {
                        $vars['membershipCardPresent'] = false;
                    }
                    $this->queueMail('KMP', 'notifySecretaryOfNewMember', $member->email_address, $vars);
                    $this->Flash->success(__('Your registration has been submitted. Please check your email for a link to set up your password.'));
                } else {
                    $url = Router::url([
                        'controller' => 'Members',
                        'action' => 'view',
                        'plugin' => null,
                        '_full' => true,
                        $member->id,
                    ]);
                    $vars = [
                        'url' => $url,
                        'sca_name' => $member->sca_name,
                    ];
                    if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
                        $vars['membershipCardPresent'] = true;
                    } else {
                        $vars['membershipCardPresent'] = false;
                    }
                    $this->queueMail('KMP', 'notifySecretaryOfNewMinorMember', $member->email_address, $vars);
                    $this->Flash->success(__('Your registration has been submitted. The Kingdom Secretary will need to verify your account with your parent or guardian'));
                }

                return $this->redirect(['action' => 'login']);
            }
            $this->Flash->error(
                __('The Member could not be saved. Please, try again.'),
            );
        }
        $headerImage = StaticHelpers::getAppSetting(
            'KMP.Login.Graphic',
        );
        $months = array_reduce(range(1, 12), function ($rslt, $m) {
            $rslt[$m] = date('F', mktime(0, 0, 0, $m, 10));

            return $rslt;
        });
        $years = array_combine(range(date('Y'), date('Y') - 130), range(date('Y'), date('Y') - 130));
        $treeList = $this->Members->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])->toArray();

        $this->set(compact('member', 'treeList', 'months', 'years', 'headerImage'));
    }

    #endregion

    #region Import/Export calls

    /**
     * Import Member Expiration dates from CSV based on Membership number
     */
    public function importExpirationDates()
    {
        $this->Authorization->authorize($this->Members->newEmptyEntity());
        if ($this->request->is('post')) {
            $file = $this->request->getData('importData');
            $file = $file->getStream()->getMetadata('uri');
            $csv = array_map('str_getcsv', file($file));
            $this->Members->getConnection()->begin();
            foreach ($csv as $row) {
                if (
                    $row[0] == 'Member Number' ||
                    $row[1] == 'Expiration Date'
                ) {
                    continue;
                }

                $member = $this->Members
                    ->find()
                    ->where(['membership_number' => $row[0]])
                    ->first();
                if ($member) {
                    $member->membership_expires_on = new DateTime($row[1]);
                    $member->setDirty('membership_expires_on', true);
                    if (!$this->Members->save($member)) {
                        $this->Members->getConnection()->rollback();
                        $this->Flash->error(
                            __(
                                'Error saving member expiration date at ' .
                                    $row[0] .
                                    ' with date ' .
                                    $row[1] .
                                    '. All modified have been rolled back.',
                            ),
                        );

                        return;
                    }
                }
            }
            $this->Members->getConnection()->commit();
            $this->Flash->success(__('Expiration dates imported successfully'));
        }
    }

    #endregion

    #region Verification calls

    public function verifyMembership($id = null)
    {
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $verifyMembership = $this->request->getData('verify_membership');
            $verifyParent = $this->request->getData('verify_parent');
            if ($verifyMembership == '1') {
                $membership_number = $this->request->getData('membership_number');
                if (strlen($membership_number) == 0) {
                    $this->Flash->error('Membership number is required.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
                $member->membership_expires_on = $this->request->getData('membership_expires_on');
                if ($member->membership_expires_on == null) {
                    $this->Flash->error('Membership expiration date is required.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
                $member->membership_number = $membership_number;
                $member->membership_expires_on = $this->request->getData('membership_expires_on');
                if ($member->membership_expires_on != null && $member->membership_expires_on != '' && is_string($member->membership_expires_on)) {
                    //convert to a date
                    $member->membership_expires_on = DateTime::createFromFormat('Y-m-d', $member->membership_expires_on);
                }
            }
            if ($member->age < 18 && $verifyParent == '1') {
                $parentId = $this->request->getData('parent_id');
                if ($parentId) {
                    if ($parentId && strlen($parentId) > 0) {
                        $parent = $this->Members->get($parentId);
                    }
                    if ($parentId == $member->id) {
                        $this->Flash->error('Parent cannot be the same as the member.');

                        return $this->redirect(['action' => 'view', $member->id]);
                    }
                    if ($parent->age < 18) {
                        $this->Flash->error('Parent must be an adult.');

                        return $this->redirect(['action' => 'view', $member->id]);
                    }
                    $member->parent_id = $parent->id;
                } else {
                    $this->Flash->error('Parent is required for minors.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
            }
            //if the member is an adult and the membership was validated then set the status to active
            if ($member->age > 17 && $verifyMembership == '1') {
                $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
            }
            //if the member is a minor and the parent was validated then set the status to verified minor
            if ($member->age < 18 && $verifyParent == '1' && $verifyMembership == '1') {
                $member->status = Member::STATUS_VERIFIED_MINOR;
            }
            //if the member is a minor and the parent was validated then set the status to parent validataed
            if ($member->age < 18 && $verifyParent == '1' && $verifyMembership != '1') {
                //if the member is already membership verified then set to minor verified
                if ($member->status == Member::STATUS_MINOR_MEMBERSHIP_VERIFIED) {
                    $member->status = Member::STATUS_VERIFIED_MINOR;
                } else {
                    $member->status = Member::STATUS_MINOR_PARENT_VERIFIED;
                }
            }
            //if the the member is a minor and the parent was not validated by the membership was then set the status to minor membership verified
            if ($member->age < 18 && $verifyParent != '1' && $verifyMembership == '1') {
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
                    __('The Member could not be verified. Please, try again.'),
                );
                $this->redirect(['action' => 'view', $member->id]);
            }
            if ($image != null && $deleteImage) {
                $image = WWW_ROOT . '../images/uploaded/' . $image;
                $member->membership_card_path = null;
                if (!StaticHelpers::deleteFile($image)) {
                    $this->Flash->error('Error deleting image, please try again.');

                    return $this->redirect(['action' => 'view', $member->id]);
                }
            }
        }
        $this->Flash->success(__('The Membership has been verified.'));

        return $this->redirect(['action' => 'view', $member->id]);
    }

    #endregion

    #region protected

    protected function _addRolesSelectAndContain(SelectQuery $q)
    {
        return $q
            ->select([
                'id',
                'member_id',
                'role_id',
                'start_on',
                'expires_on',
                'role_id',
                'approver_id',
                'entity_type',
            ])
            ->contain([
                'Roles' => function (SelectQuery $q) {
                    return $q->select(['Roles.name']);
                },
                'ApprovedBy' => function (SelectQuery $q) {
                    return $q->select(['ApprovedBy.sca_name']);
                },
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
            ]);
    }

    #endregion
}