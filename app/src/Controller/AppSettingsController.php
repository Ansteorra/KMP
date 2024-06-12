<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * AppSettings Controller
 *
 * @property \App\Model\Table\AppSettingsTable $AppSettings
 */
class AppSettingsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add");
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->AppSettings->find();
        $appSettings = $this->paginate($query);
        $emptyAppSetting = $this->AppSettings->newEmptyEntity();
        $this->set(compact("appSettings", "emptyAppSetting"));
    }
    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $appSetting = $this->AppSettings->newEmptyEntity();
        if ($this->request->is("post")) {
            $appSetting = $this->AppSettings->patchEntity(
                $appSetting,
                $this->request->getData(),
            );
            if ($this->AppSettings->save($appSetting)) {
                $this->Flash->success(__("The app setting has been saved."));

                return $this->redirect(["action" => "index"]);
            }
            $this->Flash->error(
                __("The app setting could not be saved. Please, try again."),
            );
        }

        return $this->redirect(["action" => "index"]);
    }

    /**
     * Edit method
     *
     * @param string|null $id App Setting id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $appSetting = $this->AppSettings->get($id, contain: []);
        if (!$appSetting) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($appSetting);
        if ($this->request->is(["patch", "post", "put"])) {
            $appSetting = $this->AppSettings->patchEntity(
                $appSetting,
                $this->request->getData(),
            );
            if ($this->AppSettings->save($appSetting)) {
                $this->Flash->success(__("The app setting has been saved."));

                return $this->redirect(["action" => "index"]);
            }
            $this->Flash->error(
                __("The app setting could not be saved. Please, try again."),
            );
        }

        return $this->redirect(["action" => "index"]);
    }

    /**
     * Delete method
     *
     * @param string|null $id App Setting id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $appSetting = $this->AppSettings->get($id);
        if (!$appSetting) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($appSetting);
        if ($this->AppSettings->delete($appSetting)) {
            $this->Flash->success(__("The app setting has been deleted."));
        } else {
            $this->Flash->error(
                __("The app setting could not be deleted. Please, try again."),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}
