# 10. API Reference

This section provides reference documentation for the key APIs, controllers, and models in the Kingdom Management Portal application.

## 10.1 REST Endpoints

KMP provides a RESTful API for integration with external applications. The API follows REST conventions with JSON as the primary data exchange format.

### Authentication

All API requests require authentication using a token-based approach:

```
Authorization: Bearer {api_token}
```

API tokens can be generated and managed in the user's profile settings.

### Available Endpoints

#### Member API

```
GET /api/members              # List members
GET /api/members/{id}         # Get a specific member
POST /api/members             # Create a member
PUT /api/members/{id}         # Update a member
DELETE /api/members/{id}      # Delete a member
```

#### Branches API

```
GET /api/branches                # List branches
GET /api/branches/{id}           # Get a specific branch
GET /api/branches/{id}/officers  # Get officers for a branch
POST /api/branches               # Create a branch
PUT /api/branches/{id}           # Update a branch
DELETE /api/branches/{id}        # Delete a branch
```

#### Warrants API

```
GET /api/warrants                   # List warrants
GET /api/warrants/{id}              # Get a specific warrant
GET /api/members/{id}/warrants      # Get a member's warrants
GET /api/branches/{id}/warrants     # Get warrants for a branch
POST /api/warrants                  # Create a warrant
PUT /api/warrants/{id}              # Update a warrant
DELETE /api/warrants/{id}           # Delete a warrant
```

#### Activities API

```
GET /api/activities                       # List activities
GET /api/activities/{id}                  # Get a specific activity
GET /api/members/{id}/authorizations      # Get a member's authorizations
POST /api/authorizations                  # Create an authorization
PUT /api/authorizations/{id}              # Update an authorization
DELETE /api/authorizations/{id}           # Delete an authorization
```

### Request & Response Format

All API requests and responses use JSON format. Here's an example of a member request:

```json
// GET /api/members/123
{
  "data": {
    "id": 123,
    "type": "members",
    "attributes": {
      "email_address": "user@example.com",
      "sca_name": "Sir Example",
      "legal_name": "John Smith",
      "title": "Sir",
      "pronunciation": null,
      "active": true,
      "warrantable": true,
      "created": "2025-01-15T14:30:00Z",
      "modified": "2025-03-20T08:15:22Z"
    },
    "relationships": {
      "roles": {
        "data": [
          { "id": 5, "type": "roles" }
        ]
      },
      "warrants": {
        "data": [
          { "id": 42, "type": "warrants" }
        ]
      }
    }
  },
  "included": [
    {
      "id": 5,
      "type": "roles",
      "attributes": {
        "name": "Officer",
        "admin": false
      }
    }
  ]
}
```

### Error Handling

API errors are returned with appropriate HTTP status codes and a standardized error response format:

```json
{
  "errors": [
    {
      "status": "404",
      "title": "Resource Not Found",
      "detail": "The requested member with ID 999 could not be found"
    }
  ]
}
```

Common error status codes:
- `400`: Bad Request - The request was malformed
- `401`: Unauthorized - Authentication is required
- `403`: Forbidden - The authenticated user doesn't have permission
- `404`: Not Found - The requested resource doesn't exist
- `422`: Unprocessable Entity - Validation errors
- `500`: Internal Server Error - Server-side error

## 10.2 Controllers

KMP controllers implement the application's business logic and manage the flow of requests and responses.

### Core Controllers

#### AppController

The base controller that all other controllers extend:

```php
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;

class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();
        
        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');
        $this->loadComponent('Flash');
        $this->loadComponent('FormProtection');
        
        // Other initialization...
    }
    
    public function beforeFilter(EventInterface $event)
    {
        // Common pre-request logic
    }
    
    public function beforeRender(EventInterface $event)
    {
        // Common pre-render logic
    }
}
```

#### MembersController

Manages member-related actions:

```php
namespace App\Controller;

class MembersController extends AppController
{
    /**
     * List all members
     */
    public function index()
    {
        // Implementation...
    }
    
    /**
     * View a member
     * 
     * @param int $id Member ID
     */
    public function view($id = null)
    {
        // Implementation...
    }
    
    /**
     * Add a new member
     */
    public function add()
    {
        // Implementation...
    }
    
    /**
     * Edit a member
     * 
     * @param int $id Member ID
     */
    public function edit($id = null)
    {
        // Implementation...
    }
    
    /**
     * Delete a member
     * 
     * @param int $id Member ID
     */
    public function delete($id = null)
    {
        // Implementation...
    }
    
    /**
     * User login
     */
    public function login()
    {
        // Implementation...
    }
    
    /**
     * User logout
     */
    public function logout()
    {
        // Implementation...
    }
    
    // Other actions...
}
```

#### BranchesController

Manages branch operations:

```php
namespace App\Controller;

class BranchesController extends AppController
{
    public function index() { /* ... */ }
    public function view($id = null) { /* ... */ }
    public function add() { /* ... */ }
    public function edit($id = null) { /* ... */ }
    public function delete($id = null) { /* ... */ }
}
```

#### WarrantsController

Manages warrant operations:

```php
namespace App\Controller;

class WarrantsController extends AppController
{
    public function index() { /* ... */ }
    public function view($id = null) { /* ... */ }
    public function add() { /* ... */ }
    public function edit($id = null) { /* ... */ }
    public function delete($id = null) { /* ... */ }
    public function approve($id = null) { /* ... */ }
    public function reject($id = null) { /* ... */ }
    public function revoke($id = null) { /* ... */ }
}
```

