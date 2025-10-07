<?php

declare(strict_types=1);

namespace Events\Controller;

use App\Controller\AppController;

/**
 * HelloWorld Controller
 *
 * This controller demonstrates a basic KMP plugin controller with:
 * - Standard CRUD operations
 * - Authorization integration
 * - View rendering with Bootstrap templates
 * - Flash messaging
 * - Proper error handling
 *
 * The HelloWorld controller serves as a template for creating new controllers
 * in KMP plugins. It shows the standard patterns and best practices.
 *
 * ## Authorization
 *
 * Access to controller actions is controlled by HelloWorldPolicy. The controller
 * declares which actions require authorization checking:
 * - index: Requires view permission
 * - add: Requires create permission
 * - edit: Requires update permission
 * - delete: Requires delete permission
 *
 * ## Usage Pattern
 *
 * All controllers in KMP plugins should:
 * 1. Extend AppController for consistent behavior
 * 2. Use authorization for security
 * 3. Load necessary models in initialize()
 * 4. Follow CakePHP conventions
 * 5. Use Flash messages for user feedback
 *
 * @property \Template\Model\Table\HelloWorldItemsTable $HelloWorldItems
 */
class  EventsController extends AppController
{
    /**
     * Initialize Controller
     *
     * This method is called before any action is executed. Use it to:
     * - Load additional models
     * - Configure components
     * - Set up authorization rules
     * - Initialize helpers
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load the HelloWorldItems table (if you have a database table)
        // $this->loadModel('Template.HelloWorldItems');
    }

    /**
     * Index Action - List View
     *
     * Displays a list of hello world items. In a real controller, this would
     * paginate records from a database table. This template version shows
     * static data as an example.
     *
     * ## Authorization
     * Requires 'index' permission via HelloWorldPolicy
     *
     * ## Route
     * GET /template/hello-world
     *
     * @return \Cake\Http\Response|null|void Renders the view
     */
    public function index()
    {
        $this->authorizeCurrentUrl();
        // In a real implementation, you would fetch data from the database:
        // $items = $this->paginate($this->HelloWorldItems);

        // For this template, we'll use static data
        $items = [
            [
                'id' => 1,
                'title' => 'Hello, World!',
                'description' => 'This is a sample hello world item.',
                'created' => new \DateTime(),
            ],
            [
                'id' => 2,
                'title' => 'Welcome to KMP',
                'description' => 'The Kingdom Management Portal plugin system.',
                'created' => new \DateTime(),
            ],
            [
                'id' => 3,
                'title' => 'Template Plugin',
                'description' => 'Use this as a starting point for your own plugins.',
                'created' => new \DateTime(),
            ],
        ];

        $this->set(compact('items'));
    }

    /**
     * View Action - Detail View
     *
     * Displays details for a single hello world item.
     *
     * ## Authorization
     * Requires 'view' permission via HelloWorldPolicy
     *
     * ## Route
     * GET /template/hello-world/view/{id}
     *
     * @param string|null $id The item ID
     * @return \Cake\Http\Response|null|void Renders the view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found
     */
    public function view($id = null)
    {
        $this->authorizeCurrentUrl();
        // In a real implementation:
        // $item = $this->HelloWorldItems->get($id);
        // $this->Authorization->authorize($item);

        // For this template, static data
        $item = [
            'id' => $id,
            'title' => 'Hello, World!',
            'description' => 'This is a detailed view of item ' . $id,
            'content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. This would contain the full content of the item.',
            'created' => new \DateTime(),
            'modified' => new \DateTime(),
        ];

        $this->set(compact('item'));
    }

    /**
     * Add Action - Create New Item
     *
     * Handles the creation of new hello world items.
     *
     * ## Authorization
     * Requires 'add' permission via HelloWorldPolicy
     *
     * ## Route
     * GET/POST /template/hello-world/add
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise
     */
    public function add()
    {
        $this->authorizeCurrentUrl();
        // In a real implementation:
        // $item = $this->HelloWorldItems->newEmptyEntity();
        // if ($this->request->is('post')) {
        //     $item = $this->HelloWorldItems->patchEntity($item, $this->request->getData());
        //     if ($this->HelloWorldItems->save($item)) {
        //         $this->Flash->success(__('The item has been saved.'));
        //         return $this->redirect(['action' => 'index']);
        //     }
        //     $this->Flash->error(__('The item could not be saved. Please, try again.'));
        // }
        // $this->set(compact('item'));

        // Template example
        if ($this->request->is('post')) {
            $this->Flash->success(__('This is a template example. In a real implementation, the item would be saved to the database.'));
            return $this->redirect(['action' => 'index']);
        }

        $item = [
            'title' => '',
            'description' => '',
        ];
        $this->set(compact('item'));
    }

    /**
     * Edit Action - Update Existing Item
     *
     * Handles updating existing hello world items.
     *
     * ## Authorization
     * Requires 'edit' permission via HelloWorldPolicy
     *
     * ## Route
     * GET/POST /template/hello-world/edit/{id}
     *
     * @param string|null $id The item ID
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found
     */
    public function edit($id = null)
    {
        $this->authorizeCurrentUrl();
        // In a real implementation:
        // $item = $this->HelloWorldItems->get($id);
        // $this->Authorization->authorize($item);
        // if ($this->request->is(['patch', 'post', 'put'])) {
        //     $item = $this->HelloWorldItems->patchEntity($item, $this->request->getData());
        //     if ($this->HelloWorldItems->save($item)) {
        //         $this->Flash->success(__('The item has been saved.'));
        //         return $this->redirect(['action' => 'index']);
        //     }
        //     $this->Flash->error(__('The item could not be saved. Please, try again.'));
        // }
        // $this->set(compact('item'));

        // Template example
        if ($this->request->is(['patch', 'post', 'put'])) {
            $this->Flash->success(__('This is a template example. In a real implementation, item {0} would be updated in the database.', $id));
            return $this->redirect(['action' => 'index']);
        }

        $item = [
            'id' => $id,
            'title' => 'Hello, World!',
            'description' => 'Sample item for editing',
        ];
        $this->set(compact('item'));
    }

    /**
     * Delete Action - Remove Item
     *
     * Handles deletion of hello world items.
     *
     * ## Authorization
     * Requires 'delete' permission via HelloWorldPolicy
     *
     * ## Route
     * POST /template/hello-world/delete/{id}
     *
     * @param string|null $id The item ID
     * @return \Cake\Http\Response|null|void Redirects to index
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found
     */
    public function delete($id = null)
    {
        $this->authorizeCurrentUrl();
        $this->request->allowMethod(['post', 'delete']);

        // In a real implementation:
        // $item = $this->HelloWorldItems->get($id);
        // $this->Authorization->authorize($item);
        // if ($this->HelloWorldItems->delete($item)) {
        //     $this->Flash->success(__('The item has been deleted.'));
        // } else {
        //     $this->Flash->error(__('The item could not be deleted. Please, try again.'));
        // }

        // Template example
        $this->Flash->success(__('This is a template example. In a real implementation, item {0} would be deleted from the database.', $id));

        return $this->redirect(['action' => 'index']);
    }
}