### Plugin Controllers

Each plugin provides its own controllers in the plugin's namespace:

```php
namespace PluginName\Controller;

use App\Controller\AppController as BaseController;

class AppController extends BaseController
{
    // Plugin-specific controller logic
}

class SpecificController extends AppController
{
    // Controller actions...
}
```

## 10.3 Models

KMP uses CakePHP's ORM system with model entities and tables. This section documents the core models that form the foundation of the application.

### Entity Classes

Entity classes represent individual records and their relationships:

#### Member Entity

```php
namespace App\Model\Entity;

use Cake\ORM\Entity;
use Authentication\PasswordHasher\DefaultPasswordHasher;

class Member extends Entity
{
    protected $_accessible = [
        'email_address' => true,
        'sca_name' => true,
        'legal_name' => true,
        'title' => true,
        'pronunciation' => true,
        'phone' => true,
        'address' => true,
        'is_minor' => true,
        'password' => true,
        'active' => true,
        'warrantable' => true,
        'member_roles' => true,
        'warrants' => true,
    ];
    
    protected $_hidden = [
        'password',
    ];
    
    protected function _setPassword(string $password): string
    {
        return (new DefaultPasswordHasher())->hash($password);
    }
}
```

#### Branch Entity

```php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class Branch extends Entity
{
    protected $_accessible = [
        'name' => true,
        'type' => true,
        'domain' => true,
        'parent_id' => true,
        'active' => true,
        'lft' => false,
        'rght' => false,
        'parent' => false,
        'children' => false,
        'warrants' => true,
    ];
}
```

#### Warrant Entity

```php
namespace App\Model\Entity;

use Cake\ORM\Entity;

class Warrant extends ActiveWindowBaseEntity
{
    public const STATE_PENDING = 'pending';
    public const STATE_ACTIVE = 'active';
    public const STATE_EXPIRED = 'expired';
    public const STATE_REVOKED = 'revoked';
    public const STATE_REJECTED = 'rejected';
    
    protected $_accessible = [
        'member_id' => true,
        'office_id' => true,
        'branch_id' => true,
        'start_date' => true,
        'end_date' => true,
        'state' => true,
        'member' => true,
        'office' => true,
        'branch' => true,
    ];
}
```

### Table Classes

Table classes handle data storage, retrieval, and validation:

#### MembersTable

```php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class MembersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        $this->setTable('members');
        $this->setDisplayField('sca_name');
        $this->setPrimaryKey('id');
        
        $this->hasMany('MemberRoles', [
            'foreignKey' => 'member_id',
        ]);
        
        $this->hasMany('Warrants', [
            'foreignKey' => 'member_id',
        ]);
        
        $this->belongsToMany('Roles', [
            'through' => 'MemberRoles',
            'foreignKey' => 'member_id',
            'targetForeignKey' => 'role_id',
        ]);
        
        $this->addBehavior('Timestamp');
    }
    
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('email_address', 'Email address is required')
            ->email('email_address', false, 'Must be a valid email address')
            ->add('email_address', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
                'message' => 'Email address is already in use',
            ]);
            
        $validator
            ->allowEmptyString('sca_name')
            ->maxLength('sca_name', 255, 'SCA name cannot exceed 255 characters');
            
        $validator
            ->notEmptyString('legal_name', 'Legal name is required')
            ->maxLength('legal_name', 255, 'Legal name cannot exceed 255 characters');
            
        $validator
            ->notEmptyString('password', 'Password is required')
            ->minLength('password', 8, 'Password must be at least 8 characters long');
            
        return $validator;
    }
}
```

#### BranchesTable

```php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class BranchesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        $this->setTable('branches');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        
        $this->belongsTo('ParentBranches', [
            'className' => 'Branches',
            'foreignKey' => 'parent_id',
        ]);
        
        $this->hasMany('ChildBranches', [
            'className' => 'Branches',
            'foreignKey' => 'parent_id',
        ]);
        
        $this->hasMany('Warrants', [
            'foreignKey' => 'branch_id',
        ]);
        
        $this->addBehavior('Tree', [
            'recoverOrder' => ['lft' => 'ASC'],
        ]);
        
        $this->addBehavior('Timestamp');
    }
    
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('name', 'Branch name is required')
            ->maxLength('name', 255, 'Branch name cannot exceed 255 characters');
            
        $validator
            ->notEmptyString('type', 'Branch type is required');
            
        return $validator;
    }
}
```

#### WarrantsTable

```php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class WarrantsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        $this->setTable('warrants');
        $this->setPrimaryKey('id');
        
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);
        
        $this->belongsTo('Offices', [
            'foreignKey' => 'office_id',
            'joinType' => 'INNER',
        ]);
        
        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'joinType' => 'INNER',
        ]);
        
        $this->addBehavior('Timestamp');
    }
    
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('member_id', 'Member is required')
            ->notEmptyString('office_id', 'Office is required')
            ->notEmptyString('branch_id', 'Branch is required')
            ->notEmptyDate('start_date', 'Start date is required')
            ->notEmptyDate('end_date', 'End date is required')
            ->notEmptyString('state', 'State is required');
            
        $validator
            ->add('end_date', 'comparison', [
                'rule' => ['comparison', '>', 'start_date'],
                'message' => 'End date must be after start date',
            ]);
            
        return $validator;
    }
}
```